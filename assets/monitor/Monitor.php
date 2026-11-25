<?php
declare(strict_types=1);
/** First-party module, PHP 8.3+, PDO MySQL. No dependency on GEOInflow. */
final class EWMonitor {
 public const VERSION='1.1.2';
 public function __construct(public PDO $db,public array $config,public array $catalog,public array $rules,public array $ranges){
  if(!preg_match('/^[a-f0-9]{64}$/',$config['secret']??''))throw new InvalidArgumentException('Monitor secret must be 32 random bytes encoded as hex.');
  if(!is_bool($config['preview']??null)||!preg_match('~^https?://[^/]+$~',$config['origin']??''))throw new InvalidArgumentException('Monitor environment is invalid.');
 }
 public static function cidr(string $ip,string $cidr):bool {
  $parts=explode('/',$cidr);$a=@inet_pton($ip);$b=@inet_pton($parts[0]);if($a===false||$b===false||strlen($a)!==strlen($b))return false;
  $bits=isset($parts[1])&&ctype_digit($parts[1])?(int)$parts[1]:(isset($parts[1])?-1:strlen($a)*8);if($bits<0||$bits>strlen($a)*8)return false;
  $bytes=intdiv($bits,8);$rem=$bits%8;return substr($a,0,$bytes)===substr($b,0,$bytes)&&(!$rem||((ord($a[$bytes])^ord($b[$bytes]))&(255<<(8-$rem)))===0);
 }
 public function ip(array $server):string {
  $ip=$server['REMOTE_ADDR']??'';
  foreach($this->config['trusted_proxies']??[] as $cidr)if(self::cidr($ip,$cidr)){
   // Only the explicitly named single-IP header, overwritten by a trusted proxy, is accepted.
   $value=$server[$this->config['client_ip_header']??'HTTP_X_REAL_IP']??'';return filter_var($value,FILTER_VALIDATE_IP)?$value:'';
  }
  return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'';
 }
 public function classify(string $ua,string $ip,int $when):array {
  $out=['bot'=>'','provider'=>'','purpose'=>'','verification'=>'not_applicable','rule_hash'=>''];$hits=[];
  foreach($this->rules['non_request_tokens']??[] as $token)if(preg_match('~(?<![A-Za-z0-9_-])'.preg_quote($token,'~').'(?![A-Za-z0-9_-])~i',$ua))return array_replace($out,['bot'=>'非请求控制标记','purpose'=>'other','verification'=>'unverified']);
  foreach($this->rules['bots'] as $r)if(preg_match('~(?<![A-Za-z0-9_-])'.preg_quote($r['token'],'~').'(?![A-Za-z0-9_-])~i',$ua))$hits[]=$r;
  if(count($hits)>1)return array_replace($out,['bot'=>'身份冲突','purpose'=>'unknown','verification'=>'ambiguous']);
  if(!$hits){if(preg_match('/bot|spider|crawler|curl|python|wget|headless|externalagent|fetcher/i',$ua))return array_replace($out,['bot'=>'其他自动访问','purpose'=>'other','verification'=>'unverified']);return $out;}
  $r=$hits[0];$out=array_replace($out,['bot'=>$r['token'],'provider'=>$r['provider'],'purpose'=>($r['kind']??'other')==='ai'?$r['purpose']:'other','verification'=>'unverified']);
  $feed=$this->ranges['feeds'][$r['feed']??'']??null;if(!$feed)return $out;
  $expected=$this->rules['feeds'][$r['feed']]??'';
  $fetched=strtotime($feed['fetched_at']??'');
  if(!$expected||($feed['url']??'')!==$expected||!preg_match('/^[a-f0-9]{64}$/',$feed['sha256']??'')||$fetched===false||!preg_match('/(?:Z|[+-]\d\d:\d\d)$/',$feed['fetched_at']??'')||!is_array($feed['cidrs']??null)||!$feed['cidrs']||count($feed['cidrs'])>10000){$out['verification']='invalid_evidence';return $out;}
  foreach($feed['cidrs'] as $cidr){$parts=explode('/',$cidr);$packed=@inet_pton($parts[0]);if(count($parts)!==2||!$packed||!ctype_digit($parts[1])||(int)$parts[1]<1||(int)$parts[1]>strlen($packed)*8){$out['verification']='invalid_evidence';return $out;}}
  $out['rule_hash']=$feed['sha256'];
  // Never project a current IP list backward onto historical imports.
  if($when<$fetched-300){$out['verification']='historical_unknown';return $out;}
  if($when-$fetched>604800){$out['verification']='stale';return $out;}
  if(!$ip){$out['verification']='ip_missing';return $out;}
  $out['verification']='unmatched';foreach($feed['cidrs'] as $cidr)if(self::cidr($ip,$cidr)){$out['verification']='official_ip';break;}return $out;
 }
 public static function path(string $url):string {return substr(rawurldecode(parse_url($url,PHP_URL_PATH)?:'/'),0,512);}
 public static function excluded(string $path):bool{return (bool)preg_match('~^/(admin|preview|monitor|assets|media|private|config|storage|tools)(/|$)|^/(robots\.txt|sitemap\.xml|favicon\.ico)$~i',$path);}
 public function request(array $row):bool {
  $ts=strtotime($row['time']);if($ts===false||$ts>time()+60||$ts<time()-86400*90)throw new InvalidArgumentException('日志时间无效或超出 90 天保留期。');
  $path=self::path($row['path']);if(self::excluded($path))return false;
  if(!in_array($row['method'],['GET','HEAD'],true))return false;
  $status=(int)$row['status'];if($status<100||$status>599)throw new InvalidArgumentException('状态码无效。');
  $c=$this->classify(substr($row['ua'],0,1000),filter_var($row['ip'],FILTER_VALIDATE_IP)?$row['ip']:'',$ts);
  $q=$this->db->prepare('INSERT IGNORE INTO ew_requests(dedup,observed_at,path,method,status,duration_ms,bot,provider,purpose,verification,rule_hash,rule_version,source,scope) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $q->execute([hash('sha256',$row['scope'].'|'.$row['source'].'|'.$row['key']),gmdate('Y-m-d H:i:s',$ts),$path,$row['method'],$status,max(0,min(600000,(int)($row['duration_ms']??0))),$c['bot'],$c['provider'],$c['purpose'],$c['verification'],$c['rule_hash'],$this->rules['version']??'',$row['source'],$row['scope']]);return $q->rowCount()>0;
 }
 public function attach(array $server,bool $adminCookie):void {
  $path=self::path($server['REQUEST_URI']??'/');if(self::excluded($path)||$adminCookie||!in_array($server['REQUEST_METHOD']??'',['GET','HEAD'],true))return;
  $start=microtime(true);$time=gmdate('c');$ip=$this->ip($server);$ua=$server['HTTP_USER_AGENT']??'';
  register_shutdown_function(function()use($server,$path,$start,$time,$ip,$ua){try{
   $status=http_response_code()?:200;$fatal=error_get_last();if($fatal&&in_array($fatal['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))$status=500;
   $this->request(['key'=>bin2hex(random_bytes(16)),'time'=>$time,'path'=>$path,'method'=>$server['REQUEST_METHOD'],'status'=>$status,'duration_ms'=>(microtime(true)-$start)*1000,'ua'=>$ua,'ip'=>$ip,'source'=>'php','scope'=>$this->scope()]);
  }catch(Throwable $e){error_log('monitor request write failed: '.get_class($e));}});
 }
 public function scope():string{return $this->config['preview']?'preview':'production';}
 public function visitor(array $server,?int $time=null):string{
  $day=(new DateTimeImmutable('@'.($time??time())))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d');
  return hash_hmac('sha256',$day.'|'.$this->ip($server).'|'.substr($server['HTTP_USER_AGENT']??'',0,500),$this->config['secret']);
 }
 public function token(array $server,string $path,int $time):string{return $time.'.'.hash_hmac('sha256',$time.'|'.$path.'|'.$this->visitor($server,$time),$this->config['secret']);}
 public static function platformRules():array{
  static $rules=null;if($rules===null)$rules=json_decode(file_get_contents(__DIR__.'/rules.json'),true,512,JSON_THROW_ON_ERROR);return $rules;
 }
 public static function referralLabels(?array $rules=null):array{
  $labels=[];foreach(($rules??self::platformRules())['platforms']??[] as $p){$labels[]=$p['name'];foreach($p['legacy_referral_labels']??[] as $name)$labels[]=$name;}return array_values(array_unique($labels));
 }
 public static function canonicalReferral(string $label,?array $rules=null):string{
  foreach(($rules??self::platformRules())['platforms']??[] as $p)if($label===$p['name']||in_array($label,$p['legacy_referral_labels']??[],true))return $p['name'];return $label;
 }
 public static function referral(string $ref,string $site='',?array $rules=null):string{
  $url=parse_url($ref);$host=strtolower($url['host']??'');
  if(!in_array(strtolower($url['scheme']??''),['http','https'],true))return $ref===''?'直接访问 / 来源缺失':'其他来源';
  if($host&&$host===strtolower(parse_url($site,PHP_URL_HOST)?:''))return '站内浏览';
  foreach(($rules??self::platformRules())['platforms']??[] as $p)if(in_array($host,$p['referral_hosts'],true))return $p['name'];
  return $host?'其他来源':'直接访问 / 来源缺失';
 }
 public static function aiPredicate():string{return "bot<>'' AND purpose IN ('training','search','user','agent')";}
 public function endpoint(string $route,array $server,bool $adminCookie):never {
  header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=utf-8');
  try{
   if($adminCookie||($server['HTTP_DNT']??'')==='1'||($server['HTTP_SEC_GPC']??'')==='1'){http_response_code(204);exit;}
   $c=$this->classify($server['HTTP_USER_AGENT']??'',$this->ip($server),time());if($c['bot']){http_response_code(204);exit;}
   $base=rtrim($this->config['origin'],'/');$origin=$server['HTTP_ORIGIN']??'';
   $ref=$server['HTTP_REFERER']??'';$refOrigin=preg_match('~^(https?://[^/]+)~',$ref,$m)?$m[1]:'';
   if(($origin&&$origin!==$base)||(!$origin&&$refOrigin!==$base))throw new InvalidArgumentException('origin');
   if($route==='/monitor/token'&&$server['REQUEST_METHOD']==='GET'){
    if(!is_string($_GET['path']??null))throw new InvalidArgumentException('path');
    $path=self::path($_GET['path']);if(!isset($this->catalog[$path]))throw new InvalidArgumentException('path');echo json_encode(['token'=>$this->token($server,$path,time())]);exit;
   }
   if($route!=='/monitor/event'||$server['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
   $raw=file_get_contents('php://input',false,null,0,4097);if($raw===false)throw new RuntimeException('input_read_failed');
   if(strlen($raw)>4096){http_response_code(413);exit;}$d=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
   if(!is_array($d))throw new InvalidArgumentException('payload');
   foreach(['path','token','event','id'] as $field)if(!is_string($d[$field]??null))throw new InvalidArgumentException('field');
   if(array_key_exists('referrer',$d)&&!is_string($d['referrer']))throw new InvalidArgumentException('referrer');
   $path=self::path($d['path']);if(!isset($this->catalog[$path]))throw new InvalidArgumentException('path');
   $token=$d['token'];$ts=(int)explode('.',$token)[0];if($ts>time()+5||$ts<time()-1800||!hash_equals($this->token($server,$path,$ts),$token))throw new InvalidArgumentException('token');
   if(!in_array($d['event'],['pageview','download_brief','click_phone','click_email'],true)||!preg_match('/^[a-f0-9-]{32,36}$/',$d['id']))throw new InvalidArgumentException('event');
   $visitor=$this->visitor($server);$q=$this->db->prepare('SELECT COUNT(*) FROM ew_events WHERE visitor=? AND observed_at>=?');$q->execute([$visitor,gmdate('Y-m-d H:i:s',time()-60)]);if($q->fetchColumn()>=60){http_response_code(429);exit;}
   $q=$this->db->prepare('INSERT IGNORE INTO ew_events(event_id,observed_at,path,event_name,visitor,referral,device,scope) VALUES(?,?,?,?,?,?,?,?)');
   $q->execute([hash('sha256',$visitor.'|'.$d['id']),gmdate('Y-m-d H:i:s'),$path,$d['event'],$visitor,self::referral(substr($d['referrer']??'',0,1000),$base,$this->rules),preg_match('/Mobile|Android|iPhone/i',$server['HTTP_USER_AGENT']??'')?'mobile':'desktop',$this->scope()]);http_response_code(204);exit;
  }catch(InvalidArgumentException|JsonException $e){http_response_code(400);echo '{"error":"invalid_request"}';exit;
  }catch(Throwable $e){
   // Keep driver messages, request bodies and credentials out of responses and logs.
   $code=preg_replace('/[^A-Za-z0-9_-]/','',substr((string)$e->getCode(),0,32));
   error_log('monitor endpoint failed: route='.($route==='/monitor/token'?'token':'event').' class='.get_class($e).' code='.$code);
   http_response_code(503);echo '{"error":"service_unavailable"}';exit;
  }
 }
 public function rows(string $sql,array $params=[]):array{$q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
 public function retention():int{$cut=gmdate('Y-m-d H:i:s',time()-86400*90);$n=0;foreach(['ew_requests','ew_events'] as $t){$q=$this->db->prepare("DELETE FROM $t WHERE observed_at<?");$q->execute([$cut]);$n+=$q->rowCount();}return $n;}
}

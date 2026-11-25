<?php
if(PHP_SAPI!=='cli')exit;
$rulesOnly=($argv[1]??'')==='--rules-only';
if($rulesOnly){
 require dirname(__DIR__).'/assets/monitor/Monitor.php';
 $monitor=new EWMonitor(new PDO('sqlite::memory:'),['secret'=>str_repeat('a',64),'preview'=>true,'origin'=>'https://example.com','trusted_proxies'=>[]],[],EWMonitor::platformRules(),['feeds'=>[]]);
}else require rtrim($argv[1]??'','/\\').'/app/monitor-adapter.php';
if(!$monitor instanceof EWMonitor)throw new RuntimeException('Adapter unavailable');
$checks=[];
function check(string $name,bool $ok):void{global $checks;$checks[]=['check'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
$now=time();$settings=$monitor->config;$settings['trusted_proxies']=[];$settings['client_ip_header']='HTTP_X_REAL_IP';$rules=$monitor->rules;
$m=new EWMonitor($monitor->db,$settings,$monitor->catalog,$rules,['feeds'=>['gptbot'=>['url'=>'https://openai.com/gptbot.json','sha256'=>str_repeat('a',64),'fetched_at'=>gmdate('c',$now-60),'cidrs'=>['8.8.8.0/24','2001:4860::/32']]]]);
check('IPv4 boundary',EWMonitor::cidr('8.8.8.255','8.8.8.0/24')&&!EWMonitor::cidr('8.8.9.0','8.8.8.0/24'));
check('IPv6 boundary',EWMonitor::cidr('2001:4860::8888','2001:4860::/32')&&!EWMonitor::cidr('2001:4861::1','2001:4860::/32'));
check('Invalid CIDR',!EWMonitor::cidr('garbage','0.0.0.0/0')&&!EWMonitor::cidr('8.8.8.8','8.8.8.0/33'));
check('UA plus IP match',$m->classify('GPTBot/1.3','8.8.8.8',$now)['verification']==='official_ip');
check('Spoofed UA unverified',$m->classify('GPTBot/1.3','127.0.0.1',$now)['verification']==='unmatched');
check('Token boundary',$m->classify('FakeGPTBot','8.8.8.8',$now)['verification']!=='official_ip');
check('Identity conflict',$m->classify('GPTBot ClaudeBot','8.8.8.8',$now)['verification']==='ambiguous');
check('No vendor feed',$m->classify('Bytespider','8.8.8.8',$now)['verification']==='unverified');
check('Historical evidence',$m->classify('GPTBot','8.8.8.8',$now-7200)['verification']==='historical_unknown');
check('Expired snapshot',$m->classify('GPTBot','8.8.8.8',$now+604801)['verification']==='stale');
check('Missing IP',$m->classify('GPTBot','',$now)['verification']==='ip_missing');
check('Ignore spoofed proxy',$m->ip(['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_REAL_IP'=>'8.8.8.8'])==='127.0.0.1');
$m->config['trusted_proxies']=['127.0.0.1/32'];check('Trusted single IP',$m->ip(['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_REAL_IP'=>'8.8.8.8'])==='8.8.8.8');
check('Reject multiple IP header',$m->ip(['REMOTE_ADDR'=>'127.0.0.1','HTTP_X_REAL_IP'=>'8.8.8.8, 1.1.1.1'])==='');
check('Internal referral',EWMonitor::referral('https://example.com/page','https://example.com')==='站内浏览');
check('Referrer exact domain',EWMonitor::referral('https://chatgpt.com/c/abc')==='ChatGPT / OpenAI'&&EWMonitor::referral('https://chatgpt.com.evil.example')==='其他来源');
check('Private paths and query',EWMonitor::excluded('/admin')&&EWMonitor::excluded('/private/a')&&EWMonitor::path('/products?secret=abc')==='/products');
$s=['REMOTE_ADDR'=>'127.0.0.1','HTTP_USER_AGENT'=>'test'];check('Path-bound token',$m->token($s,'/a',$now)!==$m->token($s,'/b',$now));
$morning=strtotime('2026-11-14T07:59:59+08:00');$utcBoundary=$morning+1;
check('Visitor day matches Beijing date',$m->visitor($s,$morning)===$m->visitor($s,$utcBoundary));
$midnight=strtotime('2026-11-15T00:00:00+08:00');
check('Visitor rotates at Beijing midnight',$m->visitor($s,$midnight-1)!==$m->visitor($s,$midnight));
$issued=$midnight-10;$expected=$issued.'.'.hash_hmac('sha256',$issued.'|/a|'.$m->visitor($s,$issued),$m->config['secret']);
check('Token uses issuance day across midnight',$m->token($s,'/a',$issued)===$expected);
// Isolated identity and aggregate checks use synthetic addresses, never real visits.
$invalid=clone $m;$invalid->ranges['feeds']['gptbot']['url']='https://example.com/list.json';
check('Reject wrong feed source',$invalid->classify('GPTBot','8.8.8.8',$now)['verification']==='invalid_evidence');
$invalid=clone $m;$invalid->ranges['feeds']['gptbot']['fetched_at']='invalid';
check('Reject malformed feed time',$invalid->classify('GPTBot','8.8.8.8',$now)['verification']==='invalid_evidence');
$invalid=clone $m;$invalid->ranges['feeds']['gptbot']['cidrs']=['0.0.0.0/0'];
check('Reject unbounded feed',$invalid->classify('GPTBot','8.8.8.8',$now)['verification']==='invalid_evidence');
foreach(['Googlebot','bingbot','Baiduspider','Bytespider','Google-CloudVertexBot','Google-Extended','Applebot-Extended','DeepSeekBot','DoubaoBot','KimiBot','YuanbaoBot'] as $ua){
 $c=$m->classify($ua,'8.8.8.8',$now);check('No product inference: '.$ua,!in_array($c['purpose'],['training','search','user','agent'],true));
}
check('No UA from IP alone',$m->classify('Mozilla/5.0','8.8.8.8',$now)['bot']==='');
check('Feed must match token',$m->classify('OAI-SearchBot','8.8.8.8',$now)['verification']!=='official_ip');
check('Agent cannot use crawler feed',$m->classify('Claude-Agent','8.8.8.8',$now)['verification']!=='official_ip');
check('Control token cannot establish identity',$m->classify('Google-Extended GPTBot','8.8.8.8',$now)['verification']!=='official_ip');
foreach($rules['platforms'] as $p){
 foreach($p['referral_hosts'] as $host){
  check('Referral host '.$host,EWMonitor::referral('https://'.$host.'/example','',$rules)===$p['name']);
  check('Reject lookalike '.$host,EWMonitor::referral('https://'.$host.'.evil.example/','',$rules)==='其他来源');
 }
 check('Platform is counted in referrals '.$p['id'],in_array($p['name'],EWMonitor::referralLabels($rules),true));
 foreach($p['bots'] as $token)check('Declared identity exists '.$token,in_array($token,array_column($rules['bots'],'token'),true));
}
foreach(['https://www.google.com','https://www.bing.com','https://www.baidu.com','https://www.tencent.com','https://x.com','https://facebook.com','https://unlisted.chatgpt.com','ftp://chatgpt.com'] as $url)check('Generic or unreviewed origin '.$url,EWMonitor::referral($url)==='其他来源');
check('Missing referral stays unknown',EWMonitor::referral('')==='直接访问 / 来源缺失');
check('Legacy labels retained',in_array('通义',EWMonitor::referralLabels(),true)&&in_array('ChatGPT',EWMonitor::referralLabels(),true));
check('Merge referral aliases',EWMonitor::canonicalReferral('通义')==='千问'&&EWMonitor::canonicalReferral('ChatGPT')==='ChatGPT / OpenAI');
$fixtureFeeds=[];$i=1;foreach($rules['feeds'] as $key=>$url){$fixtureFeeds[$key]=['url'=>$url,'sha256'=>str_repeat('b',64),'fetched_at'=>gmdate('c',$now-60),'cidrs'=>['8.8.4.'.$i.'/32']];$i++;}
$all=new EWMonitor($m->db,$m->config,[],$rules,['feeds'=>$fixtureFeeds]);
foreach($rules['bots'] as $r){
 $net=$fixtureFeeds[$r['feed']]['cidrs'][0]??'1.1.1.1/32';$c=$all->classify($r['token'].'/1.0',explode('/',$net)[0],$now);
 check('Known token classification '.$r['token'],$c['bot']===$r['token']&&$c['purpose']===$r['purpose']);
 check('Verification availability '.$r['token'],($c['verification']==='official_ip')===($r['feed']!==''));
}
if($rulesOnly){
 $db=$monitor->db;$db->exec('CREATE TABLE fixture (bot TEXT,purpose TEXT,verification TEXT,method TEXT,status INT,path TEXT)');
 $insert=$db->prepare('INSERT INTO fixture VALUES (?,?,?,?,?,?)');
 foreach([['GPTBot','8.8.8.8','GET',200,'/a'],['GPTBot','8.8.8.8','HEAD',200,'/head'],['GPTBot','8.8.8.8','GET',304,'/cached'],['GPTBot','1.1.1.1','GET',200,'/spoof'],['Googlebot','8.8.8.8','GET',200,'/google'],['Mozilla/5.0','8.8.8.8','GET',200,'/human'],['ClaudeBot GPTBot','8.8.8.8','GET',200,'/conflict'],['Google-Extended','8.8.8.8','GET',200,'/control']] as [$ua,$ip,$method,$status,$path]){
  $c=$m->classify($ua,$ip,$now);$insert->execute([$c['bot'],$c['purpose'],$c['verification'],$method,$status,$path]);
 }
 $ai=EWMonitor::aiPredicate();$row=$db->query("SELECT SUM($ai) claims,SUM(($ai) AND verification='official_ip') verified,COUNT(DISTINCT CASE WHEN ($ai) AND verification='official_ip' AND method='GET' AND status=200 THEN path END) covered FROM fixture")->fetch(PDO::FETCH_ASSOC);
 check('Aggregate identities separated',(int)$row['claims']===4&&(int)$row['verified']===3);
 check('Coverage excludes HEAD 304 spoof generic',(int)$row['covered']===1);
 echo json_encode(['module'=>EWMonitor::VERSION,'checks'=>$checks,'fixtures'=>'isolated memory only','mysql_http_browser'=>'not_run'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit;
}

$m->db->beginTransaction();
try{
 $source='import_unit_'.bin2hex(random_bytes(5));$row=['key'=>'a','time'=>gmdate('c'),'path'=>'/test-monitor?secret=abc','method'=>'GET','status'=>200,'ua'=>'GPTBot','ip'=>'8.8.8.8','source'=>$source,'scope'=>'preview'];
 check('Insert request',$m->request($row));check('Idempotent duplicate',!$m->request($row));
 $saved=$m->rows('SELECT * FROM ew_requests WHERE source=?',[$source])[0];check('Privacy persisted',$saved['path']==='/test-monitor'&&!isset($saved['ip'])&&!isset($saved['ua']));
 check('Verified provenance persisted',$saved['verification']==='official_ip'&&$saved['rule_hash']===str_repeat('a',64)&&$saved['rule_version']===$rules['version']);
 $row['key']='b';$row['method']='HEAD';$m->request($row);$row['key']='c';$row['method']='GET';$row['status']=304;$m->request($row);
 $n=$m->rows('SELECT COUNT(*) n FROM ew_requests WHERE source=? AND method=? AND status=200',[$source,'GET'])[0]['n'];check('Coverage strictly GET 200',(int)$n===1);
 $row['scope']='production';check('Scope-isolated dedup',$m->request($row));
 $row['key']='d';$row['path']='/admin';check('Excluded admin',!$m->request($row));
 $row['path']='/test-monitor';$row['time']='invalid';$rejected=false;try{$m->request($row);}catch(InvalidArgumentException $e){$rejected=true;}check('Reject malformed time',$rejected);
}finally{$m->db->rollBack();}
echo json_encode(['module'=>EWMonitor::VERSION,'checks'=>$checks,'fixtures'=>'transaction rolled back'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;

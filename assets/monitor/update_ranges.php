<?php
/** Run with PHP CLI; fetches reviewed HTTPS feeds from the local rules.json. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$target=$argv[1]??dirname(__DIR__,2).'/private/monitor/ranges.json';
$rules=json_decode(file_get_contents(__DIR__.'/rules.json'),true,512,JSON_THROW_ON_ERROR);
$feeds=$rules['feeds']??null;
if(!is_array($feeds)||!$feeds)throw new RuntimeException('rules.json must contain a non-empty feeds object.');
foreach($feeds as $name=>$url){
 if(!is_string($name)||!preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/',$name)||!is_string($url)||!filter_var($url,FILTER_VALIDATE_URL)||preg_match('/[\x00-\x20\x7f]/',$url)||str_contains($url,'\\'))throw new RuntimeException('Invalid feed entry in rules.json.');
 $parts=parse_url($url);$host=$parts['host']??'';
 if(($parts['scheme']??'')!=='https'||strlen($host)>253||!preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/i',$host)||!preg_match('~\Ahttps://'.preg_quote($host,'~').'(?:/|$)~i',$url)||isset($parts['port'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('Feed must be an HTTPS domain URL without credentials, port, query or fragment.');
}
$dir=dirname($target);if(!is_dir($dir))mkdir($dir,0700,true);
$lock=fopen($dir.'/update.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))exit('Update already running.');
$data=is_file($target)?json_decode(file_get_contents($target),true,512,JSON_THROW_ON_ERROR):['feeds'=>[]];$errors=[];
foreach($feeds as $name=>$url){try{
 $ctx=stream_context_create(['http'=>['timeout'=>15,'follow_location'=>0,'max_redirects'=>0,'header'=>"User-Agent: EnterpriseMonitor/1.0\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
 $fp=@fopen($url,'rb',false,$ctx);if(!$fp)throw new RuntimeException('HTTPS request failed; check OpenSSL and CA certificates');
 $meta=stream_get_meta_data($fp);$status=$meta['wrapper_data'][0]??'';
 $raw=stream_get_contents($fp,1048577);fclose($fp);if(!preg_match('~^HTTP/\S+ 200\b~',$status)||strlen($raw)>1048576)throw new RuntimeException('Invalid response');
 $doc=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$nets=[];
 foreach($doc['prefixes']??[] as $p)foreach(['ipv4Prefix','ipv6Prefix'] as $key)if(isset($p[$key])){
  $parts=explode('/',$p[$key]);$ip=$parts[0];$bits=$parts[1]??'';$packed=@inet_pton($ip);
  if(count($parts)!==2||!$packed||!ctype_digit($bits)||(int)$bits<1||(int)$bits>strlen($packed)*8||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Invalid public CIDR');
  $nets[]=$p[$key];
 }
 if(!$nets||count($nets)>10000)throw new RuntimeException('Invalid range count');
 $sha=hash('sha256',$raw);$archive=$dir.'/range-history';if(!is_dir($archive))mkdir($archive,0700,true);if(file_put_contents($archive.'/'.$name.'-'.$sha.'.json',$raw)===false)throw new RuntimeException('Archive failed');
 $data['feeds'][$name]=['url'=>$url,'fetched_at'=>gmdate('c'),'creation_time'=>$doc['creationTime']??null,'sha256'=>$sha,'cidrs'=>array_values(array_unique($nets))];
 echo $name.' '.count($nets)." ranges refreshed.\n";
 }catch(Throwable $e){$errors[]=['feed'=>$name,'error'=>$e->getMessage()];echo $name." FAILED; keeping last good snapshot.\n";}
}
$tmp=$target.'.'.bin2hex(random_bytes(6)).'.tmp';if(file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))===false||!rename($tmp,$target))throw new RuntimeException('Snapshot write failed');
file_put_contents($dir.'/range-update-status.json',json_encode(['checked_at'=>gmdate('c'),'errors'=>$errors],JSON_PRETTY_PRINT));flock($lock,LOCK_UN);fclose($lock);exit($errors?1:0);

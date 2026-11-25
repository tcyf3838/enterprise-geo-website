<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/monitor-adapter.php';
if(!$monitor instanceof EWMonitor)throw new RuntimeException('Monitor adapter unavailable.');
$command=$argv[1]??'';
if($command==='migrate'){
 // Optional dedicated migration connection; production runtime needs no DDL grants.
 $pdo=getenv('EW_MONITOR_DSN')?new PDO(getenv('EW_MONITOR_DSN'),getenv('EW_MONITOR_USER'),getenv('EW_MONITOR_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$monitor->db;
 $pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
 if(!$pdo->query("SHOW COLUMNS FROM ew_requests LIKE 'rule_version'")->fetch())$pdo->exec("ALTER TABLE ew_requests ADD COLUMN rule_version VARCHAR(32) NOT NULL DEFAULT ''");
 echo "Monitor tables ready.\n";
}elseif($command==='prune'){echo $monitor->retention()." expired rows removed.\n";}
elseif($command==='catalog'){
 $entries=[];foreach($monitor->catalog as $path=>$title)$entries[]=['url'=>$monitor->config['origin'].$path,'title'=>$title];
 echo json_encode($entries,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}
elseif($command==='import'){
 $base=realpath(dirname(__DIR__,2).'/private/imports');$file=realpath($argv[2]??'');$source=$argv[3]??'';$scope=$argv[4]??'';
 if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||filesize($file)>10485760)throw new RuntimeException('Import must be a <=10 MB JSONL file in private/imports.');
 if(!preg_match('/^import_[A-Za-z0-9_-]{1,50}$/',$source)||!in_array($scope,['production','preview'],true))throw new RuntimeException('Specify import_SOURCE and production or preview.');
 $fp=fopen($file,'r');$line=0;$added=0;$skipped=0;$monitor->db->beginTransaction();
 try{while(($raw=fgets($fp,65537))!==false){$line++;if(strlen($raw)>65535)throw new RuntimeException('Line too long');$row=json_decode($raw,true,32,JSON_THROW_ON_ERROR);foreach(['key','time','path','method','status','ua','ip'] as $key)if(!isset($row[$key])||!is_scalar($row[$key]))throw new RuntimeException('Missing field: '.$key);if(!preg_match('/(?:Z|[+-]\d\d:\d\d)$/',$row['time']))throw new RuntimeException('Time must include timezone');$row['source']=$source;$row['scope']=$scope;if($monitor->request($row))$added++;else $skipped++;}fclose($fp);$monitor->db->commit();echo json_encode(['lines'=>$line,'added'=>$added,'duplicate_or_excluded'=>$skipped]).PHP_EOL;}catch(Throwable $e){$monitor->db->rollBack();throw new RuntimeException('Import rolled back at line '.$line.': '.$e->getMessage());}
}else{echo "Commands: migrate | prune | catalog | import <private/imports/file.jsonl> <import_SOURCE> <preview|production>\n";}

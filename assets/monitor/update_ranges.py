"""Refresh only reviewed official HTTPS feeds. Keeps last-good snapshots on failure."""
from pathlib import Path
import json,hashlib,ipaddress,urllib.request,urllib.parse,datetime,sys,re
target=Path(sys.argv[1]).resolve()
rules=json.loads(Path(__file__).with_name('rules.json').read_text('utf-8'))
feeds=rules.get('feeds')
if not isinstance(feeds,dict) or not feeds:raise ValueError('rules.json must contain a non-empty feeds object')
for name,url in feeds.items():
 if not isinstance(name,str) or not re.fullmatch(r'[a-z0-9][a-z0-9_-]{0,63}',name) or not isinstance(url,str) or not url.isascii() or re.search(r'[\x00-\x20\x7f\\]',url):raise ValueError('Invalid feed entry in rules.json')
 parts=urllib.parse.urlsplit(url);host=parts.hostname or ''
 if not url.startswith('https://') or len(host)>253 or not re.fullmatch(r'(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}',host,re.I) or parts.netloc.lower()!=host or '?' in url or '#' in url:raise ValueError('Feed must be an HTTPS domain URL without credentials, port, query or fragment')
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
data=json.loads(target.read_text('utf-8')) if target.exists() else {'feeds':{}}
errors=[]
for name,url in feeds.items():
 try:
  with opener.open(urllib.request.Request(url,headers={'User-Agent':'EnterpriseMonitor/1.0'}),timeout=15) as r:raw=r.read(1024*1024+1)
  if len(raw)>1024*1024:raise ValueError('feed too large')
  doc=json.loads(raw);nets=[]
  for p in doc['prefixes']:
   for key in ['ipv4Prefix','ipv6Prefix']:
    if key in p:
     net=ipaddress.ip_network(p[key],strict=True)
     if net.prefixlen==0 or not net.network_address.is_global:raise ValueError('non-public or unbounded range')
     nets.append(str(net))
  if not nets or len(nets)>10000:raise ValueError('invalid range count')
  stamp=datetime.datetime.now(datetime.timezone.utc).isoformat();sha=hashlib.sha256(raw).hexdigest()
  archive=target.parent/'range-history';archive.mkdir(exist_ok=True,parents=True)
  (archive/(name+'-'+sha+'.json')).write_bytes(raw)
  data['feeds'][name]={'url':url,'fetched_at':stamp,'creation_time':doc.get('creationTime'),'sha256':sha,'cidrs':sorted(set(nets))}
  print(name, len(nets),'ranges refreshed')
 except Exception as e:errors.append({'feed':name,'error':type(e).__name__});print(name,'FAILED; keeping last good snapshot')
target.parent.mkdir(parents=True,exist_ok=True);temp=target.with_suffix('.tmp');temp.write_text(json.dumps(data,ensure_ascii=False,indent=2),'utf-8');temp.replace(target)
(target.parent/'range-update-status.json').write_text(json.dumps({'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'errors':errors},indent=2),'utf-8')
if errors:sys.exit(1)

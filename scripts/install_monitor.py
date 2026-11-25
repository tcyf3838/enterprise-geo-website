"""Copy the first-party module into a generated site; never overwrites modified files."""
from pathlib import Path
import argparse,json,hashlib,shutil
ap=argparse.ArgumentParser();ap.add_argument('--project',required=True);ap.add_argument('--apply',action='store_true');a=ap.parse_args()
root=Path(a.project).resolve();src=Path(__file__).resolve().parents[1]/'assets/monitor';dst=root/'app/monitor'
if not (root/'public').is_dir() or not (root/'app').is_dir():raise SystemExit('Expected site public/ and app/ directories')
manifest=root/'private/monitor-install.json';old=json.loads(manifest.read_text('utf-8')) if manifest.exists() else {};new={};changes=[]
for f in src.iterdir():
 if not f.is_file():continue
 sha=hashlib.sha256(f.read_bytes()).hexdigest();new[f.name]=sha;out=dst/f.name
 if out.exists():
  current=hashlib.sha256(out.read_bytes()).hexdigest()
  if current==sha:continue
  if current!=old.get(f.name):raise SystemExit('Local modification preserved; review '+str(out))
 changes.append(f)
print('Module files to create/update:',len(changes))
if a.apply:
 dst.mkdir(parents=True,exist_ok=True)
 for f in changes:shutil.copyfile(f,dst/f.name)
 manifest.parent.mkdir(parents=True,exist_ok=True);manifest.write_text(json.dumps(new,indent=2),'utf-8')
 print('Copied. Complete adapter, migration, routes, tracker and authenticated report per references/native-monitor.md.')

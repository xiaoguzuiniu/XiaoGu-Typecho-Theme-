#!/usr/bin/env python3
# Local HTTP CONNECT proxy that chains through a remote SOCKS5 (with auth).
# usage: UP_SOCKS_HOST=... UP_SOCKS_PORT=... UP_USER=... UP_PASS=... LISTEN=127.0.0.1:8899 python3 chain_proxy.py
import asyncio, os, struct, sys

HOST = os.environ.get('UP_SOCKS_HOST', '2.27.109.115')
PORT = int(os.environ.get('UP_SOCKS_PORT', '26265'))
USER = os.environ.get('UP_USER', 'ce5755e2')
PASS = os.environ.get('UP_PASS', '6ee609f02061f29dee296314')
LISTEN_HOST, LISTEN_PORT = os.environ.get('LISTEN', '127.0.0.1:8899').split(':')
LISTEN_PORT = int(LISTEN_PORT)

async def socks_connect(target_host, target_port):
    r, w = await asyncio.open_connection(HOST, PORT)
    w.write(b'\x05\x02\x00\x02'); await w.drain()
    ver, meth = await r.readexactly(2)
    if meth == 0x02:
        u = USER.encode(); p = PASS.encode()
        w.write(b'\x01' + bytes([len(u)]) + u + bytes([len(p)]) + p); await w.drain()
        av, st = await r.readexactly(2)
        if st != 0: raise RuntimeError('socks auth failed')
    elif meth != 0x00:
        raise RuntimeError('socks no acceptable method %r' % meth)
    hb = target_host.encode()
    req = b'\x05\x01\x00\x03' + bytes([len(hb)]) + hb + struct.pack('!H', target_port)
    w.write(req); await w.drain()
    rep = await r.readexactly(4)
    if rep[1] != 0: raise RuntimeError('socks connect rc=%d' % rep[1])
    atyp = rep[3]
    if atyp == 0x01: await r.readexactly(4)
    elif atyp == 0x03:
        ln = (await r.readexactly(1))[0]; await r.readexactly(ln)
    elif atyp == 0x04: await r.readexactly(16)
    await r.readexactly(2)
    return r, w

async def pump(a, b):
    try:
        while True:
            d = await a.read(65536)
            if not d: break
            b.write(d); await b.drain()
    except Exception: pass
    finally:
        try: b.close()
        except Exception: pass

async def handle(cr, cw):
    try:
        head = await cr.readuntil(b'\r\n\r\n')
        line = head.split(b'\r\n', 1)[0].decode('latin1')
        parts = line.split()
        if len(parts) < 2 or parts[0].upper() != 'CONNECT':
            cw.write(b'HTTP/1.1 405 Method Not Allowed\r\nContent-Length: 0\r\n\r\n'); await cw.drain(); cw.close(); return
        th = parts[1]
        host, _, port = th.rpartition(':')
        host = host.strip('[]'); port = int(port)
        try:
            rr, rw = await socks_connect(host, port)
        except Exception as e:
            cw.write(b'HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\n\r\n'); await cw.drain(); cw.close(); return
        cw.write(b'HTTP/1.1 200 Connection established\r\n\r\n'); await cw.drain()
        await asyncio.gather(pump(cr, rw), pump(rr, cw))
    except Exception:
        try: cw.close()
        except Exception: pass

async def main():
    srv = await asyncio.start_server(handle, LISTEN_HOST, LISTEN_PORT)
    print('chain_proxy listening %s:%d -> socks5 %s:%d' % (LISTEN_HOST, LISTEN_PORT, HOST, PORT), flush=True)
    async with srv:
        await srv.serve_forever()

asyncio.run(main())

import struct,zlib,sys
def read_png(fn):
    d=open(fn,'rb').read(); assert d[:8]==b'\x89PNG\r\n\x1a\n'
    i=8; idat=b''; plte=None; ihdr=None
    while i<len(d):
        ln=struct.unpack('>I',d[i:i+4])[0]; typ=d[i+4:i+8]; data=d[i+8:i+8+ln]; i+=12+ln
        if typ==b'IHDR': ihdr=struct.unpack('>IIBBBBB',data)
        elif typ==b'PLTE': plte=data
        elif typ==b'IDAT': idat+=data
        elif typ==b'IEND': break
    w,h,bd,ct,comp,filt,inter=ihdr
    assert bd==4 and ct==3 and inter==0,(bd,ct,inter)
    raw=zlib.decompress(idat)
    bpr=(w*4+7)//8  # bytes per row packed
    stride=bpr
    out=bytearray(h*stride)
    prev=bytearray(stride)
    pos=0
    for y in range(h):
        f=raw[pos]; pos+=1
        line=bytearray(raw[pos:pos+stride]); pos+=stride
        if f==0: pass
        elif f==1:
            for x in range(1,stride): line[x]=(line[x]+line[x-1])&0xff
        elif f==2:
            for x in range(stride): line[x]=(line[x]+prev[x])&0xff
        elif f==3:
            for x in range(stride):
                a=line[x-1] if x>0 else 0
                line[x]=(line[x]+((a+prev[x])>>1))&0xff
        elif f==4:
            for x in range(stride):
                a=line[x-1] if x>0 else 0; b=prev[x]; c=prev[x-1] if x>0 else 0
                p=a+b-c; pa=abs(p-a); pb=abs(p-b); pc=abs(p-c)
                pr=a if (pa<=pb and pa<=pc) else (b if pb<=pc else c)
                line[x]=(line[x]+pr)&0xff
        else: raise Exception('filt',f)
        out[y*stride:(y+1)*stride]=line; prev=line
    # extract indices
    idx=[[0]*w for _ in range(h)]
    for y in range(h):
        row=out[y*stride:(y+1)*stride]
        for x in range(w):
            b=row[x>>1]
            idx[y][x]=(b>>4) if (x&1)==0 else (b&0x0f)
    return w,h,idx,plte
if __name__=='__main__':
    for fn in sys.argv[1:]:
        w,h,idx,plte=read_png(fn)
        colors=[(plte[k*3],plte[k*3+1],plte[k*3+2]) for k in range(len(plte)//3)]
        print(fn,w,h,'palette:',colors)
        from collections import Counter
        c=Counter()
        for row in idx: c.update(row)
        print(' idx hist',sorted(c.items()))

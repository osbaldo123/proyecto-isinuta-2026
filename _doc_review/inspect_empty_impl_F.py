# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"
WP = "{http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing}"
V = "{urn:schemas-microsoft-com:vml}"
R_rel = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"

with ZipFile(src) as z:
    root = etree.fromstring(z.read("word/document.xml"))
    rels = etree.fromstring(z.read("word/_rels/document.xml.rels"))
    media = sorted(n for n in z.namelist() if n.startswith("word/media/"))
    print("media count", len(media))
    for n in media:
        print(n, len(z.read(n)))

rid = {}
for rel in rels:
    if rel.get("Id"):
        rid[rel.get("Id")] = (rel.get("Target"), rel.get("Type", "").split("/")[-1])

paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


print("\n=== around fig 35-38 body ===")
for i, p in enumerate(paras):
    t = pt(p)
    if re.search(r"Figura\s+3[5-8]\s*:", t) and "Tablade" not in st(p) and i > 1000:
        for j in range(i - 1, min(i + 6, len(paras))):
            blips = []
            for blip in paras[j].findall(".//" + A + "blip"):
                e = blip.get(R + "embed")
                blips.append((e, rid.get(e)))
            # also drawing count / pict
            drawings = len(paras[j].findall(".//" + W + "drawing"))
            picts = len(paras[j].findall(".//" + W + "pict"))
            print(f"{j}|{st(paras[j])}|draw={drawings} pict={picts} blips={blips}|{pt(paras[j])[:100]!r}")
        print("---")

print("\n=== modelo BD block ===")
for i in range(1540, 1565):
    p = paras[i]
    blips = [(b.get(R + "embed"), rid.get(b.get(R + "embed"))) for b in p.findall(".//" + A + "blip")]
    print(f"{i}|{st(p)}|blips={blips}|{pt(p)[:200]!r}")

print("\n=== last rel ids ===")
ids = sorted(rid.items(), key=lambda x: int(re.search(r"\d+", x[0]).group()) if re.search(r"\d+", x[0]) else 0)
for k, v in ids[-15:]:
    print(k, v)

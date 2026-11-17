# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re
import hashlib
from datetime import datetime

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
caps = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_real_captures")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"

z = ZipFile(src)
root = etree.fromstring(z.read("word/document.xml"))
rels = etree.fromstring(z.read("word/_rels/document.xml.rels"))
rid = {rel.get("Id"): rel.get("Target") for rel in rels if rel.get("Id")}
paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


fig_media = {}
fig_caption = {}
for i, p in enumerate(paras):
    t = pt(p)
    m = re.search(r"Figura\s+(\d+)\s*:", t)
    if not m:
        continue
    fig = int(m.group(1))
    fig_caption[fig] = t
    for j in range(i, min(i + 3, len(paras))):
        for blip in paras[j].findall(".//" + A + "blip"):
            tgt = rid.get(blip.get(R + "embed"))
            if tgt:
                fig_media[fig] = tgt
                break
        if fig in fig_media:
            break

key = {
    28: "fig28-pagos-create.png",
    32: "fig32-recibo.png",
    37: "fig37-ingresos.png",
    38: "fig38-deudas.png",
}
print("=== Esta noche (recapturadas e insertadas) ===")
for fig, png in key.items():
    ok = False
    if fig in fig_media and (caps / png).exists():
        data = z.read("word/" + fig_media[fig])
        ok = hashlib.md5(data).digest() == hashlib.md5((caps / png).read_bytes()).digest()
    print(f"Fig {fig}: {fig_media.get(fig)} insertada={ok} | {fig_caption.get(fig,'')[:70]}")

print("\n=== Resto implementación (15-36) en el doc ===")
for fig in sorted(f for f in fig_media if 15 <= f <= 36):
    media = fig_media[fig]
    size = len(z.read("word/" + media))
    print(f"Fig {fig}: {media} ({size} bytes) | {fig_caption.get(fig,'')[:75]}")

print("\n=== Capturas locales recientes ===")
for p in sorted(caps.glob("fig*.png"), key=lambda x: x.stat().st_mtime, reverse=True)[:18]:
    print(datetime.fromtimestamp(p.stat().st_mtime).strftime("%Y-%m-%d %H:%M"), p.name, p.stat().st_size)

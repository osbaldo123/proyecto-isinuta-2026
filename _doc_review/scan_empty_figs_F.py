# -*- coding: utf-8 -*-
"""Detecta figuras de implementación vacías/pequeñas y sección BD."""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"
A14 = "{http://schemas.microsoft.com/office/drawing/2010/main}"

with ZipFile(src) as z:
    root = etree.fromstring(z.read("word/document.xml"))
    rels = etree.fromstring(z.read("word/_rels/document.xml.rels"))
    rid = {rel.get("Id"): rel.get("Target") for rel in rels if rel.get("Id")}
    media_sizes = {}
    for name in z.namelist():
        if name.startswith("word/media/"):
            media_sizes[name.replace("word/", "")] = len(z.read(name))

paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


print("=== Figuras 15-38 (caption -> media size) ===")
for i, p in enumerate(paras):
    t = pt(p)
    m = re.search(r"Figura\s+(\d+)\s*:", t)
    if not m:
        continue
    fig = int(m.group(1))
    if fig < 15 or fig > 40:
        continue
    media = None
    # image usually next para(s)
    for j in range(i, min(i + 4, len(paras))):
        for blip in paras[j].findall(".//" + A + "blip"):
            tgt = rid.get(blip.get(R + "embed"))
            if tgt:
                media = tgt
                break
        if media:
            break
    size = media_sizes.get(media, 0) if media else 0
    flag = "VACIA?" if (not media or size < 20000) else ("CHICA" if size < 50000 else "ok")
    note = ""
    for j in range(i, min(i + 5, len(paras))):
        if pt(paras[j]).startswith("Nota:"):
            note = pt(paras[j])[:70]
            break
    print(f"Fig {fig:02d} | {flag:6} | {media} ({size} B) | {t[:70]} | {note}")

print("\n=== modelo / base de datos ===")
for i, p in enumerate(paras):
    t = pt(p)
    if re.search(r"base de datos|modelo de datos|diagrama ER|entidad.?relaci", t, re.I):
        print(f"{i}|{st(p)}|{t[:140]}")

print("\n=== Cap III fin / Cap IV ===")
for i, p in enumerate(paras):
    s = st(p)
    t = pt(p)
    if s in ("Ttulo1", "Ttulo2", "Ttulo3") and (
        "Sprint 4" in t or "CAPÍTULO IV" in t.upper() or "Sprint Review" in t or "base" in t.lower()
    ):
        if i > 1380:
            print(f"{i}|{s}|{t[:100]}")

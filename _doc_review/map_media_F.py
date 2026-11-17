# -*- coding: utf-8 -*-
"""Mapear figuras del doc -F a word/media/*."""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"

with ZipFile(src, "r") as z:
    xml = z.read("word/document.xml")
    rels = z.read("word/_rels/document.xml.rels")
    media = [n for n in z.namelist() if n.startswith("word/media/")]

root = etree.fromstring(xml)
rels_root = etree.fromstring(rels)
rid_to_target = {}
for rel in rels_root:
    rid = rel.get("Id")
    target = rel.get("Target")
    if rid and target:
        rid_to_target[rid] = target

paras = list(root.iter(W + "p"))


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


for i, p in enumerate(paras):
    t = para_text(p)
    m = re.search(r"Figura\s+(\d+)", t, re.I)
    if not m:
        continue
    fig = int(m.group(1))
    if fig not in (28, 32, 37, 38):
        continue
    # buscar blip en este párrafo o vecinos
    for j in range(max(0, i - 3), min(len(paras), i + 4)):
        for blip in paras[j].findall(".//" + A + "blip"):
            embed = blip.get(R + "embed")
            target = rid_to_target.get(embed)
            print(f"Fig {fig} para={i} near={j} rid={embed} target={target} text={t[:80]}")

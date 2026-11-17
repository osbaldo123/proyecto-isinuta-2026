# -*- coding: utf-8 -*-
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

root = etree.fromstring(xml)
rels_root = etree.fromstring(rels)
rid_to_target = {rel.get("Id"): rel.get("Target") for rel in rels_root if rel.get("Id")}
paras = list(root.iter(W + "p"))


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


for i, p in enumerate(paras):
    if i < 1310 or i > 1495:
        continue
    t = para_text(p)
    blips = []
    for blip in p.findall(".//" + A + "blip"):
        embed = blip.get(R + "embed")
        blips.append(rid_to_target.get(embed))
    if blips or re.search(r"Figura\s+\d+", t, re.I):
        print(f"{i}: blips={blips} text={t[:100]!r}")

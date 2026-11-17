# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
root = etree.fromstring(ZipFile(src).read("word/document.xml"))
paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


print("=== alrededor 3.7 / 3.8 / Cap IV ===")
for i, p in enumerate(paras):
    t = pt(p)
    if "3.7" in t or "3.8" in t or (st(p) == "Ttulo1" and "CAPÍTULO IV" in t.upper()) or "Pruebas realizadas" in t or "Análisis de resultados" in t:
        for j in range(max(0, i - 3), min(len(paras), i + 12)):
            print(f"{j}|{st(paras[j])}|{pt(paras[j])[:220]!r}")
        print("---")
        break

# also find all 3.7/3.8 occurrences
print("\n=== todas menciones ===")
for i, p in enumerate(paras):
    t = pt(p)
    if "Pruebas realizadas" in t or "Análisis de resultados" in t or t.strip().startswith("3.7") or t.strip().startswith("3.8") or "3.6.5" in t or "3.6.6" in t:
        # numbering in pPr
        pPr = p.find(W + "pPr")
        num = None
        if pPr is not None:
            nv = pPr.find(W + "numPr")
            num = "HAS_NUM" if nv is not None else "no_num"
        print(f"{i}|{st(p)}|{num}|{t[:180]}")

# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Downloads\PROYECTO_DE_GRADO_2026 (2).docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
root = etree.fromstring(ZipFile(src).read("word/document.xml"))
paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


print("=== Títulos Cap III / IV / pruebas / análisis ===")
for i, p in enumerate(paras):
    t = pt(p)
    s = st(p)
    if not t:
        continue
    if re.search(
        r"CAP[IÍ]TULO|3\.\d|Pruebas|An[aá]lisis|CONCLUS|Sprint\s*[34]|Recomend",
        t,
        re.I,
    ) and (s.startswith("Ttulo") or s.startswith("Heading") or re.match(r"^\d", t) or "Prueba" in t or "Análisis" in t or "CAPÍTULO" in t.upper()):
        if len(t) < 180 or "Prueba" in t or "Análisis" in t or "CAPÍTULO" in t.upper():
            print(f"{i}|{s}|{t[:160]}")

print("\n=== Bloque alrededor de Pruebas / Análisis ===")
for i, p in enumerate(paras):
    t = pt(p)
    if re.search(r"Pruebas realizadas|An[aá]lisis de resultados|3\.7|3\.8", t, re.I):
        for j in range(max(0, i - 2), min(len(paras), i + 25)):
            tt = pt(paras[j])
            if tt:
                print(f"{j}|{st(paras[j])}|{tt[:200]}")
        print("---")

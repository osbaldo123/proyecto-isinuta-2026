# -*- coding: utf-8 -*-
"""Compara títulos (Ttulo1-4 / Heading) entre -F y referencia."""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

F = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
REF = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


def load(path):
    root = etree.fromstring(ZipFile(path).read("word/document.xml"))
    paras = list(root.iter(W + "p"))
    rows = []
    for i, p in enumerate(paras):
        pPr = p.find(W + "pPr")
        style = ""
        if pPr is not None:
            ps = pPr.find(W + "pStyle")
            if ps is not None:
                style = ps.get(W + "val") or ""
        text = "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()
        outline = None
        if pPr is not None:
            ol = pPr.find(W + "outlineLvl")
            if ol is not None:
                outline = ol.get(W + "val")
        num = pPr is not None and pPr.find(W + "numPr") is not None
        if style.startswith("Ttulo") or style.startswith("Heading") or style.startswith("TDC") or (
            text.upper().startswith("CAPÍTULO") or text.upper().startswith("CAPITULO")
        ):
            rows.append((i, style, num, outline, text[:120]))
    return paras, rows


def body_titles(rows):
    """Solo títulos de cuerpo (no TOC)."""
    out = []
    for i, style, num, ol, text in rows:
        if style.startswith("TDC") or style.startswith("Tablade") or "toc" in style.lower():
            continue
        if style.startswith("Ttulo") or style.startswith("Heading") or text.upper().startswith("CAPIT"):
            out.append((i, style, num, text))
    return out


_, f_rows = load(F)
_, r_rows = load(REF)
f_body = body_titles(f_rows)
r_body = body_titles(r_rows)

print("=== REF body titles ===")
for x in r_body:
    print(f"{x[0]}|{x[1]}|num={x[2]}|{x[3]}")

print("\n=== F body titles ===")
for x in f_body:
    print(f"{x[0]}|{x[1]}|num={x[2]}|{x[3]}")

print("\n=== CAP / MARCO / BIBLIO / ANEXOS detail in F ===")
root = etree.fromstring(ZipFile(F).read("word/document.xml"))
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    text = "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()
    if re.search(r"CAP[IÍ]TULO|MARCO TE|BIBLIO|ANEXOS|PROPUESTA|CONCLUS", text, re.I):
        pPr = p.find(W + "pPr")
        style = ""
        if pPr is not None:
            ps = pPr.find(W + "pStyle")
            if ps is not None:
                style = ps.get(W + "val") or ""
        print(f"{i}|style={style!r}|{text[:140]}")

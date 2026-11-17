# -*- coding: utf-8 -*-
"""Extrae texto marcado en amarillo (highlight o shading) del doc del tutor."""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F - segunda revision del tutor.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
out = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_doc_review\yellow_tutor_obs.txt")

YELLOW = {"yellow", "FFFF00", "ffff00", "FFEB9C", "ffeb9c", "FFFF99", "ffff99"}


def is_yellow_rPr(rPr):
    if rPr is None:
        return False
    hl = rPr.find(W + "highlight")
    if hl is not None and (hl.get(W + "val") or "").lower() in {"yellow", "darkYellow"}:
        return True
    shd = rPr.find(W + "shd")
    if shd is not None:
        fill = (shd.get(W + "fill") or "").upper()
        if fill in {"FFFF00", "FFEB9C", "FFFF99", "FFF2CC", "FAED48"}:
            return True
    return False


def is_yellow_pPr(pPr):
    if pPr is None:
        return False
    shd = pPr.find(W + "shd")
    if shd is not None:
        fill = (shd.get(W + "fill") or "").upper()
        if fill in {"FFFF00", "FFEB9C", "FFFF99", "FFF2CC", "FAED48"}:
            return True
    return False


with ZipFile(src, "r") as z:
    root = etree.fromstring(z.read("word/document.xml"))

paras = list(root.iter(W + "p"))
items = []
for i, p in enumerate(paras):
    p_yellow = is_yellow_pPr(p.find(W + "pPr"))
    full = []
    yel = []
    for r in p.findall(W + "r"):
        rPr = r.find(W + "rPr")
        texts = "".join(t.text or "" for t in r.findall(W + "t"))
        if not texts:
            continue
        full.append(texts)
        if p_yellow or is_yellow_rPr(rPr):
            yel.append(texts)
    if yel:
        items.append((i, "".join(yel).strip(), "".join(full).strip()))

lines = [f"TOTAL yellow paras/spans: {len(items)}\n"]
for i, yel, full in items:
    lines.append(f"--- para {i} ---")
    lines.append(f"YELLOW: {yel}")
    if full != yel:
        lines.append(f"FULL:  {full[:400]}")
    lines.append("")

out.write_text("\n".join(lines), encoding="utf-8")
print(f"wrote {out} count={len(items)}")
# also print first 80 for console
for i, yel, full in items[:80]:
    print(f"[{i}] {yel[:200]}")
if len(items) > 80:
    print(f"... +{len(items)-80} more")

# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

F = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
REF = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


def dump_range(path, start, end, label):
    root = etree.fromstring(ZipFile(path).read("word/document.xml"))
    paras = list(root.iter(W + "p"))
    print(f"\n===== {label} {start}-{end} =====")
    for i in range(start, min(end, len(paras))):
        p = paras[i]
        text = "".join(t.text or "" for t in p.findall(".//" + W + "t"))
        pPr = p.find(W + "pPr")
        style = ""
        numPr = False
        page_break = False
        sect = False
        if pPr is not None:
            ps = pPr.find(W + "pStyle")
            if ps is not None:
                style = ps.get(W + "val") or ""
            numPr = pPr.find(W + "numPr") is not None
            for br in p.findall(".//" + W + "br"):
                if br.get(W + "type") == "page":
                    page_break = True
            if pPr.find(W + "sectPr") is not None or p.find(W + "sectPr") is not None:
                sect = True
            # lastRenderedPageBreak
        lrp = len(p.findall(".//{http://schemas.openxmlformats.org/wordprocessingml/2006/main}lastRenderedPageBreak"))
        # also w:br page in runs
        for br in p.findall(".//" + W + "br"):
            if br.get(W + "type") == "page":
                page_break = True
        print(f"{i}|style={style!r}|num={numPr}|pgBr={page_break}|lrp={lrp}|text={text[:100]!r}")
        # if title-like, show pPr xml snippet
        if "CAPÍTULO" in text.upper() or "CAPITULO" in text.upper() or style.startswith("Ttulo1"):
            if pPr is not None:
                xml = etree.tostring(pPr, encoding="unicode")
                print("  pPr:", xml[:400])


# Cap II end -> Cap III in F
dump_range(F, 455, 480, "F around CapIII")
dump_range(REF, 448, 465, "REF around CapIII")
dump_range(F, 250, 265, "F CapI")
dump_range(REF, 248, 260, "REF CapI")
dump_range(F, 1555, 1575, "F CapIV")
dump_range(REF, 1490, 1505, "REF CapIV")

# search garbage
root = etree.fromstring(ZipFile(F).read("word/document.xml"))
paras = list(root.iter(W + "p"))
print("\n=== suspicious short/garbage ===")
for i, p in enumerate(paras):
    t = "".join(x.text or "" for x in p.findall(".//" + W + "t")).strip()
    if re.search(r"Honx|Gnlow|[\x00-\x08]", t) or (len(t) < 8 and re.search(r"[A-Za-z]{4,}.*[&%$#]", t)):
        print(i, repr(t))

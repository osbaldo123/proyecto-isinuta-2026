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


# print key paragraph indices
keys = [
    r"^4\.\s*CONCLUS",
    r"^CONCLUS",
    r"3\.6",
    r"Sprint 4",
    r"BIBLIOGRAF",
    r"^ANEXOS$",
    r"Justificaci",
    r"Enfoque metodol",
    r"Definici[oó]n\.de",
    r"serivicos",
    r"TOTAL SPRINT",
    r"Historia de usuario.*Pagos",
    r"Definición de hecho",
    r"Capítulo III se describe",
    r"Product Owner",
    r"roles del sistema",
    r"Roles del sistema",
    r"Basurto, N",
    r"Hernández Sampieri",
    r"HU-02",
    r"correo electrónico o CI",
    r"Figura 28",
    r"Figura 32",
    r"Nota:.*[Rr]ecibo",
    r"Tabla 14",
    r"casos de prueba",
]
for i, p in enumerate(paras):
    t = pt(p)
    for k in keys:
        if re.search(k, t, re.I):
            print(f"{i}|{st(p)}|{t[:140]}")
            break

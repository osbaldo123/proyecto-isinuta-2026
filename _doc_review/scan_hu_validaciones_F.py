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


# Encontrar bloque Historias de usuario hasta Product Backlog
start = next(i for i, p in enumerate(paras) if pt(p).strip().startswith("Historias de usuario"))
end = next(i for i, p in enumerate(paras) if i > start and ("Product Backlog" in pt(p) or pt(p).startswith("Ejecución del Sprint 1")))
print("range", start, end)

hu = None
buf = []
for i in range(start, end):
    t = pt(paras[i]).strip()
    if re.search(r"\bHU-\d+\b", t) or t.startswith("Nombre de historia"):
        if "HU-" in t:
            print(f"\n--- {i} | {t[:100]}")
    if t == "Validación:" or t.startswith("Validación"):
        print(f"VALID @{i}: {t}")
        # print following bullets until Prioridad
        for j in range(i + 1, min(i + 25, end)):
            tj = pt(paras[j]).strip()
            if tj.startswith("Prioridad") or tj == "Prioridad:":
                break
            if tj:
                print(f"  {j}|{len(tj):3d}|{tj[:140]}")

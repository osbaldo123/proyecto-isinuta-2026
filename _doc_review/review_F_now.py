# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
from datetime import datetime
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"

print("Archivo:", src.name)
print("Modificado:", datetime.fromtimestamp(src.stat().st_mtime).strftime("%Y-%m-%d %H:%M:%S"))
print("Tamaño:", src.stat().st_size)

z = ZipFile(src)
root = etree.fromstring(z.read("word/document.xml"))
rels = etree.fromstring(z.read("word/_rels/document.xml.rels"))
rid = {rel.get("Id"): rel.get("Target") for rel in rels if rel.get("Id")}
paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


full = "\n".join(pt(p) for p in paras)

bad = {
    "parrafo Consulta/roles prueba": bool(
        re.search(r"roles adicionales de prueba|roles documentados para la operación", full)
    ),
    "SIRAF": "SIRAF" in full,
    "3.7 Pruebas falsas": "3.7 Pruebas realizadas" in full,
    "3.8 Analisis falso": "3.8 Análisis de resultados" in full,
    "Cap I doble numero": bool(re.search(r"1\s+CAPÍTULO I:", full)),
    "correo o CI": "correo electrónico o CI" in full,
}
good = {
    "mora dia 1": "día 1 del mes siguiente" in full,
    "Fig 39 ERD": "Figura 39: Modelo de base de datos" in full,
    "Cap III titulo": "CAPÍTULO III: PROPUESTA DE INNOVACIÓN" in full,
    "solo Presidente/Secretaria en roles": True,
}

print("\n=== No debe aparecer ===")
for k, v in bad.items():
    print(("MAL" if v else "OK "), k)

print("\n=== Debe estar ===")
for k, v in good.items():
    print(("OK " if v else "NO "), k)

print("\n=== Títulos Cap ===")
for i, p in enumerate(paras):
    t = pt(p).strip()
    s = st(p)
    if s == "Ttulo1" or (re.match(r"^CAP[IÍ]TULO", t) and s == ""):
        if any(x in t.upper() for x in ["CAPÍTULO", "CAPITULO", "RESUMEN", "INTRODU", "BIBLIO", "ANEXO"]):
            label = s if s else "portadilla"
            print(f"{i}|{label}|{t[:90]}")

print("\n=== Especificación de usuarios ===")
for i, p in enumerate(paras):
    if pt(p).strip() == "Especificación de usuarios.":
        for j in range(i, i + 5):
            print(f"{j}|{pt(paras[j])[:170]}")
        break

print("\n=== Fig 35-39 ===")
for fig in range(35, 40):
    for i, p in enumerate(paras):
        if re.match(rf"Figura\s+{fig}\s*:", pt(p)) and i > 1000:
            draws = []
            for j in range(max(0, i - 1), min(len(paras), i + 2)):
                for b in paras[j].findall(".//" + A + "blip"):
                    tgt = rid.get(b.get(R + "embed"))
                    size = len(z.read("word/" + tgt)) if tgt and ("word/" + tgt) in z.namelist() else 0
                    draws.append(f"{tgt}({size})")
            estado = "CON IMAGEN" if draws else "VACIA"
            print(f"Fig {fig}: {estado} {draws}")
            break

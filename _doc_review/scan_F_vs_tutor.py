# -*- coding: utf-8 -*-
"""Estado de -F.docx frente a observaciones clave del tutor."""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"

with ZipFile(src) as z:
    root = etree.fromstring(z.read("word/document.xml"))

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
checks = {
    "mora dia1": "día 1 del mes siguiente" in full,
    "mora 50-100-150": bool(re.search(r"Bs\.\s*50.*Bs\.\s*100.*Bs\.\s*150", full, re.S)),
    "Lozada biblio": "Lozada, J." in full,
    "Hernandez Sampieri biblio": bool(re.search(r"Hern[aá]ndez Sampieri", full)),
    "Adam cite": "Adam (2023)" in full,
    "Thomas cite": "Thomas (2023)" in full,
    "Inertia Team biblio": "Inertia.js Development Team" in full,
    "3.7 Pruebas": bool(re.search(r"3\.7\s+Pruebas", full)),
    "3.8 Analisis": bool(re.search(r"3\.8\s+An[aá]lisis", full)),
    "intro menciona pruebas": "pruebas realizadas" in full.lower(),
    "Definicion p.16": bool(re.search(r"Definici[oó]n\.de.*p\.\s*16", full, re.S)),
    "Gestionar / Pagos split": "Gestionar\nPagos" in full or bool(re.search(r"Gestionar\s+Pagos", full)),
    "TOTAL SPRINT 3": "TOTAL SPRINT 3" in full.upper(),
    "TOTAL SPRINT 4": "TOTAL SPRINT 4" in full.upper(),
    "Macias Basurto biblio": "Macías Basurto" in full or "Macias Basurto" in full,
    "Basurto, N. M.": "Basurto, N. M." in full,
    "Correo o CI": "Correo o CI" in full,
    "ver ANEXO 1": "ANEXO 1" in full.upper() or "Anexo 1" in full,
    "Product Owner justificacion": bool(re.search(r"Product Owner.{0,80}Developer", full, re.S)),
    "Observacion del tutor": "Observación del tutor:" in full,
}
for k, v in checks.items():
    print(("OK " if v else "NO "), k)

# find anexo titles
print("\n--- Anexos titles ---")
for p in paras:
    t = pt(p)
    if re.match(r"^ANEXO\s*\d", t.upper()) or "ANEXOS" == t.upper():
        print(t[:120])

# find HU-08 cell-ish
print("\n--- snippets ---")
for pat in [
    r".{0,40}Gestionar.{0,20}Pagos.{0,40}",
    r".{0,60}Product Owner.{0,100}",
    r".{0,40}Cap[ií]tulo III.{0,120}pruebas.{0,80}",
    r"HU-02.{0,200}",
]:
    m = re.search(pat, full, re.I | re.S)
    print(pat[:40], "=>", re.sub(r"\s+", " ", m.group(0))[:180] if m else "NONE")

# biblio count
bi = next(i for i, p in enumerate(paras) if st(p) == "Ttulo1" and pt(p).upper().startswith("BIBLIO"))
n = 0
for i in range(bi + 1, len(paras)):
    t = pt(paras[i])
    s = st(paras[i])
    if s == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if s == "Bibliografa" and t:
        n += 1
print("biblio entries", n)

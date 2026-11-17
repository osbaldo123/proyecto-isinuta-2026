# -*- coding: utf-8 -*-
"""
Alinea citas en el cuerpo con entradas de bibliografía (APA narrativa).
Corrige desajustes conocidos y reporta citas sin entrada.
Arial 11 + interlineado 1.5 en párrafos tocados.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re
import unicodedata

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_citas.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


def fold(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.lower())
    return "".join(c for c in s if not unicodedata.combining(c))


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def set_run_arial11(r):
    rPr = r.find(W + "rPr")
    if rPr is None:
        rPr = etree.Element(W + "rPr")
        r.insert(0, rPr)
    fonts = rPr.find(W + "rFonts")
    if fonts is None:
        fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    sz = rPr.find(W + "sz")
    if sz is None:
        sz = etree.SubElement(rPr, W + "sz")
    sz.set(W + "val", "22")
    szCs = rPr.find(W + "szCs")
    if szCs is None:
        szCs = etree.SubElement(rPr, W + "szCs")
    szCs.set(W + "val", "22")


def set_para_spacing_15(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")


def replace_in_para(p, old, new):
    texts = list(p.findall(".//" + W + "t"))
    if not texts:
        return False
    full = "".join(t.text or "" for t in texts)
    if old not in full:
        return False
    full2 = full.replace(old, new)
    # poner todo en primer run; vaciar el resto
    texts[0].text = full2
    for t in texts[1:]:
        t.text = ""
    for r in p.findall(W + "r"):
        set_run_arial11(r)
    set_para_spacing_15(p)
    return True


shutil.copy2(src, backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))
biblio_i = next(i for i, p in enumerate(paras) if style(p) == "Ttulo1" and para_text(p).upper().startswith("BIBLIO"))

biblio = []
for i in range(biblio_i + 1, len(paras)):
    t = para_text(paras[i])
    st = style(paras[i])
    if st == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if st == "Bibliografa" and t:
        biblio.append(t)

print("biblio", len(biblio))
for e in biblio:
    print(" •", e[:100])

# Correcciones de citas que no coinciden con la bibliografía
FIXES = [
    # Tailwind: biblio Adam, T. (2023) — cuerpo decía Thomas (2023)
    ("Según Thomas (2023), Tailwind CSS", "Según Adam (2023), Tailwind CSS"),
    ("Thomas (2023), Tailwind", "Adam (2023), Tailwind"),
    ("Thomas (2023)", "Adam (2023)"),
]

changed = 0
for i, p in enumerate(paras[:biblio_i]):
    for old, new in FIXES:
        if replace_in_para(p, old, new):
            changed += 1
            print("FIX", old, "=>", new)

# Citas narrativas típicas vs biblio
cite_re = re.compile(
    r"((?:Según|Conforme|De acuerdo con)\s+)?([A-ZÁÉÍÓÚÑ][\wÁÉÍÓÚÑáéíóúñüÜ\.\-]+(?:\s+(?:y|&|e)\s+[A-ZÁÉÍÓÚÑ][\wÁÉÍÓÚÑáéíóúñüÜ\.\-]+)*(?:\s*,\s*[A-ZÁÉÍÓÚÑ][\wÁÉÍÓÚÑáéíóúñüÜ\.\-]+)*)\s*\((\d{4})\)"
)

body_text = "\n".join(para_text(p) for p in paras[:biblio_i])
biblio_f = fold("\n".join(biblio))

missing = []
seen = set()
for m in cite_re.finditer(body_text):
    authors = m.group(2)
    year = m.group(3)
    key = (authors, year)
    if key in seen:
        continue
    seen.add(key)
    # primer apellido
    first = re.split(r"\s+y\s+|\s+&\s+|,\s*", authors)[0].strip()
    first = first.split()[-1] if " " in first and first.lower().startswith(("macías", "macias")) else first.split()[0]
    # Macías Basurto -> Basurto
    toks = authors.replace(",", " ").split()
    ok = False
    if fold(year) in biblio_f and any(fold(t) in biblio_f for t in toks if len(t) > 3):
        ok = True
    # special maps
    special = {
        "you": "you",
        "otwell": "otwell",
        "inertia.js": "inertia",
        "uam": "autonoma",
        "sparx": "sparx",
        "proagile": "proagile",
        "definicion.de": "definicion",
        "definición.de": "definicion",
        "nixon": "nixon",
        "thomas": "adam",
        "adam": "adam",
    }
    fl = fold(first)
    if fl in special and special[fl] in biblio_f:
        ok = True
    if not ok:
        missing.append(f"{authors} ({year})")

print("unique narrative cites", len(seen))
print("possibly missing in biblio", len(missing))
for x in missing:
    print("  MISSING?", x)

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_citas.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("changed", changed)
print("saved", src)

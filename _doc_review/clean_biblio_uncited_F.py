# -*- coding: utf-8 -*-
"""Elimina de la bibliografía las referencias no citadas en el cuerpo (conserva formato)."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re
import unicodedata

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_biblio_clean.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def fold(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.lower())
    return "".join(c for c in s if not unicodedata.combining(c))


def author_keys(entry: str):
    """Genera claves de búsqueda a partir de la entrada bibliográfica."""
    keys = set()
    head = entry.split("(")[0].strip()
    # quitar & y comas
    parts = re.split(r",|&| y ", head)
    surnames = []
    for p in parts:
        p = p.strip()
        if not p or p.lower() in {"et", "al", "al."}:
            continue
        # primer token suele ser apellido
        tok = p.split()[0].strip(".")
        if len(tok) >= 3:
            surnames.append(tok)
    if surnames:
        keys.add(fold(surnames[0]))
        if len(surnames) >= 2:
            keys.add(fold(surnames[0] + " y " + surnames[1]))
            keys.add(fold(surnames[0] + " & " + surnames[1]))
    # org names
    if head.lower().startswith(("ibm", "oracle", "laragon", "microsoft", "senasba", "definición", "real academia", "centro internacional", "universidad", "sparx", "phpmyadmin", "proagile", "packt", "amazon", "agile")):
        keys.add(fold(head.split(".")[0][:40]))
    # year
    m = re.search(r"\((\d{4})", entry)
    year = m.group(1) if m else None
    return keys, year


shutil.copy2(src, backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# cuerpo completo sin bibliografía
biblio_i = None
for i, p in enumerate(paras):
    if style(p) == "Ttulo1" and para_text(p).upper().startswith("BIBLIO"):
        biblio_i = i
body_text = fold("\n".join(para_text(p) for p in paras[:biblio_i]))

biblio_nodes = []
for i in range(biblio_i + 1, len(paras)):
    t = para_text(paras[i])
    st = style(paras[i])
    if st == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if st == "Bibliografa" and t:
        biblio_nodes.append(paras[i])

kept = 0
removed = 0
for p in list(biblio_nodes):
    entry = para_text(p)
    keys, year = author_keys(entry)
    cited = False
    for k in keys:
        if len(k) < 4:
            continue
        # buscar apellido en cuerpo
        if k in body_text:
            # si hay año, preferir coocurrencia cercana (heurística simple)
            if year is None or year in body_text:
                cited = True
                break
    # nombres citados de formas especiales
    specials = {
        "basurto": ["macias basurto", "macías basurto", "basurto"],
        "chiavenato": ["chiavenato"],
        "schwaber": ["schwaber"],
        "vanegas": ["vanegas"],
        "solorzano": ["solorzano", "solórzano"],
        "ferraiolo": ["ferraiolo"],
        "gitman": ["gitman"],
        "robles": ["robles"],
        "senasba": ["senasba"],
        "kendall": ["kendall"],
        "pressman": ["pressman"],
        "sommerville": ["sommerville"],
        "letelier": ["letelier"],
        "maldonado": ["maldonado"],
        "genero": ["genero", "piattini"],
        "jimenez": ["jimenez de parga", "joyanes"],
        "quispe": ["quispe"],
        "paco": ["paco tamo"],
        "solarez": ["solarez"],
        "valenzuela": ["valenzuela"],
        "adam": ["adam", "tailwind"],
        "otwell": ["laravel", "otwell"],
        "you": ["vue.js", "you, e"],
    }
    for k in keys:
        for sk, alts in specials.items():
            if sk in k or k in sk:
                if any(fold(a) in body_text for a in alts):
                    cited = True
    # siempre conservar si se cita explícitamente el título corto conocido
    if any(x in fold(entry) for x in ["scrum guide", "guia de scrum", "inertia"]):
        if "scrum" in body_text or "inertia" in body_text:
            cited = True

    if cited:
        kept += 1
    else:
        p.getparent().remove(p)
        removed += 1
        print("REMOVED", entry[:90])

print(f"kept {kept} removed {removed}")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_biblio_clean.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("saved", src)

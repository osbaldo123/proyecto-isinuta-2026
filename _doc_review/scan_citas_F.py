# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"

with ZipFile(src, "r") as z:
    root = etree.fromstring(z.read("word/document.xml"))

paras = list(root.iter(W + "p"))


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""

biblio_i = next(i for i, p in enumerate(paras) if style(p) == "Ttulo1" and para_text(p).upper().startswith("BIBLIO"))
body_paras = paras[:biblio_i]
body = "\n".join(para_text(p) for p in body_paras)

# buscar años
years = re.findall(r"\b(19|20)\d{2}\b", body)
print("year count in body", len(years), "unique", len(set(years)))

# líneas con patrón autor+año
pat = re.compile(r".{0,50}\(\s*\d{4}[a-z]?\s*\)|.{0,50},\s*\d{4}[a-z]?\s*\)|según\s+\w+.{0,40}\d{4}|cite|citado", re.I)
hits = []
for p in body_paras:
    t = para_text(p)
    if re.search(r"\d{4}", t) and re.search(r"\(|según|conforme|citado|autor", t, re.I):
        hits.append(t[:160])

print("candidate cite paras", len(hits))
for h in hits[:40]:
    print("-", h)

# autores clave mencionados
keys = [
    "Chiavenato", "Schwaber", "Pressman", "Sommerville", "Kendall", "Laravel", "Vue",
    "SENASBA", "Basurto", "Gitman", "Ferraiolo", "Letelier", "Otwell", "Adam",
    "Robles", "Vanegas", "Solorzano", "Solórzano", "Quispe", "Paco", "Maldonado",
    "Jiménez", "Genero", "Piattini", "Inertia", "Tailwind", "Scrum",
]
print("--- author mentions ---")
for k in keys:
    c = len(re.findall(re.escape(k), body, re.I))
    if c:
        print(k, c)

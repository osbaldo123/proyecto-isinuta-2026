# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re
import unicodedata
import hashlib

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
caps = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_real_captures")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"

with ZipFile(src, "r") as z:
    xml = z.read("word/document.xml")
    media_hashes = {}
    for name in [
        "word/media/image30.png",
        "word/media/image34.png",
        "word/media/image39.png",
        "word/media/image40.png",
    ]:
        data = z.read(name)
        media_hashes[name] = hashlib.md5(data).hexdigest()
        print(name, len(data), media_hashes[name][:10])

cap_map = {
    "word/media/image30.png": caps / "fig28-pagos-create.png",
    "word/media/image34.png": caps / "fig32-recibo.png",
    "word/media/image39.png": caps / "fig37-ingresos.png",
    "word/media/image40.png": caps / "fig38-deudas.png",
}
for m, p in cap_map.items():
    h = hashlib.md5(p.read_bytes()).hexdigest()
    ok = h == hashlib.md5(ZipFile(src).read(m)).hexdigest()
    print(f"match {m.split('/')[-1]} == {p.name}: {ok}")

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


full = "\n".join(para_text(p) for p in paras)
checks = [
    "50",
    "100",
    "150",
    "12 meses",
    "día 1",
    "HU-09",
    "HU-10",
    "multa",
    "mora",
]
print("--- snippets mora/HU ---")
for pat in [
    r".{0,80}50.{0,40}100.{0,40}150.{0,80}",
    r".{0,60}12 meses.{0,80}",
    r".{0,40}día 1.{0,80}",
    r"HU-09.{0,200}",
    r"HU-10.{0,200}",
]:
    m = re.search(pat, full, re.I | re.S)
    if m:
        print("FOUND:", re.sub(r"\s+", " ", m.group(0))[:220])
    else:
        print("MISSING pattern", pat)

# biblio vs citations
biblio_i = None
for i, p in enumerate(paras):
    if style(p) == "Ttulo1" and para_text(p).upper().startswith("BIBLIO"):
        biblio_i = i
        break

body = full[: full.find(para_text(paras[biblio_i]))] if biblio_i else full
biblio_entries = []
for i in range(biblio_i + 1, len(paras)):
    t = para_text(paras[i])
    st = style(paras[i])
    if st == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if st == "Bibliografa" and t:
        biblio_entries.append(t)

print(f"biblio entries: {len(biblio_entries)}")

# extract in-text citations like (Autor, 2020) or Autor (2020)
cites = re.findall(r"\(([A-ZÁÉÍÓÚÑ][^()]{1,60}?),\s*(\d{4}[a-z]?)\)", body)
cites2 = re.findall(r"([A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚÑáéíóúñüÜ\-\s]{2,40}?)\s*\((\d{4}[a-z]?)\)", body)
print("paren cites sample:", cites[:15])
print("narrative cites sample:", cites2[:15])

# suspicious: biblio without surname in body
def fold(s):
    s = unicodedata.normalize("NFKD", s.lower())
    return "".join(c for c in s if not unicodedata.combining(c))

body_f = fold(body)
weak = []
for e in biblio_entries:
    head = e.split("(")[0].strip()
    sur = head.split(",")[0].split()[0].strip(".")
    if len(sur) >= 4 and fold(sur) not in body_f:
        # also try org first words
        if fold(head[:25]) not in body_f:
            weak.append(e[:90])

print(f"possibly uncited remaining: {len(weak)}")
for w in weak[:20]:
    print("  ?", w)

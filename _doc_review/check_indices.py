# -*- coding: utf-8 -*-
from pathlib import Path
from docx import Document
import re
import unicodedata

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
doc = Document(str(src))


def norm(s: str) -> str:
    s = s.lower().strip()
    s = unicodedata.normalize("NFKD", s)
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = s.replace("“", '"').replace("”", '"').replace("‘", "'").replace("’", "'")
    s = re.sub(r"\s+", " ", s)
    s = re.sub(r"[.\s]+$", "", s)
    s = re.sub(r"[^a-z0-9 ]", "", s)
    return s


def close(a: str, b: str) -> bool:
    na, nb = norm(a), norm(b)
    return na == nb or na in nb or nb in na


# --- FIGURAS ---
idx_figs = {}
for i in range(185, 223):
    t = doc.paragraphs[i].text.strip()
    m = re.match(r"Figura\s+(\d+)\s*:\s*(.*)$", t, re.I)
    if not m:
        continue
    num = int(m.group(1))
    rest = m.group(2).strip()
    if "\t" in rest:
        title, page = rest.rsplit("\t", 1)
        title, page = title.strip(), page.strip()
    else:
        title, page = rest, None
    idx_figs[num] = (title, page)

body_figs = {}
for i, p in enumerate(doc.paragraphs):
    if i < 250:
        continue
    t = (p.text or "").strip()
    if "\t" in t:
        continue
    st = p.style.name if p.style else ""
    m = re.match(r"Figura\s+(\d+)\s*:?\s*(.*)$", t, re.I)
    if m and (st == "Caption" or t.lower().startswith("figura ")):
        body_figs[int(m.group(1))] = m.group(2).strip()

print("=== FIGURAS: indice vs cuerpo ===")
print("indice", len(idx_figs), "cuerpo", len(body_figs))
for n in range(1, 39):
    it = idx_figs.get(n)
    bt = body_figs.get(n)
    if it and bt:
        if not close(it[0], bt):
            print(f"Fig {n}: DIFERENTE")
            print(f"  indice: {it[0]!r} p.{it[1]}")
            print(f"  cuerpo: {bt!r}")
    elif it and not bt:
        print(f"Fig {n}: SOLO indice: {it[0]!r}")
    elif bt and not it:
        print(f"Fig {n}: SOLO cuerpo: {bt!r}")
print("faltan indice", [n for n in range(1, 39) if n not in idx_figs])
print("faltan cuerpo", [n for n in range(1, 39) if n not in body_figs])

# --- TABLAS ---
idx_tabs = {}
for i in range(228, 248):
    t = doc.paragraphs[i].text.strip()
    m = re.match(r"Tabla\s+(\d+)\s*:\s*(.*)$", t, re.I)
    if not m:
        continue
    num = int(m.group(1))
    rest = m.group(2).strip()
    if "\t" in rest:
        title, page = rest.rsplit("\t", 1)
        title, page = title.strip(), page.strip()
    else:
        title, page = rest, None
    idx_tabs[num] = (title, page)

body_tabs = {}
for i, p in enumerate(doc.paragraphs):
    if i < 250:
        continue
    t = (p.text or "").strip()
    if "\t" in t:
        continue
    m = re.match(r"Tabla\s+(\d+)\s*:\s*(.*)$", t, re.I)
    if m:
        body_tabs[int(m.group(1))] = m.group(2).strip()

print("\n=== TABLAS: indice vs cuerpo ===")
print("indice", len(idx_tabs), "cuerpo", len(body_tabs))
for n in range(1, 21):
    it = idx_tabs.get(n)
    bt = body_tabs.get(n)
    if it and bt:
        if not close(it[0], bt):
            print(f"Tabla {n}: DIFERENTE")
            print(f"  indice: {it[0]!r} p.{it[1]}")
            print(f"  cuerpo: {bt!r}")
    elif it and not bt:
        print(f"Tabla {n}: SOLO indice: {it[0]!r}")
    elif bt and not it:
        print(f"Tabla {n}: SOLO cuerpo: {bt!r}")
print("faltan indice", [n for n in range(1, 21) if n not in idx_tabs])
print("faltan cuerpo", [n for n in range(1, 21) if n not in body_tabs])

# --- CONTENIDO ---
print("\n=== INDICE DE CONTENIDO (completo) ===")
for i in range(74, 171):
    t = doc.paragraphs[i].text.strip()
    if t:
        print(t)

print("\n=== BODY HEADINGS (titulos) ===")
for i, p in enumerate(doc.paragraphs):
    if i < 249:
        continue
    st = p.style.name if p.style else ""
    t = (p.text or "").strip()
    if st.startswith("Heading") and t:
        print(f"{st}: {t}")

# page anomalies
print("\n=== PAGINAS SOSPECHOSAS EN TOC ===")
for i in range(74, 171):
    t = doc.paragraphs[i].text.strip()
    if not t or "\t" not in t:
        continue
    title, page = t.rsplit("\t", 1)
    page = page.strip()
    if title.upper().startswith("BIBLIO") or title.upper().startswith("ANEXOS"):
        print(t)
    # Cap IV then biblio with low page
    if page.isdigit() and int(page) < 10 and (
        "BIBLIO" in title.upper() or "ANEXO" in title.upper() or "CAPÍTULO IV" in title.upper()
    ):
        print("baja:", t)

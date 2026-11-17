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


# estilos raros
styles = {}
for p in paras:
    s = st(p) or "(none)"
    styles[s] = styles.get(s, 0) + 1
print("=== estilos (top) ===")
for s, n in sorted(styles.items(), key=lambda x: -x[1])[:25]:
    print(n, s)

print("\n=== ui-markdown / raros ===")
for i, p in enumerate(paras):
    s = st(p)
    t = pt(p)
    if "markdown" in s.lower() or s in ("HTMLPreformatted", "IntenseQuote"):
        print(f"{i}|{s}|{t[:140]}")

print("\n=== TOTAL SPRINT / viñetas insertadas / textos sueltos ===")
for i, p in enumerate(paras):
    t = pt(p).strip()
    if t.startswith("TOTAL SPRINT") or t.startswith("• ") or t.startswith("Como referencia para el presente"):
        print(f"{i}|{st(p)}|{t[:160]}")

print("\n=== duplicados CAP / PROPUESTA ===")
for i, p in enumerate(paras):
    t = pt(p)
    if "CAPÍTULO III" in t.upper() or "PROPUESTA DE INNOV" in t.upper():
        print(f"{i}|{st(p)}|{t[:120]}")

print("\n=== índice Error / marcador ===")
for i, p in enumerate(paras):
    t = pt(p)
    if "Error!" in t or "Marcador" in t or "¡Error" in t:
        print(f"{i}|{t[:160]}")

print("\n=== títulos con número manual 3.x dentro del texto ===")
for i, p in enumerate(paras):
    t = pt(p).strip()
    s = st(p)
    if s.startswith("Ttulo") and re.match(r"^\d+\.\d+", t):
        print(f"{i}|{s}|{t[:100]}")

print("\n=== inconsistencias login/CI/mora en notas ===")
for i, p in enumerate(paras):
    t = pt(p)
    if re.search(r"\bCI\b", t) and ("sesión" in t.lower() or "login" in t.lower() or "inicio de sesión" in t.lower() or "contraseña" in t.lower()):
        print(f"{i}|{t[:160]}")
    if "p. 16" in t:
        print(f"p16 {i}|{t[:120]}")

print("\n=== justificación tiempo verbal presente sospechoso ===")
for i, p in enumerate(paras):
    t = pt(p)
    if i > 270 and i < 320:
        if re.search(r"\b(permite|mejora|son|contribuye)\b", t) and "Justific" in "".join(pt(paras[j]) for j in range(max(0,i-5), i+1)):
            print(f"{i}|{t[:140]}")

print("\n=== párrafos muy cortos raros entre sprints ===")
for i, p in enumerate(paras):
    t = pt(p).strip()
    if 1190 < i < 1420 and t in ("0,5", "14 días", "26 días", "10 días") or (t.isdigit() and len(t)<=2 and 1200<i<1460):
        pass

# buscar texto de diagnóstico que no quedó en pasado
print("\n=== Se encuentra organizado ===")
for i, p in enumerate(paras):
    t = pt(p)
    if "Se encuentra organizad" in t or "Se encontraba organizad" in t:
        print(f"{i}|{t[:140]}")

print("\n=== conclusiones refs tablas ===")
for i, p in enumerate(paras):
    t = pt(p)
    if i > 1540 and i < 1570 and t.strip():
        print(f"{i}|{st(p)}|{t[:180]}")

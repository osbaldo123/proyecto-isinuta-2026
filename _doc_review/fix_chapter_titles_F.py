# -*- coding: utf-8 -*-
"""
Alinea títulos de capítulos de -F.docx con PROYECTO MERCADO.docx (referencia).
Errores de título rompen el índice.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
ref = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_titulos.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"
log = []


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def set_runs_text(p, text):
    """Reemplaza texto conservando rPr del primer run."""
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        return
    first = runs[0]
    for r in runs[1:]:
        p.remove(r)
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)
    t = etree.SubElement(first, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text


def clone_pPr_from(template_p):
    pPr = template_p.find(W + "pPr")
    if pPr is None:
        return etree.Element(W + "pPr")
    return deepcopy(pPr)


def make_para_like(template_p, text):
    p = etree.Element(W + "p")
    pPr = clone_pPr_from(template_p)
    # portadilla no debe ser Ttulo1
    ps = pPr.find(W + "pStyle")
    if ps is not None:
        pPr.remove(ps)
    # quitar numPr si hubiera
    num = pPr.find(W + "numPr")
    if num is not None:
        pPr.remove(num)
    p.append(pPr)
    # copiar rPr tipográfico del template si existe
    r = etree.SubElement(p, W + "r")
    tr = template_p.find(W + "r")
    if tr is not None and tr.find(W + "rPr") is not None:
        r.append(deepcopy(tr.find(W + "rPr")))
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    return p


shutil.copy2(src, backup)

with ZipFile(src, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}
with ZipFile(ref, "r") as zin:
    ref_xml = zin.read("word/document.xml")

root = etree.fromstring(files["word/document.xml"])
ref_root = etree.fromstring(ref_xml)
paras = list(root.iter(W + "p"))
ref_paras = list(ref_root.iter(W + "p"))

# --- 1) Cap I: quitar "1      " del texto (numPr del estilo ya numera) ---
for p in paras:
    if st(p) == "Ttulo1" and "CAPÍTULO I" in pt(p).upper():
        t = pt(p)
        nt = re.sub(r"^\s*\d+\s+", "", t).strip()
        # normalizar espacios raros
        nt = re.sub(r"\s{2,}", " ", nt)
        if not nt.upper().startswith("CAPÍTULO I:"):
            nt = "CAPÍTULO I: PLANTEAMIENTO DEL PROBLEMA"
        if nt != t:
            set_runs_text(p, nt)
            log.append(f"Cap I texto: {t!r} -> {nt!r}")
        break

# --- 2) Cap II: asegurar Ttulo1 limpio ---
for p in paras:
    if st(p) == "Ttulo1" and "CAPÍTULO II" in pt(p).upper():
        t = pt(p)
        nt = re.sub(r"^\s*\d+\s+", "", t).strip()
        nt = re.sub(r"\s{2,}", " ", nt)
        # mantener tilde
        if "MARCO TE" in nt.upper() and "TEÓRICO" not in nt:
            nt = re.sub(r"MARCO TEORICO", "MARCO TEÓRICO", nt, flags=re.I)
        desired = "CAPÍTULO II: MARCO TEÓRICO"
        if "MARCO" in nt.upper():
            nt = desired
        if nt != t:
            set_runs_text(p, nt)
            log.append(f"Cap II texto -> {nt}")
        break

# --- 3) Cap III: restaurar portadilla como en REF + título Ttulo1 limpio ---
paras = list(root.iter(W + "p"))
cap3 = None
cap3_i = None
for i, p in enumerate(paras):
    if st(p) == "Ttulo1" and "CAPÍTULO III" in pt(p).upper():
        cap3 = p
        cap3_i = i
        break

if cap3 is None:
    raise SystemExit("No está el Ttulo1 del Capítulo III")

# Título limpio (con tildes, sin número manual)
set_runs_text(cap3, "CAPÍTULO III: PROPUESTA DE INNOVACIÓN O SOLUCIÓN DEL PROBLEMA")
log.append("Cap III Ttulo1 normalizado")

# ¿Ya hay portadilla justo antes?
prev = cap3.getprevious()
prev_text = pt(prev).replace(" ", "") if prev is not None and prev.tag == W + "p" else ""
has_portadilla = "CAPÍTULOIII" in prev_text.upper() or "CAPITULOIII" in prev_text.upper()

if not has_portadilla:
    # Usar como plantilla visual la portadilla del Cap I o Cap II del mismo doc
    template = None
    for p in paras:
        t = pt(p).strip()
        if t in ("CAPÍTULO I", "CAPÍTULO II", "  CAPÍTULO I", "CAPITULO IV") or re.fullmatch(r"\s*CAP[IÍ]TULO\s+[IVX]+\s*", t):
            template = p
            break
    if template is None:
        # tomar del REF la portadilla Cap III
        for p in ref_paras:
            if "CAPÍTULO III" in pt(p).upper() and "PROPUESTA" in pt(p).upper() and st(p) == "":
                template = p
                break
    if template is None:
        template = cap3

    # Portadilla en dos líneas como Cap I/II del propio F:
    # "CAPÍTULO III" + "PROPUESTA DE INNOVACIÓN O SOLUCIÓN DEL PROBLEMA"
    # REF usa una sola línea pegada; F Cap I/II usan dos. Preferimos dos (más claro).
    p1 = make_para_like(template, "CAPÍTULO III")
    p2 = make_para_like(template, "PROPUESTA DE INNOVACIÓN O SOLUCIÓN DEL PROBLEMA")
    cap3.addprevious(p1)
    p1.addnext(p2)
    log.append("Cap III portadilla restaurada (2 líneas)")

# --- 4) Cap IV: portadillas + título ---
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    if st(p) == "Ttulo1" and "CAPÍTULO IV" in pt(p).upper():
        set_runs_text(p, "CAPÍTULO IV: CONCLUSIONES Y RECOMENDACIONES")
        log.append("Cap IV Ttulo1 normalizado")
        # corregir portadillas previas sin tilde
        for j in range(max(0, i - 5), i):
            t = pt(paras[j]).strip()
            if t.upper() in ("CAPITULO IV", "CAPÍTULO IV"):
                set_runs_text(paras[j], "CAPÍTULO IV")
                log.append("portadilla Cap IV tilde")
            if t.upper() == "CONCLUSIONES Y RECOMENDACIONES":
                set_runs_text(paras[j], "CONCLUSIONES Y RECOMENDACIONES")
        break

# --- 5) Limpiar cualquier Ttulo1 con número manual al inicio ---
paras = list(root.iter(W + "p"))
for p in paras:
    if st(p) != "Ttulo1":
        continue
    t = pt(p)
    if re.match(r"^\s*\d+\s+CAP", t, re.I):
        nt = re.sub(r"^\s*\d+\s+", "", t).strip()
        set_runs_text(p, nt)
        log.append(f"quitado num manual en Ttulo1: {t[:50]} -> {nt[:50]}")

# --- 6) BIBLIOGRAFÍA / ANEXOS / RESUMEN / INTRODUCCIÓN: sin dígito basura ---
for p in paras:
    if st(p) != "Ttulo1":
        continue
    t = pt(p).strip()
    up = t.upper()
    if up.startswith("BIBLIO") or up.startswith("ANEXO") or up in ("RESUMEN", "INTRODUCCIÓN", "INTRODUCCION"):
        nt = re.sub(r"^\s*\d+\s+", "", t).strip()
        if nt != t:
            set_runs_text(p, nt)
            log.append(f"Ttulo1 limpio: {nt}")

# --- 7) Quitar párrafos sueltos tipo título mal pegados sin estilo que digan solo CAPÍTULO IIIPROPUESTA... duplicados ---
paras = list(root.iter(W + "p"))
# keep only one portadilla block before Cap III Ttulo1
seen_cap3_title = False
for i, p in enumerate(paras):
    if st(p) == "Ttulo1" and "CAPÍTULO III" in pt(p).upper():
        seen_cap3_title = True
        # remove OTHER unstyled duplicates of full title elsewhere near it
        break

# Verify final structure Cap I-IV
paras = list(root.iter(W + "p"))
print("=== verificación títulos Cap ===")
for i, p in enumerate(paras):
    t = pt(p).strip()
    s = st(p)
    if s == "Ttulo1" or (re.match(r"^CAP[IÍ]TULO\s+[IVX]+", t, re.I) and s == ""):
        if any(k in t.upper() for k in ["CAPÍTULO", "CAPITULO", "RESUMEN", "INTRODU", "BIBLIO", "ANEXO"]):
            print(f"{i}|{s}|{t[:100]}")

files["word/document.xml"] = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_titulos.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)
shutil.copy2(tmp, src)
print("\nsaved", src)
for x in log:
    print(" -", x)

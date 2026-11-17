# -*- coding: utf-8 -*-
"""Renumerar/limpiar anexos y corregir páginas de Bibliografía/Anexos en el TOC."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.prev_anexos.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

# Títulos formales y numeración correlativa
# (texto_actual_startswith_or_exact, nuevo_titulo)
ANEXO_REPLACEMENTS = [
    (
        "ANEXO 1: Entrevista al Personal Administrativo",
        "Anexo 1. Entrevista al personal administrativo de la Asociación de Agua Potable y Alcantarillado Isinuta",
    ),
    (
        "Anexo 1. Reporte detalle de pagos de afiliados que se realizaron",
        "Anexo 2. Reporte detalle de pagos de afiliados (recaudación de cobros)",
    ),
    (
        "Anexo 2. Reporte detalle de pagos",
        "Anexo 3. Reporte detalle de pagos de los afiliados",
    ),
    (
        "Anexo 3. Recibo egreso",
        "Anexo 4. Recibo de egreso por cancelación de gastos",
    ),
    (
        "Anexo 4. Recibo de pagos",
        "Anexo 5. Recibo de pagos",
    ),
    (
        "Anexo 5. Recibo de caja",
        "Anexo 6. Recibo de caja del pago realizado por el afiliado",
    ),
    (
        "Anexo 6. Estantería",
        "Anexo 7. Archivo físico de documentos de afiliados por lote y OTB",
    ),
    (
        "Anexo 7. Se muestra todas las faltas",
        "Anexo 8. Registro de faltas de los afiliados",
    ),
    (
        "Anexo 14. Software siscocap",
        "Anexo 9. Software SISCOCAP utilizado en la gestión administrativa",
    ),
]


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def set_simple_text(p, text: str, arial11=True):
    """Reemplaza texto del párrafo conservando pPr."""
    pPr = p.find(W + "pPr")
    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)
    r = etree.SubElement(p, W + "r")
    if arial11:
        rPr = etree.SubElement(r, W + "rPr")
        fonts = etree.SubElement(rPr, W + "rFonts")
        fonts.set(W + "ascii", "Arial")
        fonts.set(W + "hAnsi", "Arial")
        fonts.set(W + "cs", "Arial")
        etree.SubElement(rPr, W + "sz").set(W + "val", "22")
        etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
        # negrita suave para títulos de anexo
        etree.SubElement(rPr, W + "b")
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text


def update_toc_page(p, new_page: str):
    """Actualiza el último nodo de texto numérico de la entrada TOC (página)."""
    texts = p.findall(".//" + W + "t")
    if not texts:
        return False
    # el último t suele ser el número de página
    last = texts[-1]
    if re.fullmatch(r"\d+", (last.text or "").strip()) or re.fullmatch(r"[ivxlcdm]+", (last.text or "").strip(), re.I):
        last.text = str(new_page)
        return True
    # si el título y página están pegados
    for t in reversed(texts):
        val = t.text or ""
        m = re.search(r"(\d+)\s*$", val)
        if m and ("BIBLIO" in val.upper() or "ANEXO" in val.upper() or val.strip().isdigit()):
            t.text = re.sub(r"\d+\s*$", str(new_page), val)
            return True
    # crear/forzar último
    last.text = str(new_page)
    return True


shutil.copy2(src, backup)
print("backup ->", backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# --- 1) Anexos ---
anexos_start = None
for i, p in enumerate(paras):
    if para_text(p).upper() == "ANEXOS" and style(p) == "Ttulo1" and i > 1500:
        anexos_start = i
        break
print("anexos_start", anexos_start)

changed = []
for i in range(anexos_start, len(paras)):
    t = para_text(paras[i])
    if not t:
        continue
    for old_prefix, new_title in ANEXO_REPLACEMENTS:
        if t.startswith(old_prefix) or t == old_prefix:
            set_simple_text(paras[i], new_title)
            changed.append((i, new_title))
            break

print("anexos actualizados:", len(changed))
for i, title in changed:
    print(f"  [{i}] {title}")

# verificar que no queden Anexo 14 / doble Anexo 1 informal
paras = list(root.iter(W + "p"))
leftovers = []
for i in range(anexos_start, len(paras)):
    t = para_text(paras[i])
    if re.match(r"(?i)^anexo\s+\d+", t):
        leftovers.append(t)
print("titulos anexo finales:")
for t in leftovers:
    print(" ", t)

# --- 2) TOC páginas ---
# Cap. IV está en 76; bibliografía sigue (~2-3 págs) y luego anexos.
# Valores coherentes con el índice actual:
TOC_PAGES = {
    "BIBLIOGRAFÍA": "77",
    "ANEXOS": "80",
}

toc_fixed = []
for i, p in enumerate(paras):
    if i > 250:
        break
    texts = [t.text or "" for t in p.findall(".//" + W + "t")]
    joined = "".join(texts).strip()
    for label, page in TOC_PAGES.items():
        if joined.upper().startswith(label) or (texts and texts[0].upper().startswith(label)):
            old = joined
            ok = update_toc_page(p, page)
            toc_fixed.append((label, old, page, ok))

print("TOC fixes:", toc_fixed)

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_anexos_toc.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)

try:
    shutil.copy2(tmp, src)
    out = src
except PermissionError:
    out = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2 - anexos.docx")
    shutil.copy2(tmp, out)
    print("ORIGINAL BLOQUEADO")

print("saved", out)

# verify
with ZipFile(out) as z:
    root2 = etree.fromstring(z.read("word/document.xml"))
paras2 = list(root2.iter(W + "p"))
print("\\nVERIFY anexos:")
for p in paras2:
    t = para_text(p)
    if re.match(r"(?i)^anexo\s+\d+", t):
        print(" ", t)
print("VERIFY TOC:")
for i, p in enumerate(paras2):
    if i > 250:
        break
    t = para_text(p)
    if t.upper().startswith("BIBLIO") or t.upper().startswith("ANEXOS"):
        print(" ", repr(t))

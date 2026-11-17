# -*- coding: utf-8 -*-
"""Agrega Lozada (2014) e Inertia.js Development Team (2024) a bibliografía; corrige p. 34→47."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import copy

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_citas2.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"


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


def set_para_spacing_15(p, hanging=False):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")
    if hanging:
        ind = pPr.find(W + "ind")
        if ind is None:
            ind = etree.SubElement(pPr, W + "ind")
        ind.set(W + "left", "720")
        ind.set(W + "hanging", "720")


def replace_in_para(p, old, new):
    texts = list(p.findall(".//" + W + "t"))
    if not texts:
        return False
    full = "".join(t.text or "" for t in texts)
    if old not in full:
        return False
    texts[0].text = full.replace(old, new)
    for t in texts[1:]:
        t.text = ""
    for r in p.findall(W + "r"):
        set_run_arial11(r)
    set_para_spacing_15(p)
    return True


def make_biblio_para(template_p, text):
    p = copy.deepcopy(template_p)
    for child in list(p):
        if child.tag == W + "r":
            p.remove(child)
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    ps = pPr.find(W + "pStyle")
    if ps is None:
        ps = etree.SubElement(pPr, W + "pStyle")
    ps.set(W + "val", "Bibliografa")
    set_para_spacing_15(p, hanging=True)
    r = etree.SubElement(p, W + "r")
    set_run_arial11(r)
    t = etree.SubElement(r, W + "t")
    t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    t.text = text
    return p


shutil.copy2(src, backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# Corregir página errónea de Lozada (artículo CienciAmérica 3(1): 47-50)
fixed = 0
for p in paras:
    if replace_in_para(p, "Lozada (2014), “la investigación aplicada busca la generación de conocimiento con aplicación directa a los problemas de la sociedad o el sector productivo” (p. 34).",
                       "Lozada (2014), “la investigación aplicada busca la generación de conocimiento con aplicación directa a los problemas de la sociedad o el sector productivo” (p. 47)."):
        fixed += 1
    elif replace_in_para(p, "(p. 34).", "(p. 47).") and "Lozada" in para_text(p):
        fixed += 1

biblio_i = next(i for i, p in enumerate(paras) if style(p) == "Ttulo1" and para_text(p).upper().startswith("BIBLIO"))
biblio_paras = []
for i in range(biblio_i + 1, len(paras)):
    t = para_text(paras[i])
    st = style(paras[i])
    if st == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if st == "Bibliografa" and t:
        biblio_paras.append(paras[i])

template = biblio_paras[0]
existing = "\n".join(para_text(p) for p in biblio_paras)

to_add = []
if "Lozada" not in existing:
    to_add.append(
        "Lozada, J. (2014). Investigación aplicada: definición, propiedad intelectual e industria. CienciAmérica, 3(1), 47-50."
    )
if "Inertia.js Development Team" not in existing and "Inertia.js Team" not in existing:
    to_add.append(
        "Inertia.js Development Team. (2024). Documentación oficial de Inertia.js. Obtenido de https://inertiajs.com/"
    )

added = 0
for entry in to_add:
    key = entry.split(",")[0].lower()
    insert_before = None
    for bp in biblio_paras:
        head = para_text(bp).split(",")[0].lower()
        if head > key:
            insert_before = bp
            break
    new_p = make_biblio_para(template, entry)
    if insert_before is not None:
        insert_before.addprevious(new_p)
    else:
        biblio_paras[-1].addnext(new_p)
    # refresh list order roughly
    biblio_paras.append(new_p)
    added += 1
    print("ADD", entry)

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_citas2.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("fixed_page", fixed, "added", added)
print("saved", src)

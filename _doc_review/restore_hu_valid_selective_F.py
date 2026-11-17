# -*- coding: utf-8 -*-
"""
Restaura Validación de HU-01..HU-07 desde backup (no debían acortarse).
Deja condensadas solo HU-08, HU-09 y HU-10 (las que ocupaban demasiado).
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
bak = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_validaciones.docx")
out_backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_valid_restore.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

RESTORE = {"HU-01", "HU-02", "HU-03", "HU-04", "HU-05", "HU-06", "HU-07"}
# HU-08, HU-09, HU-10 se mantienen condensadas


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def set_arial11(r):
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
    for tag, val in ((W + "sz", "22"), (W + "szCs", "22")):
        el = rPr.find(tag)
        if el is None:
            el = etree.SubElement(rPr, tag)
        el.set(W + "val", val)


def set_spacing15(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")


def set_para_text(p, text):
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        set_arial11(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        set_spacing15(p)
        return
    first = runs[0]
    if first.find(W + "rPr") is None:
        first.insert(0, etree.Element(W + "rPr"))
    set_arial11(first)
    for r in runs[1:]:
        p.remove(r)
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)
    t = etree.SubElement(first, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    set_spacing15(p)


def extract_validations(paras):
    start = next(i for i, p in enumerate(paras) if pt(p).strip().startswith("Historias de usuario"))
    end = next(i for i, p in enumerate(paras) if i > start and pt(p).strip().startswith("Product Backlog"))
    cur = None
    out = {}
    for i in range(start, end):
        t = pt(paras[i]).strip()
        m = re.search(r"\b(HU-\d{2})\b", t)
        if m:
            cur = m.group(1)
        if t.startswith("Validación") and cur:
            bullets = []
            j = i + 1
            while j < end:
                tj = pt(paras[j]).strip()
                if tj.startswith("Prioridad"):
                    break
                if tj:
                    bullets.append(tj)
                j += 1
            out[cur] = {"valid_i": i, "prio_i": j, "bullets": bullets}
    return start, end, out


shutil.copy2(src, out_backup)

bak_paras = list(etree.fromstring(ZipFile(bak).read("word/document.xml")).iter(W + "p"))
_, _, bak_vals = extract_validations(bak_paras)

with ZipFile(src, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}

root = etree.fromstring(files["word/document.xml"])
log = []

for hu in sorted(RESTORE):
    paras = list(root.iter(W + "p"))
    _, _, cur_vals = extract_validations(paras)
    if hu not in bak_vals or hu not in cur_vals:
        log.append(f"skip {hu}")
        continue
    info = cur_vals[hu]
    want = bak_vals[hu]["bullets"]
    valid_i = info["valid_i"]
    # localizar Prioridad otra vez
    prio_i = info["prio_i"]
    # párrafos actuales entre valid+1 y prio
    current_bullets = []
    for j in range(valid_i + 1, prio_i):
        if pt(paras[j]).strip():
            current_bullets.append(paras[j])
        elif not pt(paras[j]).strip():
            # empty - skip
            pass

    # template pPr from first current or first bak style via current
    template = current_bullets[0] if current_bullets else paras[valid_i]

    # fill / create
    for k, text in enumerate(want):
        if k < len(current_bullets):
            set_para_text(current_bullets[k], text)
        else:
            np = etree.Element(W + "p")
            pPr = template.find(W + "pPr")
            if pPr is not None:
                np.append(deepcopy(pPr))
            set_para_text(np, text)
            paras[prio_i].addprevious(np)
            current_bullets.append(np)

    # remove extras
    for extra in current_bullets[len(want) :]:
        parent = extra.getparent()
        if parent is not None:
            parent.remove(extra)

    log.append(f"restaurado {hu}: {len(want)} viñetas")

# Verificar que HU-08..10 siguen cortas
paras = list(root.iter(W + "p"))
_, _, final_vals = extract_validations(paras)
for hu, info in sorted(final_vals.items()):
    log.append(f"final {hu}: {len(info['bullets'])} viñetas")

files["word/document.xml"] = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_valid_restore.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)
shutil.copy2(tmp, src)
print("saved", src)
for x in log:
    print(" -", x)

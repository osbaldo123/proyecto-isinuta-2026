# -*- coding: utf-8 -*-
"""
Validación HU: ~5-6 viñetas con el criterio completo (sin fusionar con ';').
- HU cortas: se dejan las originales del backup.
- HU largas: se eligen hasta 6 viñetas originales íntegras (lo importante, sin resumir).
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re
import time

src_candidates = [
    Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx"),
    Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.valid_fix.docx"),
]
bak = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_validaciones.docx")
out_fix = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.valid_fix.docx")
out_main = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

# Hasta 6 viñetas originales (índice 0 = título Validación:). Lista = índices de bullets a conservar (1-based sobre bullets sin título).
# None = conservar todas.
KEEP = {
    "HU-01": None,  # 5 originales
    "HU-02": [1, 5, 7, 10, 11, 13],  # login, estado, rol, dup correo/CI/tel, password, verificar correo
    "HU-03": [1, 2, 7, 8, 9, 10],  # lista, CI, lotes, nº lote, pago/saldo, estados/corte
    "HU-04": None,  # 8 → cerca; se deja
    "HU-05": [1, 2, 3, 5, 7, 10],  # lista, alta, dup, actualizar, #afiliados, eliminar
    "HU-06": None,
    "HU-07": None,
    "HU-08": [3, 7, 8, 9, 12, 13],  # cobro multi, tarifa, no doble, recibo/concepto, E/C, transferencia
    "HU-09": [1, 2, 4, 6, 7, 9],  # lista, manual, inasistencia/lote, mora 50→100→150, permanece, config/corte
    "HU-10": [1, 2, 3, 4, 5, 6],  # ingresos, agrupado, filtros, excel/pdf, deudas, multas en ingresos
}


def pt(el):
    return "".join(t.text or "" for t in el.findall(".//" + W + "t"))


def hu_tables(root):
    out = {}
    for tbl in root.iter(W + "tbl"):
        rows = tbl.findall("./" + W + "tr")
        if not rows:
            continue
        first = " | ".join(pt(c).strip() for c in rows[0].findall("./" + W + "tc"))
        if "Nombre de historia" not in first and "Nombre de Historia" not in first:
            continue
        m = re.search(r"\b(HU-\d{2})\b", first) or re.search(r"\b(HU-\d{2})\b", pt(tbl))
        if m and m.group(1) not in out:
            out[m.group(1)] = tbl
    return out


def validacion_cell(tbl):
    for row in tbl.findall("./" + W + "tr"):
        for cell in row.findall("./" + W + "tc"):
            if pt(cell).strip().startswith("Validaci"):
                return cell
    return None


def set_arial11(r):
    rPr = r.find(W + "rPr")
    if rPr is None:
        rPr = etree.Element(W + "rPr")
        r.insert(0, rPr)
    fonts = rPr.find(W + "rFonts")
    if fonts is None:
        fonts = etree.SubElement(rPr, W + "rFonts")
    for a in ("ascii", "hAnsi", "cs"):
        fonts.set(W + a, "Arial")
    for tag in (W + "sz", W + "szCs"):
        el = rPr.find(tag)
        if el is None:
            el = etree.SubElement(rPr, tag)
        el.set(W + "val", "22")
    # sin negrita
    for b in list(rPr.findall(W + "b")) + list(rPr.findall(W + "bCs")):
        rPr.remove(b)


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


def write_paras_in_cell(cell, lines, template_p):
    tcPr = cell.find("./" + W + "tcPr")
    for child in list(cell):
        if child.tag != W + "tcPr":
            cell.remove(child)
    if tcPr is None:
        # keep none
        pass
    for text in lines:
        np = etree.Element(W + "p")
        pPr = template_p.find(W + "pPr")
        if pPr is not None:
            np.append(deepcopy(pPr))
        set_spacing15(np)
        # bullet-like: reuse numPr if template has it and text != Validación:
        r = etree.SubElement(np, W + "r")
        set_arial11(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        cell.append(np)


bak_root = etree.fromstring(ZipFile(bak).read("word/document.xml"))
bak_map = hu_tables(bak_root)

# base: prefer current main if readable structure; else valid_fix
base = None
for p in src_candidates:
    if p.exists():
        base = p
        break
assert base is not None

with ZipFile(base, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}

root = etree.fromstring(files["word/document.xml"])
cur_map = hu_tables(root)
log = []

for code, tbl in sorted(cur_map.items()):
    bak_tbl = bak_map.get(code)
    if bak_tbl is None:
        log.append(f"skip {code}: no bak")
        continue
    bak_cell = validacion_cell(bak_tbl)
    cur_cell = validacion_cell(tbl)
    if bak_cell is None or cur_cell is None:
        log.append(f"skip {code}: no cell")
        continue

    bak_paras = [pt(p).strip() for p in bak_cell.findall("./" + W + "p") if pt(p).strip()]
    # Separar título y bullets
    title = bak_paras[0] if bak_paras and bak_paras[0].startswith("Validaci") else "Validación:"
    bullets = bak_paras[1:] if bak_paras and bak_paras[0].startswith("Validaci") else bak_paras

    keep_idx = KEEP.get(code)
    if keep_idx is None:
        chosen = bullets
    else:
        chosen = []
        for i in keep_idx:
            if 1 <= i <= len(bullets):
                chosen.append(bullets[i - 1])
        # máximo 6
        chosen = chosen[:6]

    lines = [title] + chosen

    # template: primer párrafo con contenido del backup (mejor estilo de viñeta)
    bak_ps = [p for p in bak_cell.findall("./" + W + "p") if pt(p).strip()]
    template = bak_ps[1] if len(bak_ps) > 1 else bak_ps[0]

    # Si el backup tenía numPr en bullets, copiar párrafos completos del bak filtrados
    if keep_idx is None:
        # restaurar celda completa del backup (tcPr del actual)
        tcPr = cur_cell.find("./" + W + "tcPr")
        for child in list(cur_cell):
            cur_cell.remove(child)
        if tcPr is not None:
            cur_cell.append(tcPr)
        for child in bak_cell:
            if child.tag == W + "tcPr":
                continue
            cur_cell.append(deepcopy(child))
        log.append(f"{code}: original completo ({len(bullets)} pts)")
    else:
        # reconstruir con párrafos plantilla
        selected_ps = []
        # título
        selected_ps.append(deepcopy(bak_ps[0]))
        for i in keep_idx[:6]:
            if 1 <= i <= len(bak_ps) - 1:
                selected_ps.append(deepcopy(bak_ps[i]))
        tcPr = cur_cell.find("./" + W + "tcPr")
        for child in list(cur_cell):
            cur_cell.remove(child)
        if tcPr is not None:
            cur_cell.append(tcPr)
        for p in selected_ps:
            cur_cell.append(p)
        log.append(f"{code}: {len(selected_ps)-1} pts (criterios íntegros)")

files["word/document.xml"] = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_valid_6pts.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)

shutil.copy2(tmp, out_fix)
print("wrote", out_fix)

ok = False
for _ in range(6):
    try:
        shutil.copy2(tmp, out_main)
        ok = True
        break
    except PermissionError:
        time.sleep(1.2)
print("overwrite -F.docx:", "OK" if ok else "LOCKED (abrir -F.valid_fix.docx)")

# verify
root2 = etree.fromstring(ZipFile(out_fix).read("word/document.xml"))
for code, tbl in sorted(hu_tables(root2).items()):
    cell = validacion_cell(tbl)
    paras = [pt(p).strip() for p in cell.findall("./" + W + "p") if pt(p).strip()]
    print(code, "n=", len(paras) - 1, "|", " || ".join(paras[1:3])[:120])
for x in log:
    print(" -", x)

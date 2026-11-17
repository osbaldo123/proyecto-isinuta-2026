# -*- coding: utf-8 -*-
"""
Restaura la celda Validación de HU-01..HU-07 desde el backup (texto original).
Deja condensadas solo HU-08, HU-09 y HU-10 (las que exageraban en espacio).
Opera a nivel de celda de tabla para no romper Prioridad/Esfuerzo.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re
import time

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
bak = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_validaciones.docx")
alt_out = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.valid_fix.docx")
safety = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_before_valid_cell_fix.docx")

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
RESTORE = {"HU-01", "HU-02", "HU-03", "HU-04", "HU-05", "HU-06", "HU-07"}


def pt(el):
    return "".join(t.text or "" for t in el.findall(".//" + W + "t"))


def hu_tables(root):
    """Devuelve dict HU-XX -> tabla (primera coincidencia por código en fila Nombre)."""
    out = {}
    for tbl in root.iter(W + "tbl"):
        rows = tbl.findall("./" + W + "tr")
        if not rows:
            continue
        first_cells = [pt(c).strip() for c in rows[0].findall("./" + W + "tc")]
        blob = " | ".join(first_cells)
        if "Nombre de historia" not in blob and "Nombre de Historia" not in blob:
            # algunas HU usan esa etiqueta
            continue
        m = re.search(r"\b(HU-\d{2})\b", blob)
        if not m:
            # buscar en toda la tabla
            m = re.search(r"\b(HU-\d{2})\b", pt(tbl))
        if not m:
            continue
        code = m.group(1)
        if code not in out:
            out[code] = tbl
    return out


def find_validacion_cell(tbl):
    for row in tbl.findall("./" + W + "tr"):
        for cell in row.findall("./" + W + "tc"):
            text = pt(cell).strip()
            if text.startswith("Validación") or text.startswith("Validacion"):
                return cell
    return None


def find_prioridad_cell(tbl):
    for row in tbl.findall("./" + W + "tr"):
        for cell in row.findall("./" + W + "tc"):
            text = pt(cell).strip()
            if text.startswith("Prioridad") or "Estimación de esfuerzo" in text or "Estimacion de esfuerzo" in text:
                return cell
    return None


bak_root = etree.fromstring(ZipFile(bak).read("word/document.xml"))
bak_map = hu_tables(bak_root)

with ZipFile(src, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}

cur_root = etree.fromstring(files["word/document.xml"])
cur_map = hu_tables(cur_root)

log = []
for code in sorted(RESTORE):
    if code not in bak_map or code not in cur_map:
        log.append(f"SKIP {code}: bak={code in bak_map} cur={code in cur_map}")
        continue
    bak_cell = find_validacion_cell(bak_map[code])
    cur_cell = find_validacion_cell(cur_map[code])
    if bak_cell is None or cur_cell is None:
        log.append(f"SKIP {code}: cell missing bak={bak_cell is not None} cur={cur_cell is not None}")
        continue

    # Preservar tcPr del destino; reemplazar resto del contenido de la celda
    cur_tcPr = cur_cell.find("./" + W + "tcPr")
    new_cell_children = []
    if cur_tcPr is not None:
        new_cell_children.append(deepcopy(cur_tcPr))
    for child in bak_cell:
        if child.tag == W + "tcPr":
            continue
        new_cell_children.append(deepcopy(child))

    # vaciar celda actual y poner hijos nuevos
    for child in list(cur_cell):
        cur_cell.remove(child)
    for child in new_cell_children:
        cur_cell.append(child)

    # verificar que Prioridad sigue intacta
    prio = find_prioridad_cell(cur_map[code])
    prio_ok = prio is not None and pt(prio).strip().startswith("Prioridad")
    log.append(
        f"OK {code}: val {len(pt(bak_cell))} chars; prioridad_ok={prio_ok}"
    )

# Verificación final 08-10 siguen cortas vs backup
for code in ("HU-08", "HU-09", "HU-10"):
    cur_cell = find_validacion_cell(cur_map[code])
    bak_cell = find_validacion_cell(bak_map[code])
    if cur_cell is not None and bak_cell is not None:
        log.append(
            f"KEEP-SHORT {code}: cur={len(pt(cur_cell))} bak={len(pt(bak_cell))}"
        )

files["word/document.xml"] = etree.tostring(
    cur_root, xml_declaration=True, encoding="UTF-8", standalone=True
)

tmp = Path(tempfile.gettempdir()) / "mercado_F_valid_cells.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)

shutil.copy2(tmp, alt_out)
print("wrote", alt_out)

# intentar sobrescribir -F.docx (puede estar abierto en Word)
try:
    shutil.copy2(src, safety)
except Exception as e:
    print("safety backup skip:", e)

ok = False
for attempt in range(8):
    try:
        shutil.copy2(tmp, src)
        ok = True
        break
    except PermissionError:
        time.sleep(1.5)
print("overwrite -F.docx:", "OK" if ok else "LOCKED -> usa -F.valid_fix.docx")
for x in log:
    print(" -", x)

# -*- coding: utf-8 -*-
"""Aplica correcciones marcadas en amarillo en el docx (sin tocar el resto)."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.prev_yellow.docx")

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
NSMAP = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}

TEXTS = {
    # Sprint 2 backlog intro (reemplaza nota amarilla)
    1056: (
        "La siguiente tabla detalla la lista de tareas específicas asociadas a las historias "
        "de usuario del Sprint 2, como la gestión de OTB y de servicios por categoría. Para "
        "cada historia, se identifican actividades clave como la implementación de "
        "funcionalidades CRUD, control de duplicidad, actualización de tarifas y validación de datos."
    ),
    # Sprint 3 diagrama de clases (reemplaza "Texto?")
    1305: (
        "La figura muestra la estructura de clases del Sprint 3, con las relaciones entre "
        "Trabajo, Asistencia, Multa, Pago y Pago Detalle."
    ),
    # Intro de conclusiones (reemplaza nota amarilla con ejemplo SIRAF)
    1537: (
        "Se desarrolló el sistema web para la gestión administrativa de la Asociación de Agua "
        "Potable y Alcantarillado Isinuta, permitiendo automatizar el control de afiliados, "
        "pagos, multas y reportes durante la gestión 2026. La solución mejora la organización "
        "de los procesos administrativos mediante información confiable y disponible para la "
        "toma de decisiones de la mesa directiva."
    ),
}

# Texto a insertar antes de Tabla 17 (Sprint 3 backlog), índice del párrafo Tabla 17
SPRINT3_BACKLOG_INTRO = (
    "La siguiente tabla detalla la lista de tareas específicas asociadas a las historias "
    "de usuario del Sprint 3, como trabajos de los afiliados, pagos (incluido el estado de "
    "cuenta y la transferencia del lote) y multas. Para cada historia, se identifican "
    "actividades clave para su desarrollo e implementación dentro del sistema web."
)
INSERT_BEFORE = 1190  # Tabla 17: Sprint Backlog 3


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def set_paragraph_text(p, text: str) -> None:
    """Reemplaza el texto del párrafo preservando el primer rPr; quita highlight."""
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        rPr = etree.SubElement(r, W + "rPr")
        t = etree.SubElement(r, W + "t")
        t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
        t.text = text
        return

    first = runs[0]
    rPr = first.find(W + "rPr")
    if rPr is None:
        rPr = etree.Element(W + "rPr")
        first.insert(0, rPr)
    # quitar highlight amarillo
    for hl in list(rPr.findall(W + "highlight")):
        rPr.remove(hl)

    # borrar runs extras
    for r in runs[1:]:
        p.remove(r)

    # limpiar hijos de texto del primer run excepto rPr
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)

    t = etree.SubElement(first, W + "t")
    t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    t.text = text


def make_normal_para_like(template_p, text: str):
    """Clona estructura básica de un párrafo normal y pone texto."""
    # Usar un párrafo simple: pPr del template si existe + un run
    new_p = etree.Element(W + "p")
    pPr = template_p.find(W + "pPr")
    if pPr is not None:
        new_p.append(deepcopy(pPr))
        # evitar heredar estilo de título
        ps = new_p.find(W + "pPr/" + W + "pStyle")
        if ps is not None:
            new_p.find(W + "pPr").remove(ps)

    # copiar rPr tipográfico del primer run del template si hay
    r = etree.SubElement(new_p, W + "r")
    tmpl_runs = template_p.findall(W + "r")
    if tmpl_runs:
        tmpl_rPr = tmpl_runs[0].find(W + "rPr")
        if tmpl_rPr is not None:
            new_rPr = deepcopy(tmpl_rPr)
            for hl in list(new_rPr.findall(W + "highlight")):
                new_rPr.remove(hl)
            r.insert(0, new_rPr)
    # si no hay rPr, al menos sin highlight
    t = etree.SubElement(r, W + "t")
    t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    t.text = text
    return new_p


if not backup.exists():
    shutil.copy2(src, backup)
    print("backup ->", backup)
else:
    # siempre respaldo fresco de esta pasada
    shutil.copy2(src, backup)
    print("backup refreshed ->", backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))
print("paras", len(paras))

# Verificaciones previas
for idx, expected_snip in [
    (1056, "Falta texto"),
    (1305, "Texto?"),
    (1537, "Falta texto"),
    (1190, "Tabla 17"),
]:
    t = para_text(paras[idx])
    print(f"BEFORE [{idx}] {t[:80]!r}")
    if expected_snip not in t:
        raise SystemExit(f"Unexpected content at {idx}: {t[:120]!r}")

# 1) Reemplazos
for idx, text in TEXTS.items():
    set_paragraph_text(paras[idx], text)
    print(f"SET [{idx}] -> {text[:70]}...")

# 2) Insertar intro Sprint 3 backlog antes de Tabla 17
# Re-obtener parent: el párrafo 1190
target = paras[INSERT_BEFORE]
parent = target.getparent()
# Usar como plantilla el párrafo 1401 (intro Sprint 4) o 835
template = paras[1401]
new_p = make_normal_para_like(template, SPRINT3_BACKLOG_INTRO)
parent.insert(list(parent).index(target), new_p)
print(f"INSERTED before Tabla 17: {SPRINT3_BACKLOG_INTRO[:70]}...")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)

tmp = Path(tempfile.gettempdir()) / "mercado_yellow_fixed.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)

shutil.copy2(tmp, src)
print("saved", src)

# verify
with ZipFile(src) as z:
    root2 = etree.fromstring(z.read("word/document.xml"))
paras2 = list(root2.iter(W + "p"))
# after insert, indices after 1190 shift by +1
checks = {
    1056: "Sprint 2",
    1306: "Trabajo, Asistencia",  # 1305 became 1306 after insert before 1190
    1538: "Isinuta",  # 1537 -> 1538
}
# find by content
found = {"backlog2": False, "backlog3": False, "clases3": False, "conclu": False, "faltas": []}
for i, p in enumerate(paras2):
    t = para_text(p)
    if "Falta texto" in t or t == "Texto?":
        found["faltas"].append((i, t[:80]))
    if t.startswith("La siguiente tabla detalla la lista de tareas específicas asociadas a las historias de usuario del Sprint 2"):
        found["backlog2"] = True
    if t.startswith("La siguiente tabla detalla la lista de tareas específicas asociadas a las historias de usuario del Sprint 3"):
        found["backlog3"] = True
    if "estructura de clases del Sprint 3" in t:
        found["clases3"] = True
    if t.startswith("Se desarrolló el sistema web para la gestión administrativa de la Asociación"):
        found["conclu"] = True

print("VERIFY", found)

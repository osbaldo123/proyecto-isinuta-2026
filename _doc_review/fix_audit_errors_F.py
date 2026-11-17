# -*- coding: utf-8 -*-
"""Corrige errores estructurales/residuales detectados en -F.docx."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_audit_fix.docx")
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


def set_spacing15(p, justify=True):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")
    if justify:
        jc = pPr.find(W + "jc")
        if jc is None:
            jc = etree.SubElement(pPr, W + "jc")
        jc.set(W + "val", "both")


def set_para_text(p, text, justify=True):
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        set_arial11(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        set_spacing15(p, justify)
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
    set_spacing15(p, justify)


def make_para(text, justify=True):
    p = etree.Element(W + "p")
    set_spacing15(p, justify)
    r = etree.SubElement(p, W + "r")
    set_arial11(r)
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    return p


shutil.copy2(src, backup)
with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# 1) Quitar encabezado duplicado pegado "CAPÍTULO IIIPROPUESTA..."
for p in list(paras):
    t = pt(p).strip()
    if t.replace(" ", "") == "CAPÍTULOIIIPROPUESTADEINNOVACIÓNOSOLUCIÓNDELPROBLEMA" or (
        t.startswith("CAPÍTULO III") and "PROPUESTA" in t and ":" not in t and st(p) == ""
    ):
        # el duplicado exacto sin dos puntos
        if "IIIPROPUESTA" in t.replace(" ", "") or t == "CAPÍTULO IIIPROPUESTA DE INNOVACIÓN O SOLUCIÓN DEL PROBLEMA":
            log.append("DEL capIII duplicado: " + t[:80])
            p.getparent().remove(p)

paras = list(root.iter(W + "p"))

# 2) Título Cap III con tildes
for p in paras:
    t = pt(p)
    if st(p) == "Ttulo1" and "CAPÍTULO III" in t.upper() and "PROPUESTA" in t.upper():
        set_para_text(
            p,
            "CAPÍTULO III: PROPUESTA DE INNOVACIÓN O SOLUCIÓN DEL PROBLEMA",
            justify=False,
        )
        log.append("capIII tildes")
        break

# 3) Cap II MARCO TEÓRICO
for p in paras:
    t = pt(p)
    if st(p) == "Ttulo1" and "MARCO TEORICO" in t.upper() and "TEÓRICO" not in t:
        set_para_text(p, t.replace("MARCO TEORICO", "MARCO TEÓRICO").replace("Marco teorico", "Marco teórico"), justify=False)
        log.append("marco teorico tilde")
        break

# 4) Diagnóstico pasado
for p in paras:
    t = pt(p)
    if t.startswith("Se encuentra organizado mediante una mesa directiva"):
        set_para_text(p, t.replace("Se encuentra organizado", "Se encontraba organizada", 1))
        log.append("diagnostico pasado")
        break

# 5) Justificación social: presente -> pasado
repls = [
    (
        "La implementación del sistema web permite reducir los errores y demoras ocasionados por el manejo manual de la información, facilitando una",
        "La implementación del sistema web permitió reducir los errores y demoras ocasionados por el manejo manual de la información, y facilitó una",
    ),
    (
        "Los principales beneficiarios son los afiliados, quienes pueden conocer con mayor claridad sus pagos, sus multas y sus obligaciones pendient",
        "Los principales beneficiarios fueron los afiliados, quienes pudieron conocer con mayor claridad sus pagos, sus multas y sus obligaciones pendient",
    ),
]
for p in paras:
    t = pt(p)
    for old, new in repls:
        if old in t:
            set_para_text(p, t.replace(old, new))
            log.append("justificacion pasado")
            break
    # tercera: automatización... permite/mejora si aparece
    if "La automatización del cálculo de multas" in t and " permite " in t:
        set_para_text(p, t.replace(" permite ", " permitió ").replace(" mejora ", " mejoró "))
        log.append("justificacion auto pasado")

# 6) Definición de hecho Sprint 3 en viñetas (restaurar)
for p in paras:
    t = pt(p)
    if t.startswith("El Sprint 3 se consideró terminado cuando el sistema permitió:"):
        set_para_text(p, "El Sprint 3 se consideró terminado cuando el sistema permitió:")
        bullets = [
            "• Registrar el trabajo de la OTB indicada por la secretaria (tipo, fecha, descripción y monto de multa).",
            "• Mostrar la lista de afiliados de esa OTB y marcar asistencia; generar multa por cada lote ausente.",
            "• Cobrar esa multa en un recibo de caja del lote.",
            "• Gestionar pagos de servicio con recibo de ingreso (agua y alcantarillado) y afiliación con recibo de caja.",
            "• Consultar el estado de cuenta del lote y transferir el lote sin deudas a un nuevo titular.",
            "• Registrar y consultar multas por mora e inasistencia.",
        ]
        anchor = p
        for b in bullets:
            np = make_para(b)
            anchor.addnext(np)
            anchor = np
        log.append("sprint3 definicion viñetas")
        break

paras = list(root.iter(W + "p"))

# 7) TOTAL SPRINT 3 faltante (antes de la nota del backlog 3)
if "TOTAL SPRINT 3" not in "\n".join(pt(p) for p in paras):
    for p in paras:
        t = pt(p)
        if t.strip().startswith("Nota: La tabla presenta las tareas necesarias para el Sprint 3"):
            tot = make_para("TOTAL SPRINT 3: 26 días", justify=False)
            p.addprevious(tot)
            log.append("TOTAL SPRINT 3")
            break

# 8) Unificar formato TOTAL SPRINT 4
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p).strip()
    if t.startswith("TOTAL SPRINT 4"):
        set_para_text(p, "TOTAL SPRINT 4: 10 días", justify=False)
        log.append("TOTAL SPRINT 4 formato")
        break

# 9) Quitar párrafos ui-markdown vacíos
paras = list(root.iter(W + "p"))
for p in list(paras):
    if st(p) == "ui-markdownparagraph" and not pt(p).strip():
        p.getparent().remove(p)
        log.append("DEL markdown vacio")

# 10) Quitar estilo ui-markdown de notas/textos (dejar contenido, estilo Normal limpio)
paras = list(root.iter(W + "p"))
for p in paras:
    if st(p) == "ui-markdownparagraph":
        pPr = p.find(W + "pPr")
        if pPr is not None:
            ps = pPr.find(W + "pStyle")
            if ps is not None:
                pPr.remove(ps)
        set_spacing15(p)
        for r in p.findall(W + "r"):
            set_arial11(r)
        log.append("style markdown->normal: " + pt(p)[:40])

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_audit_fix.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("saved", src)
for x in log:
    print(" -", x)
print("total", len(log))

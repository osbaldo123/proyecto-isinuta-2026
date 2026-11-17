# -*- coding: utf-8 -*-
"""
Quita 3.7 Pruebas / 3.8 Análisis insertados (no están en la estructura tipo guía/Jonas).
Las pruebas quedan en cada Sprint Review (tablas 14, 16, 18, 20).
Ajusta intro y conclusiones que apuntaban a esos apartados.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_sin_37_38.docx")
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
    jc = pPr.find(W + "jc")
    if jc is None:
        jc = etree.SubElement(pPr, W + "jc")
    jc.set(W + "val", "both")


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


def is_37_38_block(t: str) -> bool:
    t = t.strip()
    return bool(
        t.startswith("3.7 Pruebas")
        or t.startswith("3.8 Análisis")
        or t.startswith("3.8 Analisis")
        or t.startswith("Se ejecutaron 22 pruebas")
        or t.startswith("Tabla 21:")
        or t.startswith("Sprint 1 (roles, usuarios")
        or t.startswith("La comparación entre el proceso anterior (cuadernos, Excel y SISCOCAP)")
        or t.startswith("Estos resultados se relacionan con los objetivos específicos")
    )


shutil.copy2(src, backup)
with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

removed = 0
for p in list(paras):
    if is_37_38_block(pt(p)):
        log.append("DEL " + pt(p)[:70])
        p.getparent().remove(p)
        removed += 1

paras = list(root.iter(W + "p"))

# Intro: no hablar de apartados 3.7/3.8 separados; las pruebas van en cada Sprint Review
for p in paras:
    t = pt(p)
    if "Capítulo III se describe la propuesta" in t and "pruebas realizadas" in t.lower():
        set_para_text(
            p,
            "El documento se organizó en cuatro capítulos. En el Capítulo I se presentan el diagnóstico, "
            "la justificación, el planteamiento del problema, los objetivos y el enfoque metodológico. "
            "En el Capítulo II se desarrolla el marco teórico conceptual que sustenta el proyecto. "
            "En el Capítulo III se describe la propuesta de solución, desarrollada en cuatro Sprint, "
            "con la definición de hecho, el backlog, la implementación y la revisión de cada Sprint "
            "(incluidas las pruebas de funcionamiento). Finalmente, en el Capítulo IV se exponen las "
            "conclusiones y recomendaciones.",
        )
        log.append("intro sin 3.7/3.8")
        break

# Conclusiones: quitar referencias a apartado 3.7 / 3.8
for p in paras:
    t = pt(p)
    if "apartado 3.7" in t or "apartado 3.8" in t or "apartados 3.7" in t:
        nt = t
        nt = re.sub(r"\s*\(apartado 3\.7\)", "", nt)
        nt = re.sub(r"\s*\(apartado 3\.8\)", "", nt)
        nt = nt.replace("apartado 3.7", "las tablas de pruebas de cada Sprint Review")
        nt = nt.replace("apartado 3.8", "los resultados de cada Sprint Review")
        # limpieza de guiones largos tipo —
        nt = nt.replace("—", ", ")
        if nt != t:
            set_para_text(p, nt)
            log.append("conclusion sin ref 3.7/3.8")

# Conclusión general: reescribir si aún menciona 3.7
for p in paras:
    t = pt(p)
    if t.startswith("Se desarrolló el sistema web para la gestión administrativa") and (
        "3.7" in t or "22 pruebas" in t
    ):
        set_para_text(
            p,
            "Se desarrolló el sistema web para la gestión administrativa de la Asociación de Agua "
            "Potable y Alcantarillado Isinuta, automatizando el control de afiliados, pagos, multas "
            "y reportes durante la gestión 2026. La solución mejoró la organización de los procesos "
            "administrativos: las pruebas de funcionamiento de los cuatro Sprint resultaron "
            "satisfactorias y la información quedó disponible para la mesa directiva mediante los "
            "reportes de ingresos y deudas.",
        )
        log.append("conclusion general limpia")

# Quitar guiones largos — en párrafos tocados cerca de pruebas si quedaran
for p in paras:
    t = pt(p)
    if "—" in t and ("prueba" in t.lower() or "satisfactorio" in t.lower() or "Sprint 1" in t):
        set_para_text(p, t.replace("—", ", "))
        log.append("guion largo quitado")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_sin_37_38.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)

print("removed", removed)
print("saved", src)
for x in log:
    print(" -", x)

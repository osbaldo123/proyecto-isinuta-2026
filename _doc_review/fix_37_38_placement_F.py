# -*- coding: utf-8 -*-
"""Mueve 3.7 y 3.8 del índice al final del Capítulo III."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_37_38_place.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"


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


def make_para(text, style=None):
    p = etree.Element(W + "p")
    pPr = etree.SubElement(p, W + "pPr")
    if style:
        ps = etree.SubElement(pPr, W + "pStyle")
        ps.set(W + "val", style)
    set_spacing15(p, justify=style not in ("Ttulo2", "Ttulo3", "Ttulo1"))
    r = etree.SubElement(p, W + "r")
    set_arial11(r)
    if style in ("Ttulo2", "Ttulo3"):
        etree.SubElement(r.find(W + "rPr"), W + "b")
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

# Identificar bloque erróneo en el índice (entre 3.6.4 TDC y 4 CAPÍTULO TDC)
to_remove = []
for i, p in enumerate(paras):
    t = pt(p)
    s = st(p)
    if s and s.startswith("TDC"):
        continue
    # bloque insertado: títulos 3.7/3.8 o textos de pruebas/análisis que quedaron antes del TDC1 cap IV
    if i < 250 and (
        t.startswith("3.7 Pruebas")
        or t.startswith("3.8 Análisis")
        or t.startswith("Se ejecutaron 22 pruebas")
        or t.startswith("Tabla 21:")
        or t.startswith("Sprint 1 (roles, usuarios")
        or t.startswith("La comparación entre el proceso anterior")
        or t.startswith("Estos resultados se relacionan con los objetivos")
    ):
        to_remove.append(p)

print("removing from TOC area:", len(to_remove))
for p in to_remove:
    print(" -", pt(p)[:80])
    parent = p.getparent()
    parent.remove(p)

paras = list(root.iter(W + "p"))
# Encontrar Capítulo IV del CUERPO (Ttulo1), no el del índice
target = None
for i, p in enumerate(paras):
    if st(p) == "Ttulo1" and "CAPÍTULO IV" in pt(p).upper() and "CONCLUS" in pt(p).upper():
        target = p
        print("insert before body", i, pt(p)[:60])
        break
if target is None:
    raise SystemExit("No se encontró CAPÍTULO IV cuerpo")

# Evitar duplicar si ya están bien ubicados
already = False
for i, p in enumerate(paras):
    if pt(p).startswith("3.7 Pruebas realizadas") and st(p) == "Ttulo3":
        # si está cerca del cap IV cuerpo
        for j in range(i, min(i + 15, len(paras))):
            if st(paras[j]) == "Ttulo1" and "CAPÍTULO IV" in pt(paras[j]).upper():
                already = True
print("already placed correctly", already)

if not already:
    blocks = [
        ("Ttulo3", "3.7 Pruebas realizadas."),
        (
            None,
            "Se ejecutaron 22 pruebas de funcionamiento distribuidas en los cuatro Sprint "
            "(Sprint 1: 6; Sprint 2: 3; Sprint 3: 7; Sprint 4: 6), según las tablas 14, 16, 18 y 20. "
            "Todas las pruebas registradas resultaron satisfactorias en el entorno de validación. "
            "Además, la secretaria y el presidente revisaron los módulos de cobro, multas y reportes "
            "en una prueba de aceptación funcional del sistema en operación.",
        ),
        (None, "Tabla 21: Resumen de pruebas de funcionamiento por Sprint"),
        (
            None,
            "Sprint 1 (roles, usuarios, afiliados y tipos de afiliación): 6 pruebas — satisfactorio. "
            "Sprint 2 (OTB y servicios por categoría): 3 pruebas — satisfactorio. "
            "Sprint 3 (trabajos, pagos y multas): 7 pruebas — satisfactorio. "
            "Sprint 4 (reportes de ingresos y deudas): 6 pruebas — satisfactorio. "
            "Total: 22 pruebas satisfactorias.",
        ),
        ("Ttulo3", "3.8 Análisis de resultados."),
        (
            None,
            "La comparación entre el proceso anterior (cuadernos, Excel y SISCOCAP) y el sistema web "
            "mostró mejoras en los tiempos de atención administrativa. El registro de un afiliado con "
            "su lote pasó de un trámite fragmentado en varios soportes a una carga única en el módulo "
            "de afiliados. El cobro con emisión de recibo consolidó en una sola pantalla las cuotas, "
            "multas y el cambio, con impresión inmediata. El pase de lista por OTB dejó de depender "
            "solo del cuaderno y genera la multa por lote ausente de forma automática. La elaboración "
            "de un reporte de ingresos o deudas, que antes exigía consolidar varios archivos, se obtiene "
            "con filtros de fecha o concepto y exportación a Excel o impresión.",
        ),
        (
            None,
            "Estos resultados se relacionan con los objetivos específicos: el control de afiliados y "
            "tipos de afiliación (objetivo 1), la organización de OTB y tarifas por categoría (objetivo 2), "
            "la automatización de trabajos, pagos y multas (objetivo 3) y la generación de reportes para "
            "la mesa directiva (objetivo 4).",
        ),
    ]
    for style, text in reversed(blocks):
        target.addprevious(make_para(text, style=style))
    print("inserted 3.7/3.8 before Capítulo IV")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_37_38.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("saved", src)

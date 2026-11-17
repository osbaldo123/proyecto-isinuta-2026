# -*- coding: utf-8 -*-
"""Corrige mora, reportes/pruebas y notas en PROYECTO MERCADO -F.docx (Arial 11, interlineado 1.5)."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_mora.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

REPLACEMENTS = {
    # Diagnóstico
    267: (
        "A partir de la afiliación, cada lote pagaba el servicio mensual de agua potable y el de "
        "alcantarillado según la categoría del inmueble. En Domiciliar el agua es Bs. 8 y el "
        "alcantarillado es Bs. 15. En Comercial el agua es Bs. 17 y el alcantarillado es Bs. 20. "
        "Si ese cobro mensual se atrasaba, al completar tres meses sin pagar se aplicaba, el día 1 "
        "del mes siguiente, una multa trimestral de Bs. 50. Ese mes se cobraba normal, pero no "
        "entraba en la cuenta. Así, un año sin pagar sumaba Bs. 50 (1 de abril), Bs. 100 (1 de "
        "agosto) y Bs. 150 (1 de diciembre); este último es la mora anual, no un cobro aparte. Con "
        "12 meses vencidos se cortaba el servicio del lote. Estas situaciones se trataban en "
        "reuniones comunitarias, donde se hacía el seguimiento y se comunicaba al afiliado la "
        "necesidad de regularizar sus pagos."
    ),
    # Problema reportes (sin afirmar filtro por categoría como filtro de pantalla)
    294: (
        "La elaboración de los informes de ingresos y deudas requería revisar manualmente los "
        "cuadernos, los recibos, las hojas de Excel y los datos del sistema de escritorio, sin "
        "posibilidad de filtrar la información por fechas, afiliado o concepto. Esto ocasionaba "
        "demoras en la obtención de los informes y dificultaba la toma de decisiones y la "
        "rendición de cuentas de la mesa directiva en las reuniones comunitarias."
    ),
    # Objetivo 4
    307: (
        "Implementar el módulo de reportes, que permita obtener el reporte de ingresos y el "
        "reporte de deudas pendientes, con filtros por fechas, afiliado y concepto, subtotales "
        "por concepto (y totales por categoría en ingresos), total general y exportación a Excel "
        "o impresión, a fin de agilizar la obtención de información para la toma de decisiones de "
        "la mesa directiva."
    ),
    # Método de pago HU-08
    721: "Método de pago: efectivo o QR.",
    # HU-09 reglas
    746: (
        "Al completar 3 meses sin pagar se genera, el día 1 del mes siguiente, una multa "
        "trimestral de Bs 50; ese mes se cobra normal, pero no entra en la cuenta. Un año sin "
        "pagar acumula Bs 50 → Bs 100 → Bs 150 (mora anual = tercer tramo, no un cobro aparte). "
        "El corte del servicio aplica por lote a los 12 meses vencidos. Cada tramo se genera una "
        "sola vez por el período que corresponde."
    ),
    749: (
        "Los montos y los meses se leen de la opción Configuración ubicada dentro del módulo "
        "Multas. Los valores iniciales son Bs 50 por cada tramo trimestral (acumulable a Bs 50, "
        "Bs 100 y Bs 150 en el año) y 12 meses para el corte del servicio por lote."
    ),
    # HU-10
    771: (
        "Se debe permitir filtrar el reporte de ingresos por afiliado y por concepto (además del "
        "rango de fechas). El reporte de deudas se filtra por concepto."
    ),
    773: (
        "El reporte de deudas pendientes se consulta desde el Panel y desde el módulo de Reportes. "
        "Muestra el detalle de deudas por concepto (cuotas y multas), con afiliado, lote y montos, "
        "ordenado por nombre de afiliado. Tiene botón de Excel y de impresión."
    ),
    # Control de multas 2.2.11 (refuerzo alineado al sistema)
    357: (
        "La Real Academia Española (2014) define la multa como “sanción pecuniaria que se impone "
        "a quien ha infringido una ley o una norma reglamentaria”. En el presente proyecto, el "
        "control de multas abarca la mora por atraso —Bs 50 el día 1 del mes siguiente a tres "
        "meses sin pagar, acumulable a Bs 100 y Bs 150 (mora anual)— y la multa por inasistencia "
        "a trabajos, con consulta de estado y cobro en recibo de caja."
    ),
    # Sprint 3 backlog tarea mora
    1295: (
        "Implementar la generación automática de multas por mora (Bs 50 el día 1 del mes "
        "siguiente a 3 meses sin pagar, acumulable a Bs 100 y Bs 150 anual por lote) y el corte "
        "del servicio a los 12 meses vencidos por lote."
    ),
    # Prueba Tabla 18 - generar multas
    1383: "Consultar multa por mora generada automáticamente",
    1384: "Afiliado/lote con 3 meses sin pagar al cumplirse el día 1 del mes siguiente",
    1386: "Al abrir el Panel o el cobro aparece la multa pendiente (también con la tarea diaria).",
    1387: "Se registra la multa por mora trimestral de Bs 50 sin un botón manual de generación.",
    # Sprint 4 DoD
    1413: (
        "El Sprint 4 se considera terminado cuando el sistema permita obtener el reporte de "
        "ingresos entre una fecha inicial y una fecha final, con subtotal por concepto y total "
        "general; filtrar ingresos por afiliado y concepto; filtrar deudas por concepto; "
        "consultar el reporte de deudas pendientes desde el Panel y desde el módulo de Reportes; "
        "e imprimir o guardar en PDF y descargar en Excel el reporte de ingresos y el reporte de "
        "deudas."
    ),
    1434: "Implementar el filtro de ingresos por afiliado y concepto, y el filtro de deudas por concepto.",
    1449: (
        "Implementar el reporte de deudas pendientes con detalle por concepto (cuotas, multas y "
        "saldo de afiliación)."
    ),
    # Notas figuras 37-38
    1481: (
        "Nota: Interfaz del reporte de ingresos por rango de fechas, con filtros por afiliado y "
        "concepto, subtotales por concepto, botón Excel e Imprimir / PDF."
    ),
    1485: (
        "Nota: Interfaz del detalle de deudas por concepto, con cuotas y multas pendientes, "
        "filtro por concepto, botón Excel e impresión."
    ),
    # Prueba Tabla 20 deudas
    1521: "Detalle de deudas por concepto",
    1522: "Se muestran las líneas de deuda (cuotas y multas) ordenadas por afiliado, con filtro por concepto.",
}


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def set_para_arial_15(p, text: str, bold=False):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    # interlineado 1.5
    spacing = pPr.find(W + "spacing")
    if spacing is None:
        spacing = etree.SubElement(pPr, W + "spacing")
    spacing.set(W + "line", "360")
    spacing.set(W + "lineRule", "auto")

    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)

    r = etree.SubElement(p, W + "r")
    rPr = etree.SubElement(r, W + "rPr")
    fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    etree.SubElement(rPr, W + "sz").set(W + "val", "22")
    etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
    if bold:
        etree.SubElement(rPr, W + "b")
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text


shutil.copy2(src, backup)
print("backup ->", backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))
print("paras", len(paras))

for idx, new_text in REPLACEMENTS.items():
    old = para_text(paras[idx])
    if not old:
        print("WARN empty", idx)
    set_para_arial_15(paras[idx], new_text)
    print(f"OK [{idx}] {old[:50]!r} -> {new_text[:50]!r}")

# También alinear DoD Sprint 3 si menciona la regla vieja
for i, p in enumerate(paras):
    t = para_text(p)
    if "Bs 50 trimestral acumulable a Bs 100 y Bs 150 anual" in t and i not in REPLACEMENTS:
        nt = t.replace(
            "Bs 50 trimestral acumulable a Bs 100 y Bs 150 anual, por lote), con corte del servicio a los 12 meses vencidos por lote",
            "Bs 50 el día 1 del mes siguiente a 3 meses sin pagar, acumulable a Bs 100 y Bs 150 anual por lote), con corte del servicio a los 12 meses vencidos por lote",
        )
        if nt != t:
            set_para_arial_15(p, nt)
            print(f"OK DoD-like [{i}]")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_mora.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)

try:
    shutil.copy2(tmp, src)
    print("saved", src)
except PermissionError:
    alt = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F-mora.docx")
    shutil.copy2(tmp, alt)
    print("LOCKED ->", alt)

# -*- coding: utf-8 -*-
"""Copia el proyecto de grado y alinea reglas de negocio con el sistema (sin anexos)."""

from __future__ import annotations

import copy
import shutil
from pathlib import Path

from docx import Document
from docx.oxml.ns import qn
from docx.oxml.table import CT_Tbl
from docx.oxml.text.paragraph import CT_P
from docx.table import Table
from docx.text.paragraph import Paragraph

SRC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
DST = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")

# Frases exactas / fragmentos a corregir (orden: más largos primero).
REPLACEMENTS: list[tuple[str, str]] = [
    (
        "Si ese cobro mensual se atrasaba, se aplicaba una multa de Bs. 50 al cumplirse tres meses y, además, una multa de Bs. 150 al cumplirse un año.",
        "Si ese cobro mensual se atrasaba, se aplicaba una multa trimestral de Bs. 50 al cumplirse tres meses sin pagar; esa multa se acumula (Bs. 50, Bs. 100 y Bs. 150) y, al cumplirse un año (12 meses vencidos), corresponde la mora anual de Bs. 150 y el corte del servicio por lote.",
    ),
    (
        "Multa de Bs 50 al acumular 3 meses vencidos en una gestión, y además Bs 150 si esa gestión llega a 12 meses. Las dos quedan registradas. Cada una, una sola vez por lote y por gestión.",
        "Multa trimestral de Bs 50 al acumular 3 meses vencidos; se acumula Bs 50 → Bs 100 → Bs 150 (mora anual). El corte del servicio aplica por lote a los 12 meses vencidos. Cada tramo se genera una sola vez por el período que corresponde.",
    ),
    (
        "Los montos y los meses se leen de la opción Configuración ubicada dentro del módulo Multas. Los valores iniciales son Bs 50 a 3 meses y Bs 150 a 12 meses.",
        "Los montos y los meses se leen de la opción Configuración ubicada dentro del módulo Multas. Los valores iniciales son Bs 50 cada 3 meses (acumulable hasta Bs 150 anual) y 12 meses para el corte del servicio por lote.",
    ),
    (
        "generar multas automáticas por mora (Bs 50 a los 3 meses y, además, Bs 150 a los 12 meses, por lote y por gestión), de modo que la multa de mora generada permanezca pendiente hasta su cobro",
        "generar multas automáticas por mora (Bs 50 trimestral acumulable a Bs 100 y Bs 150 anual, por lote), con corte del servicio a los 12 meses vencidos por lote, de modo que la multa de mora generada permanezca pendiente hasta su cobro",
    ),
    (
        "Implementar la generación automática de multas por mora (Bs 50 a los 3 meses y, además, Bs 150 a los 12 meses, por lote y por gestión).",
        "Implementar la generación automática de multas por mora (Bs 50 trimestral acumulable a Bs 100 y Bs 150 anual, por lote) y el corte del servicio a los 12 meses vencidos por lote.",
    ),
    (
        "gestionar pagos de servicio con recibo de ingreso para el agua y recibo de caja para los demás conceptos",
        "gestionar pagos de servicio con recibo de ingreso para agua y alcantarillado, y recibo de caja para multas, afiliación y demás conceptos",
    ),
    (
        "Método de pago: efectivo o QR.",
        "Método de pago: efectivo, transferencia o QR.",
    ),
    (
        "El reporte de deudas pendientes se consulta desde el Panel y desde el módulo de Reportes. Muestra lote, afiliado, OTB, cuotas, multas, saldo de afiliación y total, de mayor a menor. No tiene botón de Excel ni de impresión.",
        "El reporte de deudas pendientes se consulta desde el Panel y desde el módulo de Reportes. Muestra lote, afiliado, OTB, cuotas, multas, saldo de afiliación y total, de mayor a menor. Tiene botón de Excel y de impresión.",
    ),
    (
        "El módulo Reportes muestra la tarjeta Pagos por Concepto. El reporte de deudas pendientes se abre desde el Panel y también desde Reportes.",
        "El módulo Reportes muestra las tarjetas de Ingresos y de Deudas. El reporte de deudas pendientes se abre desde el Panel y también desde Reportes.",
    ),
    (
        "Obtener el informe de pagos por concepto y el reporte de deudas pendientes",
        "Obtener el reporte de ingresos y el reporte de deudas pendientes",
    ),
    (
        "El informe Pagos por Concepto debe tener el botón Excel y el botón Imprimir / PDF.",
        "El reporte de ingresos debe tener el botón Excel y el botón Imprimir / PDF.",
    ),
    (
        "Las multas y las faltas de trabajo cobradas aparecen dentro de Pagos x Concepto. No existe un reporte separado de recargos.",
        "Las multas y las faltas de trabajo cobradas aparecen dentro del reporte de ingresos. No existe un reporte separado de recargos.",
    ),
    (
        "consulta deudas, recibos, el estado de cuenta de los lotes, multas, OTB, servicios, trabajos y el informe Pagos por Concepto",
        "consulta deudas, recibos, el estado de cuenta de los lotes, multas, OTB, servicios, trabajos y el reporte de ingresos",
    ),
    (
        "Consulta e imprime Pagos por Concepto, consulta el reporte de deudas",
        "Consulta e imprime el reporte de ingresos, consulta el reporte de deudas",
    ),
    (
        "Implementar el módulo de reportes, que permita obtener el informe de pagos por concepto y el reporte de deudas pendientes, con filtros por fechas, afiliado, concepto y categoría, subtotales, total general y exportación a Excel o impresión, a fin de agilizar la obtención de información para la toma de decisiones de la mesa directiva.",
        "Implementar el módulo de reportes, que permita obtener el reporte de ingresos y el reporte de deudas pendientes, con filtros por fechas, afiliado, concepto y categoría, subtotales, total general y exportación a Excel o impresión, a fin de agilizar la obtención de información para la toma de decisiones de la mesa directiva.",
    ),
    (
        "obtener el informe de pagos por concepto y el reporte de deudas pendientes",
        "obtener el reporte de ingresos y el reporte de deudas pendientes",
    ),
    (
        "obtener el reporte de pagos e ingresos por concepto entre una fecha inicial y una fecha final",
        "obtener el reporte de ingresos entre una fecha inicial y una fecha final",
    ),
    (
        "e imprimir o guardar en PDF y descargar en Excel el informe de pagos por concepto",
        "e imprimir o guardar en PDF y descargar en Excel el reporte de ingresos y el reporte de deudas",
    ),
    (
        "Implementar el reporte de pagos e ingresos entre una fecha inicial y una fecha final, agrupado por concepto con subtotal y total general.",
        "Implementar el reporte de ingresos entre una fecha inicial y una fecha final, agrupado por concepto con subtotal y total general.",
    ),
    (
        "Incluir las multas cobradas dentro del informe de pagos por concepto.",
        "Incluir las multas cobradas dentro del reporte de ingresos.",
    ),
    (
        "imprimir y exportar el informe de pagos por concepto, y consultar el reporte de deudas pendientes",
        "imprimir y exportar el reporte de ingresos y el reporte de deudas pendientes",
    ),
    (
        "informe de pagos por concepto y reporte de deudas",
        "reporte de ingresos y reporte de deudas",
    ),
    (
        "con el informe de pagos por concepto y el reporte de deudas pendientes, filtros, exportación a Excel e impresión",
        "con el reporte de ingresos y el reporte de deudas pendientes, filtros, exportación a Excel e impresión",
    ),
    (
        "Estado del afiliado: activo, cortado o baja.",
        "Estado del afiliado y del lote: activo, cortado o baja. El corte por mora anual (12 meses vencidos) se aplica por lote; si el afiliado tiene varios lotes, cada uno se corta o reactiva de forma independiente.",
    ),
]


def paragraph_text(paragraph: Paragraph) -> str:
    return "".join(run.text for run in paragraph.runs)


def set_paragraph_text_keep_style(paragraph: Paragraph, new_text: str) -> None:
    """Reemplaza el texto del párrafo conservando el formato del primer run."""
    if not paragraph.runs:
        paragraph.add_run(new_text)
        return

    first = paragraph.runs[0]
    first.text = new_text
    for run in paragraph.runs[1:]:
        run.text = ""


def is_anexos_heading(text: str) -> bool:
    """Solo el título real de anexos (no la entrada del índice tipo ANEXOS8)."""
    t = text.strip().upper().rstrip('.')
    return t == "ANEXOS"


def should_skip_paragraph(text: str, in_anexos: bool) -> bool:
    if in_anexos:
        return True
    t = text.strip()
    # Contenido de anexos numerados (aunque aparezcan fuera de orden)
    if t.upper().startswith("ANEXO ") or t.upper().startswith("ANEXO."):
        return True
    if t.startswith("Figura ") or t.startswith("Figura:"):
        return True
    # Notas de figuras / capturas / diagramas de implementación
    if t.startswith("Nota:") and (
        "Interfaz" in t
        or "diagrama" in t.lower()
        or "captura" in t.lower()
        or "Recibo de ingreso generado" in t
        or "Gráfico" in t
    ):
        return True
    return False


def apply_replacements(text: str) -> tuple[str, int]:
    count = 0
    out = text
    for old, new in REPLACEMENTS:
        if old in out:
            out = out.replace(old, new)
            count += 1
    return out, count


def iter_block_items(doc: Document):
    """Párrafos y tablas en el orden real del documento."""
    body = doc.element.body
    for child in body.iterchildren():
        if isinstance(child, CT_P):
            yield Paragraph(child, doc)
        elif isinstance(child, CT_Tbl):
            yield Table(child, doc)


def iter_paragraphs_in_order(doc: Document):
    """Recorre párrafos del cuerpo y de todas las tablas, sin perder celdas."""
    seen_p: set[object] = set()

    def yield_unique(paragraph: Paragraph):
        element = paragraph._p
        if element in seen_p:
            return
        seen_p.add(element)
        yield paragraph

    for block in iter_block_items(doc):
        if isinstance(block, Paragraph):
            yield from yield_unique(block)
            continue

        tables = [block]
        tables.extend(Table(n, doc) for n in block._tbl.findall('.//' + qn('w:tbl')))
        for table in tables:
            for row in table.rows:
                for cell in row.cells:
                    for p in cell.paragraphs:
                        yield from yield_unique(p)


def process_document(doc: Document, replacements: list[tuple[str, str]]) -> tuple[int, int]:
    in_anexos = False
    changed = 0
    touched = 0

    for p in iter_paragraphs_in_order(doc):
        text = paragraph_text(p)
        if not text.strip():
            continue

        if is_anexos_heading(text):
            in_anexos = True

        if should_skip_paragraph(text, in_anexos):
            continue

        out = text
        n = 0
        for old, new in replacements:
            if old in out:
                out = out.replace(old, new)
                n += 1
        if n and out != text:
            set_paragraph_text_keep_style(p, out)
            changed += n
            touched += 1

    return touched, changed


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"No existe: {SRC}")

    shutil.copy2(SRC, DST)
    doc = Document(str(DST))

    extra = [
        ("Pagos por Concepto", "reporte de ingresos"),
        ("Pagos x Concepto", "reporte de ingresos"),
        ("informe de pagos por concepto", "reporte de ingresos"),
        ("informe Pagos por Concepto", "reporte de ingresos"),
        (
            "interfaces del informe de pagos por concepto y del reporte de deudas pendientes",
            "interfaces del reporte de ingresos y del reporte de deudas pendientes",
        ),
    ]

    touched, changed = process_document(doc, REPLACEMENTS + extra)

    doc.save(str(DST))
    print(f"OK -> {DST}")
    print(f"parrafos_tocados={touched} reemplazos={changed}")


if __name__ == "__main__":
    main()

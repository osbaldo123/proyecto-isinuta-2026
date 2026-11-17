"""Actualiza títulos y notas de implementación en el docx (solo texto necesario)."""
from copy import deepcopy
from pathlib import Path

from docx import Document

SRC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
DST = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")

# Reemplazos exactos: viejo -> nuevo (solo lo necesario)
REPLACES = [
    (
        "Figura 15: Inicio de sesión de la secretaria.",
        "Figura 15: Inicio de sesión de la secretaria.",
    ),
    (
        "Nota: Interfaz de acceso al sistema con correo y contraseña.",
        "Nota: Interfaz de acceso al sistema en tema claro, con correo y contraseña.",
    ),
    (
        "Figura 16: Registro de usuarios.",
        "Figura 16: Registro de usuarios.",
    ),
    (
        "Nota: Interfaz para registrar nuevos usuarios en el sistema.",
        "Nota: Interfaz en tema claro para registrar nuevos usuarios en el sistema.",
    ),
    (
        "Figura 17:Lista de usuarios registrados.",
        "Figura 17: Lista de usuarios registrados.",
    ),
    (
        "Figura 17: Lista de usuarios registrados.",
        "Figura 17: Lista de usuarios registrados.",
    ),
    (
        "Nota: Interfaz que muestra la información y estado de los usuarios registrados.",
        "Nota: Interfaz en tema claro que muestra la información y estado de los usuarios registrados.",
    ),
    (
        "Figura 18:Lista de Roles.",
        "Figura 18: Lista de Roles.",
    ),
    (
        "Figura 18: Lista de Roles.",
        "Figura 18: Lista de Roles.",
    ),
    (
        "Nota: Interfaz que muestra los roles registrados en el sistema.",
        "Nota: Interfaz en tema claro que muestra los roles registrados en el sistema.",
    ),
    (
        "Figura 19: Lista de afiliados.",
        "Figura 19: Lista de afiliados.",
    ),
    (
        "Nota: Interfaz que muestra los afiliados registrados, con su lote, OTB y estado.",
        "Nota: Interfaz en tema claro que muestra los afiliados registrados, con su número de lote, OTB y estado.",
    ),
    (
        "Figura 20: Tipos de afiliación.",
        "Figura 20: Tipos de afiliación.",
    ),
    (
        "Nota: Interfaz que muestra los tipos de afiliación y el monto de instalación de agua y alcantarillado.",
        "Nota: Interfaz en tema claro que muestra los tipos de afiliación con montos al contado y a plazo.",
    ),
    (
        "En la etapa de implementación del primer Sprint se desarrollaron las interfaces necesarias para la gestión de roles, usuarios, afiliados y tipos de afiliación.",
        "En la etapa de implementación del primer Sprint se desarrollaron las interfaces necesarias para la gestión de roles, usuarios, afiliados y tipos de afiliación.",
    ),
    (
        "En la etapa de implementación del segundo Sprint se desarrollaron las interfaces para la gestión de categorías, la actualización de servicios y el registro de OTB.",
        "En la etapa de implementación del segundo Sprint se desarrollaron las interfaces para la gestión de categorías, la actualización de servicios y el registro de OTB.",
    ),
    (
        "Figura 23: Lista de categorías.",
        "Figura 23: Lista de categorías.",
    ),
    (
        "Nota: Interfaz que muestra las categorías Domiciliar y Comercial, la cantidad de lotes y el estado de cada una.",
        "Nota: Interfaz en tema claro que muestra las categorías Domiciliar y Comercial, la cantidad de lotes y el estado de cada una.",
    ),
    (
        "Figura 24: Actualización de servicio.",
        "Figura 24: Actualización de servicio.",
    ),
    (
        "Nota: Interfaz para actualizar un servicio, su estado y la tarifa mensual según la categoría del inmueble.",
        "Nota: Interfaz en tema claro para actualizar un servicio, su estado y la tarifa mensual según la categoría del inmueble.",
    ),
    (
        "Figura 25: Lista de OTB.",
        "Figura 25: Lista de OTB.",
    ),
    (
        "Nota: Interfaz que muestra las OTB registradas en la asociación.",
        "Nota: Interfaz en tema claro que muestra las OTB (barrios o sindicatos) registradas en la asociación.",
    ),
    (
        "En la etapa de implementación del tercer Sprint se desarrollaron las interfaces de trabajos de la OTB, pase de lista, cobranza, recibo y multas.",
        "En la etapa de implementación del tercer Sprint se desarrollaron las interfaces de trabajos de la OTB, pase de lista, cobranza, recibo, multas y estado de cuenta.",
    ),
    (
        "Figura 28: Multa por mora generada automáticamente en el cobro.",
        "Figura 28: Multa por mora generada automáticamente en el cobro.",
    ),
    (
        "Nota: Interfaz de cobranza donde, al seleccionar el lote, el sistema muestra la multa por mora pendiente para agregarla al recibo de ese lote.",
        "Nota: Interfaz de cobranza en tema claro donde, al seleccionar el lote, el sistema muestra la multa por mora pendiente para agregarla al recibo.",
    ),
    (
        "Figura 29: Lista de trabajos.",
        "Figura 29: Lista de trabajos.",
    ),
    (
        "Nota: Interfaz que muestra el trabajo registrado de la OTB que indica la secretaria, con el tipo, la fecha de inicio, el monto de la multa y el acceso a la lista.",
        "Nota: Interfaz en tema claro que muestra el trabajo registrado por OTB, con tipo, fecha de inicio, monto de la multa y acceso a la lista.",
    ),
    (
        "Figura 30: Lista de asistencia del trabajo.",
        "Figura 30: Lista de asistencia del trabajo.",
    ),
    (
        "Nota: Interfaz para pasar lista por nombre del afiliado. Se marca presente o falta, y se ven los lotes y servicios de esa OTB. La falta genera una multa en cada lote del afiliado.",
        "Nota: Interfaz en tema claro para pasar lista por nombre del afiliado. Se marca presente o falta; la falta genera una multa en cada lote del afiliado en esa OTB.",
    ),
    (
        "Figura 31: Registro de pago.",
        "Figura 31: Registro de pago.",
    ),
    (
        "Nota: Interfaz para registrar el pago de agua potable o alcantarillado del lote, mostrando la deuda, las cuotas y las multas pendientes.",
        "Nota: Interfaz en tema claro para registrar el pago del lote, mostrando la deuda, las cuotas y las multas pendientes.",
    ),
    (
        "Figura 32: Recibo de pago.",
        "Figura 32: Recibo de pago.",
    ),
    (
        "Nota: Recibo de ingreso generado por el sistema, con el número de comprobante, el afiliado, el concepto y el importe, y con los botones Imprimir, Exel.",
        "Nota: Recibo de ingreso generado por el sistema, con el número de comprobante, el afiliado, el concepto y el importe, y con los botones Imprimir y Excel.",
    ),
    (
        "Figura 33: Lista de multas.",
        "Figura 33: Lista de multas.",
    ),
    (
        "Nota: Interfaz que muestra las multas de mora y de falta de trabajo, con motivo, monto y estado. La multa por inasistencia pendiente se retira si el afiliado pasa a presente en la lista; la multa por mora permanece pendiente hasta su cobro.",
        "Nota: Interfaz en tema claro que muestra las multas de mora y de falta de trabajo, con motivo, monto y estado.",
    ),
    (
        "Figura 34: Estado de cuenta del lote.",
        "Figura 34: Estado de cuenta del lote.",
    ),
    (
        "Nota: Interfaz que muestra el estado de cuenta del lote, con las cuotas y multas pendientes, el saldo de afiliación, los meses pagados y el kardex de pagos.",
        "Nota: Interfaz en tema claro que muestra el estado de cuenta del lote, con cuotas, multas, saldo de afiliación y kardex de pagos.",
    ),
    (
        "En la etapa de implementación del cuarto Sprint se desarrollaron las interfaces del informe de pagos por concepto y del reporte de deudas pendientes.",
        "En la etapa de implementación del cuarto Sprint se desarrollaron las interfaces del informe de pagos por concepto y del reporte de deudas pendientes.",
    ),
    (
        "Figura 37: Reporte de ingresos por concepto.",
        "Figura 37: Reporte de pagos por concepto.",
    ),
    (
        "Nota: Interfaz que muestra los pagos de un rango de fechas agrupados por concepto, con filtros por categoría, afiliado y concepto, botón Excel e Imprimir / PDF.",
        "Nota: Interfaz en tema claro que muestra los pagos de un rango de fechas agrupados por concepto, con filtros, botón Excel e Imprimir / PDF.",
    ),
    (
        "Figura 38: Reporte de deudas pendientes.",
        "Figura 38: Reporte de deudas pendientes.",
    ),
    (
        "Nota: Interfaz que muestra los afiliados con deuda pendiente, con cuotas, multas, saldo de afiliación y total.",
        "Nota: Interfaz en tema claro que muestra los lotes con deuda pendiente, con cuotas, multas, saldo de afiliación y total.",
    ),
    # Diagram titles/notes that need small fixes
    (
        "Figura 21:Diagrama de casos de uso del Sprint 2.",
        "Figura 21: Diagrama de casos de uso del Sprint 2.",
    ),
    (
        "Figura 22:Diagrama de clases del Sprint 2.",
        "Figura 22: Diagrama de clases del Sprint 2.",
    ),
    (
        "Nota:  El diagrama muestra las clases del Sprint 2: Servicio, Categoría y Otb",
        "Nota: El diagrama muestra las clases del Sprint 2: Otb, Servicio y Categoría.",
    ),
    (
        "Nota:  El diagrama muestra las clases del Sprint 3: OTB, Trabajo, Asistencia, Afiliado, Lote Afiliado, Multa, Pago y Pago Detalle.",
        "Nota: El diagrama muestra las clases del Sprint 3: Trabajo, Asistencia, Multa, Pago y PagoDetalle.",
    ),
    (
        "Figura 36:Diagrama de clases del Sprint 4.",
        "Figura 36: Diagrama de clases del Sprint 4.",
    ),
    (
        "Nota:  El diagrama muestra las clases del Sprint 4: Reporte Administrativo, Pago, Pago Detalle, Multa, Afiliado y Lote Afiliado.",
        "Nota: El diagrama muestra la clase ReporteAdministrativo del Sprint 4, orientada a consultar ingresos y deudas.",
    ),
]


def replace_in_paragraph(paragraph, old: str, new: str) -> bool:
    full = "".join(run.text for run in paragraph.runs)
    if old not in full:
        return False
    if full == old:
        # keep first run formatting
        if paragraph.runs:
            paragraph.runs[0].text = new
            for run in paragraph.runs[1:]:
                run.text = ""
        else:
            paragraph.add_run(new)
        return True
    # rebuild in first run
    updated = full.replace(old, new)
    if paragraph.runs:
        paragraph.runs[0].text = updated
        for run in paragraph.runs[1:]:
            run.text = ""
    else:
        paragraph.add_run(updated)
    return True


def main() -> None:
    doc = Document(str(SRC))
    changed = 0
    for para in doc.paragraphs:
        text = "".join(run.text for run in para.runs) or para.text
        for old, new in REPLACES:
            if old == new:
                continue
            if old in text:
                if replace_in_paragraph(para, old, new):
                    changed += 1
                    text = "".join(run.text for run in para.runs)
                    break
    # also tables
    for table in doc.tables:
        for row in table.rows:
            for cell in row.cells:
                for para in cell.paragraphs:
                    text = "".join(run.text for run in para.runs) or para.text
                    for old, new in REPLACES:
                        if old == new:
                            continue
                        if old in text and replace_in_paragraph(para, old, new):
                            changed += 1
                            break
    doc.save(str(DST))
    print(f"OK replacements={changed} saved={DST}")


if __name__ == "__main__":
    main()

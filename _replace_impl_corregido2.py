# -*- coding: utf-8 -*-
"""Reemplaza capturas reales en implementaciones del doc corregido 2 y actualiza notas."""
from __future__ import annotations

import re
import shutil
import zipfile
from pathlib import Path

from docx import Document
from PIL import Image

SRC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
DOC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2 - implementaciones.docx")
CAP = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_real_captures")
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_impl_c2")

# Mapeo verificado contra document.xml del corregido 2
MAP = {
    15: ("word/media/image16.png", "fig15-login.png"),
    16: ("word/media/image17.png", "fig16-usuarios-create.png"),
    17: ("word/media/image18.png", "fig17-usuarios.png"),
    18: ("word/media/image19.png", "fig18-roles.png"),
    19: ("word/media/image20.png", "fig19-afiliados.png"),
    20: ("word/media/image21.png", "fig20-afiliaciones.png"),
    23: ("word/media/image24.png", "fig23-categorias.png"),
    24: ("word/media/image25.png", "fig24-servicio-edit.png"),
    25: ("word/media/image26.png", "fig25-otb.png"),
    28: ("word/media/image29.png", "fig28-pagos-create.png"),
    29: ("word/media/image30.png", "fig29-trabajos.png"),
    30: ("word/media/image31.png", "fig30-trabajos-lista.png"),
    31: ("word/media/image32.png", "fig31-pagos-create2.png"),
    32: ("word/media/image33.png", "fig32-recibo.png"),
    33: ("word/media/image34.png", "fig33-multas.png"),
    34: ("word/media/image35.png", "fig34-estado-cuenta.png"),
    37: ("word/media/image38.png", "fig37-ingresos.png"),
    38: ("word/media/image39.png", "fig38-deudas.png"),
}

NOTE_REPLACEMENTS = [
    (
        "Nota: Interfaz de cobranza donde, al seleccionar el lote, el sistema muestra la multa por mora pendiente para agregarla al recibo de ese lote.",
        "Nota: Interfaz de cobranza del lote: muestra mensualidades, multas pendientes (mora trimestral/anual) y permite armar el cobro con recibos separados por propósito.",
    ),
    (
        "Nota: Interfaz para registrar el pago de agua potable o alcantarillado del lote, mostrando la deuda, las cuotas y las multas pendientes.",
        "Nota: Interfaz para registrar el pago del lote: agua y alcantarillado (recibo de ingreso), multas y afiliación (recibo de caja), con meses vencidos, por vencer y adelantos.",
    ),
    (
        "Nota: Recibo de ingreso generado por el sistema, con el número de comprobante, el afiliado, el concepto y el importe, y con los botones Imprimir y Excel.",
        "Nota: Recibo generado por el sistema (ingreso para agua/alcantarillado o caja para multas y demás), con número de comprobante, concepto, importe e Imprimir/Excel.",
    ),
    (
        "Nota: Interfaz que muestra las multas de mora y de falta de trabajo, con motivo, monto y estado. La multa por inasistencia pendiente se retira si el afiliado pasa a presente en la lista.",
        "Nota: Interfaz de multas (mora trimestral/anual y falta de trabajo), con motivo, monto y estado. La multa de trabajo pendiente se retira si el afiliado queda presente en la lista.",
    ),
    (
        "Nota: Interfaz que muestra el estado de cuenta del lote, con las cuotas y multas pendientes, el saldo de afiliación, los meses pagados y el kardex de pagos.",
        "Nota: Estado de cuenta del lote: meses pagados/vencidos/pendientes por servicio, multas, saldo de afiliación, recibos y total adeudado.",
    ),
    (
        "Nota: Interfaz que muestra los pagos de un rango de fechas agrupados por concepto, con filtros por categoría, afiliado y concepto, botón Excel e Imprimir / PDF.",
        "Nota: Reporte de ingresos por rango de fechas, agrupado por concepto, con filtros, Excel e Imprimir/PDF.",
    ),
    (
        "Nota: Interfaz que muestra los afiliados con deuda pendiente, con cuotas, multas, saldo de afiliación y total.",
        "Nota: Reporte de deudas pendientes por lote/afiliado, con cuotas, multas, saldo de afiliación y total; incluye Excel e impresión.",
    ),
]


def replace_images() -> None:
    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir(parents=True)

    prepared: dict[str, bytes] = {}
    for fig, (media, file) in MAP.items():
        src = CAP / file
        if not src.exists():
            raise FileNotFoundError(f"Falta captura Figura {fig}: {src}")
        png = WORK / f"fig{fig}.png"
        Image.open(src).convert("RGB").save(png, format="PNG", optimize=True)
        prepared[media] = png.read_bytes()
        print(f"FIG {fig:02d} <- {file} -> {media}")

    tmp = WORK / "out.docx"
    with zipfile.ZipFile(SRC, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename in prepared:
                data = prepared[item.filename]
            zout.writestr(item, data)

    shutil.copy2(tmp, DOC)
    print("Imagenes reemplazadas en", DOC)


def paragraph_text(p) -> str:
    return "".join(r.text for r in p.runs)


def set_text(p, new: str) -> None:
    if not p.runs:
        p.add_run(new)
        return
    p.runs[0].text = new
    for r in p.runs[1:]:
        r.text = ""


def update_notes() -> None:
    doc = Document(str(DOC))
    changed = 0
    for p in doc.paragraphs:
        t = paragraph_text(p)
        for old, new in NOTE_REPLACEMENTS:
            if t.strip() == old or old in t:
                set_text(p, new if t.strip() == old else t.replace(old, new))
                changed += 1
                break
    doc.save(str(DOC))
    print(f"Notas actualizadas: {changed}")


def main() -> None:
    replace_images()
    update_notes()
    print("LISTO")


if __name__ == "__main__":
    main()

"""
1) Restaura títulos/notas al formato académico (sin 'tema claro').
2) Reemplaza capturas por interfaces blancas en el slot exacto de cada figura/sprint.
"""
from __future__ import annotations

import re
import shutil
import zipfile
from pathlib import Path

from docx import Document
from PIL import Image

DOC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
ASSETS = Path(
    r"C:\Users\papu\.cursor\projects\c-laragon-www-gest-admin-ASOCIACI-N-AGUA-POTABLE-ALCANTARILLADO-ISINUTA\assets"
)
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_fix_final")

# Formato académico correcto (sin mencionar tema claro)
TEXT_REPLACES = [
    (
        "Nota: Interfaz de acceso al sistema en tema claro, con correo y contraseña.",
        "Nota: Interfaz de acceso al sistema con correo y contraseña.",
    ),
    (
        "Nota: Interfaz en tema claro para registrar nuevos usuarios en el sistema.",
        "Nota: Interfaz para registrar nuevos usuarios en el sistema.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra la información y estado de los usuarios registrados.",
        "Nota: Interfaz que muestra la información y estado de los usuarios registrados.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra los roles registrados en el sistema.",
        "Nota: Interfaz que muestra los roles registrados en el sistema.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra los afiliados registrados, con su número de lote, OTB y estado.",
        "Nota: Interfaz que muestra los afiliados registrados, con su lote, OTB y estado.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra los tipos de afiliación con montos al contado y a plazo.",
        "Nota: Interfaz que muestra los tipos de afiliación y el monto al contado y a plazo de agua y alcantarillado.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra las categorías Domiciliar y Comercial, la cantidad de lotes y el estado de cada una.",
        "Nota: Interfaz que muestra las categorías Domiciliar y Comercial, la cantidad de lotes y el estado de cada una.",
    ),
    (
        "Nota: Interfaz en tema claro para actualizar un servicio, su estado y la tarifa mensual según la categoría del inmueble.",
        "Nota: Interfaz para actualizar un servicio, su estado y la tarifa mensual según la categoría del inmueble.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra las OTB (barrios o sindicatos) registradas en la asociación.",
        "Nota: Interfaz que muestra las OTB registradas en la asociación.",
    ),
    (
        "En la etapa de implementación del tercer Sprint se desarrollaron las interfaces de trabajos de la OTB, pase de lista, cobranza, recibo, multas y estado de cuenta.",
        "En la etapa de implementación del tercer Sprint se desarrollaron las interfaces de trabajos de la OTB, pase de lista, cobranza, recibo y multas.",
    ),
    (
        "Nota: Interfaz de cobranza en tema claro donde, al seleccionar el lote, el sistema muestra la multa por mora pendiente para agregarla al recibo.",
        "Nota: Interfaz de cobranza donde, al seleccionar el lote, el sistema muestra la multa por mora pendiente para agregarla al recibo de ese lote.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra el trabajo registrado por OTB, con tipo, fecha de inicio, monto de la multa y acceso a la lista.",
        "Nota: Interfaz que muestra el trabajo registrado de la OTB que indica la secretaria, con el tipo, la fecha de inicio, el monto de la multa y el acceso a la lista.",
    ),
    (
        "Nota: Interfaz en tema claro para pasar lista por nombre del afiliado. Se marca presente o falta; la falta genera una multa en cada lote del afiliado en esa OTB.",
        "Nota: Interfaz para pasar lista por nombre del afiliado. Se marca presente o falta, y se ven los lotes y servicios de esa OTB. La falta genera una multa en cada lote del afiliado.",
    ),
    (
        "Nota: Interfaz en tema claro para registrar el pago del lote, mostrando la deuda, las cuotas y las multas pendientes.",
        "Nota: Interfaz para registrar el pago de agua potable o alcantarillado del lote, mostrando la deuda, las cuotas y las multas pendientes.",
    ),
    (
        "Nota: Recibo de ingreso generado por el sistema, con el número de comprobante, el afiliado, el concepto y el importe, y con los botones Imprimir y Excel.",
        "Nota: Recibo de ingreso generado por el sistema, con el número de comprobante, el afiliado, el concepto y el importe, y con los botones Imprimir y Excel.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra las multas de mora y de falta de trabajo, con motivo, monto y estado.",
        "Nota: Interfaz que muestra las multas de mora y de falta de trabajo, con motivo, monto y estado. La multa por inasistencia pendiente se retira si el afiliado pasa a presente en la lista; la multa por mora permanece pendiente hasta su cobro.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra el estado de cuenta del lote, con cuotas, multas, saldo de afiliación y kardex de pagos.",
        "Nota: Interfaz que muestra el estado de cuenta del lote, con las cuotas y multas pendientes, el saldo de afiliación, los meses pagados y el kardex de pagos.",
    ),
    (
        "Figura 37: Reporte de pagos por concepto.",
        "Figura 37: Reporte de ingresos por concepto.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra los pagos de un rango de fechas agrupados por concepto, con filtros, botón Excel e Imprimir / PDF.",
        "Nota: Interfaz que muestra los pagos de un rango de fechas agrupados por concepto, con filtros por categoría, afiliado y concepto, botón Excel e Imprimir / PDF.",
    ),
    (
        "Nota: Interfaz en tema claro que muestra los lotes con deuda pendiente, con cuotas, multas, saldo de afiliación y total.",
        "Nota: Interfaz que muestra los afiliados con deuda pendiente, con cuotas, multas, saldo de afiliación y total.",
    ),
    (
        "Nota: El diagrama muestra las clases del Sprint 2: Otb, Servicio y Categoría.",
        "Nota: El diagrama muestra las clases del Sprint 2: Servicio, Categoría y Otb.",
    ),
    (
        "Nota: El diagrama muestra las clases del Sprint 3: Trabajo, Asistencia, Multa, Pago y PagoDetalle.",
        "Nota: El diagrama muestra las clases del Sprint 3: Trabajo, Asistencia, Multa, Pago y Pago Detalle.",
    ),
    (
        "Nota: El diagrama muestra la clase ReporteAdministrativo del Sprint 4, orientada a consultar ingresos y deudas.",
        "Nota: El diagrama muestra las clases del Sprint 4: Reporte Administrativo, Pago, Pago Detalle, Multa, Afiliado y Lote Afiliado.",
    ),
]

# Figura -> archivo media del docx <- captura blanca
# Orden exacto por sprint (NO mezclar)
FIGURE_MEDIA = {
    # Sprint 1 implementaciones
    15: ("word/media/image17.png", "impl-s1-login-white.jpg"),
    16: ("word/media/image18.png", "impl-s1-usuarios-create-white.jpg"),
    17: ("word/media/image19.png", "impl-s1-usuarios-list-white.jpg"),
    18: ("word/media/image20.png", "impl-s1-roles-white.jpg"),
    19: ("word/media/image21.png", "impl-s1-afiliados-white.jpg"),
    20: ("word/media/image22.png", "impl-s1-afiliaciones-white.jpg"),
    # Sprint 2 implementaciones
    23: ("word/media/image25.png", "impl-s2-categorias-white.jpg"),
    24: ("word/media/image26.png", "impl-s2-servicio-white.jpg"),
    25: ("word/media/image27.png", "impl-s2-otb-white.jpg"),
    # Sprint 3 implementaciones
    28: ("word/media/image30.png", "impl-s3-cobro-mora-white.jpg"),
    29: ("word/media/image31.png", "impl-s3-trabajos-white.jpg"),
    30: ("word/media/image32.png", "impl-s3-lista-white.jpg"),
    31: ("word/media/image33.png", "impl-s3-pago-white.jpg"),
    32: ("word/media/image34.png", "impl-s3-recibo-white.jpg"),
    33: ("word/media/image35.png", "impl-s3-multas-white.jpg"),
    34: ("word/media/image52.png", "impl-s3-estado-cuenta-white.jpg"),
    # Sprint 4 implementaciones
    37: ("word/media/image38.png", "impl-s4-ingresos-white.jpg"),
    38: ("word/media/image39.png", "impl-s4-deudas-white.jpg"),
}


def replace_paragraph_text(paragraph, old: str, new: str) -> bool:
    full = "".join(run.text for run in paragraph.runs)
    if not full:
        full = paragraph.text
    if old not in full:
        return False
    updated = full.replace(old, new)
    if paragraph.runs:
        paragraph.runs[0].text = updated
        for run in paragraph.runs[1:]:
            run.text = ""
    else:
        paragraph.text = updated
    return True


def fix_notes() -> int:
    doc = Document(str(DOC))
    changed = 0
    paras = list(doc.paragraphs)
    for table in doc.tables:
        for row in table.rows:
            for cell in row.cells:
                paras.extend(cell.paragraphs)
    for para in paras:
        text = "".join(run.text for run in para.runs) or para.text
        for old, new in TEXT_REPLACES:
            if old != new and old in text:
                if replace_paragraph_text(para, old, new):
                    changed += 1
                    break
    doc.save(str(DOC))
    return changed


def to_png(src: Path, dest: Path) -> None:
    img = Image.open(src).convert("RGB")
    dest.parent.mkdir(parents=True, exist_ok=True)
    img.save(dest, format="PNG", optimize=True)


def replace_images() -> None:
    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir(parents=True)

    prepared: dict[str, bytes] = {}
    for fig, (media_path, asset_name) in FIGURE_MEDIA.items():
        src = ASSETS / asset_name
        if not src.exists():
            raise FileNotFoundError(f"Figura {fig}: falta {src}")
        png_path = WORK / f"fig{fig}.png"
        to_png(src, png_path)
        prepared[media_path] = png_path.read_bytes()
        print(f"FIG {fig:02d} -> {media_path} <- {asset_name}")

    tmp = WORK / "out.docx"
    with zipfile.ZipFile(DOC, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        rels = zin.read("word/_rels/document.xml.rels").decode("utf-8")
        document = zin.read("word/document.xml").decode("utf-8")

        if "image52.png" not in rels:
            insert = (
                '<Relationship Id="rId80" '
                'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                'Target="media/image52.png"/>'
            )
            rels = rels.replace("</Relationships>", insert + "</Relationships>")

        # Asegurar secuencia Sprint 3: cobro, trabajos, lista, pago, recibo, multas, estado cuenta
        embeds = list(re.finditer(r'r:embed="(rId\d+)"', document))
        ids = [m.group(1) for m in embeds]
        i = ids.index("rId46")
        target = ["rId46", "rId47", "rId48", "rId49", "rId50", "rId51", "rId80"]
        window = ids[i : i + 7]
        if window != target:
            pieces = []
            last = 0
            for k, m in enumerate(embeds):
                pieces.append(document[last : m.start()])
                if i <= k <= i + 6:
                    pieces.append(f'r:embed="{target[k - i]}"')
                else:
                    pieces.append(document[m.start() : m.end()])
                last = m.end()
            pieces.append(document[last:])
            document = "".join(pieces)
            print("S3 order fixed:", window, "=>", target)
        else:
            print("S3 order OK:", window)

        names = set(zin.namelist())
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename in prepared:
                data = prepared[item.filename]
            elif item.filename == "word/_rels/document.xml.rels":
                data = rels.encode("utf-8")
            elif item.filename == "word/document.xml":
                data = document.encode("utf-8")
            zout.writestr(item, data)

        if "word/media/image52.png" not in names:
            zout.writestr("word/media/image52.png", prepared["word/media/image52.png"])
            print("added image52.png")
        elif "word/media/image52.png" in prepared:
            # already written above if existed in zin; if existed, replaced in loop
            pass

    shutil.copy2(tmp, DOC)
    print("DOC saved", DOC)


def main() -> None:
    n = fix_notes()
    print(f"notes/titles fixed: {n}")
    replace_images()
    print("DONE")


if __name__ == "__main__":
    main()

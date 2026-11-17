# -*- coding: utf-8 -*-
"""Reemplaza diagramas Sprint 3 y 4 (los que requieren actualización) y actualiza títulos/notas."""
from __future__ import annotations

import re
import shutil
import zipfile
from pathlib import Path

from docx import Document
from PIL import Image

DOC_CANDIDATES = [
    Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2 - implementaciones.docx"),
    Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx"),
]
ASSETS = Path(
    r"C:\Users\papu\.cursor\projects\c-laragon-www-gest-admin-ASOCIACI-N-AGUA-POTABLE-ALCANTARILLADO-ISINUTA\assets"
)
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_diag_s34")
OUT = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2 - diagramas.docx")

SRC_IMAGES = {
    26: ASSETS / "diag-s3-casos-uso.jpg",
    27: ASSETS / "diag-s3-clases.jpg",
    35: ASSETS / "diag-s4-casos-uso.jpg",
    36: ASSETS / "diag-s4-clases.jpg",
}

TEXT_REPLACEMENTS = [
    (
        "Figura 26: Diagrama de casos de uso del Sprint 3.",
        "Figura 26: Diagrama de casos de uso del Sprint 3.",
    ),
    (
        "Nota: El diagrama muestra los casos de uso del Sprint 3: trabajos de los afiliados por OTB, pagos y multas.",
        "Nota: El diagrama muestra los casos de uso del Sprint 3: trabajos por OTB, pagos con recibos de ingreso/caja, multas por mora, corte por lote, estado de cuenta y transferencia.",
    ),
    (
        "Figura 27: Diagrama de clases del Sprint 3.",
        "Figura 27: Diagrama de clases del Sprint 3.",
    ),
    (
        "Nota: El diagrama muestra las clases del Sprint 3: Trabajo, Asistencia, Multa, Pago y Pago Detalle.",
        "Nota: El diagrama muestra las clases del Sprint 3: Trabajo, Asistencia, Multa, Cuota, Pago, Pago Detalle y Lote Afiliado (corte/reactivación por lote).",
    ),
    (
        "Figura 35: Diagrama de casos de uso del Sprint 4.",
        "Figura 35: Diagrama de casos de uso del Sprint 4.",
    ),
    (
        "Nota: El diagrama muestra los casos de uso del Sprint 4: informe de pagos por concepto y reporte de deudas.",
        "Nota: El diagrama muestra los casos de uso del Sprint 4: reporte de ingresos, reporte de deudas, filtros, exportación a Excel e impresión.",
    ),
    (
        "Figura 36: Diagrama de clases del Sprint 4.",
        "Figura 36: Diagrama de clases del Sprint 4.",
    ),
    (
        "Nota: El diagrama muestra las clases del Sprint 4: Reporte Administrativo, Pago, Pago Detalle, Multa, Afiliado y Lote Afiliado.",
        "Nota: El diagrama muestra las clases del Sprint 4: Reporte de Ingresos, Reporte de Deudas, Pago, Pago Detalle, Multa, Afiliado y Lote Afiliado.",
    ),
    (
        "informe de pagos por concepto y reporte de deudas",
        "reporte de ingresos y reporte de deudas",
    ),
]


def map_fig_media(doc_path: Path) -> dict[int, str]:
    z = zipfile.ZipFile(doc_path)
    xml = z.read("word/document.xml").decode("utf-8")
    rels = z.read("word/_rels/document.xml.rels").decode("utf-8")
    idmap = dict(re.findall(r'Id="(rId\d+)"[^>]*Target="([^"]+)"', rels))

    xml2 = re.sub(r'r:embed="(rId\d+)"', r">[[[EMBED:\1]]]<", xml)
    paras = re.split(r"</w:p>", xml2)
    fig_re = re.compile(r"Figura(?:\s+SEQ\s+Figura\s+\\\*\s+ARABIC)?\s+(\d+)\s*:", re.I)

    last = None
    found: dict[int, str] = {}
    wanted = {26, 27, 35, 36}
    for para in paras:
        plain = re.sub(r"<[^>]+>", "", para)
        embeds = re.findall(r"\[\[\[EMBED:(rId\d+)\]\]\]", plain)
        if "PAGEREF" in plain:
            continue
        for e in embeds:
            last = e
        m = fig_re.search(plain)
        if m and last:
            fig = int(m.group(1))
            if fig in wanted:
                found[fig] = idmap[last]
    return found


def paragraph_text(p) -> str:
    return "".join(r.text for r in p.runs)


def set_text(p, new: str) -> None:
    if not p.runs:
        p.add_run(new)
        return
    p.runs[0].text = new
    for r in p.runs[1:]:
        r.text = ""


def main() -> None:
    src = next((p for p in DOC_CANDIDATES if p.exists()), None)
    if not src:
        raise SystemExit("No se encontró el documento base")

    media_map = map_fig_media(src)
    print("media_map", media_map)
    for fig in (26, 27, 35, 36):
        if fig not in media_map:
            raise SystemExit(f"No se halló media para Figura {fig}")
        if not SRC_IMAGES[fig].exists():
            raise SystemExit(f"Falta imagen generada: {SRC_IMAGES[fig]}")

    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir(parents=True)

    prepared: dict[str, bytes] = {}
    for fig, media in media_map.items():
        # media path like media/image28.png -> word/media/image28.png
        key = media if media.startswith("word/") else f"word/{media}"
        png = WORK / f"fig{fig}.png"
        Image.open(SRC_IMAGES[fig]).convert("RGB").save(png, format="PNG", optimize=True)
        prepared[key] = png.read_bytes()
        print(f"FIG {fig} <- {SRC_IMAGES[fig].name} -> {key}")

    tmp = WORK / "out.docx"
    with zipfile.ZipFile(src, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = prepared[item.filename] if item.filename in prepared else zin.read(item.filename)
            zout.writestr(item, data)

    shutil.copy2(tmp, OUT)

    # Actualizar notas/títulos en la copia de salida
    doc = Document(str(OUT))
    changed = 0
    for p in doc.paragraphs:
        t = paragraph_text(p)
        for old, new in TEXT_REPLACEMENTS:
            if old == new:
                continue
            if old in t:
                set_text(p, t.replace(old, new))
                changed += 1
                break
    doc.save(str(OUT))
    print(f"OK -> {OUT} (notas={changed})")


if __name__ == "__main__":
    main()

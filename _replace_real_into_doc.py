"""Inserta capturas REALES del sistema en cada figura de implementación."""
from __future__ import annotations

import re
import shutil
import zipfile
from pathlib import Path

from PIL import Image

DOC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
CAP = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_real_captures")
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_real_work")

# Figura -> (media en docx, archivo captura real)
MAP = {
    15: ("word/media/image17.png", "fig15-login.png"),
    16: ("word/media/image18.png", "fig16-usuarios-create.png"),
    17: ("word/media/image19.png", "fig17-usuarios.png"),
    18: ("word/media/image20.png", "fig18-roles.png"),
    19: ("word/media/image21.png", "fig19-afiliados.png"),
    20: ("word/media/image22.png", "fig20-afiliaciones.png"),
    23: ("word/media/image25.png", "fig23-categorias.png"),
    24: ("word/media/image26.png", "fig24-servicio-edit.png"),
    25: ("word/media/image27.png", "fig25-otb.png"),
    28: ("word/media/image30.png", "fig28-pagos-create.png"),
    29: ("word/media/image31.png", "fig29-trabajos.png"),
    30: ("word/media/image32.png", "fig30-trabajos-lista.png"),
    31: ("word/media/image33.png", "fig31-pagos-create.png"),
    32: ("word/media/image34.png", "fig32-recibo.png"),
    33: ("word/media/image35.png", "fig33-multas.png"),
    34: ("word/media/image52.png", "fig34-estado-cuenta.png"),
    37: ("word/media/image38.png", "fig37-ingresos.png"),
    38: ("word/media/image39.png", "fig38-deudas.png"),
}


def main() -> None:
    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir(parents=True)

    prepared: dict[str, bytes] = {}
    for fig, (media, file) in MAP.items():
        src = CAP / file
        if not src.exists():
            raise FileNotFoundError(f"Falta captura real Figura {fig}: {src}")
        png = WORK / f"fig{fig}.png"
        Image.open(src).convert("RGB").save(png, format="PNG", optimize=True)
        prepared[media] = png.read_bytes()
        print(f"FIG {fig:02d} <- {file} -> {media}")

    tmp = WORK / "out.docx"
    with zipfile.ZipFile(DOC, "r") as zin, zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as zout:
        rels = zin.read("word/_rels/document.xml.rels").decode("utf-8")
        document = zin.read("word/document.xml").decode("utf-8")

        if "image52.png" not in rels:
            rels = rels.replace(
                "</Relationships>",
                '<Relationship Id="rId80" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image52.png"/></Relationships>',
            )

        # Orden Sprint 3 fijo
        embeds = list(re.finditer(r'r:embed="(rId\d+)"', document))
        ids = [m.group(1) for m in embeds]
        i = ids.index("rId46")
        target = ["rId46", "rId47", "rId48", "rId49", "rId50", "rId51", "rId80"]
        if ids[i : i + 7] != target:
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
            print("S3 order fixed")
        else:
            print("S3 order OK")

        existing = set(zin.namelist())
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename in prepared:
                data = prepared[item.filename]
            elif item.filename == "word/_rels/document.xml.rels":
                data = rels.encode("utf-8")
            elif item.filename == "word/document.xml":
                data = document.encode("utf-8")
            zout.writestr(item, data)

        if "word/media/image52.png" not in existing:
            zout.writestr("word/media/image52.png", prepared["word/media/image52.png"])
            print("added image52")

    shutil.copy2(tmp, DOC)
    print("SAVED", DOC)


if __name__ == "__main__":
    main()

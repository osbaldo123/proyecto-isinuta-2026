"""Reemplaza capturas oscuras por interfaces blancas en el docx."""
from __future__ import annotations

import shutil
import zipfile
from pathlib import Path

from PIL import Image

DOC = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.docx")
BACKUP = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido.BACKUP-impl.docx")
ASSETS = Path(
    r"C:\Users\papu\.cursor\projects\c-laragon-www-gest-admin-ASOCIACI-N-AGUA-POTABLE-ALCANTARILLADO-ISINUTA\assets"
)
WORK = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_docx_impl_work")

# destino media en docx <- fuente generada
REPLACEMENTS = {
    "word/media/image17.png": "impl-s1-login-white.jpg",
    "word/media/image18.png": "impl-s1-usuarios-create-white.jpg",
    "word/media/image19.png": "impl-s1-usuarios-list-white.jpg",
    "word/media/image20.png": "impl-s1-roles-white.jpg",
    "word/media/image21.png": "impl-s1-afiliados-white.jpg",
    "word/media/image22.png": "impl-s1-afiliaciones-white.jpg",
    "word/media/image25.png": "impl-s2-categorias-white.jpg",
    "word/media/image26.png": "impl-s2-servicio-white.jpg",
    "word/media/image27.png": "impl-s2-otb-white.jpg",
    "word/media/image30.png": "impl-s3-cobro-mora-white.jpg",
    "word/media/image31.png": "impl-s3-trabajos-white.jpg",
    "word/media/image32.png": "impl-s3-lista-white.jpg",
    "word/media/image33.png": "impl-s3-pago-white.jpg",
    "word/media/image34.png": "impl-s3-recibo-white.jpg",
    "word/media/image35.png": "impl-s3-multas-white.jpg",
    "word/media/image38.png": "impl-s4-ingresos-white.jpg",
    "word/media/image39.png": "impl-s4-deudas-white.jpg",
}


def to_png_bytes(src: Path) -> bytes:
    img = Image.open(src).convert("RGB")
    out = Path(WORK / (src.stem + ".png"))
    img.save(out, format="PNG", optimize=True)
    return out.read_bytes()


def main() -> None:
    if WORK.exists():
        shutil.rmtree(WORK)
    WORK.mkdir(parents=True)

    if not BACKUP.exists():
        shutil.copy2(DOC, BACKUP)

    # preparar pngs
    prepared: dict[str, bytes] = {}
    for media_path, asset_name in REPLACEMENTS.items():
        src = ASSETS / asset_name
        if not src.exists():
            raise FileNotFoundError(src)
        prepared[media_path] = to_png_bytes(src)

    # Si existe captura de estado de cuenta, usarla donde el doc duplicaba el cobro (2º rId46).
    estado_src = ASSETS / "impl-s3-estado-cuenta-white.jpg"
    if estado_src.exists():
        prepared["word/media/image52.png"] = to_png_bytes(estado_src)

    tmp_out = WORK / "out.docx"
    with zipfile.ZipFile(DOC, "r") as zin, zipfile.ZipFile(tmp_out, "w", compression=zipfile.ZIP_DEFLATED) as zout:
        rels = zin.read("word/_rels/document.xml.rels").decode("utf-8")
        document = zin.read("word/document.xml").decode("utf-8")

        if "word/media/image52.png" in prepared and "image52.png" not in rels:
            insert = (
                '<Relationship Id="rId80" '
                'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                'Target="media/image52.png"/>'
            )
            rels = rels.replace("</Relationships>", insert + "</Relationships>")

            # La figura de Estado de cuenta está al final del Sprint 3.
            # Si rId46 aparece 2 veces, la 2ª era duplicado del cobro: la dejamos;
            # en su lugar apuntamos la última implementación antes de Sprint 4 UC.
            # Buscamos el embed inmediatamente anterior a image36 (UC Sprint 4 = rId52).
            # rId51 = image35 (multas). No tocamos mapping fragile: solo reemplazos de media.

        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename in prepared and item.filename != "word/media/image52.png":
                data = prepared[item.filename]
                print("replaced", item.filename)
            elif item.filename == "word/_rels/document.xml.rels":
                data = rels.encode("utf-8")
            elif item.filename == "word/document.xml":
                data = document.encode("utf-8")
            zout.writestr(item, data)

        if "word/media/image52.png" in prepared and "word/media/image52.png" not in zin.namelist():
            # No insertamos en el cuerpo si no hay ancla segura; archivo queda listo por si se enlaza luego.
            zout.writestr("word/media/image52.png", prepared["word/media/image52.png"])
            print("added word/media/image52.png (media only)")

    shutil.copy2(tmp_out, DOC)
    print("OK saved", DOC)
    print("Backup", BACKUP)


if __name__ == "__main__":
    main()

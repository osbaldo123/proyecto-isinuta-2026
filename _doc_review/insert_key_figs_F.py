# -*- coding: utf-8 -*-
"""Reemplaza Fig 28, 32, 37, 38 en PROYECTO MERCADO -F.docx con capturas reales."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import shutil
import tempfile

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_figs_key.docx")
caps = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_real_captures")

REPL = {
    "word/media/image30.png": caps / "fig28-pagos-create.png",
    "word/media/image34.png": caps / "fig32-recibo.png",
    "word/media/image39.png": caps / "fig37-ingresos.png",
    "word/media/image40.png": caps / "fig38-deudas.png",
}

for media, png in REPL.items():
    if not png.exists():
        raise SystemExit(f"Falta captura: {png}")
    print(f"OK source {png.name} ({png.stat().st_size} bytes) -> {media}")

shutil.copy2(src, backup)

tmp = Path(tempfile.gettempdir()) / "mercado_F_figs_key.docx"
with ZipFile(src, "r") as zin, ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for info in zin.infolist():
        data = zin.read(info.filename)
        if info.filename in REPL:
            data = REPL[info.filename].read_bytes()
            print(f"replaced {info.filename} ({len(data)} bytes)")
        zout.writestr(info, data)

shutil.copy2(tmp, src)
print("saved", src)
print("backup", backup)

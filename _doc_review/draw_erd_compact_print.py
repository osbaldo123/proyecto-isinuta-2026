# -*- coding: utf-8 -*-
"""
ERD ISINUTA estilo Workbench (igual a la captura), layout denso para imprimir.
Empaqueta tablas en columnas sin huecos grandes.
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import json
import math

ROOT = Path(__file__).resolve().parent
SCHEMA = json.loads((ROOT / "_schema_erd.json").read_text(encoding="utf-8"))

OUT = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA_impresion.png")
OUT2 = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA.png")

# Columnas visuales: tablas de arriba abajo (aprovecha alto)
# columnas equilibradas en altura (~850–950 px) para no dejar huecos
COLUMNS = [
    [
        "servicios",
        "tipos_afiliacion",
        "servicio_categoria_tarifas",
        "lote_servicio",
        "lote_tipo_afiliacion",
    ],
    [
        "categorias",
        "lote_afiliados",
        "cuotas",
        "recibo_sequences",
    ],
    [
        "afiliados",
        "otbs",
        "cesiones_lote",
        "pagos",
    ],
    [
        "trabajos",
        "asistencias",
        "multas",
        "pago_detalles",
        "configuracion",
    ],
    [
        "users",
        "activity_logs",
        "roles",
        "permissions",
        "role_user",
        "permission_role",
    ],
]

EDGES = [
    ("servicio_categoria_tarifas", "servicios"),
    ("servicio_categoria_tarifas", "categorias"),
    ("lote_servicio", "servicios"),
    ("lote_servicio", "lote_afiliados"),
    ("lote_tipo_afiliacion", "tipos_afiliacion"),
    ("lote_tipo_afiliacion", "lote_afiliados"),
    ("lote_afiliados", "afiliados"),
    ("lote_afiliados", "otbs"),
    ("lote_afiliados", "categorias"),
    ("cesiones_lote", "lote_afiliados"),
    ("trabajos", "otbs"),
    ("asistencias", "trabajos"),
    ("asistencias", "afiliados"),
    ("cuotas", "lote_afiliados"),
    ("cuotas", "servicios"),
    ("pagos", "afiliados"),
    ("pagos", "lote_afiliados"),
    ("pago_detalles", "pagos"),
    ("pago_detalles", "cuotas"),
    ("pago_detalles", "multas"),
    ("multas", "afiliados"),
    ("multas", "lote_afiliados"),
    ("multas", "trabajos"),
    ("users", "afiliados"),
    ("activity_logs", "users"),
    ("role_user", "users"),
    ("role_user", "roles"),
    ("permission_role", "roles"),
    ("permission_role", "permissions"),
]

MARGIN = 24
GAP_X = 18
GAP_Y = 12
ROW_H = 16
TITLE_H = 20
PAD = 5


def font(size, bold=False):
    for p in (
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ):
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


F_TITLE = font(11, True)
F_COL = font(9)
F_TYPE = font(8)
F_HEAD = font(16, True)
F_NOTE = font(10)


def measure(name, cols):
    max_w = int(F_TITLE.getlength(name) + 14)
    for c, t, _ in cols:
        max_w = max(max_w, int(F_COL.getlength(c) + F_TYPE.getlength(t) + 42))
    max_w = min(max(max_w, 140), 260)
    th = TITLE_H + 3 + ROW_H * len(cols) + 3
    return max_w, th


def draw_key(draw, x, y, kind):
    if kind == "PK":
        draw.ellipse([x, y + 1, x + 8, y + 9], fill=(230, 180, 40), outline=(80, 60, 10))
        draw.rectangle([x + 6, y + 3, x + 13, y + 6], fill=(230, 180, 40), outline=(80, 60, 10))
    else:
        cx, cy = x + 5, y + 5
        draw.polygon(
            [(cx, cy - 4), (cx + 4, cy), (cx, cy + 4), (cx - 4, cy)],
            fill=(200, 60, 60),
            outline=(100, 20, 20),
        )


def draw_table(draw, x, y, name, cols, tw, th):
    draw.rectangle([x + 2, y + 2, x + tw + 2, y + th + 2], fill=(195, 195, 200))
    draw.rectangle([x, y, x + tw, y + th], fill=(255, 255, 255), outline=(55, 85, 120), width=1)
    draw.rectangle([x, y, x + tw, y + TITLE_H], fill=(70, 130, 180))
    draw.text((x + PAD, y + 2), name, font=F_TITLE, fill=(255, 255, 255))
    cy = y + TITLE_H + 2
    for col, typ, flag in cols:
        if flag:
            draw_key(draw, x + 2, cy, flag)
        draw.text((x + 16, cy), col, font=F_COL, fill=(20, 20, 20))
        twt = F_TYPE.getlength(typ)
        draw.text((x + tw - PAD - twt, cy), typ, font=F_TYPE, fill=(100, 100, 110))
        cy += ROW_H
    return (x, y, tw, th)


def side_pt(box, toward):
    x, y, tw, th = box
    cx, cy = x + tw / 2, y + th / 2
    dx, dy = toward[0] - cx, toward[1] - cy
    if abs(dx) >= abs(dy):
        return (x + tw, cy) if dx > 0 else (x, cy)
    return (cx, y + th) if dy > 0 else (cx, y)


def draw_edge(draw, child, parent):
    cc = (child[0] + child[2] / 2, child[1] + child[3] / 2)
    pc = (parent[0] + parent[2] / 2, parent[1] + parent[3] / 2)
    a = side_pt(child, pc)
    b = side_pt(parent, cc)
    x0, y0 = a
    x1, y1 = b
    segs = 16
    for i in range(segs):
        if i % 2:
            continue
        t0, t1 = i / segs, min((i + 1) / segs, 1)
        draw.line(
            [
                (x0 + (x1 - x0) * t0, y0 + (y1 - y0) * t0),
                (x0 + (x1 - x0) * t1, y0 + (y1 - y0) * t1),
            ],
            fill=(90, 110, 140),
            width=1,
        )
    ang = math.atan2(b[1] - a[1], b[0] - a[0])
    ax, ay = math.cos(ang), math.sin(ang)
    px, py = -ay, ax
    for s in (-1.0, 0.0, 1.0):
        ox, oy = px * s * 3.5, py * s * 3.5
        draw.line(
            [(a[0] + ox, a[1] + oy), (a[0] + ax * 8 + ox * 0.2, a[1] + ay * 8 + oy * 0.2)],
            fill=(70, 90, 120),
            width=2,
        )
    ang2 = math.atan2(a[1] - b[1], a[0] - b[0])
    ax2, ay2 = math.cos(ang2), math.sin(ang2)
    px2, py2 = -ay2 * 5, ax2 * 5
    bx, by = b[0] + ax2 * 5, b[1] + ay2 * 5
    draw.line([(bx + px2, by + py2), (bx - px2, by - py2)], fill=(70, 90, 120), width=2)


def pack_columns(sizes):
    positions = {}
    col_widths = []
    col_heights = []
    for col in COLUMNS:
        present = [t for t in col if t in sizes]
        cw = max((sizes[t][0] for t in present), default=0)
        y = MARGIN + 34
        for t in present:
            tw, th = sizes[t]
            # centrar horizontalmente en la columna
            x_off = (cw - tw) // 2
            positions[t] = (0, y, tw, th, x_off, cw)  # x se fija después
            y += th + GAP_Y
        col_widths.append(cw)
        col_heights.append(y - GAP_Y)

    x = MARGIN
    final = {}
    for i, col in enumerate(COLUMNS):
        present = [t for t in col if t in sizes]
        for t in present:
            _y0, y, tw, th, x_off, _cw = positions[t][0], positions[t][1], positions[t][2], positions[t][3], positions[t][4], positions[t][5]
            final[t] = (x + x_off, y, tw, th)
        x += col_widths[i] + GAP_X

    W = x - GAP_X + MARGIN
    H = max(col_heights) + MARGIN
    return final, W, H


def main():
    sizes = {name: measure(name, cols) for name, cols in SCHEMA.items()}
    positions, W, H = pack_columns(sizes)

    img = Image.new("RGB", (W, H), (255, 255, 255))
    draw = ImageDraw.Draw(img)
    draw.text((MARGIN, 8), "Modelo de base de datos del sistema ISINUTA", font=F_HEAD, fill=(40, 60, 90))

    line = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ld = ImageDraw.Draw(line)
    for child, parent in EDGES:
        if child in positions and parent in positions:
            draw_edge(ld, positions[child], positions[parent])
    img = Image.alpha_composite(img.convert("RGBA"), line).convert("RGB")
    draw = ImageDraw.Draw(img)

    for name, (x, y, tw, th) in positions.items():
        draw_table(draw, x, y, name, SCHEMA[name], tw, th)

    draw.text(
        (MARGIN, H - 18),
        "Layout compacto para impresión  ·  Estilo Workbench  ·  Sin cache / sessions / jobs / migrations",
        font=F_NOTE,
        fill=(110, 110, 120),
    )

    for path in (OUT, OUT2):
        try:
            img.save(path, "PNG", optimize=True)
            print("OK", path, img.size)
        except OSError as e:
            alt = path.with_name(path.stem + "_v2.png")
            img.save(alt, "PNG", optimize=True)
            print("ALT", alt, e)


if __name__ == "__main__":
    main()

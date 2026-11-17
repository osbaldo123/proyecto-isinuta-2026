# -*- coding: utf-8 -*-
"""
ERD ISINUTA estilo MySQL Workbench (como la captura de referencia),
con tablas reales del sistema (sin cache/jobs/sessions/migrations/logs).
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import math

OUT = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA_Workbench.png")
OUT2 = Path(
    r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_doc_review\erd_isinuta.png"
)
OUT_ALT = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA.png")

# tabla -> [(col, tipo, flag)] flag: PK | FK | ""
# tipos reales / cercanos a MySQL del proyecto
TABLES = {
    "servicios": [
        ("id", "BIGINT", "PK"),
        ("codigo", "VARCHAR(20)", ""),
        ("nombre", "VARCHAR(100)", ""),
        ("tipo", "VARCHAR(50)", ""),
        ("tarifa_mensual", "DECIMAL(10,2)", ""),
        ("costo_instalacion_contado", "DECIMAL(10,2)", ""),
        ("costo_instalacion_plazos", "DECIMAL(10,2)", ""),
        ("activo", "TINYINT", ""),
    ],
    "tipos_afiliacion": [
        ("id", "BIGINT", "PK"),
        ("codigo", "VARCHAR(30)", ""),
        ("nombre", "VARCHAR(100)", ""),
        ("modalidad", "VARCHAR(30)", ""),
        ("monto", "DECIMAL(10,2)", ""),
        ("monto_plazo", "DECIMAL(10,2)", ""),
        ("activo", "TINYINT", ""),
    ],
    "categorias": [
        ("id", "BIGINT", "PK"),
        ("nombre", "VARCHAR(80)", ""),
        ("tarifa_agua", "DECIMAL(10,2)", ""),
        ("tarifa_alcantarillado", "DECIMAL(10,2)", ""),
        ("activo", "TINYINT", ""),
    ],
    "servicio_categoria_tarifas": [
        ("id", "BIGINT", "PK"),
        ("servicio_id", "BIGINT", "FK"),
        ("categoria_id", "BIGINT", "FK"),
        ("tarifa", "DECIMAL(10,2)", ""),
    ],
    "lote_servicio": [
        ("id", "BIGINT", "PK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("servicio_id", "BIGINT", "FK"),
        ("tarifa_acordada", "DECIMAL(10,2)", ""),
        ("fecha_inicio", "DATE", ""),
    ],
    "lote_tipo_afiliacion": [
        ("id", "BIGINT", "PK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("tipo_afiliacion_id", "BIGINT", "FK"),
        ("monto", "DECIMAL(10,2)", ""),
    ],
    "afiliados": [
        ("id", "BIGINT", "PK"),
        ("nombres", "VARCHAR(100)", ""),
        ("apellidos", "VARCHAR(100)", ""),
        ("ci", "VARCHAR(20)", ""),
        ("telefono", "VARCHAR(30)", ""),
        ("direccion", "VARCHAR(255)", ""),
        ("estado", "VARCHAR(20)", ""),
    ],
    "otbs": [
        ("id", "BIGINT", "PK"),
        ("nombre", "VARCHAR(120)", ""),
        ("descripcion", "TEXT", ""),
        ("activa", "TINYINT", ""),
    ],
    "lote_afiliados": [
        ("id", "BIGINT", "PK"),
        ("afiliado_id", "BIGINT", "FK"),
        ("numero_lote", "VARCHAR(30)", ""),
        ("otb_id", "BIGINT", "FK"),
        ("categoria_id", "BIGINT", "FK"),
        ("direccion", "VARCHAR(255)", ""),
        ("fecha_afiliacion", "DATE", ""),
        ("forma_pago_afiliacion", "VARCHAR(30)", ""),
        ("monto_afiliacion", "DECIMAL(10,2)", ""),
        ("saldo_afiliacion_agua", "DECIMAL(10,2)", ""),
        ("saldo_afiliacion_alcantarillado", "DECIMAL(10,2)", ""),
        ("estado", "VARCHAR(20)", ""),
    ],
    "cesiones_lote": [
        ("id", "BIGINT", "PK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("afiliado_anterior_id", "BIGINT", "FK"),
        ("afiliado_nuevo_id", "BIGINT", "FK"),
        ("fecha_cesion", "DATE", ""),
        ("motivo", "VARCHAR(255)", ""),
    ],
    "trabajos": [
        ("id", "BIGINT", "PK"),
        ("otb_id", "BIGINT", "FK"),
        ("tipo", "VARCHAR(50)", ""),
        ("descripcion", "TEXT", ""),
        ("fecha_inicio", "DATE", ""),
        ("monto_multa", "DECIMAL(10,2)", ""),
    ],
    "asistencias": [
        ("id", "BIGINT", "PK"),
        ("trabajo_id", "BIGINT", "FK"),
        ("afiliado_id", "BIGINT", "FK"),
        ("presente", "TINYINT", ""),
    ],
    "cuotas": [
        ("id", "BIGINT", "PK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("servicio_id", "BIGINT", "FK"),
        ("periodo", "VARCHAR(7)", ""),
        ("gestion", "INT", ""),
        ("monto_original", "DECIMAL(10,2)", ""),
        ("saldo", "DECIMAL(10,2)", ""),
        ("fecha_vencimiento", "DATE", ""),
        ("estado", "VARCHAR(20)", ""),
    ],
    "pagos": [
        ("id", "BIGINT", "PK"),
        ("numero_recibo", "VARCHAR(40)", ""),
        ("afiliado_id", "BIGINT", "FK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("fecha_pago", "DATE", ""),
        ("monto_total", "DECIMAL(10,2)", ""),
        ("metodo_pago", "VARCHAR(30)", ""),
        ("estado", "VARCHAR(20)", ""),
    ],
    "pago_detalles": [
        ("id", "BIGINT", "PK"),
        ("pago_id", "BIGINT", "FK"),
        ("concepto", "VARCHAR(80)", ""),
        ("monto", "DECIMAL(10,2)", ""),
        ("servicio_id", "BIGINT", "FK"),
        ("multa_id", "BIGINT", "FK"),
        ("cuota_id", "BIGINT", "FK"),
        ("periodo", "VARCHAR(7)", ""),
    ],
    "multas": [
        ("id", "BIGINT", "PK"),
        ("afiliado_id", "BIGINT", "FK"),
        ("lote_afiliado_id", "BIGINT", "FK"),
        ("trabajo_id", "BIGINT", "FK"),
        ("fecha_generacion", "DATE", ""),
        ("motivo", "VARCHAR(100)", ""),
        ("gestion", "INT", ""),
        ("cuotas_vencidas", "INT", ""),
        ("monto", "DECIMAL(10,2)", ""),
        ("saldo", "DECIMAL(10,2)", ""),
        ("estado", "VARCHAR(20)", ""),
    ],
    "users": [
        ("id", "BIGINT", "PK"),
        ("name", "VARCHAR(100)", ""),
        ("email", "VARCHAR(150)", ""),
        ("ci", "VARCHAR(20)", ""),
        ("password", "VARCHAR(255)", ""),
        ("is_active", "TINYINT", ""),
        ("afiliado_id", "BIGINT", "FK"),
    ],
    "roles": [
        ("id", "BIGINT", "PK"),
        ("nombre", "VARCHAR(80)", ""),
        ("descripcion", "VARCHAR(255)", ""),
        ("activo", "TINYINT", ""),
    ],
    "permissions": [
        ("id", "BIGINT", "PK"),
        ("nombre", "VARCHAR(100)", ""),
        ("descripcion", "VARCHAR(255)", ""),
        ("grupo", "VARCHAR(80)", ""),
    ],
    "role_user": [
        ("id", "BIGINT", "PK"),
        ("role_id", "BIGINT", "FK"),
        ("user_id", "BIGINT", "FK"),
    ],
    "permission_role": [
        ("id", "BIGINT", "PK"),
        ("permission_id", "BIGINT", "FK"),
        ("role_id", "BIGINT", "FK"),
    ],
    "configuracion": [
        ("id", "BIGINT", "PK"),
        ("clave", "VARCHAR(100)", ""),
        ("valor", "TEXT", ""),
        ("tipo", "VARCHAR(30)", ""),
        ("descripcion", "VARCHAR(255)", ""),
    ],
}

# Layout estilo Workbench: módulos con rectangulos grandes
# (title, bg, border, rect x,y,w,h, tables[(name,x,y)])
MODULES = [
    (
        "Servicios, tarifas y afiliación",
        (255, 228, 196),
        (210, 140, 50),
        (30, 70, 1120, 900),
        [
            ("servicios", 55, 130),
            ("tipos_afiliacion", 400, 130),
            ("categorias", 760, 130),
            ("servicio_categoria_tarifas", 55, 470),
            ("lote_servicio", 430, 560),
            ("lote_tipo_afiliacion", 760, 560),
        ],
    ),
    (
        "Afiliados y lotes",
        (210, 240, 210),
        (55, 140, 70),
        (1190, 70, 1180, 900),
        [
            ("afiliados", 1220, 130),
            ("otbs", 1680, 130),
            ("lote_afiliados", 1380, 420),
            ("cesiones_lote", 1900, 700),
        ],
    ),
    (
        "Trabajos comunitarios",
        (230, 210, 245),
        (130, 80, 170),
        (2420, 70, 640, 620),
        [
            ("trabajos", 2460, 130),
            ("asistencias", 2460, 400),
        ],
    ),
    (
        "Cobranza: cuotas, pagos y multas",
        (255, 210, 210),
        (190, 55, 55),
        (30, 1010, 1680, 980),
        [
            ("cuotas", 60, 1080),
            ("pagos", 430, 1080),
            ("pago_detalles", 820, 1080),
            ("multas", 1240, 1080),
        ],
    ),
    (
        "Seguridad y acceso",
        (200, 220, 250),
        (50, 100, 180),
        (1760, 1010, 900, 980),
        [
            ("users", 1800, 1080),
            ("roles", 2220, 1080),
            ("permissions", 1800, 1420),
            ("role_user", 2220, 1420),
            ("permission_role", 2000, 1720),
        ],
    ),
    (
        "Parámetros del sistema",
        (225, 225, 225),
        (110, 110, 110),
        (2420, 740, 640, 230),
        [
            ("configuracion", 2460, 800),
        ],
    ),
]

# (child, parent) 1:N  child.FK -> parent.PK
EDGES = [
    ("lote_afiliados", "afiliados"),
    ("lote_afiliados", "otbs"),
    ("lote_afiliados", "categorias"),
    ("lote_servicio", "lote_afiliados"),
    ("lote_servicio", "servicios"),
    ("lote_tipo_afiliacion", "lote_afiliados"),
    ("lote_tipo_afiliacion", "tipos_afiliacion"),
    ("servicio_categoria_tarifas", "servicios"),
    ("servicio_categoria_tarifas", "categorias"),
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
    ("role_user", "users"),
    ("role_user", "roles"),
    ("permission_role", "permissions"),
    ("permission_role", "roles"),
]

W, H = 3120, 2060


def font(size, bold=False):
    paths = [
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ]
    for p in paths:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def table_size(cols, f_name, f_type):
    row_h = 20
    title_h = 26
    pad = 8
    max_w = 160
    for name, typ, _ in cols:
        max_w = max(max_w, int(f_name.getlength(name) + f_type.getlength(typ) + 56))
    return max_w, title_h + pad + row_h * len(cols) + 6, title_h, row_h, pad


def draw_key(draw, x, y, kind):
    # icono llave simple
    if kind == "PK":
        fill = (218, 165, 32)  # dorado
    else:
        fill = (70, 130, 200)  # azul FK
    draw.ellipse([x, y + 2, x + 10, y + 12], fill=fill, outline=(40, 40, 40))
    draw.rectangle([x + 8, y + 5, x + 16, y + 9], fill=fill, outline=(40, 40, 40))
    draw.line([(x + 12, y + 9), (x + 12, y + 13)], fill=(40, 40, 40), width=1)
    draw.line([(x + 15, y + 9), (x + 15, y + 12)], fill=(40, 40, 40), width=1)


def draw_table(draw, x, y, name, cols, f_title, f_col, f_type):
    tw, th, title_h, row_h, pad = table_size(cols, f_col, f_type)
    # sombra
    draw.rectangle([x + 4, y + 4, x + tw + 4, y + th + 4], fill=(160, 160, 160))
    # marco
    draw.rectangle([x, y, x + tw, y + th], fill=(255, 255, 255), outline=(40, 40, 40), width=2)
    # cabecera oscura Workbench
    draw.rectangle([x, y, x + tw, y + title_h], fill=(55, 65, 80))
    draw.text((x + pad, y + 4), name, font=f_title, fill=(255, 255, 255))
    # columnas
    cy = y + title_h + 4
    for col, typ, flag in cols:
        if flag:
            draw_key(draw, x + 4, cy, flag)
        draw.text((x + 22, cy), col, font=f_col, fill=(25, 25, 25))
        # tipo alineado a la derecha
        tw_type = f_type.getlength(typ)
        draw.text((x + tw - pad - tw_type, cy), typ, font=f_type, fill=(90, 90, 100))
        cy += row_h
        # línea sutil
        if cy < y + th - 2:
            draw.line([(x + 1, cy - 1), (x + tw - 1, cy - 1)], fill=(230, 230, 235), width=1)
    return (x, y, tw, th)


def side_point(box, toward):
    x, y, tw, th = box
    cx, cy = x + tw / 2, y + th / 2
    tx, ty = toward
    dx, dy = tx - cx, ty - cy
    if abs(dx) > abs(dy):
        if dx > 0:
            return x + tw, cy
        return x, cy
    if dy > 0:
        return cx, y + th
    return cx, y


def draw_crows_foot(draw, x, y, angle, many=True):
    # pie de cuervo en el extremo "many" (child)
    size = 10
    ax = math.cos(angle)
    ay = math.sin(angle)
    px, py = -ay, ax
    # tres líneas
    for s in (-1, 0, 1):
        ox = px * s * 5
        oy = py * s * 5
        draw.line(
            [(x + ox, y + oy), (x - ax * size + ox * 0.3, y - ay * size + oy * 0.3)],
            fill=(60, 60, 80),
            width=2,
        )
    # barra de "uno" en el otro extremo se dibuja aparte


def draw_one_bar(draw, x, y, angle):
    ax = math.cos(angle)
    ay = math.sin(angle)
    px, py = -ay * 7, ax * 7
    bx, by = x + ax * 8, y + ay * 8
    draw.line([(bx + px, by + py), (bx - px, by - py)], fill=(60, 60, 80), width=2)


def draw_edge(draw, child_box, parent_box):
    c_center = (child_box[0] + child_box[2] / 2, child_box[1] + child_box[3] / 2)
    p_center = (parent_box[0] + parent_box[2] / 2, parent_box[1] + parent_box[3] / 2)
    c_pt = side_point(child_box, p_center)
    p_pt = side_point(parent_box, c_center)
    # línea
    draw.line([c_pt, p_pt], fill=(70, 70, 95), width=2)
    # crow's foot en child (many)
    ang = math.atan2(p_pt[1] - c_pt[1], p_pt[0] - c_pt[0])
    draw_crows_foot(draw, c_pt[0], c_pt[1], ang)
    # barra uno en parent
    ang2 = math.atan2(c_pt[1] - p_pt[1], c_pt[0] - p_pt[0])
    draw_one_bar(draw, p_pt[0], p_pt[1], ang2)


def main():
    img = Image.new("RGB", (W, H), (245, 246, 248))
    draw = ImageDraw.Draw(img)
    f_head = font(26, True)
    f_mod = font(18, True)
    f_title = font(14, True)
    f_col = font(12)
    f_type = font(11)
    f_note = font(13)

    draw.text((30, 18), "db_agua_potable_isinuta — Diagrama EER (tablas de negocio)", font=f_head, fill=(35, 45, 60))

    # módulos
    for title, bg, border, rect, _tables in MODULES:
        x, y, w, h = rect
        draw.rounded_rectangle([x, y, x + w, y + h], radius=16, fill=bg, outline=border, width=3)
        # etiqueta módulo
        tw = int(f_mod.getlength(title) + 20)
        draw.rounded_rectangle([x + 12, y + 10, x + 12 + tw, y + 38], radius=8, fill=(255, 255, 255), outline=border, width=2)
        draw.text((x + 22, y + 14), title, font=f_mod, fill=border)

    # primeras posiciones de tablas (para edges)
    boxes = {}
    for _t, _bg, _b, _r, tables in MODULES:
        for name, tx, ty in tables:
            boxes[name] = draw_table(draw, tx, ty, name, TABLES[name], f_title, f_col, f_type)

    # relaciones debajo
    # crear capa de líneas: redibujar módulos limpios es difícil; dibujar lines then tables again
    line_layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ld = ImageDraw.Draw(line_layer)
    for child, parent in EDGES:
        if child in boxes and parent in boxes:
            draw_edge(ld, boxes[child], boxes[parent])
    img = Image.alpha_composite(img.convert("RGBA"), line_layer).convert("RGB")
    draw = ImageDraw.Draw(img)

    # tablas encima
    for _t, _bg, _b, _r, tables in MODULES:
        for name, tx, ty in tables:
            boxes[name] = draw_table(draw, tx, ty, name, TABLES[name], f_title, f_col, f_type)

    draw.text(
        (30, H - 32),
        "Sin cache, sessions, jobs, migrations, activity_logs ni recibo_sequences.  ·  Notación pie de cuervo (1:N)",
        font=f_note,
        fill=(100, 100, 110),
    )

    for path in (OUT, OUT2, OUT_ALT):
        try:
            img.save(path, "PNG", optimize=True)
            print("OK", path)
        except OSError as e:
            print("FAIL", path, e)


if __name__ == "__main__":
    main()

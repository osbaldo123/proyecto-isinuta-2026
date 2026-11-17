# -*- coding: utf-8 -*-
"""ERD ISINUTA estilo Workbench: módulos pintados, sin cache/jobs/sessions."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

OUT = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA.png")
OUT2 = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_doc_review\erd_isinuta.png")

# columnas visibles (sin timestamps ni campos técnicos de relleno)
TABLES = {
    "servicios": ["id PK", "codigo", "nombre", "tipo", "tarifa_mensual", "activo"],
    "tipos_afiliacion": ["id PK", "codigo", "nombre", "monto", "monto_plazo", "activo"],
    "categorias": ["id PK", "nombre", "tarifa_agua", "tarifa_alcantarillado", "activo"],
    "servicio_categoria_tarifas": ["id PK", "servicio_id FK", "categoria_id FK", "tarifa"],
    "lote_servicio": ["id PK", "lote_afiliado_id FK", "servicio_id FK", "tarifa_acordada"],
    "lote_tipo_afiliacion": ["id PK", "lote_afiliado_id FK", "tipo_afiliacion_id FK", "monto"],
    "afiliados": ["id PK", "nombres", "apellidos", "ci", "telefono", "direccion", "estado"],
    "otbs": ["id PK", "nombre", "descripcion", "activa"],
    "lote_afiliados": [
        "id PK", "afiliado_id FK", "numero_lote", "otb_id FK", "categoria_id FK",
        "fecha_afiliacion", "forma_pago_afiliacion", "monto_afiliacion",
        "saldo_afiliacion_agua", "saldo_afiliacion_alcantarillado", "estado",
    ],
    "cesiones_lote": [
        "id PK", "lote_afiliado_id FK", "afiliado_anterior_id FK",
        "afiliado_nuevo_id FK", "fecha_cesion", "motivo",
    ],
    "trabajos": ["id PK", "otb_id FK", "tipo", "descripcion", "fecha_inicio", "monto_multa"],
    "asistencias": ["id PK", "trabajo_id FK", "afiliado_id FK", "presente"],
    "cuotas": [
        "id PK", "lote_afiliado_id FK", "servicio_id FK", "periodo", "gestion",
        "monto_original", "saldo", "fecha_vencimiento", "estado",
    ],
    "pagos": [
        "id PK", "numero_recibo", "afiliado_id FK", "lote_afiliado_id FK",
        "fecha_pago", "monto_total", "metodo_pago", "estado",
    ],
    "pago_detalles": [
        "id PK", "pago_id FK", "concepto", "monto", "servicio_id FK",
        "multa_id FK", "cuota_id FK", "periodo",
    ],
    "multas": [
        "id PK", "afiliado_id FK", "lote_afiliado_id FK", "trabajo_id FK",
        "fecha_generacion", "motivo", "gestion", "cuotas_vencidas",
        "monto", "saldo", "estado",
    ],
    "users": ["id PK", "name", "email", "ci", "password", "is_active", "afiliado_id FK"],
    "roles": ["id PK", "nombre", "descripcion", "activo"],
    "permissions": ["id PK", "nombre", "descripcion", "grupo"],
    "role_user": ["id PK", "role_id FK", "user_id FK"],
    "permission_role": ["id PK", "permission_id FK", "role_id FK"],
    "configuracion": ["id PK", "clave", "valor", "tipo", "descripcion"],
}

# módulos: título, color fondo, color borde título, tablas [(name, x, y)]
# lienzo grande estilo Workbench
W, H = 3200, 2200

MODULES = [
    {
        "title": "Servicios, tarifas y afiliación",
        "bg": (255, 236, 210),
        "border": (230, 150, 60),
        "rect": (40, 40, 1180, 980),
        "tables": [
            ("servicios", 70, 100),
            ("tipos_afiliacion", 420, 100),
            ("categorias", 770, 100),
            ("servicio_categoria_tarifas", 70, 420),
            ("lote_servicio", 480, 520),
            ("lote_tipo_afiliacion", 820, 520),
        ],
    },
    {
        "title": "Afiliados y lotes",
        "bg": (220, 245, 220),
        "border": (70, 160, 80),
        "rect": (1260, 40, 1200, 980),
        "tables": [
            ("afiliados", 1300, 100),
            ("otbs", 1750, 100),
            ("lote_afiliados", 1450, 400),
            ("cesiones_lote", 1950, 700),
        ],
    },
    {
        "title": "Trabajos comunitarios",
        "bg": (235, 220, 250),
        "border": (140, 90, 180),
        "rect": (2500, 40, 660, 700),
        "tables": [
            ("trabajos", 2550, 100),
            ("asistencias", 2550, 400),
        ],
    },
    {
        "title": "Cobranza: cuotas, pagos y multas",
        "bg": (255, 220, 220),
        "border": (200, 70, 70),
        "rect": (40, 1060, 1600, 1080),
        "tables": [
            ("cuotas", 80, 1140),
            ("pagos", 480, 1140),
            ("pago_detalles", 880, 1140),
            ("multas", 1280, 1140),
        ],
    },
    {
        "title": "Seguridad y acceso",
        "bg": (210, 230, 255),
        "border": (60, 110, 190),
        "rect": (1700, 1060, 900, 1080),
        "tables": [
            ("users", 1750, 1140),
            ("roles", 2150, 1140),
            ("permissions", 1750, 1500),
            ("role_user", 2150, 1500),
            ("permission_role", 1950, 1780),
        ],
    },
    {
        "title": "Parámetros del sistema",
        "bg": (230, 230, 230),
        "border": (120, 120, 120),
        "rect": (2660, 800, 500, 420),
        "tables": [
            ("configuracion", 2700, 870),
        ],
    },
]

# relaciones (from_table, to_table) — líneas simples
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
    ("permission_role", "roles"),
    ("permission_role", "permissions"),
]


def font(size, bold=False):
    candidates = [
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
        r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\calibri.ttf",
    ]
    for p in candidates:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def measure_table(name, cols, f_title, f_col):
    pad_x, pad_y, row_h = 12, 8, 22
    title_h = 28
    widths = [f_title.getlength(name)] + [f_col.getlength(c) for c in cols]
    tw = int(max(widths) + pad_x * 2 + 8)
    th = title_h + pad_y + row_h * len(cols) + pad_y
    return tw, th, title_h, row_h, pad_x, pad_y


def draw_table(draw, x, y, name, cols, f_title, f_col):
    tw, th, title_h, row_h, pad_x, pad_y = measure_table(name, cols, f_title, f_col)
    # sombra
    draw.rounded_rectangle([x + 3, y + 3, x + tw + 3, y + th + 3], radius=6, fill=(180, 180, 180))
    # cuerpo
    draw.rounded_rectangle([x, y, x + tw, y + th], radius=6, fill=(255, 255, 255), outline=(50, 50, 50), width=2)
    # cabecera
    draw.rounded_rectangle([x, y, x + tw, y + title_h], radius=6, fill=(45, 55, 70), outline=(45, 55, 70), width=1)
    draw.rectangle([x, y + title_h - 8, x + tw, y + title_h], fill=(45, 55, 70))
    draw.text((x + pad_x, y + 5), name, font=f_title, fill=(255, 255, 255))
    cy = y + title_h + pad_y
    for c in cols:
        color = (20, 20, 20)
        if c.endswith(" PK"):
            color = (160, 40, 40)
        elif c.endswith(" FK"):
            color = (30, 90, 160)
        draw.text((x + pad_x, cy), c, font=f_col, fill=color)
        cy += row_h
    return x, y, tw, th


def center_of(box):
    x, y, tw, th = box
    return x + tw / 2, y + th / 2


def main():
    img = Image.new("RGB", (W, H), (248, 249, 252))
    draw = ImageDraw.Draw(img)
    f_mod = font(22, bold=True)
    f_title = font(16, bold=True)
    f_col = font(13)
    f_head = font(28, bold=True)
    f_note = font(14)

    draw.text((40, 10), "ISINUTA — Modelo entidad-relación (tablas de negocio)", font=f_head, fill=(30, 40, 60))
    draw.text(
        (40, H - 36),
        "Sin cache, sessions, jobs, migrations ni activity_logs. PK en rojo · FK en azul.",
        font=f_note,
        fill=(90, 90, 90),
    )

    # módulos
    for mod in MODULES:
        x1, y1, w, h = mod["rect"]
        draw.rounded_rectangle([x1, y1, x1 + w, y1 + h], radius=18, fill=mod["bg"], outline=mod["border"], width=3)
        draw.text((x1 + 16, y1 + 12), mod["title"], font=f_mod, fill=mod["border"])

    boxes = {}
    for mod in MODULES:
        for name, tx, ty in mod["tables"]:
            cols = TABLES[name]
            boxes[name] = draw_table(draw, tx, ty, name, cols, f_title, f_col)

    # relaciones
    for a, b in EDGES:
        if a not in boxes or b not in boxes:
            continue
        x1, y1 = center_of(boxes[a])
        x2, y2 = center_of(boxes[b])
        draw.line([(x1, y1), (x2, y2)], fill=(90, 90, 110), width=2)

    # redibujar tablas encima de líneas para legibilidad
    for mod in MODULES:
        for name, tx, ty in mod["tables"]:
            boxes[name] = draw_table(draw, tx, ty, name, TABLES[name], f_title, f_col)

    img.save(OUT, "PNG", optimize=True)
    img.save(OUT2, "PNG", optimize=True)
    print("saved", OUT)
    print("saved", OUT2)
    print("size", img.size)


if __name__ == "__main__":
    main()

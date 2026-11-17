# -*- coding: utf-8 -*-
"""
Reacomoda la captura Workbench ORIGINAL (mismo dibujo al 100%).
Solo mueve recortes de tablas para imprimir sin huecos.
"""
from pathlib import Path
import numpy as np
from PIL import Image, ImageDraw

SRC = Path(
    r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA"
    r"\Claude outputs\Modelo de base de datos del sistema Isinuta.png"
)
OUT = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA_impresion.png")
OUT2 = Path(r"c:\Users\papu\Desktop\ERD_ISINUTA.png")
DEBUG = Path(
    r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA"
    r"\_doc_review\erd_detect_debug.png"
)

MARGIN = 36
GAP_X = 26
GAP_Y = 20


def detect_headers(arr):
    r = arr[:, :, 0].astype(np.int16)
    g = arr[:, :, 1].astype(np.int16)
    b = arr[:, :, 2].astype(np.int16)
    mask = (b > 155) & (g > 145) & (r < 230) & (b > r + 8)
    row_on = mask.mean(axis=1) > 0.008
    h, w = mask.shape
    headers = []
    y = 0
    while y < h:
        if not row_on[y]:
            y += 1
            continue
        y0 = y
        while y < h and row_on[y]:
            y += 1
        y1 = y - 1
        bh = y1 - y0 + 1
        if bh < 25:  # ignorar franjas azul de 11px (ruido)
            continue
        # si banda muy alta, partir cabecera típica ~50px
        if bh > 70:
            y1 = y0 + 49
            bh = 50
        strip = mask[y0 : y1 + 1].any(axis=0)
        # cierre horizontal de huecos de texto
        dil = np.zeros(w, dtype=bool)
        xs = np.where(strip)[0]
        for i in xs:
            dil[max(0, i - 22) : min(w, i + 23)] = True
        x = 0
        while x < w:
            if not dil[x]:
                x += 1
                continue
            x0 = x
            while x < w and dil[x]:
                x += 1
            x1 = x - 1
            if x1 - x0 >= 120:
                headers.append((x0, y0, x1, min(y1, y0 + 55)))
    # fusionar solapes misma banda
    headers = sorted(headers, key=lambda t: (t[1], t[0]))
    merged = []
    for hd in headers:
        if merged and abs(merged[-1][1] - hd[1]) <= 8 and hd[0] <= merged[-1][2] + 30:
            m = list(merged[-1])
            m[0] = min(m[0], hd[0])
            m[2] = max(m[2], hd[2])
            merged[-1] = tuple(m)
        else:
            merged.append(hd)
    return merged


def x_overlap(a0, a1, b0, b1):
    return max(0, min(a1, b1) - max(a0, b0) + 1)


def row_has_ink(arr, y, left, right):
    mid = left + max(50, int((right - left) * 0.7))
    row = arr[y, left : mid + 1]
    return float(np.mean(row[:, 0] < 100)) > 0.01 or float(np.mean(row[:, 0] < 200)) > 0.05


def build_tables(arr, headers):
    h, w, _ = arr.shape
    headers = sorted(headers, key=lambda t: (t[1], t[0]))
    tables = []
    for i, (x0, y0, x1, y1) in enumerate(headers):
        ym = (y0 + y1) // 2
        left, right = x0, x1
        while left > 1 and int(arr[ym, left - 1].mean()) < 247:
            left -= 1
        while right < w - 2 and int(arr[ym, right + 1].mean()) < 247:
            right += 1

        width = right - left + 1
        limit = min(h - 1, y1 + 1100)
        for j in range(i + 1, len(headers)):
            hx0, hy0, hx1, hy1 = headers[j]
            if hy0 <= y1 + 5:
                continue
            if x_overlap(left, right, hx0, hx1) >= min(width, hx1 - hx0 + 1) * 0.4:
                limit = min(limit, hy0 - 2)
                break

        top = y0
        bottom = y1 + 70
        miss = 0
        for y in range(y1 + 1, limit + 1):
            if row_has_ink(arr, y, left, right):
                bottom = y
                miss = 0
            else:
                miss += 1
                # Workbench deja ~15-25 px blancos entre filas; cortar solo tras hueco grande
                if y > y1 + 100 and miss >= 40:
                    break
        bottom = min(h - 1, bottom + 6)
        if right - left < 130 or bottom - top < 120:
            continue
        tables.append((left, top, right, bottom))
    return tables


def pack(crops, n_cols=5):
    items = sorted(enumerate(crops), key=lambda t: (-t[1].size[1], -t[1].size[0]))
    cols = [[] for _ in range(n_cols)]
    heights = [0] * n_cols
    widths = [0] * n_cols
    for _, im in items:
        i = int(np.argmin(heights))
        cols[i].append(im)
        heights[i] += im.size[1] + GAP_Y
        widths[i] = max(widths[i], im.size[0])
    heights = [max(0, v - GAP_Y) for v in heights]
    W = MARGIN * 2 + sum(widths) + GAP_X * (n_cols - 1)
    H = MARGIN * 2 + max(heights)
    canvas = Image.new("RGB", (W, H), (255, 255, 255))
    x = MARGIN
    for i, col in enumerate(cols):
        y = MARGIN
        for im in col:
            canvas.paste(im, (x + (widths[i] - im.size[0]) // 2, y))
            y += im.size[1] + GAP_Y
        x += widths[i] + GAP_X
    return canvas


def main():
    im = Image.open(SRC).convert("RGB")
    arr = np.array(im)
    print("src", im.size)
    headers = detect_headers(arr)
    print("headers", len(headers))
    for hd in headers:
        print(" ", hd)

    boxes = build_tables(arr, headers)
    print("tables", len(boxes))
    for b in sorted(boxes, key=lambda x: (x[1], x[0])):
        print(f"  {b[2]-b[0]:4d}x{b[3]-b[1]:4d} @ ({b[0]:4d},{b[1]:4d})")

    dbg = im.copy()
    d = ImageDraw.Draw(dbg)
    for b in boxes:
        d.rectangle(b, outline=(255, 0, 0), width=4)
    dbg.save(DEBUG)

    order = sorted(range(len(boxes)), key=lambda i: (boxes[i][1] // 120, boxes[i][0]))
    crops = [im.crop((boxes[i][0], boxes[i][1], boxes[i][2] + 1, boxes[i][3] + 1)) for i in order]
    out = pack(crops, n_cols=5 if len(crops) >= 18 else 4)

    for path in (OUT, OUT2):
        try:
            out.save(path, "PNG", optimize=True)
            print("OK", path, out.size)
        except OSError as e:
            alt = path.with_name(path.stem + "_v2.png")
            out.save(alt, "PNG", optimize=True)
            print("ALT", alt, e)


if __name__ == "__main__":
    main()

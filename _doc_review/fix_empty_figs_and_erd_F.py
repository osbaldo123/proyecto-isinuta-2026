# -*- coding: utf-8 -*-
"""
- Inserta Fig 35, 36, 37 (vacías) + imagen ERD en Modelo de BD
- Reemplaza textos SIRAF por ISINUTA
- Arial 11, interlineado 1.5, sin negrita en textos tocados
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
from PIL import Image
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_figs_erd.docx")
base = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA")
caps = base / "_real_captures"
diags = base / "diagramas_graficos" / "actualizados"
erd_src = base / "_doc_review" / "erd_isinuta.jpg"

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
R = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"
WP = "{http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing}"
REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"
EMU_PER_PX = 9525  # 96 dpi approx

log = []


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def set_arial11_no_bold(r):
    rPr = r.find(W + "rPr")
    if rPr is None:
        rPr = etree.Element(W + "rPr")
        r.insert(0, rPr)
    fonts = rPr.find(W + "rFonts")
    if fonts is None:
        fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    for tag, val in ((W + "sz", "22"), (W + "szCs", "22")):
        el = rPr.find(tag)
        if el is None:
            el = etree.SubElement(rPr, tag)
        el.set(W + "val", val)
    for tag in (W + "b", W + "bCs"):
        el = rPr.find(tag)
        if el is not None:
            rPr.remove(el)


def set_spacing15(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")
    jc = pPr.find(W + "jc")
    if jc is None:
        jc = etree.SubElement(pPr, W + "jc")
    jc.set(W + "val", "both")


def set_para_text(p, text, justify=True):
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        set_arial11_no_bold(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        set_spacing15(p)
        return
    first = runs[0]
    if first.find(W + "rPr") is None:
        first.insert(0, etree.Element(W + "rPr"))
    set_arial11_no_bold(first)
    for r in runs[1:]:
        p.remove(r)
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)
    t = etree.SubElement(first, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    set_spacing15(p)
    if not justify:
        pPr = p.find(W + "pPr")
        jc = pPr.find(W + "jc") if pPr is not None else None
        if jc is not None:
            jc.set(W + "val", "center")


def make_drawing(rid: str, cx: int, cy: int, name: str):
    """Construye w:drawing inline mínimo válido."""
    # namespaces on drawing children via Clark notation
    drawing = etree.Element(W + "drawing")
    inline = etree.SubElement(drawing, WP + "inline")
    inline.set("distT", "0")
    inline.set("distB", "0")
    inline.set("distL", "0")
    inline.set("distR", "0")
    extent = etree.SubElement(inline, WP + "extent")
    extent.set("cx", str(cx))
    extent.set("cy", str(cy))
    effect = etree.SubElement(inline, WP + "effectExtent")
    for k in ("l", "t", "r", "b"):
        effect.set(k, "0")
    docPr = etree.SubElement(inline, WP + "docPr")
    docPr.set("id", str(abs(hash(name)) % 100000 + 100))
    docPr.set("name", name)
    etree.SubElement(inline, WP + "cNvGraphicFramePr")
    graphic = etree.SubElement(inline, A + "graphic")
    graphicData = etree.SubElement(graphic, A + "graphicData")
    graphicData.set("uri", "http://schemas.openxmlformats.org/drawingml/2006/picture")
    pic = etree.SubElement(graphicData, "{http://schemas.openxmlformats.org/drawingml/2006/picture}pic")
    PIC = "{http://schemas.openxmlformats.org/drawingml/2006/picture}"
    nvPicPr = etree.SubElement(pic, PIC + "nvPicPr")
    cNvPr = etree.SubElement(nvPicPr, PIC + "cNvPr")
    cNvPr.set("id", "0")
    cNvPr.set("name", name)
    etree.SubElement(nvPicPr, PIC + "cNvPicPr")
    blipFill = etree.SubElement(pic, PIC + "blipFill")
    blip = etree.SubElement(blipFill, A + "blip")
    blip.set(R + "embed", rid)
    stretch = etree.SubElement(blipFill, A + "stretch")
    etree.SubElement(stretch, A + "fillRect")
    spPr = etree.SubElement(pic, PIC + "spPr")
    xfrm = etree.SubElement(spPr, A + "xfrm")
    off = etree.SubElement(xfrm, A + "off")
    off.set("x", "0")
    off.set("y", "0")
    ext = etree.SubElement(xfrm, A + "ext")
    ext.set("cx", str(cx))
    ext.set("cy", str(cy))
    prstGeom = etree.SubElement(spPr, A + "prstGeom")
    prstGeom.set("prst", "rect")
    etree.SubElement(prstGeom, A + "avLst")
    return drawing


def put_image_in_para(p, rid, img_path: Path, max_width_px=620, name="imagen"):
    # clear existing content except pPr
    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)
    iw, ih = Image.open(img_path).size
    if iw > max_width_px:
        scale = max_width_px / iw
        disp_w = max_width_px
        disp_h = int(ih * scale)
    else:
        disp_w, disp_h = iw, ih
    cx = int(disp_w * EMU_PER_PX)
    cy = int(disp_h * EMU_PER_PX)
    r = etree.SubElement(p, W + "r")
    set_arial11_no_bold(r)
    r.append(make_drawing(rid, cx, cy, name))
    # center
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    jc = pPr.find(W + "jc")
    if jc is None:
        jc = etree.SubElement(pPr, W + "jc")
    jc.set(W + "val", "center")


def next_rid(rels_root):
    nums = []
    for rel in rels_root:
        m = re.search(r"(\d+)$", rel.get("Id") or "")
        if m:
            nums.append(int(m.group(1)))
    return f"rId{max(nums) + 1 if nums else 1}"


def next_media_index(existing_names):
    nums = []
    for n in existing_names:
        m = re.search(r"image(\d+)\.", n)
        if m:
            nums.append(int(m.group(1)))
    return (max(nums) + 1) if nums else 46


shutil.copy2(src, backup)

# prepare images
fig35 = diags / "fig35_casos_uso_sprint4.png"
if not fig35.exists():
    fig35 = base / "diagramas_graficos" / "fig35_casos_uso_sprint4.png"
fig36 = diags / "fig36_clases_sprint4.png"
if not fig36.exists():
    fig36 = base / "diagramas_graficos" / "fig36_clases_sprint4.png"
fig37 = caps / "fig37-ingresos.png"
fig38 = caps / "fig38-deudas.png"

# convert ERD to png for consistency
erd_png = base / "_doc_review" / "erd_isinuta.png"
Image.open(erd_src).convert("RGB").save(erd_png, "PNG", optimize=True)

for pth, label in [(fig35, "fig35"), (fig36, "fig36"), (fig37, "fig37"), (fig38, "fig38"), (erd_png, "erd")]:
    if not pth.exists():
        raise SystemExit(f"Falta imagen: {pth}")
    log.append(f"src ok {label} {pth.name} {pth.stat().st_size}")

with ZipFile(src, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}

root = etree.fromstring(files["word/document.xml"])
rels_root = etree.fromstring(files["word/_rels/document.xml.rels"])
paras = list(root.iter(W + "p"))

# map existing media names
media_names = [n.replace("word/", "") for n in files if n.startswith("word/media/")]
idx = next_media_index([Path(n).name for n in files if n.startswith("word/media/")])

# Add relationships + media files
new_media = {}


def add_image(path: Path, ext: str):
    global idx
    name = f"media/image{idx}.{ext}"
    idx += 1
    rid = next_rid(rels_root)
    rel = etree.SubElement(
        rels_root,
        "{http://schemas.openxmlformats.org/package/2006/relationships}Relationship",
    )
    rel.set("Id", rid)
    rel.set("Type", "http://schemas.openxmlformats.org/officeDocument/2006/relationships/image")
    rel.set("Target", name)
    new_media["word/" + name] = path.read_bytes()
    log.append(f"add {rid} -> {name} ({len(new_media['word/' + name])} B)")
    return rid, name


rid35, _ = add_image(fig35, "png")
rid36, _ = add_image(fig36, "png")
rid37, _ = add_image(fig37, "png")
rid38, _ = add_image(fig38, "png")
rid_erd, _ = add_image(erd_png, "png")


def find_caption(fig_num: int):
    for i, p in enumerate(paras):
        t = pt(p)
        if re.match(rf"Figura\s+{fig_num}\s*:", t) and i > 1000 and st(p) in ("Descripcin", "Caption", ""):
            # prefer body Descripcin
            if st(p) == "Descripcin" or (st(p) == "" and "Tablade" not in st(p)):
                return i, p
    return None, None


# Insert/replace images: put drawing in empty para BEFORE caption if empty, else create
for fig_num, rid, img, label in [
    (35, rid35, fig35, "Fig35"),
    (36, rid36, fig36, "Fig36"),
    (37, rid37, fig37, "Fig37"),
    (38, rid38, fig38, "Fig38"),
]:
    i, cap = find_caption(fig_num)
    if cap is None:
        log.append(f"NO caption fig{fig_num}")
        continue
    # look for empty para just before caption
    target = None
    if i > 0 and not pt(paras[i - 1]).strip() and len(paras[i - 1].findall(".//" + W + "drawing")) == 0:
        target = paras[i - 1]
    else:
        # insert new empty para before caption
        target = etree.Element(W + "p")
        # copy Descripcin style if possible
        pPr = etree.SubElement(target, W + "pPr")
        ps = etree.SubElement(pPr, W + "pStyle")
        ps.set(W + "val", "Descripcin")
        cap.addprevious(target)
        paras = list(root.iter(W + "p"))
    put_image_in_para(target, rid, img, max_width_px=620 if fig_num >= 37 else 580, name=label)
    log.append(f"inserted image fig{fig_num}")

paras = list(root.iter(W + "p"))

# === Modelo de base de datos: textos ISINUTA + ERD ===
bd_title_i = None
for i, p in enumerate(paras):
    if st(p) == "Ttulo2" and "Modelo de base de datos" in pt(p):
        bd_title_i = i
        break

if bd_title_i is None:
    raise SystemExit("No se encontró Modelo de base de datos")

# Collect block until Cap IV or empty run of many empties before Cap IV
# Replace paragraphs after title until Figura/Nota SIRAF area
texts = {
    0: (  # after title
        "La base de datos del sistema web de la Asociación de Agua Potable y Alcantarillado Isinuta "
        "utiliza un modelo relacional. La información se organiza en tablas vinculadas mediante claves "
        "primarias y foráneas, de modo que afiliados, lotes, servicios, cuotas, pagos, multas, trabajos "
        "y usuarios queden relacionados de forma consistente."
    ),
    1: (
        "La Figura 39 presenta el modelo de base de datos con las principales tablas del sistema. "
        "Se incluyen las entidades de afiliados y lotes, servicios y tarifas, cobranza (cuotas, pagos "
        "y multas), trabajos comunitarios, seguridad (usuarios, roles y permisos) y la configuración general."
    ),
    2: (
        "Para facilitar la lectura del diagrama, las tablas se agrupan por módulos funcionales. "
        "No se omitieron tablas operativas relevantes; el esquema corresponde a la base "
        "db_agua_potable_isinuta utilizada por la aplicación."
    ),
}

# Find and rewrite following content paragraphs; remove SIRAF leftovers
j = bd_title_i + 1
# ensure title not bold
title_p = paras[bd_title_i]
set_para_text(title_p, "Modelo de base de datos.", justify=False)
# remove bold from title runs explicitly
for r in title_p.findall(W + "r"):
    set_arial11_no_bold(r)
log.append("titulo BD sin negrita")

# Map next few non-empty / placeholder paras
rewritten = 0
while j < len(paras) and rewritten < 3:
    t = pt(paras[j]).strip()
    s = st(paras[j])
    if s == "Ttulo1" and "CAPÍTULO IV" in t.upper():
        break
    if not t:
        j += 1
        continue
    if t.startswith("Figura ") or t.startswith("Nota:"):
        break
    if "SIRAF" in t or "Aquí falta" in t or "Figura 55" in t or "y la figura y su nota" in t or t.startswith("La Figura") or t.startswith("Para facilitar") or t.startswith("La base de datos del sistema"):
        set_para_text(paras[j], texts[rewritten])
        log.append(f"BD texto {rewritten}")
        rewritten += 1
        j += 1
        continue
    # unexpected - still replace first 3 content paras after title if placeholders
    if rewritten < 3 and ("SIRAF" in t or "falta contexto" in t or "ejemplo" in t.lower()):
        set_para_text(paras[j], texts[rewritten])
        rewritten += 1
    j += 1

# If fewer than 3 rewritten, insert missing texts
paras = list(root.iter(W + "p"))
bd_title_i = next(i for i, p in enumerate(paras) if st(p) == "Ttulo2" and "Modelo de base de datos" in pt(p))
# ensure three body paras exist after title before figure
# Delete leftover SIRAF caption/note and "ejemplo" lines
for p in list(paras):
    t = pt(p)
    if "Figura 54: Modelo de base de datos del sistema SIRAF" in t:
        set_para_text(p, "Figura 39: Modelo de base de datos del sistema Isinuta", justify=False)
        # caption style
        log.append("caption fig39")
    elif t.startswith("Nota:") and "principales tablas de la base de datos" in t and "SIRAF" not in t:
        set_para_text(
            p,
            "Nota: El diagrama representa las principales tablas de la base de datos db_agua_potable_isinuta, "
            "sus atributos y las relaciones establecidas mediante claves primarias y foráneas.",
        )
        log.append("nota fig39")
    elif "y la figura y su nota ejemplo" in t or (t.startswith("Aquí falta") or "sistema SIRAF" in t):
        p.getparent().remove(p)
        log.append("DEL leftover " + t[:50])

paras = list(root.iter(W + "p"))
# Find fig 39 caption and put ERD in previous empty / create
cap39 = None
for i, p in enumerate(paras):
    if pt(p).startswith("Figura 39:"):
        cap39 = p
        cap39_i = i
        break

if cap39 is None:
    # create caption + note before Cap IV
    cap_iv = next(p for p in paras if st(p) == "Ttulo1" and "CAPÍTULO IV" in pt(p).upper())
    for text, style in [
        ("Figura 39: Modelo de base de datos del sistema Isinuta", "Descripcin"),
        (
            "Nota: El diagrama representa las principales tablas de la base de datos db_agua_potable_isinuta, "
            "sus atributos y las relaciones establecidas mediante claves primarias y foráneas.",
            None,
        ),
    ]:
        np = etree.Element(W + "p")
        if style:
            pPr = etree.SubElement(np, W + "pPr")
            ps = etree.SubElement(pPr, W + "pStyle")
            ps.set(W + "val", style)
        set_para_text(np, text, justify=False if style else True)
        cap_iv.addprevious(np)
    paras = list(root.iter(W + "p"))
    cap39 = next(p for p in paras if pt(p).startswith("Figura 39:"))

# image para before caption
img_p = None
# previous sibling
prev = cap39.getprevious()
while prev is not None and prev.tag != W + "p":
    prev = prev.getprevious()
if prev is not None and not pt(prev).strip() and len(prev.findall(".//" + W + "drawing")) == 0:
    img_p = prev
else:
    img_p = etree.Element(W + "p")
    pPr = etree.SubElement(img_p, W + "pPr")
    ps = etree.SubElement(pPr, W + "pStyle")
    ps.set(W + "val", "Descripcin")
    cap39.addprevious(img_p)

put_image_in_para(img_p, rid_erd, erd_png, max_width_px=640, name="ERD_Isinuta")
log.append("inserted ERD fig39")

# Ensure BD body texts exist (if rewrite missed)
paras = list(root.iter(W + "p"))
bd_title_i = next(i for i, p in enumerate(paras) if st(p) == "Ttulo2" and "Modelo de base de datos" in pt(p))
body_after = []
for k in range(bd_title_i + 1, min(bd_title_i + 12, len(paras))):
    t = pt(paras[k]).strip()
    if t.startswith("Figura 39") or len(paras[k].findall(".//" + W + "drawing")):
        break
    if t and not t.startswith("Nota:"):
        body_after.append((k, t[:60]))
if len(body_after) < 3:
    # insert missing at start after title
    anchor = paras[bd_title_i]
    # insert in reverse order texts not present
    existing = "\n".join(pt(p) for p in paras[bd_title_i : bd_title_i + 15])
    for text in reversed([texts[0], texts[1], texts[2]]):
        if text[:40] not in existing:
            np = etree.Element(W + "p")
            set_para_text(np, text)
            anchor.addnext(np)
            anchor = np
            log.append("insert BD missing para")

# Final pass: no bold on BD section paragraphs
paras = list(root.iter(W + "p"))
bd_title_i = next(i for i, p in enumerate(paras) if st(p) == "Ttulo2" and "Modelo de base de datos" in pt(p))
for k in range(bd_title_i, min(bd_title_i + 20, len(paras))):
    if st(paras[k]) == "Ttulo1":
        break
    for r in paras[k].findall(W + "r"):
        set_arial11_no_bold(r)
    if pt(paras[k]).strip() and not paras[k].findall(".//" + W + "drawing"):
        set_spacing15(paras[k])

# save
files["word/document.xml"] = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
files["word/_rels/document.xml.rels"] = etree.tostring(rels_root, xml_declaration=True, encoding="UTF-8", standalone=True)
files.update(new_media)

# Content_Types: ensure png
ct = files.get("[Content_Types].xml", b"")
if b"image/png" not in ct and ct:
    ct_root = etree.fromstring(ct)
    # usually already has Default Extension png
    files["[Content_Types].xml"] = etree.tostring(ct_root, xml_declaration=True, encoding="UTF-8", standalone=True)

tmp = Path(tempfile.gettempdir()) / "mercado_F_figs_erd.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)
shutil.copy2(tmp, src)

print("saved", src)
print("backup", backup)
for x in log:
    print(" -", x)

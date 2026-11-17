# -*- coding: utf-8 -*-
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from docx import Document
from lxml import etree
from PIL import Image
import shutil
import tempfile

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.backup.docx")
base = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\diagramas_graficos")
fig35 = base / "fig35_casos_uso_sprint4.png"
fig36 = base / "fig36_clases_sprint4.png"

if not backup.exists():
    shutil.copy2(src, backup)
    print("backup ->", backup)

doc = Document(str(src))

replacements = {
    639: "Nota: El diagrama muestra los casos de uso del Sprint 4: informe de ingresos e informe de deudas.",
    641: "La figura muestra la estructura de clases del Sprint 4, centrada en la clase ReporteAdministrativo para generar el reporte de ingresos y el reporte de deudas.",
    644: "Nota: El diagrama muestra la clase del Sprint 4: ReporteAdministrativo.",
}


def set_para_text(p, new_text: str) -> None:
    if p.runs:
        first = p.runs[0]
        for r in p.runs:
            r.text = ""
        first.text = new_text
        for r in list(p.runs)[1:]:
            r._element.getparent().remove(r._element)
    else:
        p.add_run(new_text)


for idx, text in replacements.items():
    set_para_text(doc.paragraphs[idx], text)

tmp_docx = Path(tempfile.gettempdir()) / "mercado_patch_text.docx"
doc.save(str(tmp_docx))

media_map = {
    "word/media/image37.png": fig35,
    "word/media/image38.png": fig36,
}

with ZipFile(tmp_docx, "r") as zin:
    doc_xml = zin.read("word/document.xml")
    rels_xml = zin.read("word/_rels/document.xml.rels")

root = etree.fromstring(doc_xml)
rels = etree.fromstring(rels_xml)
rid_to_target = {el.get("Id"): el.get("Target") for el in rels}
target_to_rid = {v: k for k, v in rid_to_target.items()}

WP_EXTENT = "{http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing}extent"
A_BLIP = "{http://schemas.openxmlformats.org/drawingml/2006/main}blip"
R_EMBED = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}embed"

for media_name, img_path in [
    ("media/image37.png", fig35),
    ("media/image38.png", fig36),
]:
    rid = target_to_rid.get(media_name)
    if not rid:
        print("MISSING rid for", media_name)
        continue
    iw, ih = Image.open(img_path).size
    aspect = ih / iw
    for blip in root.findall(f".//{A_BLIP}"):
        if blip.get(R_EMBED) != rid:
            continue
        extent = None
        for anc in blip.iterancestors():
            extent = anc.find(WP_EXTENT)
            if extent is not None:
                break
        if extent is None:
            print("no extent for", media_name)
            continue
        cx = int(extent.get("cx"))
        new_cy = int(cx * aspect)
        print(media_name, "cx", cx, "cy", extent.get("cy"), "->", new_cy, "img", iw, ih)
        extent.set("cy", str(new_cy))

new_doc_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)

out_tmp = Path(tempfile.gettempdir()) / "mercado_patch_final.docx"
with ZipFile(tmp_docx, "r") as zin, ZipFile(out_tmp, "w", compression=ZIP_DEFLATED) as zout:
    for item in zin.infolist():
        data = zin.read(item.filename)
        if item.filename in media_map:
            data = media_map[item.filename].read_bytes()
            print("replaced", item.filename, "bytes", len(data))
        elif item.filename == "word/document.xml":
            data = new_doc_xml
        zout.writestr(item, data)

shutil.copy2(out_tmp, src)
print("saved", src)

doc2 = Document(str(src))
for i in (639, 641, 644):
    print(i, doc2.paragraphs[i].text)

with ZipFile(src) as z:
    for m in ("word/media/image37.png", "word/media/image38.png"):
        data = z.read(m)
        p = Path(tempfile.gettempdir()) / Path(m).name
        p.write_bytes(data)
        print(m, Image.open(p).size, len(data))

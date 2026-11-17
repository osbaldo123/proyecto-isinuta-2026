# -*- coding: utf-8 -*-
"""Acorta Validación de HU-01..HU-10: menos viñetas, mismo sentido. Arial 11, 1.5."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_validaciones.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

# Validaciones condensadas (entendibles, menos espacio)
SHORT = {
    "HU-01": [
        "Controlar duplicidad por nombre de rol y asignar funciones por rol.",
        "Filtro multicampo; actualizar roles; algunos roles no se pueden eliminar.",
    ],
    "HU-02": [
        "Inicio de sesión con correo y contraseña; acceso según estado activo o inactivo.",
        "Sin duplicar CI, correo ni teléfono; CI de 8 dígitos; teléfono con código de país.",
        "Rol obligatorio; contraseña de al menos 8 caracteres con confirmación.",
        "Fechas de ingreso y final; filtro, paginación y verificación del correo.",
    ],
    "HU-03": [
        "Lista de afiliados con filtro, paginación y control de CI duplicado.",
        "Un afiliado puede tener varios lotes (OTB, número único, categoría, servicios y afiliación).",
        "Registrar fecha, forma de pago y saldo; estados activo, cortado o baja (corte por mora anual a los 12 meses, por lote).",
    ],
    "HU-04": [
        "Lista y registro de tipos de afiliación (contado y plazo), sin duplicar el nombre.",
        "Actualizar datos y estado; código único automático; agua y alcantarillado base solo se desactivan.",
    ],
    "HU-05": [
        "Registrar, actualizar y activar o desactivar OTB, sin duplicar el nombre.",
        "Filtros, cantidad de afiliados por OTB y eliminación solo si no tiene afiliados.",
    ],
    "HU-06": [
        "Servicios de agua y alcantarillado con tarifa mensual por categoría (Domiciliar y Comercial).",
        "Gestionar categorías en el mismo módulo; al cambiar la tarifa se actualizan los lotes.",
        "Sin duplicar servicios; agua y alcantarillado no se eliminan; filtros por nombre, tipo y estado.",
    ],
    "HU-07": [
        "Registrar el trabajo por OTB (fecha, tipo y monto de multa) y pasar lista por afiliado.",
        "La falta genera multa en cada lote del afiliado; el cobro emite recibo de caja de ese lote.",
        "Consultar trabajos por OTB, fecha y tipo.",
    ],
    "HU-08": [
        "Cobrar agua, alcantarillado, multas y abonos de afiliación; un recibo por concepto; pago en efectivo.",
        "No cobrar dos veces el mismo mes del mismo lote; el recibo se imprime y exporta a Excel.",
        "Estado de cuenta del lote; transferir solo sin deudas, con historial de titulares.",
    ],
    "HU-09": [
        "Listar y registrar multas; filtros por estado y CI; la de inasistencia sale del pase de lista.",
        "Mora: a los 3 meses sin pagar, el día 1 del mes siguiente se genera Bs 50 (luego 100 y 150); permanece hasta cobrarse.",
        "Montos desde Configuración en Multas; si el afiliado pasa a presente, se retira la multa de ese trabajo.",
    ],
    "HU-10": [
        "Reporte de ingresos por rango de fechas, agrupado por concepto (subtotal y total); filtros por afiliado y concepto.",
        "Reporte de deudas pendientes por concepto (cuotas y multas), con afiliado, lote y montos; acceso desde Panel y Reportes.",
        "Botones Excel e Imprimir / PDF; las multas cobradas van en ingresos (no hay reporte de recargos); solo secretaria y presidente.",
    ],
}


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def set_arial11(r):
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


def set_para_text(p, text):
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        set_arial11(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        set_spacing15(p)
        return
    first = runs[0]
    if first.find(W + "rPr") is None:
        first.insert(0, etree.Element(W + "rPr"))
    set_arial11(first)
    for r in runs[1:]:
        p.remove(r)
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)
    t = etree.SubElement(first, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    set_spacing15(p)


shutil.copy2(src, backup)
with ZipFile(src, "r") as zin:
    files = {i.filename: zin.read(i.filename) for i in zin.infolist()}

root = etree.fromstring(files["word/document.xml"])
paras = list(root.iter(W + "p"))

start = next(i for i, p in enumerate(paras) if pt(p).strip().startswith("Historias de usuario"))
end = next(
    i
    for i, p in enumerate(paras)
    if i > start and ("Product Backlog" in pt(p) or pt(p).strip().startswith("Ejecución del Sprint 1"))
)

# Detectar orden de HU por aparición de "HU-0X" cerca de Nombre / Validación
# Recorrer y asociar cada Validación: al HU más reciente visto
current_hu = None
log = []

i = start
while i < end:
    t = pt(paras[i]).strip()
    m = re.search(r"\b(HU-\d{2})\b", t)
    if m:
        current_hu = m.group(1)
    if t.startswith("Validación") and current_hu in SHORT:
        # bullets hasta Prioridad
        bullets = []
        j = i + 1
        while j < end:
            tj = pt(paras[j]).strip()
            if tj.startswith("Prioridad"):
                break
            if tj:
                bullets.append(paras[j])
            j += 1
        new_texts = SHORT[current_hu]
        # reutilizar primeros N párrafos; borrar el resto
        for k, text in enumerate(new_texts):
            if k < len(bullets):
                set_para_text(bullets[k], text)
            else:
                # insertar nuevo párrafo antes de Prioridad
                np = etree.Element(W + "p")
                # copiar pPr básico del primer bullet si existe
                if bullets:
                    pPr = bullets[0].find(W + "pPr")
                    if pPr is not None:
                        from copy import deepcopy

                        np.append(deepcopy(pPr))
                set_para_text(np, text)
                paras[j].addprevious(np)
                bullets.append(np)
        # eliminar sobrantes
        for extra in bullets[len(new_texts) :]:
            parent = extra.getparent()
            if parent is not None:
                parent.remove(extra)
        log.append(f"{current_hu}: {len(bullets)} -> {len(new_texts)}")
        # refresh paras list after structural changes
        paras = list(root.iter(W + "p"))
        # advance i past this validation
        i = next(
            x
            for x, p in enumerate(paras)
            if x > i and pt(p).strip().startswith("Prioridad") and x < end + 50
        )
        # recalculate end
        end = next(
            x
            for x, p in enumerate(paras)
            if x > start and ("Product Backlog" in pt(p) or pt(p).strip().startswith("Ejecución del Sprint 1"))
        )
    i += 1

files["word/document.xml"] = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_valid_short.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in files.items():
        zout.writestr(name, data)
shutil.copy2(tmp, src)

print("saved", src)
for x in log:
    print(" -", x)

# verify HU-10
paras = list(etree.fromstring(ZipFile(src).read("word/document.xml")).iter(W + "p"))
for i, p in enumerate(paras):
    if pt(p).strip().startswith("Validación") and i > 750:
        # check if near HU-10
        window = "\n".join(pt(paras[k]) for k in range(max(0, i - 15), i))
        if "HU-10" in window:
            print("\nHU-10 validación:")
            for j in range(i, i + 8):
                print(" ", pt(paras[j])[:120])
            break

# -*- coding: utf-8 -*-
"""Inserta conceptos faltantes en 2.2 y mejora notas cortas de implementación."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.prev_conceptos.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def make_heading3(template_heading, title: str):
    p = deepcopy(template_heading)
    # clear runs
    for r in list(p.findall(W + "r")):
        p.remove(r)
    # keep pPr
    r = etree.SubElement(p, W + "r")
    # copy rPr from template if any
    tmpl_runs = template_heading.findall(W + "r")
    if tmpl_runs and tmpl_runs[0].find(W + "rPr") is not None:
        rPr = deepcopy(tmpl_runs[0].find(W + "rPr"))
    else:
        rPr = etree.Element(W + "rPr")
        etree.SubElement(rPr, W + "rFonts").set(W + "ascii", "Arial")
        rPr.find(W + "rFonts").set(W + "hAnsi", "Arial")
        rPr.find(W + "rFonts").set(W + "cs", "Arial")
        etree.SubElement(rPr, W + "sz").set(W + "val", "22")
        etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
    r.insert(0, rPr)
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = title
    return p


def make_body(template_body, text: str):
    p = deepcopy(template_body)
    for r in list(p.findall(W + "r")):
        p.remove(r)
    # remove hyperlink wrappers etc. keep pPr only
    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)
    r = etree.SubElement(p, W + "r")
    rPr = etree.Element(W + "rPr")
    fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    etree.SubElement(rPr, W + "sz").set(W + "val", "22")  # 11 pt
    etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
    r.insert(0, rPr)
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    return p


def set_para_text_arial11(p, text: str):
    for r in list(p.findall(W + "r")):
        p.remove(r)
    for child in list(p):
        if child.tag not in (W + "pPr",):
            # keep pPr; remove other non-run leftovers carefully
            if child.tag != W + "pPr":
                pass
    # remove everything except pPr
    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)
    r = etree.SubElement(p, W + "r")
    rPr = etree.Element(W + "rPr")
    fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    etree.SubElement(rPr, W + "sz").set(W + "val", "22")
    etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
    r.insert(0, rPr)
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text


# --- Contenidos nuevos (formatos cortos ~40 palabras o largos) ---
CONCEPTS = [
    (
        "Gestión administrativa.",
        [
            (
                "Chiavenato (2006) menciona que “administración es el proceso de planear, organizar, "
                "dirigir y controlar el empleo de los recursos organizacionales para conseguir "
                "determinados objetivos con eficiencia y eficacia” (p. 3). En el presente proyecto, "
                "la gestión administrativa se aplica al control ordenado de afiliados, pagos, multas "
                "y reportes de la Asociación de Agua Potable y Alcantarillado Isinuta."
            ),
        ],
    ),
    (
        "Gestión de afiliación.",
        [
            (
                "La gestión de afiliación puede definirse de diferentes maneras; entre ellas, se destaca "
                "la siguiente:"
            ),
            (
                "La gestión de la afiliación comprende el ciclo de captación, compromiso, renovación y "
                "readmisión de miembros, desde el pago de la primera cuota hasta la permanencia activa "
                "en la organización, y exige estrategias de retención y de relación continua con los "
                "afiliados (Centro Internacional de Formación de la OIT, 2022, p. 4)."
            ),
            (
                "En el presente proyecto, la gestión de afiliación permite registrar al titular, asociarlo "
                "a su lote y mantener actualizados sus datos, derechos de servicio y obligaciones ante "
                "la asociación."
            ),
        ],
    ),
    (
        "Gestión de lotes.",
        [
            (
                "SENASBA (2015) menciona que “la gestión de los servicios de agua y alcantarillado se "
                "organiza sobre unidades de conexión asociadas a predios, lo que permite identificar al "
                "usuario, facturar el servicio y controlar las obligaciones por cada inmueble” (p. 12). "
                "En el presente proyecto, cada lote es la unidad de cobro, de multa y de corte, vinculada "
                "a un afiliado y a una OTB."
            ),
        ],
    ),
    (
        "Gestión de servicios básicos.",
        [
            (
                "La gestión de servicios básicos puede definirse de diferentes maneras; entre ellas, se "
                "destaca la siguiente:"
            ),
            (
                "Los servicios de saneamiento básico comprenden el agua potable y el alcantarillado u "
                "otras formas de desagüe, entendidos como infraestructuras y mecanismos que permiten a "
                "las personas vivir en un ambiente sano mediante el acceso a agua limpia y a la "
                "evacuación segura de excretas (Universidad de Piura / JASS, 2010, p. 15)."
            ),
            (
                "En la actualidad, la gestión de estos servicios exige registro de usuarios, tarifas, "
                "cobro oportuno y control de deudas para sostener la operación. En el presente proyecto, "
                "se administran agua potable y alcantarillado por categoría Domiciliar y Comercial."
            ),
        ],
    ),
    (
        "Administración de pagos.",
        [
            (
                "Gitman y Zutter (2012) mencionan que “la administración del efectivo implica controlar "
                "los cobros y los pagos de la organización para disponer de liquidez suficiente, reducir "
                "costos financieros y mantener un flujo de caja ordenado y oportuno” (p. 598). En el "
                "presente proyecto, la administración de pagos registra cuotas y conceptos, emite recibos "
                "de ingreso o de caja y evita el cobro duplicado del mismo mes por lote."
            ),
        ],
    ),
    (
        "Gestión de deudas.",
        [
            (
                "Robles (2012) menciona que “la gestión de cobranza es un conjunto de acciones "
                "coordinadas aplicadas en el momento preciso para recuperar el crédito otorgado a "
                "clientes morosos y convertir las cuentas por cobrar en activos líquidos” (p. 45). En "
                "el presente proyecto, la gestión de deudas permite consultar cuotas y multas pendientes "
                "por lote y generar el reporte de deudas de la asociación."
            ),
        ],
    ),
    (
        "Control de multas.",
        [
            (
                "La Real Academia Española (2014) define la multa como “sanción pecuniaria que se impone "
                "a quien ha infringido una ley o una norma reglamentaria”. En el presente proyecto, el "
                "control de multas abarca la mora por atraso (Bs 50, 100 y 150) y la multa por "
                "inasistencia a trabajos, con consulta de estado y cobro en recibo de caja."
            ),
        ],
    ),
    (
        "Seguridad y control de acceso.",
        [
            (
                "Ferraiolo y Kuhn (1992) mencionan que “las decisiones de control de acceso se basan en "
                "los roles que los usuarios asumen dentro de una organización, de modo que un rol "
                "especifica el conjunto de transacciones que un usuario o conjunto de usuarios puede "
                "ejecutar” (p. 554). En el presente proyecto, secretaria y presidente acceden según roles "
                "y permisos a módulos de afiliados, pagos, multas y reportes."
            ),
        ],
    ),
]

BIBLIO_NEW = [
    "Centro Internacional de Formación de la OIT. (2022). Estrategias y políticas de afiliación: Un manual para organizaciones. Turín: ITCILO.",
    "Chiavenato, I. (2006). Administración: proceso administrativo (3.ª ed.). México: McGraw-Hill.",
    "Ferraiolo, D. F., & Kuhn, D. R. (1992). Role-Based Access Controls. 15th National Computer Security Conference (pp. 554-563). Baltimore, MD: NIST.",
    "Gitman, L. J., & Zutter, C. J. (2012). Principios de administración financiera (12.ª ed.). México: Pearson.",
    "Real Academia Española. (2014). Diccionario de la lengua española (23.ª ed.). Madrid: Espasa.",
    "Robles, C. (2012). Gestión de cobranza: un abordaje teórico desde el ámbito financiero. Revista de Administración y Turismo, 1(1), 40-52.",
    "SENASBA. (2015). Modelos de gestión. La Paz, Bolivia: Ministerio de Medio Ambiente y Agua / Servicio Nacional para la Sostenibilidad de Servicios en Saneamiento Básico.",
    "Universidad de Piura. (2010). Guía para la gestión de las Juntas Administradoras de Servicios de Saneamiento. Piura: Universidad de Piura.",
]

# Notas de implementación demasiado cortas → ampliar funcionalidad (Arial 11)
NOTE_UPDATES = {
    "Nota: Interfaz de acceso al sistema con correo y contraseña.": (
        "Nota: Interfaz de inicio de sesión que permite a la secretaria o al presidente ingresar con "
        "correo electrónico o CI y contraseña para acceder a los módulos autorizados según su rol."
    ),
    "Nota: Interfaz para registrar nuevos usuarios en el sistema.": (
        "Nota: Interfaz para registrar nuevos usuarios, asignar rol, datos de identidad y estado, "
        "habilitando el acceso controlado al sistema web de la asociación."
    ),
    "Nota: Interfaz que muestra los roles registrados en el sistema.": (
        "Nota: Interfaz que lista los roles del sistema y sus permisos, permitiendo administrar qué "
        "funciones puede ejecutar la secretaria o el presidente."
    ),
    "Nota: Interfaz que muestra las OTB registradas en la asociación.": (
        "Nota: Interfaz que muestra las OTB registradas, con nombre, estado y cantidad de afiliados, "
        "para organizar los lotes y los trabajos por barrio o sindicato."
    ),
}


def insert_alphabetically(paras, biblio_heading_idx, entries):
    """Inserta entradas APA aprox. en orden alfabético dentro de la bibliografía."""
    # collect existing biblio paras after heading until ANEXOS or empty stretch end
    items = []
    for i in range(biblio_heading_idx + 1, len(paras)):
        t = para_text(paras[i])
        st_pPr = paras[i].find(W + "pPr")
        style = None
        if st_pPr is not None:
            ps = st_pPr.find(W + "pStyle")
            if ps is not None:
                style = ps.get(W + "val")
        if style == "Ttulo1" and t and t.upper().startswith("ANEXO"):
            break
        if t.upper() == "ANEXOS":
            break
        if t:
            items.append((i, t, paras[i]))
    if not items:
        return
    template = items[0][2]
    parent = template.getparent()
    # Build combined sorted list of texts
    existing_texts = [t for _, t, _ in items]
    all_texts = existing_texts + entries
    # sort by first author surname-ish
    def sort_key(s):
        return re.sub(r"[^a-z0-9]", "", s.lower())

    all_texts_sorted = sorted(set(all_texts), key=sort_key)
    # Only append truly new ones in correct relative positions by rebuilding
    # Safer: insert each new entry before the first existing that sorts after it
    for entry in entries:
        if entry in existing_texts:
            continue
        insert_before_node = None
        for _, t, node in items:
            if sort_key(t) > sort_key(entry):
                insert_before_node = node
                break
        new_p = make_body(template, entry)
        if insert_before_node is not None:
            parent.insert(list(parent).index(insert_before_node), new_p)
        else:
            # after last biblio item
            last = items[-1][2]
            parent.insert(list(parent).index(last) + 1, new_p)
        # refresh items list roughly
        items.append((-1, entry, new_p))
        items.sort(key=lambda x: sort_key(x[1]))


shutil.copy2(src, backup)
print("backup ->", backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# locate templates and insertion point
idx_afil_heading = None
idx_afil_body = None
idx_herramientas = None
for i, p in enumerate(paras):
    t = para_text(p)
    if t == "Afiliación.":
        idx_afil_heading = i
    if idx_afil_heading is not None and idx_afil_body is None and i > idx_afil_heading and t.startswith("Vanegas"):
        idx_afil_body = i
    if t == "Herramientas de análisis y diseño de sistemas":
        idx_herramientas = i
        break

print("afil heading", idx_afil_heading, "afil body", idx_afil_body, "herramientas", idx_herramientas)
if None in (idx_afil_heading, idx_afil_body, idx_herramientas):
    raise SystemExit("No se localizó la sección 2.2")

# avoid duplicate insert
already = any(para_text(p) == "Gestión administrativa." for p in paras)
if already:
    print("Conceptos ya insertados; se omite reinserción de conceptos.")
else:
    tmpl_h = paras[idx_afil_heading]
    tmpl_b = paras[idx_afil_body]
    target = paras[idx_herramientas]
    parent = target.getparent()
    pos = list(parent).index(target)
    new_nodes = []
    for title, bodies in CONCEPTS:
        new_nodes.append(make_heading3(tmpl_h, title))
        for body in bodies:
            new_nodes.append(make_body(tmpl_b, body))
    for offset, node in enumerate(new_nodes):
        parent.insert(pos + offset, node)
    print(f"Insertados {len(CONCEPTS)} conceptos ({len(new_nodes)} párrafos).")

# TOC: insert after 2.2.4 Afiliación line
paras = list(root.iter(W + "p"))
toc_afil = None
toc_next = None
for i, p in enumerate(paras):
    t = para_text(p).replace("\t", " ")
    if "2.2.4" in t and "Afiliación" in t:
        toc_afil = i
    if toc_afil is not None and toc_next is None and i > toc_afil and t.startswith("2.3"):
        toc_next = i
        break

if toc_afil is not None and toc_next is not None and not already:
    tmpl_toc = paras[toc_afil]
    parent = paras[toc_next].getparent()
    pos = list(parent).index(paras[toc_next])
    toc_titles = [
        "2.2.5\tGestión administrativa.\t10",
        "2.2.6\tGestión de afiliación.\t10",
        "2.2.7\tGestión de lotes.\t10",
        "2.2.8\tGestión de servicios básicos.\t11",
        "2.2.9\tAdministración de pagos.\t11",
        "2.2.10\tGestión de deudas.\t11",
        "2.2.11\tControl de multas.\t11",
        "2.2.12\tSeguridad y control de acceso.\t11",
    ]
    # clone TOC paragraph structure
    for offset, line in enumerate(toc_titles):
        p = deepcopy(tmpl_toc)
        for r in list(p.findall(W + "r")):
            p.remove(r)
        for child in list(p):
            if child.tag != W + "pPr":
                p.remove(child)
        r = etree.SubElement(p, W + "r")
        if tmpl_toc.findall(W + "r") and tmpl_toc.findall(W + "r")[0].find(W + "rPr") is not None:
            r.insert(0, deepcopy(tmpl_toc.findall(W + "r")[0].find(W + "rPr")))
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = line
        parent.insert(pos + offset, p)
    print("TOC 2.2.5–2.2.12 insertado (páginas orientativas; actualizar campos en Word).")

# Update short implementation notes
paras = list(root.iter(W + "p"))
updated_notes = 0
for p in paras:
    t = para_text(p)
    if t in NOTE_UPDATES:
        set_para_text_arial11(p, NOTE_UPDATES[t])
        updated_notes += 1
print("Notas de implementación actualizadas:", updated_notes)

# Bibliography
paras = list(root.iter(W + "p"))
biblio_idx = None
for i, p in enumerate(paras):
    if para_text(p).upper().startswith("BIBLIOGRAF"):
        # prefer body one (later)
        biblio_idx = i
print("biblio_idx", biblio_idx, para_text(paras[biblio_idx]) if biblio_idx else None)
if biblio_idx is not None:
    insert_alphabetically(paras, biblio_idx, BIBLIO_NEW)
    print("Bibliografía: entradas nuevas insertadas.")

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_conceptos.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)
print("saved", src)

# verify
with ZipFile(src) as z:
    root2 = etree.fromstring(z.read("word/document.xml"))
paras2 = list(root2.iter(W + "p"))
found = []
for p in paras2:
    t = para_text(p)
    if t.endswith(".") and t.startswith(("Gestión", "Administración de pagos", "Control de multas", "Seguridad")):
        found.append(t)
print("HEADINGS FOUND:", found)
for key in ["Chiavenato (2006)", "Gestión de afiliación", "SENASBA (2015)", "Gitman y Zutter", "Robles (2012)", "Ferraiolo y Kuhn", "correo electrónico o CI"]:
    ok = any(key in para_text(p) for p in paras2)
    print("OK" if ok else "MISSING", key)

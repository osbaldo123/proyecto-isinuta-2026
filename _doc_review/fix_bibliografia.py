# -*- coding: utf-8 -*-
"""
Limpia bibliografía: quita duplicados, títulos en español cuando aplica,
conserva estilo Bibliografa (sangría francesa) y añade fuentes citadas faltantes.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re
import unicodedata

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2.prev_biblio.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"


def para_text(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t")).strip()


def style(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def norm_key(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.lower())
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = re.sub(r"[^a-z0-9]+", "", s)
    return s


def author_year_key(s: str) -> str:
    m = re.match(r"^(.+?)\s*\((\d{4}|[^)]+)\)", s)
    if not m:
        return norm_key(s[:60])
    return norm_key(m.group(1)) + norm_key(str(m.group(2)))


def set_biblio_text(p, text: str):
    """Cambia solo el texto; conserva pPr (estilo Bibliografa + sangría)."""
    pPr = p.find(W + "pPr")
    # eliminar todo excepto pPr
    for child in list(p):
        if child.tag != W + "pPr":
            p.remove(child)
    r = etree.SubElement(p, W + "r")
    rPr = etree.SubElement(r, W + "rPr")
    fonts = etree.SubElement(rPr, W + "rFonts")
    fonts.set(W + "ascii", "Arial")
    fonts.set(W + "hAnsi", "Arial")
    fonts.set(W + "cs", "Arial")
    etree.SubElement(rPr, W + "sz").set(W + "val", "22")  # 11 pt
    etree.SubElement(rPr, W + "szCs").set(W + "val", "22")
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    # asegurar estilo
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    ps = pPr.find(W + "pStyle")
    if ps is None:
        ps = etree.SubElement(pPr, W + "pStyle")
    ps.set(W + "val", "Bibliografa")
    ind = pPr.find(W + "ind")
    if ind is None:
        ind = etree.SubElement(pPr, W + "ind")
    ind.set(W + "left", "720")
    ind.set(W + "hanging", "720")


def make_biblio_para(template, text: str):
    p = deepcopy(template)
    set_biblio_text(p, text)
    return p


# Traducciones / normalizaciones a español (si ya está en español se deja)
TRANSLATE = {
    "visual modeling with enterprise architect": "Modelado visual con Enterprise Architect",
    "laravel enterprise applications": "Aplicaciones empresariales con Laravel",
    "the relational model for database management": "El modelo relacional para la gestión de bases de datos",
    "database systems: design, implementation, and management": "Sistemas de bases de datos: diseño, implementación y administración",
    "javascript: the good parts and beyond": "JavaScript: lo esencial y más allá",
    "an introduction to database systems": "Introducción a los sistemas de bases de datos",
    "java how to program, early objects": "Cómo programar en Java",
    "visual studio code succinctly": "Visual Studio Code de forma concisa",
    "mysql cookbook": "Manual práctico de MySQL",
    "html and css: design and build websites": "HTML y CSS: diseño y construcción de sitios web",
    "fundamentals of database systems": "Fundamentos de sistemas de bases de datos",
    "building applications with vue.js": "Construcción de aplicaciones con Vue.js",
    "javascript: the definitive guide": "JavaScript: la guía definitiva",
    "gui architectures and patterns": "Arquitecturas y patrones de interfaces gráficas",
    "pro vue.js 3": "Vue.js 3 profesional",
    "the book of css3": "El libro de CSS3",
    "beginning php and mysql: from novice to professional": "PHP y MySQL desde cero hasta profesional",
    "next-generation tools for web engineers": "Herramientas de nueva generación para ingenieros web",
    "vue.js in action": "Vue.js en acción",
    "eloquent javascript": "JavaScript elocuente",
    "database design for mere mortals": "Diseño de bases de datos para no expertos",
    "laragon features and core advantages": "Características y ventajas principales de Laragon",
    "applying uml and patterns": "UML y patrones: una introducción al análisis y diseño orientado a objetos",
    "programming php: creating dynamic web pages": "Programación en PHP: creación de páginas web dinámicas",
    "database modeling and design": "Modelado y diseño de bases de datos",
    "database design, application development, and administration": "Diseño de bases de datos, desarrollo de aplicaciones y administración",
    "responsive web design": "Diseño web adaptable",
    "html, css, and javascript all in one": "HTML, CSS y JavaScript todo en uno",
    "cascading style sheets: the definitive guide": "Hojas de estilo en cascada: la guía definitiva",
    "visual studio code documentation manual": "Manual de documentación de Visual Studio Code",
    "high performance mysql architectures": "Arquitecturas de alto rendimiento con MySQL",
    "modern local web development environments": "Entornos modernos de desarrollo web local",
    "learning php, mysql & javascript": "Aprendiendo PHP, MySQL y JavaScript",
    "databases demystified": "Bases de datos sin misterios",
    "mysql reference manual and architecture guides": "Manual de referencia y guías de arquitectura de MySQL",
    "laravel: the framework for web artisans": "Laravel: el framework para artesanos web",
    "mastering visual studio code productivity": "Dominar la productividad con Visual Studio Code",
    "php solutions: dynamic web design made easy": "Soluciones PHP: diseño web dinámico facilitado",
    "software engineering: a practitioner's approach": "Ingeniería de software: un enfoque práctico",
    "visual mysql management with phpmyadmin": "Administración visual de MySQL con phpMyAdmin",
    "full-stack development infrastructure": "Infraestructura para el desarrollo full-stack",
    "database management systems": "Sistemas de gestión de bases de datos",
    "learning web design": "Aprendiendo diseño web",
    "advanced laravel web architecture": "Arquitectura web avanzada con Laravel",
    "the scrum guide: the definitive guide to scrum": "La Guía de Scrum: la guía definitiva de Scrum",
    "web-based database administration tools": "Herramientas web para administración de bases de datos",
    "database system concepts": "Conceptos de sistemas de bases de datos",
    "modern web development environments": "Entornos modernos de desarrollo web",
    "requirements engineering: a good practice guide": "Ingeniería de requisitos: guía de buenas prácticas",
    "beginning database design solutions": "Soluciones iniciales de diseño de bases de datos",
    "enterprise architect user guide": "Guía de usuario de Enterprise Architect",
    "phpmyadmin official documentation manual": "Manual oficial de documentación de phpMyAdmin",
    "php and mysql for dynamic web sites": "PHP y MySQL para sitios web dinámicos",
    "the object-oriented thought process": "El proceso de pensamiento orientado a objetos",
    "vue.js up and running": "Vue.js en marcha",
}


def to_spanish_entry(entry: str) -> str:
    # Extraer título entre ") . " y siguiente ". Ciudad" es frágil; usar heurística
    m = re.match(r"^(.+?\([^)]+\))\.\s*(.+)$", entry)
    if not m:
        return entry
    head, rest = m.group(1), m.group(2)
    # web con Obtenido de: traducir solo título si inglés
    if "Obtenido de" in rest or "obtenido de" in rest:
        # Título hasta ". Obtenido"
        m2 = re.match(r"^(.+?)\.\s*(Obtenido de.+)$", rest, re.I)
        if m2:
            title, tail = m2.group(1), m2.group(2)
            key = title.lower().strip()
            if key in TRANSLATE:
                title = TRANSLATE[key]
            return f"{head}. {title}. {tail[0].upper() + tail[1:] if tail else tail}".replace("Obtenido de", "Obtenido de")
        return entry

    # libro: Title. Ciudad: Editorial.
    m3 = re.match(r"^(.+?)\.\s+([A-ZÁÉÍÓÚÜÑ].+)$", rest)
    if not m3:
        return entry
    title, loc = m3.group(1), m3.group(2)
    key = title.lower().strip()
    if key in TRANSLATE:
        title = TRANSLATE[key]
    # Si el título sigue claramente en inglés (muchas palabras inglesas comunes) y no tradujo
    return f"{head}. {title}. {loc}"


# Entradas citadas en conceptos que deben existir (español)
EXTRA = [
    "Basurto, N. M. (2020). Aplicación informática para la gestión administrativa del “Hotel Paraíso”. Jipijapa, Manabí, Ecuador: Universidad Estatal del Sur de Manabí.",
    "Centro Internacional de Formación de la OIT. (2022). Estrategias y políticas de afiliación: Un manual para organizaciones. Turín, Italia: ITCILO.",
    "Chiavenato, I. (2006). Administración: proceso administrativo (3.ª ed.). México: McGraw-Hill.",
    "Definición.de. (2023). Asociación. Obtenido de Definición.de: https://definicion.de/asociacion/",
    "Ferraiolo, D. F., & Kuhn, D. R. (1992). Controles de acceso basados en roles. 15th National Computer Security Conference (pp. 554-563). Baltimore, MD: NIST.",
    "Gitman, L. J., & Zutter, C. J. (2012). Principios de administración financiera (12.ª ed.). México: Pearson.",
    "Real Academia Española. (2014). Diccionario de la lengua española (23.ª ed.). Madrid, España: Espasa.",
    "Robles, C. (2012). Gestión de cobranza: un abordaje teórico desde el ámbito financiero. Revista de Administración y Turismo, 1(1), 40-52.",
    "SENASBA. (2015). Modelos de gestión. La Paz, Bolivia: Ministerio de Medio Ambiente y Agua / SENASBA.",
    "Solórzano Trejo, M. del C. (2021). Trabajo previo a la obtención del título de magíster en administración de empresas, mención en gestión de procesos organizacionales. Santo Domingo, Ecuador: Universidad UTE.",
    "Universidad de Piura. (2010). Guía para la gestión de las Juntas Administradoras de Servicios de Saneamiento. Piura, Perú: Universidad de Piura.",
    "Vanegas Calderón, C. A., Villarraga, Y. V., & Zapata Sotto, B. A. (2022). Estudio de afiliados para diseñar estrategias de crecimiento en la Fundación Dignidad por los Héroes de Colombia (FUNDIHECO). Bogotá, Colombia: Universidad Cooperativa de Colombia.",
]


def prefer_spanish(a: str, b: str) -> str:
    """Entre dos similares, prefiere la que ya está más en español."""
    eng_markers = [" the ", " and ", " with ", " for ", " guide", " systems", " design", " learning", " beginning", " advanced", " modern ", " official "]
    def score(s):
        low = f" {s.lower()} "
        return sum(1 for m in eng_markers if m in low)
    return a if score(a) <= score(b) else b


shutil.copy2(src, backup)
print("backup ->", backup)

with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))

# Localizar bloque bibliografía
biblio_heading = None
for i, p in enumerate(paras):
    if para_text(p).upper().startswith("BIBLIOGRAF") and style(p) == "Ttulo1":
        biblio_heading = i
print("heading", biblio_heading, para_text(paras[biblio_heading]))

biblio_nodes = []
anexos_node = None
for i in range(biblio_heading + 1, len(paras)):
    t = para_text(paras[i])
    st = style(paras[i])
    if st == "Ttulo1" or t.upper() == "ANEXOS":
        anexos_node = paras[i]
        break
    if st == "Bibliografa" and t:
        biblio_nodes.append(paras[i])

print("biblio entries raw", len(biblio_nodes))
template = biblio_nodes[0]

# Normalizar entradas actuales
raw_entries = [para_text(p) for p in biblio_nodes]

# Correcciones puntuales de autores mal escritos / duplicados conocidos
FIXES = {
    "schwaberken": None,  # eliminar versión mal escrita; se conserva Schwaber, K. & Sutherland
}

normalized = []
for e in raw_entries:
    # Fix Metropolitana
    if e.startswith("Metropolitana), U."):
        e = "Universidad Autónoma Metropolitana. (2020). Introducción al desarrollo de software. Ciudad de México, México: UAM."
    # Fix Sparx
    if e.startswith("Systems, S."):
        e = "Sparx Systems. (2022). Guía de usuario de Enterprise Architect. Creswick, Victoria, Australia: Sparx Systems Pty Ltd."
    # Fix phpMyAdmin team
    if e.startswith("Team, p."):
        e = "phpMyAdmin Project. (2024). Manual oficial de documentación de phpMyAdmin. Ginebra, Suiza: phpMyAdmin Project."
    # Fix Solórzano ALL CAPS author form
    if e.upper().startswith("MARIBEL DEL CARMEN"):
        e = EXTRA[9]  # Solórzano Trejo
        normalized.append(e)
        continue
    # Fix Basurto incomplete
    if e.startswith("Basurto, N. M."):
        e = EXTRA[0]
        normalized.append(e)
        continue
    # Schwaber mal escrito
    if e.startswith("Schwaber Ken"):
        continue
    # Pressman inglés si ya hay español
    if "Software Engineering: A Practitioner" in e:
        continue
    # Sommerville inglés requirements - traducir
    e = to_spanish_entry(e)
    normalized.append(e)

normalized.extend(EXTRA)

# Deduplicar por autor+año, preferir español
by_key = {}
for e in normalized:
    k = author_year_key(e)
    # claves especiales para Pressman 2021
    if k not in by_key:
        by_key[k] = e
    else:
        by_key[k] = prefer_spanish(by_key[k], e)

# Segunda pasada: duplicados exactos de título muy parecido Coronel/Date etc. ya cubiertos por author_year

final = sorted(by_key.values(), key=lambda s: norm_key(s))
print("final count", len(final))

parent = template.getparent()
# Actualizar nodos existentes; crear/borrar según necesidad
n_old = len(biblio_nodes)
n_new = len(final)

for i, text in enumerate(final):
    if i < n_old:
        set_biblio_text(biblio_nodes[i], text)
    else:
        # insertar antes de ANEXOS
        new_p = make_biblio_para(template, text)
        if anexos_node is not None:
            parent.insert(list(parent).index(anexos_node), new_p)
        else:
            parent.append(new_p)

# Eliminar sobrantes
if n_new < n_old:
    for p in biblio_nodes[n_new:]:
        parent.remove(p)

print("updated", min(n_old, n_new), "inserted", max(0, n_new - n_old), "removed", max(0, n_old - n_new))

new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_biblio.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)

try:
    shutil.copy2(tmp, src)
    print("saved", src)
except PermissionError:
    alt = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO - corregido 2 - biblio.docx")
    shutil.copy2(tmp, alt)
    print("LOCKED; saved", alt)

# verify
with ZipFile(tmp) as z:
    root2 = etree.fromstring(z.read("word/document.xml"))
paras2 = list(root2.iter(W + "p"))
entries2 = []
seen_b = False
for p in paras2:
    t = para_text(p)
    st = style(p)
    if st == "Ttulo1" and t.upper().startswith("BIBLIO"):
        seen_b = True
        continue
    if seen_b:
        if st == "Ttulo1" or t.upper() == "ANEXOS":
            break
        if st == "Bibliografa" and t:
            entries2.append(t)

print("VERIFY count", len(entries2))
# duplicates check
keys = [author_year_key(e) for e in entries2]
from collections import Counter
dups = [k for k, c in Counter(keys).items() if c > 1]
print("dup keys", len(dups), dups[:5])
print("first3:")
for e in entries2[:3]:
    print(" ", e[:120])
print("has Chiavenato", any("Chiavenato" in e for e in entries2))
print("has Schwaber Ken", any(e.startswith("Schwaber Ken") for e in entries2))
print("has Coronel once", sum(1 for e in entries2 if e.startswith("Coronel")))

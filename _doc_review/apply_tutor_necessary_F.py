# -*- coding: utf-8 -*-
"""
Aplica en PROYECTO MERCADO -F.docx solo observaciones del tutor
necesarias y coherentes con el sistema real (sin diagramas UML ni
recaptura masiva). Arial 11 + interlineado 1,5 en párrafos tocados.
"""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
from lxml import etree
from copy import deepcopy
import shutil
import tempfile
import re

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
backup = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.prev_tutor_fixes.docx")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
XML_SPACE = "{http://www.w3.org/XML/1998/namespace}space"

log = []


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


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
    # quitar highlight
    for hl in list(rPr.findall(W + "highlight")):
        rPr.remove(hl)


def set_spacing15(p, justify=True):
    pPr = p.find(W + "pPr")
    if pPr is None:
        pPr = etree.Element(W + "pPr")
        p.insert(0, pPr)
    sp = pPr.find(W + "spacing")
    if sp is None:
        sp = etree.SubElement(pPr, W + "spacing")
    sp.set(W + "line", "360")
    sp.set(W + "lineRule", "auto")
    if justify:
        jc = pPr.find(W + "jc")
        if jc is None:
            jc = etree.SubElement(pPr, W + "jc")
        jc.set(W + "val", "both")


def set_para_text(p, text, justify=True):
    runs = p.findall(W + "r")
    if not runs:
        r = etree.SubElement(p, W + "r")
        set_arial11(r)
        t = etree.SubElement(r, W + "t")
        t.set(XML_SPACE, "preserve")
        t.text = text
        set_spacing15(p, justify=justify)
        return
    first = runs[0]
    rPr = first.find(W + "rPr")
    if rPr is None:
        rPr = etree.Element(W + "rPr")
        first.insert(0, rPr)
    set_arial11(first)
    for r in runs[1:]:
        p.remove(r)
    for child in list(first):
        if child.tag != W + "rPr":
            first.remove(child)
    t = etree.SubElement(first, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    set_spacing15(p, justify=justify)


def make_para(template, text, style=None, hanging=False, justify=None):
    p = etree.Element(W + "p")
    pPr_src = template.find(W + "pPr")
    pPr = etree.SubElement(p, W + "pPr")
    if pPr_src is not None:
        for child in pPr_src:
            if child.tag not in (W + "pStyle", W + "shd"):
                pPr.append(deepcopy(child))
    if style:
        ps = etree.SubElement(pPr, W + "pStyle")
        ps.set(W + "val", style)
    if justify is None:
        justify = style not in ("Ttulo3", "Ttulo2", "Ttulo1")
    set_spacing15(p, justify=justify)
    if hanging:
        ind = pPr.find(W + "ind")
        if ind is None:
            ind = etree.SubElement(pPr, W + "ind")
        ind.set(W + "left", "720")
        ind.set(W + "hanging", "720")
    r = etree.SubElement(p, W + "r")
    set_arial11(r)
    if style in ("Ttulo2", "Ttulo3"):
        # bold titles
        rPr = r.find(W + "rPr")
        b = rPr.find(W + "b")
        if b is None:
            etree.SubElement(rPr, W + "b")
    t = etree.SubElement(r, W + "t")
    t.set(XML_SPACE, "preserve")
    t.text = text
    return p


def replace_everywhere(paras, old, new, limit=None):
    n = 0
    for p in paras:
        t = pt(p)
        if old in t:
            set_para_text(p, t.replace(old, new))
            n += 1
            if limit and n >= limit:
                break
    return n


shutil.copy2(src, backup)
with ZipFile(src, "r") as zin:
    xml = zin.read("word/document.xml")
    other = {i.filename: zin.read(i.filename) for i in zin.infolist() if i.filename != "word/document.xml"}

root = etree.fromstring(xml)
paras = list(root.iter(W + "p"))
body = root.find(W + "body")

# ========== 1) Diagnóstico: datos cuantitativos + anexos + SISCOCAP ==========
# Insertar después del párrafo fundacional (262)
diag_extra = (
    "Según el registro cargado en el sistema, la asociación administra aproximadamente "
    "525 afiliados y 558 lotes organizados en 11 OTB o sindicatos. El personal administrativo "
    "atiende los cobros y reportes; el sistema de escritorio utilizado hasta la migración "
    "es SISCOCAP (ver ANEXO 6). La entrevista al personal administrativo (ver ANEXO 1) y los "
    "reportes físicos de cobros (ver ANEXO 2 y ANEXO 3) respaldan este diagnóstico."
)
if "525 afiliados" not in pt(paras[263]) and "525 afiliados" not in "\n".join(pt(p) for p in paras[260:275]):
    new_p = make_para(paras[262], diag_extra)
    paras[262].addnext(new_p)
    paras = list(root.iter(W + "p"))
    log.append("diagnostico cuantitativo + anexos")

# Tiempo verbal / organización
if pt(paras[263]).startswith("Se encuentra organizado"):
    set_para_text(paras[263], pt(paras[263]).replace("Se encuentra organizado", "Se encontraba organizada", 1))
    log.append("diagnostico pasado: se encontraba")

# ========== 2) Enfoque metodológico: entrevista + cita anexo ==========
for i, p in enumerate(paras):
    t = pt(p)
    if t.startswith("Para la recolección de información se emplearon instrumentos"):
        if "ver ANEXO 1" not in t:
            set_para_text(
                p,
                "Para la recolección de información se emplearon instrumentos como entrevistas a la "
                "secretaria de la Asociación (una persona, mayo de 2025; ver ANEXO 1) y revisión de "
                "documentos institucionales, tales como cuadernos de registro, recibos físicos y hojas "
                "de cálculo utilizadas en la institución. Estos instrumentos permitieron identificar "
                "necesidades, dificultades y procesos relacionados con la gestión administrativa existente. "
                "El enfoque fue mixto porque combinó la recolección y el análisis de datos cualitativos y "
                "cuantitativos en un mismo estudio (Hernández Sampieri et al., 2014): cualitativo, en la "
                "recolección de información mediante estos instrumentos, y cuantitativo, en el registro de "
                "montos, multas y reportes del proceso administrativo."
            )
            log.append("enfoque metodologico + ANEXO 1")
        break

# ========== 3) Definición.de: quitar (p. 16) ==========
n = replace_everywhere(paras, "”(p. 16).", "”. ")
n += replace_everywhere(paras, "”(p. 16)", "”")
n += replace_everywhere(paras, "(p. 16).", ".")
n += replace_everywhere(paras, "(p. 16)", "")
if n:
    log.append(f"quitar p.16 Definicion.de ({n})")

# ========== 4) Scrum: paráfrasis sin comillas ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    if "Scrum está compuesto por tres roles" in t and "“" in t:
        rest = ""
        if "Los roles de Scrum" in t:
            rest = " " + t[t.find("Los roles de Scrum") :]
        set_para_text(
            p,
            "Schwaber y Sutherland (2020) señalan que el equipo Scrum se organiza en torno a las "
            "responsabilidades de Product Owner, Scrum Master y Developers, quienes colaboran para "
            "maximizar el valor del producto, organizar el trabajo y aplicar el marco ágil en proyectos complejos."
            + rest,
        )
        log.append("scrum roles parafrasis")
    elif "Scrum define eventos como Sprint" in t and "“" in t:
        rest = ""
        if "Los eventos de Scrum" in t:
            rest = " " + t[t.find("Los eventos de Scrum") :]
        set_para_text(
            p,
            "Schwaber y Sutherland (2020) describen los eventos Sprint, Sprint Planning, Daily Scrum, "
            "Sprint Review y Sprint Retrospective como espacios de inspección y adaptación continua, "
            "que promueven la transparencia, la coordinación del equipo y la mejora del proceso."
            + rest,
        )
        log.append("scrum eventos parafrasis")
    elif "artefactos de Scrum incluyen Product Backlog" in t and "“" in t:
        rest = ""
        if "Los artefactos de Scrum facilitan" in t:
            rest = " " + t[t.find("Los artefactos de Scrum facilitan") :]
        set_para_text(
            p,
            "Schwaber y Sutherland (2020) indican que los artefactos Product Backlog, Sprint Backlog e "
            "Incremento representan el trabajo, las prioridades y el resultado del equipo, y aportan "
            "transparencia en la entrega de valor."
            + rest,
        )
        log.append("scrum artefactos parafrasis")

# Título roles -> responsabilidades (si existe)
for p in paras:
    if pt(p).strip() in ("Roles de la metodología Scrum.", "Roles de la metodología Scrum"):
        set_para_text(p, "Responsabilidades del equipo Scrum.", justify=False)
        log.append("titulo responsabilidades scrum")

# ========== 5) Antecedentes: párrafo de relación ==========
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    if "Paco Tamo (2025)" in pt(p):
        # insert after if not already
        nxt = pt(paras[i + 1]) if i + 1 < len(paras) else ""
        if "relación con el presente proyecto" not in nxt.lower() and "Como referencia para el presente" not in nxt:
            rel = (
                "Como referencia para el presente proyecto se tomaron de estos antecedentes la idea de "
                "centralizar cobros y afiliados en una aplicación web. El sistema de Isinuta se diferencia "
                "porque automatiza el pase de lista por OTB con multa por lote, la multa por mora acumulable "
                "(Bs. 50, 100 y 150), la transferencia del lote sin deudas y los reportes de ingresos y deudas "
                "por concepto."
            )
            new_p = make_para(p, rel)
            p.addnext(new_p)
            log.append("relacion proyectos relacionados")
        break

# ========== 6) Product Owner justificación ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    if t.startswith("Javier Mercado Huerta: desempeña el rol de Product Owner y Developer"):
        set_para_text(
            p,
            "Javier Mercado Huerta: desempeña el rol de Product Owner y Developer. En el contexto académico "
            "del proyecto de grado, el postulante recopiló los requerimientos con la mesa directiva, priorizó "
            "el Product Backlog e implementó los módulos; el presidente de la Asociación validó las prioridades "
            "en las revisiones de cada Sprint, sin asumir la codificación.",
        )
        log.append("product owner justificacion")
        break

# ========== 7) Roles del sistema (alineado al seeder real) ==========
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    t = pt(p)
    if t.startswith("Secretaria: registra y actualiza afiliados"):
        # ensure ends with period and add consulta role after
        if not t.rstrip().endswith("."):
            set_para_text(p, t.rstrip() + ".")
        nxt = pt(paras[i + 1]) if i + 1 < len(paras) else ""
        if "Consulta de pagos y multas" not in nxt and "consulta de pagos" not in nxt.lower():
            extra = (
                "Consulta de pagos y multas: rol de solo lectura para consultar recibos y multas, "
                "sin registrar cobros ni modificar catálogos. En el sistema también pueden existir roles "
                "adicionales de prueba; los roles documentados para la operación son presidente, secretaria "
                "y consulta de pagos y multas."
            )
            new_p = make_para(p, extra)
            p.addnext(new_p)
            log.append("rol consulta documentado")
        break

# ========== 8) Login = correo (sistema real AccesoSistema; CI de afiliados desactivado) ==========
login_repls = [
    ("correo electrónico o CI y contraseña", "correo electrónico y contraseña"),
    ("correo electrónico o CI", "correo electrónico"),
    ("con correo electrónico o CI", "con correo electrónico"),
]
paras = list(root.iter(W + "p"))
for old, new in login_repls:
    n = replace_everywhere(paras, old, new)
    if n:
        log.append(f"login '{old}' -> '{new}' ({n})")

# ========== 9) HU-08 Gestionar Pagos (unir celdas-texto) ==========
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    if pt(p).strip() == "Gestionar" and i + 1 < len(paras) and pt(paras[i + 1]).strip() == "Pagos":
        set_para_text(p, "Gestionar Pagos", justify=False)
        set_para_text(paras[i + 1], "", justify=False)
        log.append("HU-08 Gestionar Pagos unido")
        break

# ========== 10) Fig 32 nota: recibo de caja (captura real) + aclaración ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    if t.startswith("Nota: Recibo de ingreso generado por el sistema"):
        set_para_text(
            p,
            "Nota: Recibo emitido por el sistema (en la captura, recibo de caja), con el número de comprobante, "
            "el afiliado, el concepto y el importe, y con los botones Imprimir y Excel. En el cobro, el agua y el "
            "alcantarillado se emiten como recibo de ingreso; las multas y la afiliación, como recibo de caja.",
        )
        log.append("nota fig32 ingreso/caja")
        break

# Fig 28 nota refuerzo
for p in paras:
    t = pt(p)
    if t.startswith("Nota: Interfaz de cobranza donde, al seleccionar el lote"):
        set_para_text(
            p,
            "Nota: Interfaz de cobranza (Cobrar) con un lote seleccionado: el sistema muestra las mensualidades "
            "vencidas, la multa por mora pendiente (por ejemplo Bs. 50 al cumplirse tres meses) y el total a cobrar, "
            "separando recibos de ingreso (servicios) y de caja (multas).",
        )
        log.append("nota fig28 mora visible")
        break

# ========== 11) TOTAL SPRINT 3 y 4 ==========
paras = list(root.iter(W + "p"))
for i, p in enumerate(paras):
    t = pt(p)
    if t.startswith("Nota: La tabla presenta las tareas necesarias para el Sprint 4"):
        if "TOTAL SPRINT 4" not in "\n".join(pt(x) for x in paras[max(0, i - 5) : i + 3]):
            tot = make_para(p, "TOTAL SPRINT 4: 10 días", justify=False)
            p.addprevious(tot)
            log.append("TOTAL SPRINT 4")
        break

paras = list(root.iter(W + "p"))
# buscar nota del backlog 3
for i, p in enumerate(paras):
    t = pt(p)
    if "Sprint Backlog 3" in t or (t.startswith("Nota:") and "Sprint 3" in t and "tareas" in t.lower()):
        pass
# insert after last task near HU-09 / before diagrama sprint 3
for i, p in enumerate(paras):
    t = pt(p)
    if t.strip().startswith("Figura 26:") or "Diagrama de casos de uso del Sprint 3" in t:
        # look backward for a good anchor - paragraph before diagrams intro
        prev = paras[i - 1] if i else p
        block = "\n".join(pt(x) for x in paras[max(0, i - 8) : i])
        if "TOTAL SPRINT 3" not in block:
            tot = make_para(prev, "TOTAL SPRINT 3: 26 días", justify=False)
            p.addprevious(tot)
            log.append("TOTAL SPRINT 3")
        break

# ========== 12) Definición de hecho Sprint 3 en viñetas ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    if t.startswith("El Sprint 3 se considera terminado cuando el sistema permita: registrar el trabajo"):
        set_para_text(
            p,
            "El Sprint 3 se consideró terminado cuando el sistema permitió:",
        )
        bullets = [
            "Registrar el trabajo de la OTB indicada por la secretaria (tipo, fecha, descripción y monto de multa).",
            "Mostrar la lista de afiliados de esa OTB y marcar asistencia; generar multa por cada lote ausente.",
            "Cobrar esa multa en un recibo de caja del lote.",
            "Gestionar pagos de servicio con recibo de ingreso (agua y alcantarillado) y afiliación con recibo de caja.",
            "Consultar el estado de cuenta del lote y transferir el lote sin deudas a un nuevo titular.",
            "Registrar y consultar multas por mora e inasistencia.",
        ]
        anchor = p
        for b in reversed(bullets):
            bp = make_para(p, "• " + b)
            anchor.addnext(bp)
        log.append("definicion hecho sprint3 viñetas")
        break

# ========== 13) 3.7 y 3.8 antes del Capítulo IV ==========
paras = list(root.iter(W + "p"))
insert_at = None
for i, p in enumerate(paras):
    if "CAPÍTULO IV" in pt(p).upper() and "CONCLUS" in pt(p).upper():
        insert_at = p
        break

if insert_at is not None and "3.7 Pruebas realizadas" not in "\n".join(pt(p) for p in paras):
    tmpl = paras[1412] if len(paras) > 1412 else paras[255]
    blocks = [
        ("Ttulo3", "3.7 Pruebas realizadas."),
        (
            None,
            "Se ejecutaron 22 pruebas de funcionamiento distribuidas en los cuatro Sprint "
            "(Sprint 1: 6; Sprint 2: 3; Sprint 3: 7; Sprint 4: 6), según las tablas 14, 16, 18 y 20. "
            "Todas las pruebas registradas resultaron satisfactorias en el entorno de validación. "
            "Además, la secretaria y el presidente revisaron los módulos de cobro, multas y reportes "
            "en una prueba de aceptación funcional del sistema en operación.",
        ),
        (
            None,
            "Tabla 21: Resumen de pruebas de funcionamiento por Sprint",
        ),
        (
            None,
            "Sprint 1 (roles, usuarios, afiliados y tipos de afiliación): 6 pruebas — satisfactorio. "
            "Sprint 2 (OTB y servicios por categoría): 3 pruebas — satisfactorio. "
            "Sprint 3 (trabajos, pagos y multas): 7 pruebas — satisfactorio. "
            "Sprint 4 (reportes de ingresos y deudas): 6 pruebas — satisfactorio. "
            "Total: 22 pruebas satisfactorias.",
        ),
        ("Ttulo3", "3.8 Análisis de resultados."),
        (
            None,
            "La comparación entre el proceso anterior (cuadernos, Excel y SISCOCAP) y el sistema web "
            "mostró mejoras en los tiempos de atención administrativa. El registro de un afiliado con "
            "su lote pasó de un trámite fragmentado en varios soportes a una carga única en el módulo "
            "de afiliados. El cobro con emisión de recibo consolidó en una sola pantalla las cuotas, "
            "multas y el cambio, con impresión inmediata. El pase de lista por OTB dejó de depender "
            "solo del cuaderno y genera la multa por lote ausente de forma automática. La elaboración "
            "de un reporte de ingresos o deudas, que antes exigía consolidar varios archivos, se obtiene "
            "con filtros de fecha o concepto y exportación a Excel o impresión.",
        ),
        (
            None,
            "Estos resultados se relacionan con los objetivos específicos: el control de afiliados y "
            "tipos de afiliación (objetivo 1), la organización de OTB y tarifas por categoría (objetivo 2), "
            "la automatización de trabajos, pagos y multas (objetivo 3) y la generación de reportes para "
            "la mesa directiva (objetivo 4).",
        ),
    ]
    # insert in reverse before Capítulo IV
    for style, text in reversed(blocks):
        np = make_para(tmpl, text, style=style)
        insert_at.addprevious(np)
    log.append("secciones 3.7 y 3.8")

# ========== 14) Conclusiones con datos ==========
paras = list(root.iter(W + "p"))
conc = {
    "Se desarrolló el sistema web para la gestión administrativa de la Asociación de Agua Potable y Alcantarillado Isinuta, permitiendo automatizar el control de afiliados, pagos, multas y reportes durante la gestión 2026. La solución mejora la organización de los procesos administrativos mediante información confiable y disponible para la toma de decisiones de la mesa directiva.": (
        "Se desarrolló el sistema web para la gestión administrativa de la Asociación de Agua Potable y "
        "Alcantarillado Isinuta, automatizando el control de afiliados, pagos, multas y reportes durante la "
        "gestión 2026. La solución mejoró la organización de los procesos administrativos: las 22 pruebas de "
        "funcionamiento de los cuatro Sprint resultaron satisfactorias (apartado 3.7) y la información quedó "
        "disponible para la mesa directiva mediante los reportes de ingresos y deudas."
    ),
}
for p in paras:
    t = pt(p)
    for old, new in conc.items():
        if t.startswith("Se desarrolló el sistema web para la gestión administrativa"):
            set_para_text(p, new)
            log.append("conclusion general con datos")
            break

# objetivos -> menciones 3.8
for p in paras:
    t = pt(p)
    if t.startswith("Se desarrollaron los módulos de roles, usuarios, afiliados"):
        set_para_text(
            p,
            t.rstrip(".")
            + ", conforme a las pruebas del Sprint 1 (Tabla 14) y al análisis del apartado 3.8.",
        )
        log.append("conclusion obj1 dato")
    elif t.startswith("Se implementaron los módulos de OTB y de servicios"):
        set_para_text(
            p,
            t.rstrip(".")
            + ", validados en las pruebas del Sprint 2 (Tabla 16).",
        )
        log.append("conclusion obj2 dato")
    elif t.startswith("Se codificaron los módulos de trabajos, pagos y multas"):
        set_para_text(
            p,
            t.rstrip(".")
            + ", con las siete pruebas del Sprint 3 (Tabla 18) satisfactorias.",
        )
        log.append("conclusion obj3 dato")
    elif t.startswith("Se implementó el módulo de reportes"):
        set_para_text(
            p,
            t.rstrip(".")
            + ", respaldado por las seis pruebas del Sprint 4 (Tabla 20).",
        )
        log.append("conclusion obj4 dato")

# ========== 15) Bibliografía: Basurto, Sampieri, títulos tesis ==========
paras = list(root.iter(W + "p"))
biblio_i = next(i for i, p in enumerate(paras) if st(p) == "Ttulo1" and pt(p).upper().startswith("BIBLIO"))
biblio_paras = []
for i in range(biblio_i + 1, len(paras)):
    t = pt(paras[i])
    s = st(paras[i])
    if s == "Ttulo1" or t.upper() == "ANEXOS":
        break
    if s == "Bibliografa" and t:
        biblio_paras.append(paras[i])

for p in biblio_paras:
    t = pt(p)
    if t.startswith("Basurto, N. M."):
        set_para_text(
            p,
            t.replace("Basurto, N. M.", "Macías Basurto, N.", 1),
            justify=True,
        )
        # hanging
        set_spacing15(p)
        log.append("biblio Macias Basurto")
    if "Universidad Continetal" in t:
        set_para_text(p, t.replace("Universidad Continetal", "Universidad Continental"))
        log.append("biblio Continental")
    if "Instituto Tecnologico" in t:
        set_para_text(p, t.replace("Instituto Tecnologico", "Instituto Tecnológico"))
        log.append("biblio Tecnologico")
    if t.startswith("Paco Tamo") and "APLICACIÓN WEB" in t:
        set_para_text(
            p,
            "Paco Tamo, D. (2025). Aplicación web para la administración de cobros de los afiliados de la OTB Porvenir. "
            "Eterazama, Cochabamba, Bolivia: Instituto Tecnológico Eterazama.",
        )
        log.append("biblio Paco titulo minusculas")
    if t.startswith("Solarez Chico") and "SISTEMA WEB PARA LA ADMINISTRACIÓN" in t:
        set_para_text(
            p,
            "Solarez Chico, N. L. (2023). Sistema web para la administración de información y control de la "
            "recaudación de recursos del suministro de agua potable. El Alto, Bolivia: Universidad Continental.",
        )
        log.append("biblio Solarez titulo")

existing = "\n".join(pt(p) for p in biblio_paras)
if "Hernández Sampieri" not in existing and "Hernandez Sampieri" not in existing:
    entry = (
        "Hernández Sampieri, R., Fernández Collado, C., & Baptista Lucio, P. (2014). "
        "Metodología de la investigación (6.ª ed.). México: McGraw-Hill."
    )
    template = biblio_paras[0]
    new_p = make_para(template, entry, style="Bibliografa", hanging=True)
    # insert alphabetically near H
    inserted = False
    for bp in biblio_paras:
        head = pt(bp).split(",")[0].lower()
        if head > "hernández sampieri":
            bp.addprevious(new_p)
            inserted = True
            break
    if not inserted:
        biblio_paras[-1].addnext(new_p)
    log.append("biblio Hernandez Sampieri")

# ========== 16) Anexos formato + fecha entrevista ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    m = re.match(r"^Anexo\s+(\d+)\.\s*(.+)$", t.strip())
    if m:
        set_para_text(p, f"ANEXO {m.group(1)}: {m.group(2)}", justify=False)
        log.append(f"formato {m.group(1)}")
    if t.startswith("Institución: Asociación") and "Entrevistado:" in t:
        set_para_text(
            p,
            "Institución: Asociación de Agua Potable y Alcantarillado Isinuta. "
            "Área: Administración. "
            "Entrevistado: Secretaria de la Asociación. "
            "Lugar: Isinuta – Cochabamba. "
            "Fecha: mayo de 2025. "
            "Entrevistador: Javier Mercado Huerta.",
        )
        log.append("anexo1 fecha y cargo")

# ========== 17) Nota pruebas Sprint 1 (secuencia) ==========
paras = list(root.iter(W + "p"))
for p in paras:
    t = pt(p)
    if t.startswith("Nota: Cada entrada de datos tiene su resultado de acuerdo a su uso correspondiente en el sistema."):
        # only first occurrence near table 14 - check nearby context by uniqueness: change all similar notes carefully
        pass

# Replace first Sprint Review note after table 14 more specifically
for i, p in enumerate(paras):
    if pt(p).startswith("En este Sprint Review se realizaron pruebas sobre la gestión de roles, usuarios"):
        # find following table note
        for j in range(i, min(i + 80, len(paras))):
            if pt(paras[j]).startswith("Nota: Cada entrada de datos tiene su resultado"):
                set_para_text(
                    paras[j],
                    "Nota: En el Sprint 1 se comprobó el alta de rol y de usuario, el inicio de sesión con "
                    "correo y contraseña, y la edición de datos del usuario, en ese orden, para evitar "
                    "inconsistencias de estado o de correo entre casos.",
                )
                log.append("nota pruebas sprint1 secuencia")
                break
        break

# ========== save ==========
new_xml = etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)
tmp = Path(tempfile.gettempdir()) / "mercado_F_tutor_fixes.docx"
with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
    for name, data in other.items():
        zout.writestr(name, data)
    zout.writestr("word/document.xml", new_xml)
shutil.copy2(tmp, src)

print("BACKUP", backup)
print("SAVED", src)
print("CHANGES:")
for x in log:
    print(" -", x)
print("total", len(log))

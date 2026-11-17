# -*- coding: utf-8 -*-
"""Auditoría de inconsistencias en PROYECTO MERCADO -F.docx"""
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import re
import unicodedata

src = Path(r"c:\Users\papu\Desktop\PROYECTO MERCADO -F.docx")
out = Path(r"c:\laragon\www\gest_admin_ASOCIACIÓN_AGUA _POTABLE_ALCANTARILLADO _ISINUTA\_doc_review\audit_F_errors.txt")
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"

root = etree.fromstring(ZipFile(src).read("word/document.xml"))
paras = list(root.iter(W + "p"))


def pt(p):
    return "".join(t.text or "" for t in p.findall(".//" + W + "t"))


def st(p):
    pPr = p.find(W + "pPr")
    if pPr is None:
        return ""
    ps = pPr.find(W + "pStyle")
    return ps.get(W + "val") if ps is not None else ""


def has_num(p):
    pPr = p.find(W + "pPr")
    return pPr is not None and pPr.find(W + "numPr") is not None


issues = []

# 1) Observaciones / notas de revisión
for i, p in enumerate(paras):
    t = pt(p)
    if re.search(r"Observaci[oó]n del tutor|OBSERVACIONES DEL TUTOR|pendiente de la revisi[oó]n|¡Error! Marcador", t, re.I):
        issues.append(("CRITICO", i, st(p), "Texto de revisión/error Word", t[:180]))

# 2) Títulos con doble numeración tipo "3.6.5 3.7 ..."
for i, p in enumerate(paras):
    t = pt(p).strip()
    if re.match(r"^\d+\.\d+(\.\d+)?\s+\d+\.\d+", t):
        issues.append(("CRITICO", i, st(p), "Doble numeración en título", t[:120]))
    # estilo título + texto que ya trae número distinto
    if st(p) in ("Ttulo2", "Ttulo3", "Ttulo4") and re.match(r"^3\.\d", t) and has_num(p):
        issues.append(("ALTO", i, st(p), "Título con numPr + número en texto (puede generar 3.6.5 3.7)", t[:120]))

# 3) Referencias a apartados inexistentes
full = "\n".join(pt(p) for p in paras)
for ref in ["apartado 3.7", "apartado 3.8", "Tabla 21", "3.7 Pruebas", "3.8 Análisis", "3.8 Analisis"]:
    if ref.lower() in full.lower():
        issues.append(("ALTO", -1, "", f"Referencia residual: {ref}", ""))

# 4) Contenido ajeno al sistema ISINUTA
alien = [
    r"\bSIRAF\b",
    r"reconocimiento facial",
    r"MediaMTX",
    r"bimestre",
    r"Instituto Tecnol[oó]gico Eterazama(?!.*Paco)",  # ok in biblio Paco
    r"Hotel Para[ií]so",  # only ok in Basurto biblio cite context
    r"correo electr[oó]nico o CI",
    r"DomPDF|Snappy",
    r"Thomas \(2023\)",
    r"Cajera",
    r"poll mendes|Goku Son",
    r"Centro Isinuta",
    r"Zona central de prueba",
]
for pat in alien:
    for i, p in enumerate(paras):
        t = pt(p)
        if re.search(pat, t, re.I):
            # allow biblio entries for related theses
            if st(p) == "Bibliografa" and re.search(r"Paco Tamo|Basurto|Mac[ií]as Basurto|Eterazama", t, re.I):
                continue
            issues.append(("MEDIO", i, st(p), f"Posible contenido ajeno/desactualizado: {pat}", t[:160]))

# 5) Guiones raros / placeholders
for i, p in enumerate(paras):
    t = pt(p)
    if "Texto?" in t or "????" in t or "[completar]" in t.lower() or "xxx" in t.lower():
        issues.append(("ALTO", i, st(p), "Placeholder", t[:120]))
    if "—" in t and st(p) in ("Ttulo2", "Ttulo3", "Normal", ""):
        # only flag if looks like our summary style
        if "satisfactorio" in t.lower() or re.search(r"pruebas\s*—", t, re.I):
            issues.append(("BAJO", i, st(p), "Guion largo —", t[:100]))

# 6) Estructura Cap III: secuencia de títulos principales
print_titles = []
for i, p in enumerate(paras):
    s = st(p)
    t = pt(p).strip()
    if s in ("Ttulo1", "Ttulo2", "Ttulo3") and t:
        print_titles.append((i, s, t[:100], has_num(p)))

# 7) Párrafos vacíos raros con solo espacios en títulos
for i, p in enumerate(paras):
    t = pt(p).strip()
    s = st(p)
    if s in ("Ttulo2", "Ttulo3", "Ttulo4") and len(t) < 3:
        issues.append(("MEDIO", i, s, "Título vacío", repr(t)))

# 8) Inconsistencias mora / login / recibos
checks = {
    "mora dia1": "día 1 del mes siguiente" in full,
    "login CI residual": bool(re.search(r"correo electr[oó]nico o CI", full, re.I)),
    "p.16 Definicion": "(p. 16)" in full and "Definición" in full,
}

# 9) Anexos formato
anexo_titles = [pt(p) for p in paras if re.match(r"^ANEXO\s+\d|^Anexo\s+\d", pt(p).strip())]

# 10) Calibri / fuente rara en runs cercanos a implementacion (sample)
calibri = 0
for p in paras:
    for r in p.findall(W + "r"):
        rPr = r.find(W + "rPr")
        if rPr is None:
            continue
        fonts = rPr.find(W + "rFonts")
        if fonts is not None:
            for attr in (W + "ascii", W + "hAnsi"):
                if (fonts.get(attr) or "").lower() == "calibri":
                    calibri += 1
                    break

lines = []
lines.append(f"Archivo: {src}")
lines.append(f"Párrafos: {len(paras)}")
lines.append(f"Issues: {len(issues)}")
lines.append(f"Runs Calibri: {calibri}")
lines.append(f"Anexos: {anexo_titles}")
lines.append(f"Checks: {checks}")
lines.append("")
lines.append("=== TITULOS Cap (Ttulo1/2/3) ===")
for i, s, t, n in print_titles:
    if "CAPÍTULO" in t.upper() or t.startswith("3.") or "Ejecución" in t or "Conclus" in t or "Prueba" in t or "Análisis" in t or "Sprint" in t and s != "Ttulo4":
        lines.append(f"{i}|{s}|num={n}|{t}")

lines.append("")
lines.append("=== ISSUES ===")
# dedupe similar
seen = set()
for sev, i, s, kind, t in issues:
    key = (sev, kind, t[:80])
    if key in seen:
        continue
    seen.add(key)
    lines.append(f"[{sev}] para={i} style={s} | {kind}")
    if t:
        lines.append(f"    {t}")

out.write_text("\n".join(lines), encoding="utf-8")
print(out.read_text(encoding="utf-8")[:8000])
print("\n... saved", out, "total issues", len(seen))

#!/usr/bin/env python3
"""Consolida lotes JSON extraídos del documento Word en un maestro deduplicado."""

from __future__ import annotations

import json
import re
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
IMPORT_DIR = ROOT / "storage" / "app" / "import-docx"
OUTPUT = ROOT / "database" / "data" / "afiliados_documento.json"

ZONE_ALIASES = {
    "BARIO CENTRO": "BARRIO CENTRO",
    "BARIO 21": "BARRIO 21",
    "BARIO PRIMAVERA": "BARRIO PRIMAVERA",
    "S.ISINUTA": "SINDICATO ISINUTA",
    "S. ISINUTA": "SINDICATO ISINUTA",
    "SIDICATO URKUPIÑA": "SINDICATO URKUPIÑA",
    "SIDICATO URKUPINA": "SINDICATO URKUPIÑA",
}

SKIP_NAME_PATTERNS = re.compile(
    r"(CORTADO|BAÑO|MERCADO|IGLESIA|OFICINA|COOPERATIVA|ESCUELA|SEDE|GALPON|INSTITUTO)",
    re.I,
)


def normalize_zone(zona: str) -> str:
    z = " ".join(zona.upper().split())
    return ZONE_ALIASES.get(z, z)


def normalize_text(value: str) -> str:
    value = unicodedata.normalize("NFKD", value.upper().strip())
    value = "".join(c for c in value if not unicodedata.combining(c))
    return " ".join(value.split())


def dedupe_key(entry: dict) -> tuple[str, str]:
    return (
        normalize_text(entry["apellidos"]),
        normalize_text(entry["nombres"]),
    )


def title_case_name(value: str) -> str:
    parts = value.strip().split()
    return " ".join(p[:1].upper() + p[1:].lower() if p else p for p in parts)


def load_batches() -> list[dict]:
    batches: list[dict] = []
    for path in sorted(IMPORT_DIR.glob("batch_*.json")):
        data = json.loads(path.read_text(encoding="utf-8"))
        if isinstance(data, list):
            batches.extend(data)

    partial = IMPORT_DIR / "asistencia_image19-41.json"
    if partial.exists():
        batches.extend(json.loads(partial.read_text(encoding="utf-8")))

    return batches


def main() -> None:
    raw = load_batches()
    seen: dict[tuple[str, str], dict] = {}

    for entry in raw:
        zona = normalize_zone(entry.get("zona", ""))
        apellidos = normalize_text(entry.get("apellidos", ""))
        nombres = normalize_text(entry.get("nombres", ""))
        notas = (entry.get("notas") or "").strip()

        if not zona or not apellidos or not nombres:
            continue
        if SKIP_NAME_PATTERNS.search(nombres) or SKIP_NAME_PATTERNS.search(apellidos):
            continue

        key = dedupe_key(entry)
        if key not in seen:
            seen[key] = {
                "zona": zona,
                "apellidos": title_case_name(apellidos),
                "nombres": title_case_name(nombres),
                "notas": notas,
                "_zonas_extra": [],
            }
            continue

        existente = seen[key]
        if zona not in (existente["zona"], *existente["_zonas_extra"]):
            existente["_zonas_extra"].append(zona)

    merged: list[dict] = []
    for row in seen.values():
        notas = row["notas"]
        if row["_zonas_extra"]:
            extra = ", ".join(row["_zonas_extra"])
            notas = f"{notas} También en: {extra}.".strip() if notas else f"También en: {extra}."
        merged.append(
            {
                "zona": row["zona"],
                "apellidos": row["apellidos"],
                "nombres": row["nombres"],
                "notas": notas,
            }
        )

    merged.sort(key=lambda x: (x["zona"], x["apellidos"], x["nombres"]))
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(json.dumps(merged, ensure_ascii=False, indent=2), encoding="utf-8")

    zonas = {}
    for row in merged:
        zonas[row["zona"]] = zonas.get(row["zona"], 0) + 1

    print(f"Registros únicos: {len(merged)}")
    for zona, count in sorted(zonas.items()):
        print(f"  {zona}: {count}")
    print(f"Archivo: {OUTPUT}")


if __name__ == "__main__":
    main()

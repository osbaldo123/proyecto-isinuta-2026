#!/usr/bin/env python3
"""Analiza duplicados por nombre en los lotes crudos (antes de deduplicar)."""

import json
import re
import unicodedata
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
IMPORT_DIR = ROOT / "storage" / "app" / "import-docx"


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.upper().strip())
    s = "".join(c for c in s if not unicodedata.combining(c))
    return re.sub(r"\s+", " ", s)


def load_all() -> list[dict]:
    rows: list[dict] = []
    for path in sorted(IMPORT_DIR.glob("batch_*.json")):
        rows.extend(json.loads(path.read_text(encoding="utf-8")))
    partial = IMPORT_DIR / "asistencia_image19-41.json"
    if partial.exists():
        rows.extend(json.loads(partial.read_text(encoding="utf-8")))
    return rows


raw = load_all()
by_name = defaultdict(set)
for row in raw:
    key = (norm(row.get("apellidos", "")), norm(row.get("nombres", "")))
    if key[0] and key[1]:
        by_name[key].add(row.get("zona", ""))

multi = {k: v for k, v in by_name.items() if len(v) > 1}
print(f"Filas crudas: {len(raw)}")
print(f"Nombres unicos en crudo: {len(by_name)}")
print(f"Personas en mas de una zona: {len(multi)}")
print(f"Registros a eliminar por dedupe global: {len(raw) - len(by_name)} (aprox)")

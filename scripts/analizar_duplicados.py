#!/usr/bin/env python3
import json
import re
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JSON_PATH = ROOT / "database" / "data" / "afiliados_documento.json"


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s.upper().strip())
    s = "".join(c for c in s if not unicodedata.combining(c))
    return re.sub(r"\s+", " ", s)


data = json.loads(JSON_PATH.read_text(encoding="utf-8"))
by_name = defaultdict(list)
for row in data:
    by_name[(norm(row["apellidos"]), norm(row["nombres"]))].append(row["zona"])

cross = {k: v for k, v in by_name.items() if len(v) > 1}
print(f"Registros actuales: {len(data)}")
print(f"Nombres unicos: {len(by_name)}")
print(f"Nombres repetidos en distintas zonas: {len(cross)}")
for (a, n), zonas in sorted(cross.items())[:15]:
    print(f"  {a} {n} -> {zonas}")

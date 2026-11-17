# -*- coding: utf-8 -*-
import json
import subprocess
import sys

sys.stdout.reconfigure(encoding="utf-8")

# Tablas de la captura del usuario (sin cache/jobs/sessions/migrations)
KEEP = {
    "servicios", "servicio_categoria_tarifas", "categorias", "tipos_afiliacion",
    "lote_servicio", "lote_tipo_afiliacion", "lote_afiliados", "afiliados", "otbs",
    "cesiones_lote", "cuotas", "pago_detalles", "pagos", "multas", "recibo_sequences",
    "trabajos", "asistencias", "configuracion", "users", "activity_logs",
    "roles", "role_user", "permissions", "permission_role",
}

r = subprocess.run(
    [
        "mysql", "-h127.0.0.1", "-uroot", "db_agua_potable_isinuta", "-N", "-e",
        "SELECT TABLE_NAME, COLUMN_NAME, UPPER(COLUMN_TYPE), COLUMN_KEY "
        "FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA='db_agua_potable_isinuta' "
        "ORDER BY TABLE_NAME, ORDINAL_POSITION;",
    ],
    capture_output=True,
    text=True,
    encoding="utf-8",
    errors="replace",
)
tables = {}
for line in r.stdout.splitlines():
    parts = line.split("\t")
    if len(parts) < 4:
        continue
    t, c, typ, ck = parts[0], parts[1], parts[2], parts[3]
    if t not in KEEP:
        continue
    flag = "PK" if ck == "PRI" else ("FK" if ck == "MUL" or c.endswith("_id") else "")
    # simplify type display like Workbench
    typ_s = typ.upper()
    if typ_s.startswith("BIGINT"):
        typ_s = "BIGINT"
    elif typ_s.startswith("INT"):
        typ_s = "INT"
    elif typ_s.startswith("TINYINT"):
        typ_s = "TINYINT"
    elif typ_s.startswith("DECIMAL"):
        typ_s = typ_s  # keep precision
    elif typ_s.startswith("VARCHAR"):
        pass
    elif typ_s.startswith("TEXT"):
        typ_s = "TEXT"
    elif typ_s.startswith("TIMESTAMP") or typ_s.startswith("DATETIME"):
        typ_s = "TIMESTAMP"
    elif typ_s.startswith("DATE"):
        typ_s = "DATE"
    tables.setdefault(t, []).append([c, typ_s, flag])

out = Path = __import__("pathlib").Path
p = out(__file__).with_name("_schema_erd.json")
p.write_text(json.dumps(tables, ensure_ascii=False, indent=2), encoding="utf-8")
print("tables", len(tables), "->", p)
for t in sorted(tables):
    print(t, len(tables[t]))

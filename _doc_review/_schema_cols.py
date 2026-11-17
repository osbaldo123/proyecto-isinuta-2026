# -*- coding: utf-8 -*-
import subprocess
import sys
import json

sys.stdout.reconfigure(encoding="utf-8")

EXCLUDE = {
    "cache", "cache_locks", "sessions", "password_reset_tokens",
    "jobs", "job_batches", "failed_jobs", "migrations",
    "activity_logs", "recibo_sequences",
}

r = subprocess.run(
    [
        "mysql", "-h127.0.0.1", "-uroot", "db_agua_potable_isinuta", "-N", "-e",
        "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_KEY, DATA_TYPE, "
        "IF(COLUMN_KEY='PRI','PK', IF(COLUMN_KEY='MUL' OR COLUMN_NAME LIKE '%_id','FK','')) "
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
    if len(parts) < 5:
        continue
    t, c, ck, dt, role = parts[0], parts[1], parts[2], parts[3], parts[4]
    if t in EXCLUDE:
        continue
    # skip timestamps noise partially later
    tables.setdefault(t, []).append({"name": c, "type": dt, "role": role, "ck": ck})

print(json.dumps({k: [x["name"] for x in v] for k, v in tables.items()}, ensure_ascii=False, indent=2))

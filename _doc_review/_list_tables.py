# -*- coding: utf-8 -*-
import subprocess
import sys

sys.stdout.reconfigure(encoding="utf-8")

for db in ("db_isinuta_documento", "db_agua_potable_isinuta"):
    r = subprocess.run(
        ["mysql", "-h127.0.0.1", "-uroot", db, "-e", "SHOW TABLES;"],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    print("====", db, "====")
    print(r.stdout or r.stderr)

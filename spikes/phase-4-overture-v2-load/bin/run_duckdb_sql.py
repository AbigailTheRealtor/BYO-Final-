#!/usr/bin/env python3
"""Run one committed DuckDB SQL file (scripts/overture-v2/*.sql) with the `duckdb` Python package.

The recipe documents `duckdb -c ".read <file>"`. The pip package ships no CLI, so this is the same
thing: it strips `--` comment lines, executes the statements in order in one in-memory DuckDB
session, and prints the rows of the last statement if it returned any. It adds no SQL of its own,
opens no PostgreSQL connection and reads no credential. The committed SQL blanks any ambient AWS
credentials itself (an anonymous read of the public Overture bucket).

    python3 run_duckdb_sql.py <path/to/file.sql>
"""
import sys

import duckdb


def main():
    if len(sys.argv) != 2:
        print("usage: run_duckdb_sql.py <file.sql>", file=sys.stderr)
        return 2
    with open(sys.argv[1], encoding="utf-8") as fh:
        sql = "\n".join(line for line in fh.read().splitlines() if not line.strip().startswith("--"))
    con = duckdb.connect()
    result = None
    for statement in (s for s in sql.split(";") if s.strip()):
        result = con.execute(statement)
    if result is not None and result.description:
        for row in result.fetchall():
            print("\t".join("" if v is None else str(v) for v in row))
    return 0


if __name__ == "__main__":
    sys.exit(main())

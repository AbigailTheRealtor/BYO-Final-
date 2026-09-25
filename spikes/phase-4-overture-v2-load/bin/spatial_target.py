#!/usr/bin/env python3
# ============================================================================
# spatial_target.py  —  Overture v2 operator load · the ONE reading of SPATIAL_DATABASE_URL
# Spatial Intelligence Platform · Phase 4 — see ../RUNBOOK.md
# ----------------------------------------------------------------------------
# Every check this procedure applies to the target URL lives here, so bin/preflight.sh and
# bin/spatial_psql.sh cannot come to disagree about which URL is acceptable.
#
#   spatial_target.py check
#       Prints `OK <host> <port>` or `ERR <reason>`. Nothing else.
#   spatial_target.py service <path>
#       Applies the same checks, then writes a libpq service file (section [ov2_spatial]) at
#       <path>, created exclusively with mode 0600. Prints `OK` or `ERR <reason>`.
#
# The URL is read from the environment only — never from argv — and is never printed: no
# output line carries the URL, the password, the user, the host (except `check`'s host, which
# preflight.sh needs for the fingerprint and which a GitHub job has already masked) or an address.
# ============================================================================
import os
import re
import sys
import urllib.parse

SERVICE = "ov2_spatial"


def parse():
    """Return (fields, None) for an acceptable URL, or (None, reason)."""
    u = os.environ.get("SPATIAL_DATABASE_URL", "")
    try:
        s = urllib.parse.urlsplit(u)
        netloc_host = s.netloc.rsplit("@", 1)[-1].split(":", 1)[0]
        host = (s.hostname or "").lower()
        port = s.port or 5432
    except ValueError:
        return None, "url does not parse"
    db = urllib.parse.unquote(s.path.lstrip("/"))
    if s.scheme not in ("postgres", "postgresql"):
        return None, "scheme is not postgres/postgresql"
    if "," in s.netloc:
        return None, "multi-host URL"
    if not host:
        return None, "URL has no host (libpq would fall back to an ambient PGHOST)"
    if netloc_host != netloc_host.lower():
        return None, "host must be written in lowercase (log masking is case-sensitive)"
    if not re.fullmatch(r"[a-z0-9-]+(\.[a-z0-9-]+)*\.db\.postgresbridge\.com", host):
        return None, "host is not a *.db.postgresbridge.com Crunchy Bridge host"
    if host == "helium" or host.startswith("helium."):
        return None, "host is the application database"
    if not db or "heliumdb" in db.lower():
        return None, "database name is empty or the application database"
    # Only these query parameters. A `host=` / `hostaddr=` / `port=` / `dbname=` / `service=` /
    # `options=` parameter would reroute libpq away from the host checked above.
    params = urllib.parse.parse_qs(s.query, keep_blank_values=True)
    extra = sorted(set(params) - {"sslmode", "connect_timeout", "application_name"})
    if extra:
        return None, "URL carries a connection parameter this procedure does not allow: " + ", ".join(extra)
    sslmode = params.get("sslmode", ["require"])[-1]
    if sslmode not in ("require", "verify-ca", "verify-full"):
        return None, "sslmode must be require, verify-ca or verify-full"
    fields = {
        "host": host,
        "port": str(port),
        "dbname": db,
        "user": urllib.parse.unquote(s.username or ""),
        "password": urllib.parse.unquote(s.password or ""),
        "sslmode": sslmode,
    }
    for key in ("connect_timeout", "application_name"):
        if key in params:
            fields[key] = params[key][-1]
    return fields, None


def service_file(fields):
    """The service file text, or (None, reason) when a value cannot be written faithfully."""
    lines = ["[" + SERVICE + "]"]
    for key in ("host", "port", "dbname", "user", "password", "sslmode", "connect_timeout", "application_name"):
        value = fields.get(key, "")
        if value == "":
            continue
        # libpq reads `key=value` to the end of the line and strips trailing whitespace; there is
        # no quoting. A value it would read differently is refused, never altered.
        if any(c in value for c in "\r\n\0") or value != value.strip():
            return None, f"the URL's {key} cannot be written to a libpq service file (control character or edge whitespace)"
        line = f"{key}={value}"
        if len(line.encode()) > 255:
            return None, f"the URL's {key} is too long for a libpq service file line"
        lines.append(line)
    return "\n".join(lines) + "\n", None


def main(argv):
    mode = argv[1] if len(argv) > 1 else ""
    fields, reason = parse()
    if mode == "check" and len(argv) == 2:
        print(f"ERR {reason}" if reason else f"OK {fields['host']} {fields['port']}")
        return 0
    if mode == "service" and len(argv) == 3:
        if reason:
            print(f"ERR {reason}")
            return 0
        text, reason = service_file(fields)
        if reason:
            print(f"ERR {reason}")
            return 0
        fd = os.open(argv[2], os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, "w") as fh:
            fh.write(text)
        print("OK")
        return 0
    print("ERR usage: spatial_target.py check | service <path>")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))

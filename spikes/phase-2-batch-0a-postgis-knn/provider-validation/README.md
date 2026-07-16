# Stage 0b — Provider Validation Framework

Smallest possible **provider-neutral** harness to test the Stage 0a PostGIS/KNN
spike identically against **DigitalOcean Managed PostgreSQL**, **Crunchy Bridge**,
**Neon**, and **Amazon RDS** before selecting the Phase 2 database host.

This directory is **scaffolding only**. It does not — and must not —

- provision infrastructure, create accounts, or purchase services,
- connect to any provider on its own, or spend money,
- handle, store, print, or commit credentials,
- modify any Stage 0a file, application code, config, schema, or migration.

Running it against a provider (Stage 0b proper) is a **separate, operator-driven
exercise on its own branch** — nothing here has been run against any provider.

## What it provides

| Piece | File |
|-------|------|
| Provider run wrapper | `run_provider_spike.sh` |
| Tier-2 (~5M) deterministic generator | `sql/10_generate_data_tier2.sql` |
| Storage / index / version measurements | `sql/70_measurements.sql` |
| Results directory convention | `results/<provider>/tier<N>/` |
| Comparison scorecard | `templates/SCORECARD.md` |
| Per-provider result writeup | `templates/PROVIDER_RESULT_TEMPLATE.md` |
| Non-secret env contract | `env.example` |
| Secret-exclusion rules | `.gitignore` |

Everything else — schema (`00_setup`), index strategies (`20`/`30`/`40`), KNN
correctness (`50`), distribution (`60`), and the Tier-1 generator (`10`) — is the
**committed Stage 0a SQL in `../sql/`, reused read-only**. This harness never
copies or edits it.

## Two tiers

- **Tier 1 — parity (176,560 rows):** reuses the committed Stage 0a generator
  unchanged. Fast, near-zero cost, deterministic; confirms the composite index
  reproduces the exact Stage 0a plan shape and KNN result on the provider.
- **Tier 2 — scale (~5,000,200 rows):** `sql/10_generate_data_tier2.sql`, same
  seed and category structure scaled ~28x. Confirms the planner still chooses the
  composite index at scale, and yields index-size / bytes-per-row for a **150 GB
  extrapolation** (do not load a literal 150 GB — measure and project).

## Secrets — never in git, never printed

- The wrapper reads the DB password **only from `~/.pgpass`** (psql handles it).
  It never reads, exports, echoes, or forwards `PGPASSWORD`.
- Put real connection values in `.env.local` (gitignored). `env.example` is the
  committed, non-secret template — never place a real secret in it.
- `.gitignore` excludes `.env.local`, `*.pgpass`, `pg_service.conf`, `secrets/`.

  ```bash
  echo 'HOST:PORT:spike:USER:REDACTED' >> ~/.pgpass && chmod 600 ~/.pgpass
  ```

## Direct connection only

Point the wrapper at a **direct, session-mode** endpoint. Never a transaction-mode
pooler (Neon `-pooler`, a PgBouncer transaction port): `setseed()`, `EXPLAIN`,
and `CREATE EXTENSION/INDEX` require a stable session. On Neon, also disable
autosuspend for the run so cold/warm methodology is controlled.

## Usage

```bash
# 1. Configure (locally, gitignored)
cp env.example .env.local && $EDITOR .env.local        # PROVIDER, TIER, PG* (no password)
echo 'HOST:PORT:spike:USER:REDACTED' >> ~/.pgpass && chmod 600 ~/.pgpass

# 2. Inspect what would run — offline, no connection, no secrets
./run_provider_spike.sh --help
set -a; . ./.env.local; set +a
./run_provider_spike.sh --dry-run

# 3. Real run (connects to the provider; writes results/<provider>/tier<N>/)
./run_provider_spike.sh
```

Then copy `templates/PROVIDER_RESULT_TEMPLATE.md` to
`results/<provider>/RESULT.md`, fill from the captured `.out` files, and roll each
provider into `templates/SCORECARD.md`.

## PASS/FAIL (per provider, per tier)

Composite passes iff, for all four sparse categories on both physical layouts:
`Index Scan using places_cat_geom` · category as `Index Cond` · `<->` `Order By`
inside the scan · 0 rows removed by filter · no top-level sort · no seq scan · and
KNN == brute-force. If composite fails, validate the partial-index fallback (C)
by the same criteria. See `templates/SCORECARD.md` for the eligibility gate.

## Cleanup (mandatory, same-day — the real cost control)

Drop spike objects → **destroy the instance/cluster/project** → delete storage,
volumes, and snapshots → remove firewall/trusted-source entries → shred
`~/.pgpass` + `.env.local` → confirm $0 residual resources. A destroyed instance
with a lingering snapshot still bills.

## Stop conditions

Extensions not installable · PostGIS < 3.5 / no PG16 · only a transaction-mode
pooler reachable · composite degrades to seq-scan/sort at Tier 2 · KNN ≠
brute-force · a run would require literally loading 150 GB or exceeding a
pre-agreed per-provider $ cap · credentials would have to be committed to proceed.

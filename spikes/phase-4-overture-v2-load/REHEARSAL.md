# Overture v2 load — full-size rehearsal record

**This file is a GATE, not a note.** The `migrate` and `import` stages of
`.github/workflows/overture-v2-operator-load.yml` read the block below **at the commit being run** and
refuse unless it says `PASSED` with every field satisfied. It changes only through a reviewed commit,
after the rehearsal in `RUNBOOK.md` §4 has actually been performed on a **throwaway** PostgreSQL 16 +
PostGIS **3.6.x** instance — never on the live spatial cluster, and never on a reduced dataset.

The gate also requires that nothing the load depends on changed between `rehearsed_commit` and the
commit being run (`app/`, `config/`, `database/migrations/spatial/`, `composer.lock`,
`scripts/overture-v2/`, and this directory's `bin/` and `sql/`). A rehearsal is evidence for the code
it ran, and for nothing else.

Required values when `PASSED`: `postgis_version` 3.6.x, `postgresql_version` 16.x,
`places_loaded` 52716, `memberships_loaded` 11082, `second_import` ALREADY_READY, `verify_v2_load`
VERIFY_OK, `sample_fidelity` OK, `v1_snapshot_unchanged` yes, `rollback_recovery_exercised` yes,
`rehearsed_commit` a full 40-hex SHA on `main`.

<!-- rehearsal-gate:start -->
rehearsal_status: NOT_RUN
rehearsed_commit:
performed_on:
postgresql_version:
postgis_version:
places_loaded:
memberships_loaded:
second_import:
verify_v2_load:
sample_fidelity:
v1_snapshot_unchanged:
rollback_recovery_exercised:
<!-- rehearsal-gate:end -->

## Evidence

_(When the rehearsal is performed: where it ran, the exact commands, the full `verify_v2_load.sql` and
`compare_sample.py` output, the before/after `v1_snapshot.sql` diff, the second-import output, and the
failure-injection / retry results from RUNBOOK §4.)_

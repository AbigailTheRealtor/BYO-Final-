# Phase 2 · Batch 2D Part C1 — Cross-source authority linking (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster.**

This spike holds the **Class-2 recipe** for populating `place_authority_links` — the resolved
authority↔corpus pairings from SSOT §8.2. The offline, deterministic counterpart is authored in
`app/Services/Spatial/` (`NameNormalizer`, `AuthorityRecord`, `AuthorityLinkMatcher`,
`PlaceAuthorityLinkMaterializer`, `AuthorityLinkAcceptance`) and exercised by `corpus:link-authority`
over the synthetic fixtures in `tests/fixtures/spatial/authority/`.

## The rule (SSOT §8.2, verbatim)

> Match on `ST_DWithin(150 m)` + normalised-name trigram similarity ≥ 0.6; human-review the
> ambiguous tail; persist the resolved pairing in `place_authority_links`.

- Exactly one candidate within (150 m **and** sim ≥ 0.6) → automatic `spatial_name` link.
- Zero candidates → no link.
- **Two or more candidates → the ambiguous tail: never auto-linked, surfaced for human review**
  (decision D3). No tie-break is invented.
- **Link, not merge**: a link references a place by its natural key `(source, source_ref)`; no
  `places` row is mutated, deleted, or collapsed.

## Files

- `sql/link_authority.sql` — **AUTHORED, NOT RUN.** The Class-2 `INSERT INTO place_authority_links`
  (`ST_DWithin(…,150)` + `similarity(…) >= 0.6`, single-candidate only) plus the read-only ambiguous
  report. Requires `spatial_normalize()` — an IMMUTABLE SQL function mirroring `NameNormalizer` (D1),
  authored before the recipe runs.

## Offline dry-run

```bash
php artisan corpus:link-authority
# → storage/app/spatial/authority/link/{links.ndjson, ambiguous_report.json, summary.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no
network call.

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running `link_authority.sql` | No cluster / `places` not loaded / no authority staging | Class-2 |
| `spatial_normalize()` SQL function | Class-2 SQL authoring (mirror `NameNormalizer`) | Class-2 |
| Exact `pg_trgm` score parity (D2) | Offline similarity is a documented approximation | Class-2 |
| Real authority-source data | No downloads in Class-1 | Class-2 |
| Category-compatibility gate (D4) | Undefined in SSOT | future decision |
| Ambiguous-tail review band | Undefined in SSOT | product decision |

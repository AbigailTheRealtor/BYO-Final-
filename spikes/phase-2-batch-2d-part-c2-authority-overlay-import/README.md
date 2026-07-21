# Phase 2 · Batch 2D Part C2 — Authority-overlay importers (spike)

**Status: cluster-free authoring. Nothing here runs against a cluster.**

This spike holds the **Class-2 recipes** for staging and loading the authority overlays (SSOT §8.2
deliverable #8). The offline, deterministic counterpart is authored in `app/Services/Spatial/`
(`AuthorityOverlaySource`, `AuthorityOverlayNormalizationResult`, `AuthorityOverlayAcceptance`,
`AuthorityStagingMaterializer`, and `Overlay/{CmsHospitalOverlaySource,UsgsBoatRampOverlaySource}`)
and exercised by `corpus:import-authority-overlay` over the synthetic fixtures in
`tests/fixtures/spatial/authority_overlay/`.

## The two output modes (SSOT §8.2 / §9.1)

Every importer emits the **same** canonical `AuthorityRecord` NDJSON. What Class-2 does with it is
the source's `target`:

- **`target = 'link'` — OVERLAY source (CMS).** The AuthorityRecords are matched to Overture places
  by the **Batch 2D Part C1** linker (`corpus:link-authority` / `sql/link_authority.sql`), populating
  `place_authority_links` and the linked place's `authority_metric`. Overture carries no CCN, so this
  is fuzzy `ST_DWithin(150 m)` + trigram matching — never a join.
- **`target = 'place'` — BASE source (USGS boat ramps).** No Overture counterpart exists, so the
  AuthorityRecords become `places` rows directly (`source='usgs'`, `category_key='boat_ramp'`);
  ranking is by membership, `authority_metric` NULL.

## Files

- `sql/stage_authority_overlay.sql` — **AUTHORED, NOT RUN.** Creates the transient `authority_staging`
  table and stages the offline `overlay.ndjson`. COPY column list mirrors
  `AuthorityStagingMaterializer::COLUMNS`.
- `sql/load_usgs_boat_ramps.sql` — **AUTHORED, NOT RUN.** The single `INSERT INTO places` for the USGS
  base source (target=place). CMS (target=link) has no load SQL here — it reuses the C1
  `link_authority.sql` recipe.

## Offline dry-run

```bash
php artisan corpus:import-authority-overlay --source=cms
php artisan corpus:import-authority-overlay --source=usgs-boat-ramp
# → storage/app/spatial/authority/overlay/<source>/{overlay.ndjson, staging.json, summary.json, rejects.json}
```

Refuses production; opens no `pgsql_spatial` connection; reads no `SPATIAL_*` secret; makes no network
call; downloads nothing.

## Fixture outcome

- **CMS** (4 raw rows): **2 kept** (`100001` metric 4.0, `100002` metric null — "Not Available" star
  kept as identity), 1 rejected_invalid (no CCN), 1 rejected_out_of_domain (rating 9).
- **USGS boat ramps** (3 raw rows): **2 kept** (`BR-0001`, `BR-0002`, metric null), 1 rejected_invalid
  (no coordinates).

## Blocked / pending (not failures)

| Item | Why | Unblocks in |
|---|---|---|
| Running `stage_authority_overlay.sql` / `load_usgs_boat_ramps.sql` | No cluster / `places` not loaded | Class-2 |
| Real CMS DKAN / USGS CC0 downloads | No downloads in Class-1 | Class-2 |
| CMS coordinate sourcing (star file is address-only) | Geocode / POS-file join is a live step (D5) | Class-2 |
| `authority_staging` as a real table | SSOT defines none; authored in the manifest, not migrated | Class-2 |
| NCES, FAA, GTFS/NTD, PAD-US, EPA WI importers | One source per slice; GTFS gated on per-feed licensing; PAD-US polygon → C3 | later batches |
| Category-compatibility gate (C1 D4) | Undefined in SSOT | future decision |

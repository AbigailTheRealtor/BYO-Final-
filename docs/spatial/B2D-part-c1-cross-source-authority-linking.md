# B2D Part C1 — Cross-source authority linking (offline authoring)

**Phase 2 · Batch 2D Part C1 · Spatial Intelligence Platform**
Branch: `phase-2-batch-2d-part-c1-cross-source-authority-linking`
Status: **cluster-free authoring complete. No PostGIS, no `SPATIAL_*` secrets, no migrations, no corpus import, no downloads, no infrastructure.**

This batch authors the offline, deterministic counterpart of the SSOT §8.2 **cross-source
deduplication** rule — the linker that populates `place_authority_links` — following the 2A/2C
offline-authoring pattern. Live linking is deferred to the Class-2 phase.

## The rule (SSOT §8.2, verbatim)

> The authority record is authoritative for identity and `authority_metric`; the Overture row is
> authoritative for `brand` / `confidence` / `source_count`. **Match on `ST_DWithin(150 m)` +
> normalised-name trigram similarity ≥ 0.6; human-review the ambiguous tail; persist the resolved
> pairing in `place_authority_links`.**

- Exactly one candidate within (150 m **and** sim ≥ 0.6) → automatic `spatial_name` link.
- Zero candidates → no link.
- **Two or more → the ambiguous tail: never auto-linked; surfaced for human review** (D3). No
  tie-break is invented.
- **Link, not merge**: a link references a place by its natural key `(source, source_ref)`; no
  `places` row is mutated, deleted, or collapsed. `places.authority_metric` remains the authority's
  own signal, set at Class-2.

## Approved decisions

| # | Decision |
|---|---|
| **D1** | Name normalisation (authored convention): lowercase → transliterate common Unicode accents to ASCII → punctuation→space → collapse whitespace → trim → trigrams. |
| **D2** | Offline trigram similarity follows the public `pg_trgm` spec; the **`link_authority.sql` manifest is the authoritative rule**; exact `pg_trgm` score parity is Class-2. |
| **D3** | Ambiguous tail (≥2 candidates) is reported, never auto-linked; no tie-break invented. |
| **D4** | **No category-compatibility gate** — the SSOT rule is spatial + name only. |
| **D5** | `match_score` rounds to `numeric(4,3)` (3 dp). |

## What's here (no migration — `place_authority_links` already exists, B1.2 migration 05)

**Services (`app/Services/Spatial/`):** `NameNormalizer` (D1/D2), `AuthorityRecord` (DTO + NDJSON),
`AuthorityLinkMatcher` (the §8.2 matcher; link/unlinked/ambiguous), `PlaceAuthorityLinkMaterializer`
(link rows, column order == migration 05), `AuthorityLinkAcceptance` (7 invariants).
**Config:** `config/spatial_authority.php` (`match_radius_m=150`, `name_similarity_min=0.60` — transcribed from SSOT §8.2).
**Command:** `corpus:link-authority` — offline dry-run; refuses production; no DB/network.
**Spike:** `spikes/phase-2-batch-2d-part-c1-authority-linking/` (`sql/link_authority.sql` AUTHORED-NOT-RUN, README, RESULTS_TEMPLATE).
**Fixtures (synthetic):** `tests/fixtures/spatial/authority/{authority_sample,places_sample,expected_links}.ndjson`.

## Command

```bash
php artisan corpus:link-authority
# → storage/app/spatial/authority/link/{links.ndjson, ambiguous_report.json, summary.json}
```

On the shipped fixtures: **1 linked** (A1→P1, spatial_name, 1.000), **1 unlinked** (A2 — in radius
but dissimilar name), **1 ambiguous** (A3 → P3, P4 — two same-name candidates). A far same-name place
(P5, ~1113 m) proves the radius filter excludes it.

## Acceptance invariants

`pk_unique`, `method_valid`, `score_in_range`, `within_radius`, `no_orphan_place_ref`,
`ambiguous_excluded`, `fully_partitioned`.

## Class-2 handoff

Author `spatial_normalize()` (IMMUTABLE SQL mirroring `NameNormalizer`); run
`sql/link_authority.sql` against the loaded `places` + staged authority rows; populate
`place_authority_links`; human-review the ambiguous tail (→ `match_method='manual'`); validate against
`AuthorityLinkAcceptance`.

## Deferred (not failures)

Real authority-source downloads/loads · exact `pg_trgm` parity (D2) · category-compatibility gate
(D4, undefined) · ambiguous-tail review band (product decision) · authority-overlay importers (C2) ·
boundaries + Gate 2 (C3).

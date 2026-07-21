-- ============================================================================
-- link_authority.sql  —  AUTHORED, NOT RUN
-- Spatial Intelligence Platform · Phase 2 Batch 2D Part C1
-- Cross-source authority -> corpus linking (place_authority_links)
-- ============================================================================
--
-- This is the CLASS-2 recipe. It requires the pgsql_spatial PostGIS cluster
-- (SPATIAL_DATABASE_URL / SPATIAL_PGHOST) with PostGIS + pg_trgm installed, the
-- `places` table loaded, and the authority rows staged in `authority_staging`.
-- It is NEVER executed offline; the offline linker (AuthorityLinkMatcher) is the
-- deterministic dry-run counterpart.
--
-- Rule (SSOT §8.2, verbatim): "Match on ST_DWithin(150 m) + normalised-name
-- trigram similarity >= 0.6; human-review the ambiguous tail; persist the
-- resolved pairing in place_authority_links."
--
--   • spatial_normalize(name) applies the Part C1 D1 normalisation (lowercase,
--     transliterate accents, punctuation->space, collapse, trim). Author it as an
--     IMMUTABLE SQL function mirroring App\Services\Spatial\NameNormalizer before
--     running this recipe. similarity() is pg_trgm's trigram Jaccard.
--   • Exactly ONE candidate within (150 m AND sim >= 0.6) -> auto spatial_name link.
--   • Two or more candidates -> the ambiguous tail: reported READ-ONLY below,
--     never auto-linked (resolved later as match_method='manual').
-- ----------------------------------------------------------------------------

WITH candidate AS (
    SELECT
        a.authority_source,
        a.authority_ref,
        p.source     AS place_source,
        p.source_ref AS place_source_ref,
        similarity(spatial_normalize(a.name), spatial_normalize(p.name)) AS sim
    FROM authority_staging a
    JOIN places p
      ON ST_DWithin(a.geom, p.geom, 150)
     AND similarity(spatial_normalize(a.name), spatial_normalize(p.name)) >= 0.6
),
tally AS (
    SELECT authority_source, authority_ref, count(*) AS n
    FROM candidate
    GROUP BY authority_source, authority_ref
)
INSERT INTO place_authority_links
    (authority_source, authority_ref, place_source, place_source_ref, match_method, match_score, reviewed_by)
SELECT
    c.authority_source,
    c.authority_ref,
    c.place_source,
    c.place_source_ref,
    'spatial_name',
    round(c.sim::numeric, 3),
    NULL
FROM candidate c
JOIN tally t USING (authority_source, authority_ref)
WHERE t.n = 1;

-- ----------------------------------------------------------------------------
-- Ambiguous tail — REVIEW ONLY. This SELECT writes nothing; a human resolves
-- these and inserts the chosen pairing with match_method = 'manual'.
-- ----------------------------------------------------------------------------
WITH candidate AS (
    SELECT
        a.authority_source,
        a.authority_ref,
        p.source     AS place_source,
        p.source_ref AS place_source_ref,
        similarity(spatial_normalize(a.name), spatial_normalize(p.name)) AS sim
    FROM authority_staging a
    JOIN places p
      ON ST_DWithin(a.geom, p.geom, 150)
     AND similarity(spatial_normalize(a.name), spatial_normalize(p.name)) >= 0.6
),
tally AS (
    SELECT authority_source, authority_ref, count(*) AS n
    FROM candidate GROUP BY authority_source, authority_ref
)
SELECT c.*
FROM candidate c
JOIN tally t USING (authority_source, authority_ref)
WHERE t.n >= 2
ORDER BY c.authority_source, c.authority_ref, c.place_source_ref;

-- Phase 2 · Batch 0 · Stage 0a — PostGIS/KNN feasibility spike
-- 00_setup.sql — extensions + SSOT-shaped tables (unpartitioned + LIST-partitioned)
--
-- SSOT constraints under test:
--   * PostgreSQL 16 / PostGIS 3.5
--   * geography(Point,4326)  (NOT geometry)
--   * btree_gist available for composite (scalar, geography) GiST indexes
--
-- Idempotent: safe to re-run. Drops spike tables only; touches nothing else.

\set ON_ERROR_STOP on

CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

DROP TABLE IF EXISTS places_spike CASCADE;
DROP TABLE IF EXISTS places_spike_part CASCADE;

-- ---------------------------------------------------------------------------
-- Unpartitioned candidate table
-- ---------------------------------------------------------------------------
CREATE TABLE places_spike (
    id             bigint                 PRIMARY KEY,
    category_key   text                   NOT NULL,
    corpus_version text                   NOT NULL,
    name           text                   NOT NULL,
    geom           geography(Point, 4326) NOT NULL
);

-- ---------------------------------------------------------------------------
-- LIST-partitioned candidate table (partitioned by corpus_version)
-- Partition key must participate in the primary key.
-- ---------------------------------------------------------------------------
CREATE TABLE places_spike_part (
    id             bigint                 NOT NULL,
    category_key   text                   NOT NULL,
    corpus_version text                   NOT NULL,
    name           text                   NOT NULL,
    geom           geography(Point, 4326) NOT NULL,
    PRIMARY KEY (id, corpus_version)
) PARTITION BY LIST (corpus_version);

CREATE TABLE places_spike_part_v1 PARTITION OF places_spike_part FOR VALUES IN ('v1');
CREATE TABLE places_spike_part_v2 PARTITION OF places_spike_part FOR VALUES IN ('v2');

\echo '00_setup.sql complete: extensions ready, places_spike + places_spike_part created'

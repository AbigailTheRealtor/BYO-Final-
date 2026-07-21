<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * The one new abstraction of the C2 framework (decision D1): a source-agnostic seam that turns a
 * raw authority-source extract (CMS, USGS, NCES, FAA, GTFS/NTD, …) into canonical
 * {@see AuthorityRecord} rows. The SSOT anticipates "eleven mutually independent importers, highly
 * parallel" (roadmap Track E), so — unlike the singular Overture importer — a thin interface is
 * justified now. Each concrete source stays `final` and bespoke; polymorphism is by this contract
 * plus the shared {@see AuthorityOverlayNormalizationResult} / verdict shapes, not a base class.
 *
 * Both output modes emit the SAME DTO (D2 — no new DTO, no `NormalizedPlaceRecord` change):
 *   • target() === 'link'  — an OVERLAY source (e.g. CMS): the AuthorityRecord is matched to an
 *     Overture place by the Batch 2D Part C1 linker (`corpus:link-authority`), supplying
 *     `place_authority_links` + the linked place's `authority_metric`.
 *   • target() === 'place' — a BASE source (e.g. USGS boat ramps): the AuthorityRecord becomes a
 *     `places` row directly (no Overture counterpart exists for that category).
 * Which one applies is Class-2 metadata (drives the spike SQL manifest), NOT a different offline
 * shape.
 *
 * Pure and deterministic — no DB, no network, no PostGIS.
 *
 * @see \App\Services\Spatial\Overlay\CmsHospitalOverlaySource
 * @see \App\Services\Spatial\Overlay\UsgsBoatRampOverlaySource
 */
interface AuthorityOverlaySource
{
    /** Stable registry key == `authority_source` on every emitted record (e.g. 'cms', 'usgs'). */
    public function sourceKey(): string;

    /** Class-2 load target: 'link' (overlay → place_authority_links) or 'place' (base → places). */
    public function target(): string;

    /** Human label of the authority_metric this source supplies, or null for membership sources. */
    public function metricLabel(): ?string;

    /**
     * Inclusive [min, max] domain for a present authority_metric, or null when the source carries
     * no numeric metric (membership sources leave authority_metric NULL).
     *
     * @return array{0: float, 1: float}|null
     */
    public function metricDomain(): ?array;

    /**
     * Normalize decoded raw source rows into canonical AuthorityRecords. Every input row is
     * accounted for exactly once (kept + rejected); rejects are COUNTED, never silently dropped.
     *
     * @param iterable<array<string,mixed>> $rawRows
     */
    public function normalize(iterable $rawRows): AuthorityOverlayNormalizationResult;
}

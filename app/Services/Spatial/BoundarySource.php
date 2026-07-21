<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * Source-agnostic seam for boundary importers (mirrors {@see AuthorityOverlaySource} from C2). Each
 * concrete source (PAD-US now; Census TIGER = C3b, FEMA NFHL = C3c later) transforms a raw extract
 * into canonical {@see BoundaryRecord} rows for the `boundaries` table. Concretes stay `final` and
 * bespoke; polymorphism is by this contract + the shared normalization/verdict shapes.
 *
 * Pure and deterministic — no DB, no network, no PostGIS.
 *
 * @see \App\Services\Spatial\Boundary\PadUsBoundarySource
 */
interface BoundarySource
{
    /** Stable registry key for the source (e.g. 'padus'). */
    public function sourceKey(): string;

    /** The `boundaries.kind` every emitted record carries (e.g. 'protected_area'). */
    public function kind(): string;

    /**
     * Normalize decoded raw source rows into canonical BoundaryRecords. Every input row is accounted
     * for exactly once; rejects are COUNTED, never silently dropped.
     *
     * @param iterable<array<string,mixed>> $rawRows
     */
    public function normalize(iterable $rawRows): BoundaryNormalizationResult;
}

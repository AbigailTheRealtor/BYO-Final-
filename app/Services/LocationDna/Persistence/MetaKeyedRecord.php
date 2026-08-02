<?php

namespace App\Services\LocationDna\Persistence;

/**
 * G1f-1 — the only implementation of {@see LocationDnaWritableRecord}.
 *
 * Wraps any auction model exposing the established EAV pair `info($key)` / `saveMeta($key, $value)`.
 * That pair is the codebase's universal metadata accessor (`BuyerAgentAuction:84`,
 * `BuyerCriteriaAuction:51` and siblings), so one adapter serves every record type without any
 * per-model subclassing.
 *
 * This is the single class in the Location DNA domain that touches persistence. It is deliberately
 * thin enough to read in one screen: it forwards three calls and holds no state beyond the model.
 *
 * It does NOT read mirrors. The projection derives them from canonical state alone (D-G1F-2 2-A),
 * so no read accessor for mirror keys exists here — a caller that wanted one would have to add it
 * deliberately rather than find it lying around.
 */
final class MetaKeyedRecord implements LocationDnaWritableRecord
{
    /** @param object $model any model exposing info()/saveMeta() */
    public function __construct(private readonly object $model)
    {
    }

    public function readCanonical(): mixed
    {
        return $this->model->info(CanonicalMetaKey::KEY);
    }

    public function writeCanonical(string $json): void
    {
        $this->model->saveMeta(CanonicalMetaKey::KEY, $json);
    }

    public function writeMirror(string $key, string $value): void
    {
        $this->model->saveMeta($key, $value);
    }
}

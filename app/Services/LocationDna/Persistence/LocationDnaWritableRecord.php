<?php

namespace App\Services\LocationDna\Persistence;

/**
 * G1f-1 — the narrow write port the persistence service uses.
 *
 * WHY A PORT AND NOT AN ELOQUENT MODEL
 * ------------------------------------
 * v1.2 §6 requires the domain to stay free of framework types, and the G1c inertness guard
 * enforces that for the contract core by forbidding `Model`, `Eloquent` and `saveMeta` inside
 * it. `LocationDnaPersistenceService` sits one layer out — it may open a transaction — but it
 * still must not know how a particular auction stores its metadata. This interface is the whole
 * of what it needs.
 *
 * DELIBERATELY NOT A REPOSITORY FRAMEWORK
 * ---------------------------------------
 * Three methods, one implementation ({@see MetaKeyedRecord}), proven by the one workflow G1f-1
 * migrates. There is no find, no query, no unit of work and no generic persistence abstraction —
 * the G1f-1 authorization asks for the smallest design the single migration proves, and a wider
 * abstraction would be speculative.
 *
 * `readCanonical()` returns `mixed` on purpose: the EAV accessor returns boolean `false` for an
 * unwritten key, which G1a pinned and {@see \App\Services\LocationDna\Contract\LocationDnaHydrator}
 * already treats as absent alongside `null` and `''`.
 */
interface LocationDnaWritableRecord
{
    /** The stored canonical document, exactly as the record holds it. May be false, null or a string. */
    public function readCanonical(): mixed;

    /** Persist the canonical document. */
    public function writeCanonical(string $json): void;

    /** Persist one derived legacy mirror key. */
    public function writeMirror(string $key, string $value): void;
}

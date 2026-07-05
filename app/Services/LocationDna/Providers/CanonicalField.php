<?php

namespace App\Services\LocationDna\Providers;

/**
 * CanonicalField — the atomic unit of the Location Intelligence contract.
 *
 * Every canonical Location field is represented as an *envelope*, not a bare
 * value: the value always travels with its source, confidence, provenance,
 * freshness, and human-corroboration flag. See docs/canonical-field-mapping-spec.md §1.
 *
 * This value object is pure and framework-free. It is produced by
 * {@see CanonicalLocationMerger} and (in a later stage) persisted alongside
 * the raw POI rows. Nothing in the current runtime path constructs it yet.
 *
 * `value === null` means UNKNOWN / unavailable — never "zero" or "none".
 * Consumers must distinguish "no rating" from "rated 0".
 */
class CanonicalField
{
    public const METHOD_API     = 'api';
    public const METHOD_DERIVED = 'derived';
    public const METHOD_MANUAL  = 'manual';
    public const METHOD_CACHE   = 'cache';
    public const METHOD_MERGED  = 'merged';

    /**
     * @param  mixed        $value              Normalized value (scalar | assoc struct | geometry). null = unknown.
     * @param  string       $source             Winning provider id (matches config/location_providers.php ids).
     * @param  float|null   $confidence         0.0–1.0. null only for provider-free / derivation-pending fields.
     * @param  array        $provenance         { provider, method, raw_ref?, license, contributors[] }.
     * @param  string|null  $lastRefreshed      UTC ISO-8601 timestamp of the winning value.
     * @param  bool         $humanCorroborated  True iff a human confirmed/corrected this value.
     * @param  array        $contradictions     Detected disagreements between contributors (audit only).
     */
    public function __construct(
        public readonly mixed $value,
        public readonly string $source,
        public readonly ?float $confidence,
        public readonly array $provenance,
        public readonly ?string $lastRefreshed = null,
        public readonly bool $humanCorroborated = false,
        public readonly array $contradictions = [],
    ) {}

    /**
     * Build the provenance sub-struct with a stable key order.
     *
     * @param  string[]  $contributors  All providers considered (for merge audit).
     */
    public static function provenance(
        string $provider,
        string $method,
        string $license,
        ?string $rawRef = null,
        array $contributors = [],
    ): array {
        return [
            'provider'     => $provider,
            'method'       => $method,
            'raw_ref'      => $rawRef,
            'license'      => $license,
            'contributors' => $contributors,
        ];
    }

    /** Serialize to the persisted/consumed array shape (canonical-field-mapping-spec §1). */
    public function toArray(): array
    {
        return [
            'value'              => $this->value,
            'source'             => $this->source,
            'confidence'         => $this->confidence,
            'provenance'         => $this->provenance,
            'last_refreshed'     => $this->lastRefreshed,
            'human_corroborated' => $this->humanCorroborated,
            'contradictions'     => $this->contradictions,
        ];
    }
}

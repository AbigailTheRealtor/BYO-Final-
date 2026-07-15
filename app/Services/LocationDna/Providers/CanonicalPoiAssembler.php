<?php

namespace App\Services\LocationDna\Providers;

/**
 * CanonicalPoiAssembler — the real runtime path for CanonicalLocationMerger
 * (Phase 1 Batch 2, Deliverable 4).
 *
 * Before this class, `CanonicalLocationMerger` had zero runtime callers. This
 * assembler co-locates the registry (which provider is the source of truth) and the
 * merger (how multiple providers combine) at the POI-candidate boundary: each raw
 * candidate fetched from the resolved provider is run through
 * `CanonicalLocationMerger::merge()` as a one-provider contribution, and the merged
 * `CanonicalField::value` is returned to feed the existing pipeline.
 *
 * SINGLE-PROVIDER PASSTHROUGH (byte-identical, provable):
 * with exactly one contribution the merger performs no attribute overlay, so
 * `merge([$c])->value` is a by-value copy of the raw candidate — same keys, same
 * order. `assemble()` maps over the candidates in order, so the returned list is
 * byte-identical to the input for the current one-provider configuration. When Phase 2
 * enables a second provider, the SAME call performs genuine base+overlay merges with
 * no further wiring.
 *
 * EXCEPTION / EMPTY SEMANTICS: this class is pure and never throws for array input.
 * It runs on the RETURN of the fetch, so a thrown fetch error propagates before
 * `assemble()` is reached (caller records status='error'), and an empty fetch yields
 * `assemble([]) === []` (caller records status='not_found'). The distinction the
 * caller depends on is untouched.
 */
class CanonicalPoiAssembler
{
    private const POI_DEFAULT_KEY = 'poi.default';

    private readonly LocationProviderRegistry $registry;
    private readonly CanonicalLocationMerger $merger;

    /**
     * @param  array                        $config  The `config/location_providers.php` array.
     * @param  CanonicalLocationMerger|null  $merger  Injectable for testing (a spy can prove
     *                                                the merger is genuinely invoked); production
     *                                                defaults to a fresh instance.
     */
    public function __construct(private readonly array $config, ?CanonicalLocationMerger $merger = null)
    {
        $this->registry = new LocationProviderRegistry($this->config);
        $this->merger   = $merger ?? new CanonicalLocationMerger();
    }

    /**
     * Assemble raw provider candidate rows into canonical `.value` rows.
     *
     * @param  array<int, array>  $rawCandidates  Provider-native candidate rows, in order.
     * @return array<int, mixed>  The merged values, in the same order. Byte-identical to
     *                            the input for the current single-provider configuration.
     */
    public function assemble(array $rawCandidates): array
    {
        if ($rawCandidates === []) {
            return [];
        }

        $base       = $this->registry->effectiveBase(self::POI_DEFAULT_KEY);
        $providerId = $base['provider'] ?? 'google_places';
        $role       = $base['role'] ?? LocationProviderRegistry::ROLE_BASE;
        $license    = $base['descriptor']['license'] ?? 'unknown';

        $assembled = [];
        foreach ($rawCandidates as $row) {
            $contribution = [
                'value'          => $row,
                'source'         => $providerId,
                'role'           => $role,
                'method'         => CanonicalField::METHOD_API,
                'license'        => $license,
                'raw_ref'        => is_array($row) ? ($row['place_id'] ?? null) : null,
                // Confidence and freshness are Batch 3 (persistence); null here keeps the
                // one-provider .value byte-identical and writes nothing yet.
                'confidence'     => null,
                'last_refreshed' => null,
            ];

            $assembled[] = $this->merger->merge([$contribution])->value;
        }

        return $assembled;
    }
}

<?php

namespace App\Services\LocationDna;

use App\Services\LocationDna\Providers\LocationProviderRegistry;

/**
 * LocationDnaVersionService — computes the two INDEPENDENT version stamps that
 * govern POI cache invalidation (docs/canonical-field-mapping-spec.md §7;
 * launch-audits/location-dna-architecture-review.md §1).
 *
 *   fetchVersion   — hashes the inputs that decide WHICH candidates get fetched:
 *                    the category query definitions, category groups, search
 *                    radius, and the active provider surface (capabilityHash()).
 *                    A change here requires a fresh fetch — new params/providers
 *                    can surface candidates never stored.
 *
 *   scoringVersion — hashes the inputs that decide HOW stored candidates are
 *                    filtered and ranked: ranking profiles, exclusion rules, and
 *                    scoring constants. A change here recomputes from cache only
 *                    (no API call).
 *
 * Keeping them independent is the whole point: a weight tweak must never force a
 * refetch, and a provider swap must never masquerade as a scoring change.
 *
 * Pure and deterministic: hashes are order-independent (assoc arrays are sorted
 * before hashing) so semantically-equal inputs always yield equal hashes.
 * Nothing is mutated. Stage E0 introduces this service but does not yet wire the
 * AppServiceProvider provider binding.
 */
class LocationDnaVersionService
{
    private ?string $fetchVersion   = null;
    private ?string $scoringVersion = null;

    /**
     * @param  LocationProviderRegistry|null  $registry  Injected for testability;
     *         defaults to one built from config('location_providers').
     */
    public function __construct(private readonly ?LocationProviderRegistry $registry = null)
    {
    }

    /** Version of the fetch-defining inputs (category defs + groups + radius + provider surface). */
    public function fetchVersion(): string
    {
        if ($this->fetchVersion !== null) {
            return $this->fetchVersion;
        }

        $registry = $this->registry ?? new LocationProviderRegistry((array) config('location_providers', []));

        return $this->fetchVersion = $this->hash([
            'categories'      => LocationDnaPoiDistanceService::CATEGORIES,
            'category_groups' => LocationDnaPoiDistanceService::CATEGORY_GROUPS,
            'radius_miles'    => (int) config('location_dna.poi.max_radius_miles', 25),
            'capability'      => $registry->capabilityHash(),
        ]);
    }

    /** Version of the scoring-defining inputs (ranking profiles + exclusion rules + constants). */
    public function scoringVersion(): string
    {
        if ($this->scoringVersion !== null) {
            return $this->scoringVersion;
        }

        return $this->scoringVersion = $this->hash([
            'profiles' => LocationDnaRankingProfileService::profiles(),
            'scoring'  => LocationDnaPoiDistanceService::scoringInputs(),
        ]);
    }

    private function hash(array $input): string
    {
        return hash('sha256', (string) json_encode($this->normalize($input), JSON_UNESCAPED_SLASHES));
    }

    /** Recursively sort associative-array keys so hash order-independence holds. */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(fn ($v) => $this->normalize($v), $value);

        if ($this->isAssoc($value)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }
}

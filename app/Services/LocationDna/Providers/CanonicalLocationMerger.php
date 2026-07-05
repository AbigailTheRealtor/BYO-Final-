<?php

namespace App\Services\LocationDna\Providers;

/**
 * CanonicalLocationMerger — merges the outputs of several providers for the SAME
 * canonical field into one {@see CanonicalField} envelope, applying precedence,
 * attribute-level merge, and contradiction detection.
 *
 * Precedence (highest wins) — docs/canonical-field-mapping-spec.md §6:
 *   1. human_corroborated = true
 *   2. authoritative source (per $options['authoritative'])
 *   3. role = base
 *   4. role = overlay
 *   5. role = fallback
 *   6. tiebreak: higher confidence, then newer last_refreshed
 *
 * Merge, don't just replace: the common OSM(base)+Google(overlay) case merges
 * attributes — the base supplies geometry/name/category, the overlay supplies
 * rating/review_count. Overlay attributes fill only keys the winner is MISSING
 * (null/absent); they never clobber a value the winner already has.
 *
 * STAGE B: pure and framework-free. Nothing in the runtime path calls it yet.
 */
class CanonicalLocationMerger
{
    private const ROLE_PRECEDENCE = [
        LocationProviderRegistry::ROLE_BASE     => 3,
        LocationProviderRegistry::ROLE_OVERLAY  => 2,
        LocationProviderRegistry::ROLE_FALLBACK => 1,
    ];

    /**
     * @param  array<int, array>  $contributions  One entry per provider, each:
     *   {
     *     value: mixed,                 // scalar | assoc struct | null (=unknown)
     *     source: string,               // provider id
     *     role: string,                 // base|overlay|fallback (default: base)
     *     confidence?: float|null,
     *     license?: string,
     *     method?: string,              // CanonicalField::METHOD_* (default: api)
     *     raw_ref?: string|null,
     *     last_refreshed?: string|null, // UTC ISO-8601
     *     human_corroborated?: bool,
     *   }
     * @param  array  $options {
     *     authoritative?: string[],           // provider ids that outrank base (e.g. ['fema','census_tiger'])
     *     numeric_tolerance?: float,          // abs diff below which two numbers are "equal" (default 0.0)
     *     contradiction_keys?: string[],      // struct keys to compare for contradictions (default: whole scalar value)
     *   }
     */
    public function merge(array $contributions, array $options = []): CanonicalField
    {
        $usable = array_values(array_filter(
            $contributions,
            static fn ($c) => is_array($c) && array_key_exists('value', $c) && $c['value'] !== null
        ));

        $contributors = array_values(array_unique(array_map(
            static fn ($c) => (string) ($c['source'] ?? 'unknown'),
            array_filter($contributions, 'is_array')
        )));

        if ($usable === []) {
            // Nothing usable — an explicit UNKNOWN envelope, not a fabricated value.
            return new CanonicalField(
                value: null,
                source: 'none',
                confidence: null,
                provenance: CanonicalField::provenance('none', CanonicalField::METHOD_MERGED, 'n/a', null, $contributors),
            );
        }

        $ranked = $this->rankByPrecedence($usable, $options['authoritative'] ?? []);
        $winner = $ranked[0];

        // Attribute-level merge: fill keys the winner is missing from lower-precedence
        // struct contributions (overlay enrichment). Scalars pass through unchanged.
        $mergedValue     = $winner['value'];
        $usedContributor = false;
        if (is_array($mergedValue) && $this->isAssoc($mergedValue)) {
            foreach (array_slice($ranked, 1) as $other) {
                if (is_array($other['value']) && $this->isAssoc($other['value'])) {
                    foreach ($other['value'] as $k => $v) {
                        if ($v !== null && (!array_key_exists($k, $mergedValue) || $mergedValue[$k] === null)) {
                            $mergedValue[$k] = $v;
                            $usedContributor = true;
                        }
                    }
                }
            }
        }

        $multiSource = $usedContributor || count($contributors) > 1;
        $method      = $multiSource
            ? CanonicalField::METHOD_MERGED
            : (string) ($winner['method'] ?? CanonicalField::METHOD_API);

        return new CanonicalField(
            value: $mergedValue,
            source: $multiSource ? CanonicalField::METHOD_MERGED : (string) $winner['source'],
            confidence: isset($winner['confidence']) ? (float) $winner['confidence'] : null,
            provenance: CanonicalField::provenance(
                provider: (string) $winner['source'],
                method: $method,
                license: (string) ($winner['license'] ?? 'unknown'),
                rawRef: $winner['raw_ref'] ?? null,
                contributors: $contributors,
            ),
            lastRefreshed: $winner['last_refreshed'] ?? null,
            humanCorroborated: (bool) ($winner['human_corroborated'] ?? false),
            contradictions: $this->detectContradictions($winner, $ranked, $options),
        );
    }

    /**
     * Detect disagreements between the winning contribution and the others,
     * beyond the configured tolerance. Precedence still decides which wins; this
     * only surfaces that they disagreed (canonical-field-mapping-spec §6).
     *
     * @return array<int, array{field:string, winner_source:string, winner_value:mixed, other_source:string, other_value:mixed}>
     */
    public function detectContradictions(array $winner, array $ranked, array $options = []): array
    {
        $tolerance = (float) ($options['numeric_tolerance'] ?? 0.0);
        $keys      = $options['contradiction_keys'] ?? [];
        $out       = [];

        foreach (array_slice($ranked, 1) as $other) {
            if ($keys !== [] && is_array($winner['value']) && is_array($other['value'])) {
                foreach ($keys as $key) {
                    $a = $winner['value'][$key] ?? null;
                    $b = $other['value'][$key] ?? null;
                    if ($a !== null && $b !== null && $this->disagree($a, $b, $tolerance)) {
                        $out[] = $this->contradiction($key, $winner, $other, $a, $b);
                    }
                }
                continue;
            }

            $a = $winner['value'];
            $b = $other['value'];
            if (!is_array($a) && !is_array($b) && $this->disagree($a, $b, $tolerance)) {
                $out[] = $this->contradiction('value', $winner, $other, $a, $b);
            }
        }

        return $out;
    }

    /** Sort usable contributions by precedence (stable within equal precedence). */
    private function rankByPrecedence(array $usable, array $authoritative): array
    {
        $authoritative = array_flip($authoritative);

        // Decorate with original index so usort stays stable across PHP versions.
        $decorated = [];
        foreach ($usable as $i => $c) {
            $decorated[] = ['i' => $i, 'c' => $c, 'score' => $this->precedenceScore($c, $authoritative)];
        }

        usort($decorated, static function ($x, $y) {
            return $y['score'] <=> $x['score'] ?: $x['i'] <=> $y['i'];
        });

        return array_map(static fn ($d) => $d['c'], $decorated);
    }

    /**
     * Compose a single comparable precedence score. Ordered tiers are spaced so a
     * higher tier always dominates lower tiers plus their confidence tiebreak.
     */
    private function precedenceScore(array $c, array $authoritativeFlip): float
    {
        $tier = 0.0;
        if (!empty($c['human_corroborated'])) {
            $tier = 4.0;
        } elseif (isset($authoritativeFlip[$c['source'] ?? null])) {
            $tier = 3.0;
        } else {
            $tier = (self::ROLE_PRECEDENCE[$c['role'] ?? LocationProviderRegistry::ROLE_BASE] ?? 3) / 10.0;
        }

        $confidence = isset($c['confidence']) ? max(0.0, min(1.0, (float) $c['confidence'])) : 0.0;

        // tier dominates; confidence is the sub-unit tiebreak.
        return ($tier * 10.0) + $confidence;
    }

    private function disagree(mixed $a, mixed $b, float $tolerance): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs(((float) $a) - ((float) $b)) > $tolerance;
        }

        if (is_string($a) && is_string($b)) {
            return strcasecmp(trim($a), trim($b)) !== 0;
        }

        return $a !== $b;
    }

    private function contradiction(string $field, array $winner, array $other, mixed $a, mixed $b): array
    {
        return [
            'field'         => $field,
            'winner_source' => (string) ($winner['source'] ?? 'unknown'),
            'winner_value'  => $a,
            'other_source'  => (string) ($other['source'] ?? 'unknown'),
            'other_value'   => $b,
        ];
    }

    private function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }
}

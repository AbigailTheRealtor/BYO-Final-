<?php

namespace App\Services\Stellar;

use App\Models\BridgeProperty;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Illuminate\Support\Collection;

/**
 * Transforms a Collection<BuyerMatchResult> into a Blade-safe associative array.
 *
 * Compliance rules enforced here:
 *  - raw_json is NEVER passed to the view layer.
 *  - No Tier 6 field (agent PII, brokerage info, lockbox, showing instructions) is
 *    included in the output array.
 *  - fields_used and dimension machine keys are stripped from all explanation entries.
 *  - listing_key is included as an internal key for future detail page linking but
 *    MUST NOT be rendered as visible text in the Blade view.
 *  - Category scores are clamped to [0, weight_max] for progress-bar width calculations.
 */
class BuyerResultViewMapper
{
    /**
     * Category weight maximums (matches the 100-point scoring model).
     * Used to calculate proportional bar widths for each category.
     */
    private const CATEGORY_WEIGHTS = [
        'location'      => 30,
        'price'         => 25,
        'size'          => 15,
        'property_type' => 10,
        'amenities'     => 10,
        'financial'     => 5,
        'lifestyle'     => 5,
    ];

    private const CATEGORY_LABELS = [
        'location'      => 'Location',
        'price'         => 'Price',
        'size'          => 'Size',
        'property_type' => 'Type',
        'amenities'     => 'Amenities',
        'financial'     => 'Fees',
        'lifestyle'     => 'Lifestyle',
    ];

    /**
     * git-C11 (Plan-C7, F4) — display-only max for the 8th `non_residential` category, which the
     * scorer emits but the residential CATEGORY_WEIGHTS omit. This is the scorer's neutral-points
     * value; kept local to the mapper (no scorer change) and used for detailed bar width only.
     * Sum reconciliation depends on `contributed`, not this max.
     */
    private const NON_RESIDENTIAL_MAX = 10;
    private const NON_RESIDENTIAL_LABEL = 'Property Fit';

    /**
     * Map a collection of BuyerMatchResult DTOs into plain arrays safe for Blade.
     *
     * @param  Collection<BuyerMatchResult> $results
     * @return array[]
     */
    public function map(Collection $results): array
    {
        return $results->map(fn(BuyerMatchResult $result) => $this->mapOne($result))->values()->all();
    }

    /**
     * Map a single BuyerMatchResult to a Blade-safe array.
     *
     * The allowlisted fields are:
     *   listing_key, score_display, total_score, category_bars,
     *   price_display, address, city_state_zip, beds, baths, sqft,
     *   property_type, property_sub_type,
     *   why_this_matches, tradeoffs, caution_flags, missing_data
     *
     * Never includes: raw_json, agent name/email/phone, brokerage info,
     *                 lockbox fields, showing instructions, or any Tier 6 field.
     */
    public function mapOne(BuyerMatchResult $result): array
    {
        $listing = $result->listing;

        // -----------------------------------------------------------------------
        // Score display
        // -----------------------------------------------------------------------
        $totalScore   = max(0, min(100, $result->totalScore));
        $scoreDisplay = "{$totalScore} / 100";

        // -----------------------------------------------------------------------
        // Category score bars — clamped + percentage of their weight maximum
        // -----------------------------------------------------------------------
        $categoryBars = [];
        foreach (self::CATEGORY_WEIGHTS as $key => $max) {
            $raw     = $result->categoryScores[$key] ?? 0;
            $clamped = max(0, min($max, (int) $raw));
            $pct     = $max > 0 ? round(($clamped / $max) * 100) : 0;
            $categoryBars[] = [
                'key'        => $key,
                'label'      => self::CATEGORY_LABELS[$key] ?? ucfirst($key),
                'score'      => $clamped,
                'max'        => $max,
                'pct'        => $pct,
            ];
        }

        // -----------------------------------------------------------------------
        // Price display
        // -----------------------------------------------------------------------
        $listPrice    = $listing->list_price !== null ? (float) $listing->list_price : null;
        $priceDisplay = $listPrice !== null ? '$' . number_format($listPrice, 0) : null;

        // -----------------------------------------------------------------------
        // Address — safe per IDX gate (all results have passed IDXParticipationYN check)
        // -----------------------------------------------------------------------
        $address = $listing->unparsed_address ?: null;

        // -----------------------------------------------------------------------
        // City / State / ZIP
        // -----------------------------------------------------------------------
        $cityStateParts = array_filter([$listing->city, $listing->state_or_province]);
        $cityState      = implode(', ', $cityStateParts);
        $postalCode     = $listing->postal_code;
        $cityStateZip   = trim($cityState . ($postalCode ? ' ' . $postalCode : ''));

        // -----------------------------------------------------------------------
        // Beds / Baths / Sqft (use "—" fallback for null)
        // -----------------------------------------------------------------------
        $beds = $listing->bedrooms_total !== null ? (int) $listing->bedrooms_total : null;
        $baths = $listing->bathrooms_total_integer !== null ? (int) $listing->bathrooms_total_integer : null;
        $sqft  = $listing->living_area !== null ? number_format((int) $listing->living_area) : null;

        // -----------------------------------------------------------------------
        // Property type / subtype
        // -----------------------------------------------------------------------
        $propertyType    = $listing->property_type ?? null;
        $propertySubType = $listing->property_sub_type ?? null;

        // -----------------------------------------------------------------------
        // Explanation blocks — strip internal machine keys
        // -----------------------------------------------------------------------
        $whyThisMatches = $this->mapWhyThisMatches($result->whyThisMatches);
        $tradeoffs      = $this->mapExplanationBlock($result->tradeoffs, ['dimension', 'fields_used', 'deviation']);
        $cautionFlags   = $this->mapCautionFlags($result->cautionFlags);
        $missingData    = $this->mapMissingData($result->missingData);

        return [
            'listing_key'       => $result->listingKey,
            'total_score'       => $totalScore,
            'score_display'     => $scoreDisplay,
            'category_bars'     => $categoryBars,
            'price_display'     => $priceDisplay,
            'address'           => $address,
            'city'              => $listing->city ?? null,
            'city_state_zip'    => $cityStateZip ?: null,
            'beds'              => $beds,
            'baths'             => $baths,
            'sqft'              => $sqft,
            'property_type'     => $propertyType,
            'property_sub_type' => $propertySubType,
            'why_this_matches'  => $whyThisMatches,
            'tradeoffs'         => $tradeoffs,
            'caution_flags'     => $cautionFlags,
            'missing_data'      => $missingData,
            'latitude'          => $listing->latitude !== null ? (float) $listing->latitude : null,
            'longitude'         => $listing->longitude !== null ? (float) $listing->longitude : null,
            'hero_photo_url'    => $this->extractFirstPhotoUrl($listing),
        ];
    }

    /**
     * git-C11 (Plan-C7, F3/F4) — detailed, compliance-preserving mapping for the Match Check
     * report view. Reuses mapOne() for the compliance-safe scalar fields, then:
     *   - overrides the explanation blocks with detail-preserving versions (keeps fields_used /
     *     dimension / deviation that mapOne strips — field names / magnitudes, never values or PII),
     *   - surfaces the git-C10 blocks (why_not / confidence / recommendations), and
     *   - renders every CONTRIBUTING category including `non_residential` with a reconciled total
     *     (F4: contributed_sum + rounding_adjustment == total_score).
     *
     * mapOne() and the batch card path are NOT touched. The git-C9 slots may be null (a result that
     * only went through build(), not buildDetailed()); those are handled gracefully. This method is
     * unwired — the git-C13 orchestrator will call it. No scorer/weight change (F4 is display-only).
     */
    public function mapOneDetailed(BuyerMatchResult $result): array
    {
        $base = $this->mapOne($result);

        return array_merge($base, [
            'category_bars'    => $this->mapDetailedCategoryBars($result),
            'category_totals'  => $this->reconcileCategoryTotals($result),
            'why_this_matches' => $this->mapWhyDetailed($result->whyThisMatches),
            'why_not'          => $this->mapWhyDetailed($result->whyNot ?? []),
            'tradeoffs'        => $this->mapTradeoffsDetailed($result->tradeoffs),
            'confidence'       => $this->mapConfidence($result->confidence),
            'recommendations'  => $this->mapRecommendations($result->recommendations ?? []),
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Extract the first public photo CDN URL from raw_json Media[], sorted by Order.
     */
    private function extractFirstPhotoUrl(BridgeProperty $listing): ?string
    {
        if (!$listing->raw_json) {
            return null;
        }
        $raw   = json_decode($listing->raw_json, true) ?? [];
        $media = $raw['Media'] ?? [];
        if (!is_array($media) || empty($media)) {
            return null;
        }
        usort($media, fn($a, $b) => (int) ($a['Order'] ?? 0) <=> (int) ($b['Order'] ?? 0));
        foreach ($media as $item) {
            if (!empty($item['MediaURL'])) {
                return (string) $item['MediaURL'];
            }
        }
        return null;
    }

    /**
     * Map why_this_matches — filter to score_contribution > 0, sort DESC,
     * strip fields_used and dimension machine keys.
     */
    private function mapWhyThisMatches(array $entries): array
    {
        $filtered = array_filter($entries, fn($e) => (($e['score_contribution'] ?? 0) > 0));
        usort($filtered, fn($a, $b) => ($b['score_contribution'] ?? 0) <=> ($a['score_contribution'] ?? 0));

        return array_map(fn($e) => [
            'label'              => $e['label'] ?? '',
            'score_contribution' => (int) ($e['score_contribution'] ?? 0),
        ], array_values($filtered));
    }

    /**
     * Strip a set of machine keys from each entry of an explanation block array.
     */
    private function mapExplanationBlock(array $entries, array $stripKeys): array
    {
        return array_map(function ($entry) use ($stripKeys) {
            $out = [];
            foreach ($entry as $k => $v) {
                if (!in_array($k, $stripKeys, true)) {
                    $out[$k] = $v;
                }
            }
            return $out;
        }, $entries);
    }

    /**
     * Map caution_flags — strip 'type' machine code, keep label and severity.
     */
    private function mapCautionFlags(array $flags): array
    {
        return array_map(fn($f) => [
            'severity' => $f['severity'] ?? 'info',
            'label'    => $f['label'] ?? '',
        ], $flags);
    }

    /**
     * Map missing_data — strip 'field' machine key, keep only 'label'.
     */
    private function mapMissingData(array $missing): array
    {
        return array_map(fn($m) => [
            'label' => $m['label'] ?? '',
        ], $missing);
    }

    // =========================================================================
    // git-C11 (Plan-C7, F3/F4) — detailed-view helpers. Reached ONLY via mapOneDetailed();
    // mapOne()/map() and their helpers above are untouched.
    // =========================================================================

    /**
     * Per-category display maxima for the detailed view: the seven residential CATEGORY_WEIGHTS
     * plus the 8th `non_residential` category the scorer emits. Order matches the scorer.
     */
    private function detailedCategoryMaxes(): array
    {
        return self::CATEGORY_WEIGHTS + ['non_residential' => self::NON_RESIDENTIAL_MAX];
    }

    private function detailedCategoryLabel(string $key): string
    {
        if ($key === 'non_residential') {
            return self::NON_RESIDENTIAL_LABEL;
        }

        return self::CATEGORY_LABELS[$key] ?? ucfirst($key);
    }

    /**
     * Detailed category bars — every CONTRIBUTING category (score > 0), across all eight keys,
     * including `non_residential`. Each exposes contributed / available / pct.
     */
    private function mapDetailedCategoryBars(BuyerMatchResult $result): array
    {
        $bars = [];
        foreach ($this->detailedCategoryMaxes() as $key => $max) {
            $contributed = max(0, min($max, (int) ($result->categoryScores[$key] ?? 0)));
            if ($contributed <= 0) {
                continue; // contributing categories only (F4)
            }
            $bars[] = [
                'key'         => $key,
                'label'       => $this->detailedCategoryLabel($key),
                'contributed' => $contributed,
                'available'   => $max,
                'pct'         => $max > 0 ? round(($contributed / $max) * 100) : 0,
            ];
        }

        return $bars;
    }

    /**
     * F4 reconciliation: guarantees the visible breakdown reconciles to the authoritative
     * total_score. Because the scorer computes total = round(Σ raw) while each category is
     * round(raw_i) independently (and total is clamped to 0–100), Σ(contributed) can differ by a
     * small delta; rounding_adjustment absorbs it so contributed_sum + rounding_adjustment ==
     * total_score exactly. No scorer math is changed.
     */
    private function reconcileCategoryTotals(BuyerMatchResult $result): array
    {
        $totalScore = max(0, min(100, $result->totalScore));

        $contributedSum = 0;
        foreach ($this->detailedCategoryMaxes() as $key => $max) {
            $contributed = max(0, min($max, (int) ($result->categoryScores[$key] ?? 0)));
            if ($contributed > 0) {
                $contributedSum += $contributed;
            }
        }

        return [
            'contributed_sum'     => $contributedSum,
            'total_score'         => $totalScore,
            'rounding_adjustment' => $totalScore - $contributedSum,
        ];
    }

    /**
     * Detailed why_this_matches / why_not — keeps `dimension` + `fields_used` (field names, not
     * values/PII) that mapOne strips. Sorted by contribution DESC; why_not entries (all zero) keep
     * their input order. No score-based filtering (why_not is intentionally the zero-scoring set).
     */
    private function mapWhyDetailed(array $entries): array
    {
        $mapped = array_map(fn($e) => [
            'dimension'          => $e['dimension'] ?? null,
            'label'              => $e['label'] ?? '',
            'fields_used'        => $e['fields_used'] ?? [],
            'score_contribution' => (int) ($e['score_contribution'] ?? 0),
        ], $entries);

        usort($mapped, fn($a, $b) => $b['score_contribution'] <=> $a['score_contribution']);

        return array_values($mapped);
    }

    /**
     * Detailed tradeoffs — keeps `deviation` (magnitude) + `fields_used` + `dimension` that mapOne
     * strips. All are field-name / magnitude strings, never values or PII.
     */
    private function mapTradeoffsDetailed(array $entries): array
    {
        return array_map(fn($e) => [
            'dimension'   => $e['dimension'] ?? null,
            'label'       => $e['label'] ?? '',
            'fields_used' => $e['fields_used'] ?? [],
            'deviation'   => $e['deviation'] ?? null,
        ], $entries);
    }

    /**
     * Confidence (git-C10) — nullable structured block passthrough. Safe: booleans/floats/labels.
     */
    private function mapConfidence(?array $confidence): ?array
    {
        if ($confidence === null) {
            return null;
        }

        return [
            'level'   => $confidence['level'] ?? null,
            'score'   => $confidence['score'] ?? null,
            'factors' => $confidence['factors'] ?? [],
        ];
    }

    /**
     * Recommendations (git-C10) — keep type (for view iconography) + dimension + label. Safe.
     */
    private function mapRecommendations(array $recommendations): array
    {
        return array_map(fn($r) => [
            'type'      => $r['type'] ?? null,
            'dimension' => $r['dimension'] ?? null,
            'label'     => $r['label'] ?? '',
        ], $recommendations);
    }
}

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
}

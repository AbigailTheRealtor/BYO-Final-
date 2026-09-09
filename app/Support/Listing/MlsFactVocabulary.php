<?php

namespace App\Support\Listing;

/**
 * Enum vocabularies an imported MLS fact must be filtered against, and the two
 * role-specific transforms that are not a straight copy.
 *
 * WHY THIS EXISTS
 * ---------------
 * Most Bridge facts land in a form field that accepts free text or whose option
 * list the feed already matches, so the import is a copy. Two are different:
 *
 *   · Flooring lands in a multi-select with a fixed 26-value option list. A feed
 *     value outside that list would be stored and then never render as a
 *     selected option — invisible data the user cannot see or correct.
 *   · Furnished does not land in a "furnished" field at all. It merges a single
 *     label into building_features, an array the user also edits.
 *
 * Both rules are FAIL CLOSED: a value this class does not recognise is dropped,
 * not passed through. That is the same direction every other MLS boundary in
 * this codebase fails, and for the same reason — a value the form cannot show is
 * worse than no value.
 *
 * The furnished rule lives here rather than in either import path because BOTH
 * paths need it: the URL/text importer already applied it in
 * HasMlsImport::applyImportedFields(), and the quick-import writer now needs the
 * identical behaviour. One rule, two callers, no drift.
 */
final class MlsFactVocabulary
{
    /**
     * The Landlord "Floor Covering" option list, verbatim from
     * offer-landlord-tabs/commission-based/property-preferences.blade.php.
     *
     * Seller has no flooring field at all, which is why this is landlord-only.
     *
     * @return list<string>
     */
    public static function floorCoverings(): array
    {
        return [
            'Bamboo', 'Brick/Stone', 'Carpet', 'Ceramic Tile', 'Concrete', 'Cork',
            'Engineered Hardwood', 'Epoxy', 'Forestry Stewardship Certified', 'Granite',
            'Laminate', 'Linoleum', 'Luxury Vinyl', 'Marble', 'Parquet', 'Porcelain Tile',
            'Quarry Tile', 'Reclaimed Wood', 'Recycled/Composite Flooring', 'Slate',
            'Terrazzo', 'Tile', 'Travertine', 'Vinyl', 'Wood', 'Other',
        ];
    }

    /**
     * Keep only the feed values the Floor Covering select actually offers.
     *
     * Case-insensitive, because feeds vary on capitalisation while the stored
     * value must match the option exactly or the select renders nothing as
     * chosen. Order follows the FEED, not the option list, so a repeated import
     * of an unchanged record produces an identical array.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function filterFloorCoverings(array $values): array
    {
        $canonical = [];

        foreach (self::floorCoverings() as $option) {
            $canonical[mb_strtolower($option)] = $option;
        }

        $kept = [];

        foreach ($values as $value) {
            $key = mb_strtolower(trim((string) $value));

            if ($key === '' || ! isset($canonical[$key])) {
                continue;
            }

            // De-duplicated: a feed that lists "Tile" twice yields one selection.
            if (! in_array($canonical[$key], $kept, true)) {
                $kept[] = $canonical[$key];
            }
        }

        return $kept;
    }

    /**
     * The building_features label a Furnished value earns, or null.
     *
     * "Unfurnished" deliberately returns null. building_features is a list of
     * features the property HAS; absence of a furnishing label already means
     * unfurnished, and adding an "Unfurnished" entry to a features list reads as
     * a feature rather than the absence of one. This matches the rule the
     * URL/text importer has always applied.
     *
     * Anything the vocabulary does not recognise also returns null.
     */
    public static function furnishedFeatureLabel(?string $raw): ?string
    {
        $value = mb_strtolower(trim((string) $raw));

        // The live feed says "Partially" where this vocabulary says "partial".
        // Aliased rather than added as a fifth label so both spellings produce
        // the SAME stored feature — "Partial" — and no listing ends up with two
        // near-identical furnishing entries depending on which word the feed used.
        if ($value === 'partially') {
            $value = 'partial';
        }

        return in_array($value, ['furnished', 'turnkey', 'partial', 'negotiable'], true)
            ? ucfirst($value)
            : null;
    }

    /**
     * Merge the furnishing label into an existing building_features list.
     *
     * Preserves every existing selection, adds at most one entry, never removes
     * anything, and is idempotent — importing the same record twice leaves the
     * array unchanged the second time. Nothing else in the array is touched.
     *
     * @param  mixed  $existing  whatever the listing currently holds
     * @return list<string>
     */
    public static function mergeFurnishedFeature(mixed $existing, ?string $raw): array
    {
        $features = [];

        foreach ((array) ($existing ?? []) as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $features[] = trim((string) $item);
            }
        }

        $features = array_values(array_unique($features));
        $label    = self::furnishedFeatureLabel($raw);

        if ($label !== null && ! in_array($label, $features, true)) {
            $features[] = $label;
        }

        return $features;
    }

    // =========================================================================
    // Parity vocabularies (2026-09-04 payload audit)
    // =========================================================================

    /**
     * The Seller "Business Type" option list, verbatim from
     * offer-seller-tabs/commission-based/property-preferences.blade.php.
     *
     * Stellar's own `BusinessType` enumeration is the same vocabulary — every
     * one of the 39 distinct values in the cached corpus matches an entry here
     * exactly — which is what makes this mapping a filter rather than a
     * translation. A value that ever stops matching is dropped and still shown
     * verbatim under Commercial / Business, so nothing is lost either way.
     *
     * @return list<string>
     */
    public static function businessTypes(): array
    {
        return [
            'Aeronautical', 'Agriculture', 'Arts and Entertainment', 'Assembly Hall',
            'Assisted Living', 'Auto Dealer', 'Auto Service', 'Bar/Tavern/Lounge',
            'Barber/Beauty', 'Car Wash', 'Child Care', 'Church', 'Commercial',
            'Concession Trailers/Vehicles', 'Construction/Contractor', 'Convenience Store',
            'Distribution', 'Distributor Routine Ven', 'Education/School', 'Farm',
            'Fashion/Specialty', 'Flex Space', 'Florist/Nursery', 'Food & Beverage',
            'Gas Station', 'Grocery', 'Heavy Weight Sales Service', 'Hotel/Motel',
            'Industrial', 'Light Items Sales Only', 'Manufacturing', 'Marine/Marina',
            'Medical', 'Mixed', 'Mobile/Trailer Park', 'Personal Service',
            'Professional Service', 'Professional/Office', 'Recreation',
            'Research & Development', 'Residential', 'Restaurant', 'Retail',
            'Shopping Center/Strip Center', 'Storage', 'Theatre', 'Timberland',
            'Veterinary', 'Warehouse', 'Wholesale', 'Other',
        ];
    }

    /**
     * The acreage bands the Seller/Landlord "Total Acreage" select offers,
     * verbatim from config('property_types.acreage_options').
     *
     * @return list<string>
     */
    public static function acreageBands(): array
    {
        return [
            '0 to less than 1/4 acre', '1/4 to less than 1/2 acre', '1/2 to less than 1 acre',
            '1 to less than 2 acres', '2 to less than 5 acres', '5 to less than 10 acres',
            '10 to less than 20 acres', '20 to less than 50 acres', '50 to less than 100 acres',
            '100 to less than 200 acres', '200 to less than 500 acres', '500+ acres',
            'Non-Applicable',
        ];
    }

    /**
     * The band a numeric acreage falls into, or null.
     *
     * DERIVED FROM THE NUMBER, NOT FROM `STELLAR_TotalAcreage`.
     * Stellar publishes both: a numeric `LotSizeAcres` (populated on 1,061 of
     * 1,224 cached records) and a pre-banded `STELLAR_TotalAcreage` string
     * (545). Their spellings differ from ours by a suffix — "1/2 to less than 1"
     * against "1/2 to less than 1 acre" — so matching on the string means
     * matching on punctuation, and it covers half as many listings. Bucketing
     * the number is exact, covers every listing that has one, and cannot drift
     * if Stellar re-words a band.
     *
     * A zero or negative acreage returns null: the feed's way of saying it has
     * no lot measurement, not a claim that the lot is smaller than a quarter
     * acre.
     */
    public static function acreageBand(float|int|string|null $acres): ?string
    {
        if ($acres === null || $acres === '' || ! is_numeric($acres)) {
            return null;
        }

        $value = (float) $acres;

        if ($value <= 0.0) {
            return null;
        }

        return match (true) {
            $value < 0.25  => '0 to less than 1/4 acre',
            $value < 0.5   => '1/4 to less than 1/2 acre',
            $value < 1     => '1/2 to less than 1 acre',
            $value < 2     => '1 to less than 2 acres',
            $value < 5     => '2 to less than 5 acres',
            $value < 10    => '5 to less than 10 acres',
            $value < 20    => '10 to less than 20 acres',
            $value < 50    => '20 to less than 50 acres',
            $value < 100   => '50 to less than 100 acres',
            $value < 200   => '100 to less than 200 acres',
            $value < 500   => '200 to less than 500 acres',
            default        => '500+ acres',
        };
    }

    /**
     * Stellar's `LivingAreaSource` in the words the form's own select uses.
     *
     * The two vocabularies overlap but are not identical: the feed says
     * "Owner" and "Appraiser" where the form offers "Owner Provided" and
     * "Appraisal". "Estimated" has no option at all and is dropped rather than
     * stored as a value that would never render as selected — the fact still
     * appears verbatim under Property Details.
     */
    public static function livingAreaSource(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'public records'  => 'Public Records',
            'owner', 'owner provided' => 'Owner Provided',
            'builder'         => 'Builder',
            'appraiser', 'appraisal'  => 'Appraisal',
            'measured'        => 'Measured',
            default           => null,
        };
    }

    /**
     * Keep only feed values the Business Type select actually offers.
     *
     * @param  list<string>|string|null  $values
     * @return list<string>
     */
    public static function filterBusinessTypes(mixed $values): array
    {
        return self::filterAgainst($values, self::businessTypes());
    }

    /**
     * Case-insensitive intersection with an option list, returning the OPTION's
     * spelling so the stored value matches the markup exactly.
     *
     * Feed order is preserved rather than option order, so re-importing an
     * unchanged record produces an identical array — which is what makes the
     * write idempotent.
     *
     * @param  list<string>|string|null  $values
     * @param  list<string>              $allowed
     * @return list<string>
     */
    public static function filterAgainst(mixed $values, array $allowed): array
    {
        if ($values === null || $values === '' || $values === []) {
            return [];
        }

        $lookup = [];
        foreach ($allowed as $option) {
            $lookup[mb_strtolower($option)] = $option;
        }

        $out = [];
        foreach (is_array($values) ? $values : explode(',', (string) $values) as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $key = mb_strtolower(trim((string) $value));

            if ($key !== '' && isset($lookup[$key]) && ! in_array($lookup[$key], $out, true)) {
                $out[] = $lookup[$key];
            }
        }

        return $out;
    }

    /**
     * Translate one canonical import value into the destination control's own
     * vocabulary, or return null to skip the write entirely.
     *
     * WHY BOTH WRITE PATHS CALL THIS AND NEITHER OWNS IT
     * --------------------------------------------------
     * There are two ways a Bridge fact reaches a listing — the tabbed wizard's
     * `HasMlsImport::applyImportedFields()` and the quick import's
     * `MlsQuickImportDraftWriter::writeFacts()`. They already carried two
     * lookalike copies of the property-type and furnished rules, which is how
     * "the same import behaves differently depending on which button you
     * pressed" gets built. Every vocabulary rule added since lives here, and
     * both callers ask the same question.
     *
     * Returning null means SKIP, not "store empty". A value the destination
     * select cannot offer would be stored, never render as chosen, and read to
     * the user as a field the import failed to fill — while the fact itself is
     * already preserved verbatim under MLS Details. Dropping it is the honest
     * outcome, and it is the same fail-closed direction as every other MLS
     * boundary here.
     *
     * A canonical key with no rule is returned unchanged; this is a narrow
     * translation table, not a gate.
     */
    public static function toFormValue(string $canonicalKey, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($canonicalKey) {
            'lot_size_acres'            => self::acreageBand(is_scalar($value) ? $value : null),
            'sqft_heated_source'        => self::livingAreaSource(is_scalar($value) ? (string) $value : null),
            'association_fee_frequency' => self::nullIfBlank(
                \App\Services\ListingImport\MlsNormalizer::normalizeHoaFeeFrequency((string) $value)
            ),
            'lease_amount_frequency'    => self::nullIfBlank(
                \App\Services\ListingImport\MlsNormalizer::normalizeLeaseFrequency((string) $value)
            ),
            // A single-select destination: the feed sends an array and the form
            // holds one value, so the first recognised entry wins and the rest
            // stay visible under Commercial / Business.
            'business_type'             => self::filterBusinessTypes($value)[0] ?? null,
            default                     => $value,
        };
    }

    private static function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

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
}

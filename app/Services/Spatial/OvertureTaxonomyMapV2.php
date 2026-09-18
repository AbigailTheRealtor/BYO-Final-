<?php

namespace App\Services\Spatial;

/**
 * Overture corpus v2 — the `taxonomy.primary` → canonical category crosswalk.
 *
 * Pure, side-effect-free, cluster-independent. The contract a later v2 extractor will
 * classify rows by; nothing calls it yet. Design and measured evidence:
 * docs/spatial/overture-corpus-v2-design.md.
 *
 * WHY A SEPARATE CLASS, AND NOT A CHANGE TO OvertureCategoryMap
 * -------------------------------------------------------------
 * {@see OvertureCategoryMap} is the crosswalk the ACTIVE corpus
 * (`overture-2026-06-17.0-fl`) was built from, keyed on the legacy `categories.primary`
 * field. It stays byte-identical: it describes rows that exist. This class describes rows
 * a future import may produce, keyed on a different source field with a different
 * vocabulary, so the two must never share a table.
 *
 * THE SOURCE FIELD IS `taxonomy.primary`, AND THERE IS NO FALLBACK
 * ----------------------------------------------------------------
 * In release 2026-08-19.0, `taxonomy.primary` renames 35% of rows relative to
 * `categories.primary` (`shopping_center` → `shopping_mall` among them), and the two
 * fields are null on exactly the same rows — so falling back to `categories.primary`
 * would recover nothing and would mix two vocabularies. `basic_category` is a coarser
 * family (it folds burger / chicken / taco restaurants into `restaurant`) and is not a
 * classifier here either. A token this map does not name — including null, empty, the
 * legacy `shopping_center`, and `fitness_center` (absent from the release) — maps to null
 * and must be rejected and tallied by the caller, never guessed.
 *
 * Input is trimmed and lower-cased before lookup, exactly as the v1 map does, so casing or
 * whitespace variance cannot silently drop a named token. No other normalisation happens:
 * no alias, no similarity, no partial match.
 *
 * WHAT THIS MAP DOES NOT DECIDE
 * -----------------------------
 * Which categories Location DNA may use. That is an explicit, separate list in
 * {@see \App\Services\LocationDna\Providers\CorpusPoiCategoryMap}, deliberately NOT derived
 * from this map, so widening the corpus can never widen Location DNA. The nine keys added
 * here for brand search reach no Location DNA section.
 */
final class OvertureTaxonomyMapV2
{
    /** The Overture field this crosswalk reads. */
    public const SOURCE_FIELD = 'taxonomy.primary';

    /** Crosswalk version, for the import ledger once a v2 import exists. */
    public const VERSION = 'overture-taxonomy-v2.0';

    /**
     * `taxonomy.primary` token → canonical category_key. Exactly 16 entries.
     *
     * The first seven carry the existing Location DNA categories forward; only
     * `shopping_mall` differs from its canonical key, because the taxonomy renamed the
     * source token while the section it feeds keeps its name. The remaining nine exist
     * for the corpus and brand search only.
     */
    private const MAP = [
        // Legacy-equivalent — the seven existing Location DNA categories.
        'restaurant'           => 'restaurant',
        'gas_station'          => 'gas_station',
        'gym'                  => 'gym',
        'grocery_store'        => 'grocery_store',
        'coffee_shop'          => 'coffee_shop',
        'pharmacy'             => 'pharmacy',
        'shopping_mall'        => 'shopping_center',

        // Corpus v2 / brand search only.
        'convenience_store'    => 'convenience_store',
        'fast_food_restaurant' => 'fast_food_restaurant',
        'cafe'                 => 'cafe',
        'burger_restaurant'    => 'burger_restaurant',
        'department_store'     => 'department_store',
        'chicken_restaurant'   => 'chicken_restaurant',
        'drugstore'            => 'drugstore',
        'taco_restaurant'      => 'taco_restaurant',
        'superstore'           => 'superstore',
    ];

    /**
     * The canonical category_key for a `taxonomy.primary` token, or null when the token is
     * not imported (unknown, null, empty, or deliberately excluded).
     */
    public function mapSource(?string $taxonomyPrimary): ?string
    {
        $token = strtolower(trim((string) $taxonomyPrimary));

        if ($token === '') {
            return null;
        }

        return self::MAP[$token] ?? null;
    }

    public function isImported(?string $taxonomyPrimary): bool
    {
        return $this->mapSource($taxonomyPrimary) !== null;
    }

    /**
     * The imported `taxonomy.primary` tokens, in declaration order (16).
     *
     * @return list<string>
     */
    public function sourceTokens(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * The distinct canonical keys this crosswalk can produce, in declaration order (16).
     *
     * @return list<string>
     */
    public function canonicalKeys(): array
    {
        return array_values(array_unique(array_values(self::MAP)));
    }

    /**
     * The full crosswalk, for tests and the future import ledger.
     *
     * @return array<string, string>
     */
    public function mappings(): array
    {
        return self::MAP;
    }
}

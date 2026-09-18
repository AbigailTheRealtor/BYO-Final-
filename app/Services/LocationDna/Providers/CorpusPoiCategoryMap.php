<?php

namespace App\Services\LocationDna\Providers;

use App\Services\LocationDna\CanonicalCategoryRegistry;
use App\Services\LocationDna\LocationDnaPoiDistanceService;

/**
 * CorpusPoiCategoryMap — which canonical categories the loaded Overture corpus can
 * actually answer, and how a fetch request names the one it wants.
 *
 * WHY THIS IS A SEPARATE CLASS
 * ----------------------------
 * It carries the one claim in this feature that is easy to get wrong and impossible
 * to notice: WHICH CATEGORIES ARE REAL. The corpus holds seven; the pipeline asks for
 * nineteen. A map that quietly answered `beach` with the nearest coffee shop, or
 * answered `school` with nothing while reporting success, would produce a listing page
 * that looks complete and is wrong. Keeping the claim in one small, purely functional
 * object is what lets a test assert it against both the corpus taxonomy and the
 * pipeline's own category table, rather than against a comment.
 *
 * THE SUPPORTED SET IS AN EXPLICIT LIST OF SEVEN, AND NOTHING ELSE
 * -----------------------------------------------------------------
 * {@see \App\Services\Spatial\OvertureCategoryMap} defines the crosswalk the loaded
 * corpus was built from: 8 Overture primary tokens collapsing to 7 canonical keys.
 * Those 7 are the `place_categories` rows the v1 import seeded, so they are exactly the
 * `category_key` values a v1 corpus row can carry.
 *
 * The set is written out in {@see self::LOCATION_DNA_KEYS} rather than derived from any
 * corpus taxonomy, and that is a boundary, not a restatement. A corpus may hold more
 * categories than Location DNA uses — corpus v2
 * ({@see \App\Services\Spatial\OvertureTaxonomyMapV2}) adds nine for brand search — and a
 * set derived from the corpus would widen Location DNA the moment the corpus widened.
 * Adding a Location DNA category is an explicit edit here, never a side effect of an
 * import. Tests pin the list to exactly these seven and prove every one of them is still
 * a key the v1 and v2 crosswalks can produce.
 *
 * Everything else — beach, beach_access, school, park, hospital, transit_station,
 * boat_ramp, marina, waterfront_park, dog_park, golf_course, airport, urgent_care,
 * downtown, highway_access — is UNSUPPORTED. They are declared in
 * {@see CanonicalCategoryRegistry} against open-data providers (NOAA CUSP, NCES,
 * PAD-US, CMS, GTFS/NTD, USGS, OSM, FAA NASR) that are all `PROVIDER_PLANNED` with
 * deliberately empty parameters. No import exists. An unsupported category resolves
 * to null here and the adapter returns no candidates — which the caller records as
 * `not_found`, the honest answer.
 *
 * HOW A FETCH NAMES ITS CATEGORY, AND WHY IT IS NOT SIMPLY PASSED
 * ---------------------------------------------------------------
 * {@see \App\Contracts\NearbyPoiFetcherInterface::fetchNearby()} receives the category
 * DESCRIPTOR, not the category key: `$meta` is one value out of
 * `LocationDnaPoiDistanceService::CATEGORIES`, and the key it was stored under is left
 * behind by the caller. Rather than change that signature — it is the seam the whole
 * production path already runs through — this class recovers the key from the
 * descriptor.
 *
 * The recovery is keyed on the `(google_type, keyword)` PAIR, and that pair is not an
 * arbitrary choice: it is the identity {@see \App\Services\LocationDna\LocationDnaPoiTileCache}
 * already uses to tell one category's cached candidates from another's. If the pair
 * were ever ambiguous, the tile cache would already be serving one category's results
 * for another. `descriptorPairsAreUnique()` exposes that invariant so a test asserts it
 * directly instead of trusting the coincidence.
 *
 * The name `google_type` is inherited vocabulary, not a dependency: this class reads it
 * as an opaque discriminator and issues no Google request, holds no Google credential,
 * and has no Google code path.
 *
 * A DESCRIPTOR THIS CLASS CANNOT PLACE RESOLVES TO NULL
 * -----------------------------------------------------
 * If a future category is added upstream with a pair this map has never seen, the
 * answer is null — no candidates — not a guess. That is the same posture
 * {@see CanonicalCategoryRegistry::resolve()} takes toward an unknown category, for the
 * same reason: a silent fallback here would let a new category be served with another
 * category's rows.
 *
 * Pure and deterministic. No container, no database, no clock, no network.
 *
 * @see \App\Services\LocationDna\OvertureCorpusPoiAdapter
 * @see \Tests\Unit\Services\LocationDna\Providers\CorpusPoiCategoryMapTest
 */
final class CorpusPoiCategoryMap
{
    /**
     * Buyer/tenant view slugs → canonical keys, for the subset the corpus can serve.
     *
     * {@see \App\Services\LocationDna\PoiDistanceLookupService} offers seven slugs:
     * schools, parks, shopping, hospitals, gyms, airports, downtown. The corpus holds
     * rows for exactly two of them. The other five are absent here on purpose and
     * resolve to null — a buyer asking for nearby hospitals must get nothing from a
     * corpus that contains no hospitals, not the nearest gym.
     *
     * Values are canonical keys or registry aliases; both resolve through
     * {@see CanonicalCategoryRegistry::tryResolve()}.
     */
    private const VIEW_SLUG_TO_CANONICAL = [
        'gyms'     => 'gym',
        'shopping' => 'shopping_center',
    ];

    /**
     * The canonical categories Location DNA may answer from the corpus. Exactly seven,
     * sorted. Explicit on purpose — see the class docblock. Never derive this from a corpus
     * taxonomy, the canonical registry or database rows.
     */
    public const LOCATION_DNA_KEYS = [
        'coffee_shop',
        'gas_station',
        'grocery_store',
        'gym',
        'pharmacy',
        'restaurant',
        'shopping_center',
    ];

    /**
     * The canonical keys the loaded corpus can answer for Location DNA, in a stable order.
     *
     * @return list<string>
     */
    public static function supportedCanonicalKeys(): array
    {
        return self::LOCATION_DNA_KEYS;
    }

    /** Is this canonical key (or registry alias) one the corpus holds rows for? */
    public static function supports(string $keyOrAlias): bool
    {
        $canonical = CanonicalCategoryRegistry::tryResolve($keyOrAlias);

        if ($canonical === null) {
            return false;
        }

        return in_array($canonical, self::supportedCanonicalKeys(), true);
    }

    /**
     * The corpus `category_key` for a canonical key, or null when the corpus holds no
     * rows for it.
     *
     * The corpus stores canonical keys verbatim — `place_categories.category_key` was
     * seeded from the same crosswalk — so a supported key maps to itself. The method
     * exists rather than the caller assuming identity because that assumption is what
     * would silently break if a later import ever chose different storage keys.
     */
    public static function corpusCategoryFor(string $keyOrAlias): ?string
    {
        $canonical = CanonicalCategoryRegistry::tryResolve($keyOrAlias);

        if ($canonical === null || ! in_array($canonical, self::supportedCanonicalKeys(), true)) {
            return null;
        }

        return $canonical;
    }

    /**
     * The corpus `category_key` for a buyer/tenant view slug, or null when the corpus
     * cannot serve that slug.
     */
    public static function corpusCategoryForViewSlug(string $slug): ?string
    {
        $canonical = self::VIEW_SLUG_TO_CANONICAL[$slug] ?? null;

        return $canonical === null ? null : self::corpusCategoryFor($canonical);
    }

    /**
     * Recover the canonical category key from a `LocationDnaPoiDistanceService::CATEGORIES`
     * descriptor, or null when no category carries this `(google_type, keyword)` pair.
     *
     * @param array<string, mixed> $meta the descriptor handed to `fetchNearby()`
     */
    public static function canonicalKeyForDescriptor(array $meta): ?string
    {
        $wanted = self::descriptorPair($meta);

        foreach (LocationDnaPoiDistanceService::CATEGORIES as $key => $descriptor) {
            if (self::descriptorPair($descriptor) === $wanted) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The corpus `category_key` for a fetch descriptor, or null when the descriptor is
     * unplaceable OR names a category the corpus does not hold. One call answers the
     * adapter's whole question.
     *
     * @param array<string, mixed> $meta
     */
    public static function corpusCategoryForDescriptor(array $meta): ?string
    {
        $canonical = self::canonicalKeyForDescriptor($meta);

        return $canonical === null ? null : self::corpusCategoryFor($canonical);
    }

    /**
     * Is the `(google_type, keyword)` pair a unique identity across every pipeline
     * category?
     *
     * Exposed so a test can assert the invariant this class's descriptor recovery rests
     * on — and so that adding a nineteenth category that collides with an existing pair
     * fails in CI rather than silently resolving to whichever one is declared first.
     */
    public static function descriptorPairsAreUnique(): bool
    {
        $seen = [];

        foreach (LocationDnaPoiDistanceService::CATEGORIES as $descriptor) {
            $pair = self::descriptorPair($descriptor);

            if (isset($seen[$pair])) {
                return false;
            }

            $seen[$pair] = true;
        }

        return true;
    }

    /**
     * The discriminating pair, normalised to a comparable string.
     *
     * Null and '' collapse to the same token deliberately: a descriptor carrying
     * `'keyword' => null` and one carrying `'keyword' => ''` name the same category, and
     * the tile cache already treats them identically (it casts both to `(string)`).
     *
     * @param array<string, mixed> $descriptor
     */
    /**
     * Public so the uniqueness guard can also scan descriptors that are NOT in
     * `CATEGORIES` — `LocationDnaPoiDistanceService::TOP_RATED_DINING_META` is derived
     * rather than fetched, so `descriptorPairsAreUnique()` cannot see it.
     */
    public static function descriptorPair(array $descriptor): string
    {
        return ((string) ($descriptor['google_type'] ?? ''))
            . "\0"
            . ((string) ($descriptor['keyword'] ?? ''));
    }
}

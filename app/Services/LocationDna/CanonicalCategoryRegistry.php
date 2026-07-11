<?php

namespace App\Services\LocationDna;

use InvalidArgumentException;

/**
 * CanonicalCategoryRegistry — Phase 1, Deliverable 5.
 *
 * The single authoritative vocabulary of POI categories. Today the same concepts are spelled
 * five different ways across the codebase:
 *
 *   - LocationDnaPoiDistanceService::CATEGORIES   19 fetched keys + a derived 'top_rated_dining'
 *   - GooglePlacesPoiAdapter::CATEGORY_MAP        7 PLURAL slugs ('schools', 'gyms', ...)
 *   - PoiDistanceLookupService::SUPPORTED_CATEGORIES  the same 7 slugs
 *   - LocationDnaRankingProfileService::profiles()    20 keys + 'default'
 *   - LocationDnaSummaryService::THEMATIC_BLOCKS      grouping references
 *
 * This registry names each concept once, and records the plural forms as ALIASES so
 * 'schools' and 'school' can never drift apart again.
 *
 * NOTHING READS THIS YET
 * ----------------------
 * It is additive. No consumer has been migrated onto it, and no runtime behaviour, persisted
 * value, ranking outcome, exclusion rule, label, or API shape changes because it exists. Its
 * companion parity test asserts that the registry reproduces every one of the five maps above
 * exactly — which is what earns later batches the right to delete them.
 *
 * PROVIDER-NEUTRAL BY CONSTRUCTION
 * --------------------------------
 * Google is NOT a privileged provider here. `providers` is an open map keyed by provider id;
 * every entry carries a `status`, and Google's is PROVIDER_LEGACY — retained only as migration
 * metadata so Phase 3a's Gate 3 can diff a corpus-sourced result against the frozen Google rows,
 * and so the removal phases have an audit trail of what each category used to mean. Under
 * SIA-D25 the platform is Google-free by design: Google is a legacy dependency to be removed,
 * never a fallback. Nothing in this class may be used to issue a Google request, and the legacy
 * entries are designed to be deletable in one edit (drop the 'google_places' key from each
 * category and the PROVIDER_LEGACY constant) without disturbing anything else.
 *
 * Open-data providers (Overture, CMS, NCES, FAA NASR, NOAA CUSP, PAD-US, GTFS/NTD, USGS, OSM)
 * are declared with PROVIDER_PLANNED and deliberately EMPTY parameters: Phase 2 has not been
 * implemented, and an empty mapping is the honest representation of that. New providers slot in
 * beside the existing ones without a redesign.
 *
 * PER-VIEW ENABLEMENT, NOT A GLOBAL FLAG
 * --------------------------------------
 * A single `enabled` boolean would be wrong and would encode a silent regression. `airport` and
 * `downtown` have never existed as Seller/Landlord Location DNA categories, but they ARE offered
 * today by the Buyer/Tenant lookup. Disabling them outright would quietly remove two categories
 * from that path. So enablement is per view:
 *
 *   VIEW_SELLER_LANDLORD  the Location DNA pipeline (LocationDnaPoiDistanceService)
 *   VIEW_BUYER_TENANT     the distance-lookup path (PoiDistanceLookupService) — a 7-category
 *                         SUBSET VIEW, exactly as the SSOT requires
 *   VIEW_CORPUS           PLANNING SIGNAL ONLY — see below
 *
 * VIEW_CORPUS answers "should Phase 2 import an open-data source for this category?", NOT "is
 * this category live?". No corpus exists yet, so it must never be used as a runtime gate. It is
 * false for the two categories that have no identified open-data source at all (`downtown`,
 * `highway_access`) and for the derived category that Phase 3b deletes.
 *
 * @see \Tests\Unit\Services\LocationDna\CanonicalCategoryRegistryTest
 * @see \Tests\Unit\Services\LocationDna\CanonicalCategoryRegistryParityTest
 */
final class CanonicalCategoryRegistry
{
    /** The Location DNA pipeline's categories (seller / landlord). */
    public const VIEW_SELLER_LANDLORD = 'seller_landlord';

    /** The buyer / tenant distance-lookup subset view. */
    public const VIEW_BUYER_TENANT = 'buyer_tenant';

    /**
     * PLANNING SIGNAL ONLY — "Phase 2 should import a source for this category."
     * Not a runtime gate. No corpus exists yet.
     */
    public const VIEW_CORPUS = 'corpus';

    public const VIEWS = [
        self::VIEW_SELLER_LANDLORD,
        self::VIEW_BUYER_TENANT,
        self::VIEW_CORPUS,
    ];

    /** A provider we are removing. Present for migration and audit only; never callable. */
    public const PROVIDER_LEGACY = 'legacy';

    /** A provider Phase 2 will implement. Parameters are legitimately empty until then. */
    public const PROVIDER_PLANNED = 'planned';

    /** Provider ids whose mappings exist solely to support removal. */
    public const LEGACY_PROVIDERS = ['google_places'];

    /**
     * The canonical vocabulary: 23 categories + 1 derived.
     *
     * The 23 reconcile exactly with the SSOT's "activate all 23 categories" in Phase 3b:
     * 19 currently fetched + the 4 never-built. `top_rated_dining` is derived rather than
     * fetched, which is why it falls outside that count — and why Phase 3b deletes it.
     *
     * Declaration order for the first 19 deliberately mirrors LocationDnaPoiDistanceService::
     * CATEGORIES so the parity test can reconstruct that map key-for-key, in order.
     */
    private const CATEGORIES = [
        'grocery_store' => [
            'label'            => 'Grocery Store',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'grocery_or_supermarket', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'school' => [
            'label'            => 'School',
            'aliases'          => ['schools'],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'school', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => ['type' => 'school', 'keyword' => null]],
                'nces'          => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'hospital' => [
            'label'            => 'Hospital',
            'aliases'          => ['hospitals'],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'hospital', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => ['type' => 'hospital', 'keyword' => null]],
                'cms'           => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'park' => [
            'label'            => 'Park',
            'aliases'          => ['parks'],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'park', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => ['type' => 'park', 'keyword' => null]],
                'pad_us'        => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'pharmacy' => [
            'label'            => 'Pharmacy',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'pharmacy', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'gas_station' => [
            'label'            => 'Gas Station',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'gas_station', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'restaurant' => [
            'label'            => 'Restaurant',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'restaurant', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'gym' => [
            'label'            => 'Gym',
            'aliases'          => ['gyms'],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'gym', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => ['type' => 'gym', 'keyword' => null]],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'fitness_center' => [
            'label'            => 'Fitness Center',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            // Phase 3b merges this into `gym`. Recorded, NOT acted on here.
            'deprecation'      => ['phase' => '3b', 'action' => 'merge', 'merge_into' => 'gym'],
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'gym', 'keyword' => 'fitness center', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'transit_station' => [
            'label'            => 'Transit Station',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'transit_station', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'gtfs_ntd'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'coffee_shop' => [
            'label'            => 'Coffee Shop',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'cafe', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => null],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'shopping_center' => [
            'label'            => 'Shopping Center',
            'aliases'          => ['shopping'],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'shopping_mall', 'keyword' => null, 'query_strategy' => 'native_type'], 'buyer_tenant' => ['type' => 'shopping_mall', 'keyword' => null]],
                'overture'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'beach' => [
            'label'            => 'Beach',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'beach', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'noaa_cusp'     => ['status' => self::PROVIDER_PLANNED],
                'osm'           => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'beach_access' => [
            'label'            => 'Beach Access',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'beach access', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'noaa_cusp'     => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'boat_ramp' => [
            'label'            => 'Boat Ramp',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'boat ramp', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'usgs'          => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'marina' => [
            'label'            => 'Marina',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'marina', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'osm'           => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'waterfront_park' => [
            'label'            => 'Waterfront Park',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => 'park', 'keyword' => 'waterfront', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'pad_us'        => ['status' => self::PROVIDER_PLANNED],
                'noaa_cusp'     => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'dog_park' => [
            'label'            => 'Dog Park',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'dog park', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'osm'           => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'golf_course' => [
            'label'            => 'Golf Course',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => true,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => ['google_type' => null, 'keyword' => 'golf course', 'query_strategy' => 'keyword'], 'buyer_tenant' => null],
                'osm'           => ['status' => self::PROVIDER_PLANNED],
            ],
        ],

        // ── Derived, not fetched. Built from `restaurant` candidates. ──────────────────
        // Phase 3b REMOVES this key and its three view consumers. Recorded, not acted on.
        'top_rated_dining' => [
            'label'            => 'Top Rated Dining',
            'aliases'          => [],
            'derived'          => true,
            'exclusion_policy' => false,
            'deprecation'      => ['phase' => '3b', 'action' => 'remove', 'merge_into' => null],
            'views'            => [self::VIEW_SELLER_LANDLORD => true, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => false],
            'providers'        => [
                // No provider: derived in-process from already-fetched restaurant candidates.
            ],
        ],

        // ── The four never-built categories (SSOT Phase 1 D5) ──────────────────────────
        // Never Seller/Landlord Location DNA categories. `airport` and `downtown` ARE live
        // Buyer/Tenant slugs today, which is exactly why enablement is per view: a flat
        // `enabled => false` would silently remove two categories from that path.
        'airport' => [
            'label'            => 'Airport',
            'aliases'          => ['airports'],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => false, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => true],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => null, 'buyer_tenant' => ['type' => 'airport', 'keyword' => null]],
                'faa_nasr'      => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'downtown' => [
            'label'            => 'Downtown',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            // No open-data source has been identified for "downtown" — it is a fuzzy keyword
            // search, not an entity in any authority dataset. VIEW_CORPUS is false and must
            // stay false until a source exists.
            'views'            => [self::VIEW_SELLER_LANDLORD => false, self::VIEW_BUYER_TENANT => true, self::VIEW_CORPUS => false],
            'providers'        => [
                'google_places' => ['status' => self::PROVIDER_LEGACY, 'seller_landlord' => null, 'buyer_tenant' => ['type' => null, 'keyword' => 'downtown']],
            ],
        ],
        'urgent_care' => [
            'label'            => 'Urgent Care',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            'views'            => [self::VIEW_SELLER_LANDLORD => false, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => true],
            'providers'        => [
                'cms' => ['status' => self::PROVIDER_PLANNED],
            ],
        ],
        'highway_access' => [
            'label'            => 'Highway Access',
            'aliases'          => [],
            'derived'          => false,
            'exclusion_policy' => false,
            'deprecation'      => null,
            // Referenced nowhere in the codebase today, and no source identified.
            'views'            => [self::VIEW_SELLER_LANDLORD => false, self::VIEW_BUYER_TENANT => false, self::VIEW_CORPUS => false],
            'providers'        => [],
        ],
    ];

    /** Every canonical key, in declaration order. @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** The full definition of every category, keyed by canonical key. */
    public static function all(): array
    {
        return self::CATEGORIES;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::CATEGORIES);
    }

    /**
     * Resolve a canonical key or a known alias to its canonical key.
     *
     * Fails closed: an unknown category THROWS rather than returning a default. A silent
     * fallback here would let a typo select the wrong ranking profile or persist an
     * unrecognised poi_category.
     *
     * @throws InvalidArgumentException
     */
    public static function resolve(string $keyOrAlias): string
    {
        $resolved = self::tryResolve($keyOrAlias);

        if ($resolved === null) {
            throw new InvalidArgumentException("Unknown POI category '{$keyOrAlias}'.");
        }

        return $resolved;
    }

    /** Resolve, or null when the category is unknown. */
    public static function tryResolve(string $keyOrAlias): ?string
    {
        if (self::has($keyOrAlias)) {
            return $keyOrAlias;
        }

        foreach (self::CATEGORIES as $key => $definition) {
            if (in_array($keyOrAlias, $definition['aliases'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The full definition for a category (canonical key or alias).
     *
     * @throws InvalidArgumentException on an unknown category.
     */
    public static function get(string $keyOrAlias): array
    {
        return self::CATEGORIES[self::resolve($keyOrAlias)];
    }

    public static function label(string $keyOrAlias): string
    {
        return self::get($keyOrAlias)['label'];
    }

    /**
     * Is this category enabled for the given view?
     *
     * @throws InvalidArgumentException on an unknown category or an unknown view.
     */
    public static function isEnabledFor(string $keyOrAlias, string $view): bool
    {
        self::assertKnownView($view);

        return self::get($keyOrAlias)['views'][$view];
    }

    /**
     * Canonical keys enabled for a view, in declaration order.
     *
     * @return list<string>
     * @throws InvalidArgumentException on an unknown view.
     */
    public static function enabledFor(string $view): array
    {
        self::assertKnownView($view);

        $enabled = [];

        foreach (self::CATEGORIES as $key => $definition) {
            if ($definition['views'][$view]) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    /** Categories that carry an exclusion policy. The RULES themselves live with their consumer. */
    public static function withExclusionPolicy(): array
    {
        return array_keys(array_filter(
            self::CATEGORIES,
            static fn (array $d): bool => $d['exclusion_policy'],
        ));
    }

    public static function isDerived(string $keyOrAlias): bool
    {
        return self::get($keyOrAlias)['derived'];
    }

    /** Deprecation metadata, or null. Recorded for the removal phases; NOT acted on at runtime. */
    public static function deprecation(string $keyOrAlias): ?array
    {
        return self::get($keyOrAlias)['deprecation'];
    }

    /**
     * A provider's mapping for a category, or [] when that provider has none.
     *
     * Provider-neutral: 'google_places' has no privileged position and is marked
     * PROVIDER_LEGACY. An empty result is the correct answer for a planned provider whose
     * import Phase 2 has not built.
     */
    public static function providerMapping(string $keyOrAlias, string $provider): array
    {
        return self::get($keyOrAlias)['providers'][$provider] ?? [];
    }

    public static function isLegacyProvider(string $provider): bool
    {
        return in_array($provider, self::LEGACY_PROVIDERS, true);
    }

    /** @throws InvalidArgumentException */
    private static function assertKnownView(string $view): void
    {
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException("Unknown category view '{$view}'.");
        }
    }
}

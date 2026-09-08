<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Criteria Location DNA — geography cascade (Phase 1)
    |--------------------------------------------------------------------------
    |
    | Master gate for the Criteria geography experience: state (required) →
    | counties (required) → ZIPs and cities (optional).
    |
    | DEFAULT OFF, and Phase 1a ships nothing that reads it. The flag is
    | introduced with the foundation so the UI increment (Phase 1c) has a gate
    | already in place and reviewed, rather than adding a switch in the same
    | commit that adds the surface it controls.
    |
    | Turning this on does NOT change how anything is saved. The Phase 1 preview
    | is read-only by construction: `App\Services\LocationDna\Criteria` contains
    | no write path of any kind, and the criteria controllers' write paths are
    | untouched. Writing through the canonical writer is Phase 2, gated
    | separately and authorised separately.
    |
    */

    'geography_preview_enabled' => (bool) env('CRITERIA_LDNA_PREVIEW_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geography source
    |--------------------------------------------------------------------------
    |
    | Which CriteriaGeographyRepository implementation backs the cascade.
    |
    | ALLOWED VALUES — and there is no fourth. Anything else THROWS at resolution
    | rather than falling back, because a silent fallback to 'eloquent' is
    | indistinguishable from success and would serve legacy data unannounced.
    | See the binding in AppServiceProvider.
    |
    |   'eloquent'  the `us_states` / `us_counties` / `us_cities` / `us_zip_codes`
    |               reference tables. Real data, already populated, no new
    |               infrastructure. THE DEFAULT, and unchanged by Phase 1d-2.
    |
    |   'census'    the `census_*` corpus imported by `census:import-geography`
    |               (Phase 1d-1). Published Census geography: places instead of
    |               USPS ZIP localities, ZCTA-to-county relationships instead of
    |               a county-name join, and real many-to-many for both places and
    |               ZIPs. Identifiers become GEOIDs rather than surrogate keys —
    |               which changes nothing stored, because a selection is
    |               persisted as LABELS and repository ids live for one request.
    |
    |               REQUIRES THE CORPUS TO BE PRESENT. The migrations must have
    |               run and the import must have completed, or every tier
    |               enumerates empty — which is worse than an error because the
    |               cascade simply renders nothing. Run
    |               `php artisan census:verify-geography` before selecting this,
    |               and in the deploy sequence of any environment that uses it.
    |
    |   'fake'      the in-memory fixture repository. For local composition and
    |               demos only — it returns whatever it was handed.
    |
    | A PostGIS-backed implementation joins this list when the spatial corpus is
    | provisioned; that is a map-GEOMETRY concern (later phase), not a selection
    | concern, so the cascade does not wait on it. Adding it means adding an arm
    | to the match, which is deliberate work rather than a config value that
    | quietly resolves to something else.
    |
    */

    'geography_source' => env('CRITERIA_LDNA_GEOGRAPHY_SOURCE', 'eloquent'),

    /*
    |--------------------------------------------------------------------------
    | Geography cascade — the editing surface
    |--------------------------------------------------------------------------
    |
    | Master gate for replacing a workflow's four free-text geography inputs with
    | the corpus-backed cascade: state → counties → optional cities → optional
    | ZIPs.
    |
    | DEFAULT OFF. With the gate closed every workflow renders exactly what it
    | rendered before — the shared map widget keeps its own geography inputs and
    | its own third-party place autocomplete — and no new query is issued.
    |
    | Turning this on does NOT change how anything is saved. The cascade projects
    | its selection back into the same four canonical keys, in the same label
    | format, and hands them to the same writer. No meta key is added, no mirror
    | set changes, and no schema changes.
    |
    | INDEPENDENT OF `geography_source` ON PURPOSE. The source decides WHICH DATA
    | backs the tiers; this decides WHETHER THE CASCADE RENDERS AT ALL. Either can
    | move without the other, so the census corpus can be exercised through the
    | repository tests while every editing surface stays on its legacy inputs.
    |
    | MANUAL VISUAL VERIFICATION IS A PREREQUISITE before enabling this in any
    | shared environment. There is no automated browser coverage for these tabs,
    | so layout and CSS regressions are not caught by the suite.
    |
    */

    'geography_cascade_enabled' => (bool) env('CRITERIA_LDNA_CASCADE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geography cascade scope
    |--------------------------------------------------------------------------
    |
    | Which workflows the cascade applies to while the master gate is open. Both
    | must agree before anything renders, so a single environment variable cannot
    | widen the rollout by accident.
    |
    | A WORKFLOW MAY ONLY BE LISTED ONCE ITS TAB RENDERS THE CASCADE, and that is
    | a data-safety rule rather than a tidiness one. The cascade states all four
    | geography keys whenever it is enabled, so a workflow switched on while its
    | tab still showed the legacy inputs would submit four empty values and
    | silently clear the user's stored geography. The tab opt-in and the entry
    | here must therefore land together.
    |
    | THE SHIPPED DEFAULT NAMES ONLY WHAT IS WIRED. Slice 1 wired `hire_buyer`
    | across all three reachable surfaces, so it is listed here in the same commit
    | that made its tab render the cascade. Each later slice adds its own key
    | alongside its tab, which keeps "listed" and "wired" the same statement at
    | every commit rather than only at the end of the rollout.
    |
    | Valid keys are the four Buyer/Tenant workflows. Their status:
    |
    |   `hire_buyer`     WIRED and LISTED. Slice 1.
    |
    |   `create_buyer`   WIRED and LISTED. Both BuyerOfferListing and
    |                    BuyerOfferListingEdit carry the cascade and search traits,
    |                    their shared property-preferences tab renders the cascade
    |                    behind `$geoCascadeEnabled`, and the widget's own tier
    |                    inputs are suppressed when it does. Deliberately its own
    |                    key rather than a reuse of `hire_buyer`: separate record
    |                    family, separate rollout step. This family writes no
    |                    `zipCodes` mirror and declares no `$zipCodes` property, so
    |                    it is correctly absent from ZIP_MIRROR_WORKFLOWS — the same
    |                    answer as `hire_buyer`, for the same structural reason.
    |
    |   `hire_tenant`    WIRED but NOT LISTED. Its components and tab carry the
    |                    cascade already; listing it is a separate rollout decision
    |                    that has not been taken. Being wired is what makes adding
    |                    it SAFE, not what makes it due.
    |
    |   `create_tenant`  WIRED but NOT LISTED, exactly like `hire_tenant`.
    |
    |                    THIS ENTRY PREVIOUSLY READ "NOT WIRED. Must not be added
    |                    here." That was true when it was written (d5473e68f,
    |                    2026-08-11 17:33) and stopped being true four hours later,
    |                    and the correction matters because the stale text does not
    |                    merely describe the wrong state — it warns AGAINST the
    |                    restoration, on a data-safety ground that no longer holds.
    |
    |                    The objection was real: these components own live `$zipCodes`
    |                    state and write a legacy `zipCodes` mirror from it, while
    |                    their load path did not fold that legacy key into the blob —
    |                    so a cascade would hydrate empty and project an empty list
    |                    over stored ZIPs. `5ca23ff65` (20:41) landed exactly that
    |                    load-side normalization, and `bbfec52c0` (21:13) then wired
    |                    both Create Tenant surfaces on top of it. Neither commit
    |                    updated this file, which is how the warning outlived its
    |                    cause.
    |
    |                    The precondition is pinned, not assumed:
    |                    `CreateTenantLegacyGeographyBackfillTest` asserts legacy
    |                    tenant ZIPs reach the blob on both surfaces and that a
    |                    populated blob is never overwritten, and
    |                    `CreateTenantGeographyWiringTest` asserts the cascade cannot
    |                    write `$zipCodes` and that `create_tenant` is correctly
    |                    absent from ZIP_MIRROR_WORKFLOWS.
    |
    | Seller and Landlord are excluded STRUCTURALLY, not by this list — their tabs
    | carry no geography surface, and every component that serves them maps their
    | user types to no workflow at all, so no value here can reach them.
    |
    | Note that the master gate above still ships OFF, so listing a workflow here
    | does not turn anything on by itself. Both must agree.
    |
    */

    'geography_cascade_workflows' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CRITERIA_LDNA_CASCADE_WORKFLOWS', 'hire_buyer,create_buyer'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Neighborhood tier (Phase 1d-5)
    |--------------------------------------------------------------------------
    |
    | Master gate for the tier BELOW cities: neighbourhoods and communities,
    | served from the supplemental `location_places` layer rather than from any
    | published corpus. Clearwater Beach is the case it exists for — a barrier
    | island inside the City of Clearwater that the Census cannot publish because
    | it is not a unit of government.
    |
    | DEFAULT OFF. With the gate closed, `CriteriaNeighborhoodRepository` resolves
    | to the null object, every enumeration returns an empty list, and a stored
    | `Clearwater Beach, FL` stays preserved-but-unmatched exactly as it is today.
    |
    | IT ALSO REQUIRES `geography_source = census`, AND THAT IS NOT A SECOND
    | SWITCH TO REMEMBER — it is enforced in the binding. The tier joins a city
    | option's id to `location_places.census_place_geoid`, which is only the same
    | value under the census source; under `eloquent` a city id is a `us_cities`
    | surrogate key that would address a different place or none at all. So this
    | flag turned on against the eloquent source yields the null object rather
    | than wrong neighbourhoods. Failing to an empty tier is safe; the geography
    | source's own binding throws instead, because there a silent fallback would
    | serve real-but-wrong data.
    |
    | TURNING THIS ON DOES NOT ADD A STORAGE KEY. A selected neighbourhood is
    | projected into the EXISTING `cities` array, in the same `{name}, {ST}` label
    | format. There is no `neighborhoods` key in a stored document and there must
    | not be one — six consumers read `cities` and none would read a fifth key.
    | See `GeographyTier::Neighborhoods`.
    |
    */

    'neighborhood_tier_enabled' => (bool) env('CRITERIA_LDNA_NEIGHBORHOOD_TIER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geography search (M2)
    |--------------------------------------------------------------------------
    |
    | Master gate for the search box that SEEDS the cascade: type "Clearwater",
    | pick the result, and the state/county/city tiers are filled in as though
    | the three selects had been used in order.
    |
    | DEFAULT OFF. With the gate closed the cascade renders exactly as it does
    | today and `App\Http\Livewire\Concerns\HasGeographySearch` runs no query.
    |
    | IT ALSO REQUIRES THE CASCADE, AND THAT IS NOT A SECOND SWITCH TO REMEMBER —
    | it is enforced in `geographySearchIsEnabled()`. Search fills the cascade's
    | tiers; where those tiers do not render there is nothing to fill, and a
    | search box above the legacy free-text inputs would populate selections no
    | surface shows. So Seller and Landlord stay excluded here for exactly the
    | reason they are excluded from the cascade, with no second rule to keep in
    | step.
    |
    | IT REQUIRES `geography_source = census` IN PRACTICE, though nothing here
    | asserts it: `GeographySearchRepository` binds to the null object under any
    | other source, so search returns nothing rather than identifiers the
    | cascade cannot hold.
    |
    | NEIGHBOURHOODS ARE NOT SEARCHED regardless of this flag — the tier above
    | governs that, and the search surface requests only state, county, city and
    | ZIP.
    |
    */

    'geography_search_enabled' => (bool) env('CRITERIA_LDNA_SEARCH_ENABLED', false),

];

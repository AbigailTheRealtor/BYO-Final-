<?php

/*
|--------------------------------------------------------------------------
| Overture chain registry — chain-registry-v2
|--------------------------------------------------------------------------
|
| The declarative contract that decides which corpus-v2 place rows belong to which retail
| chain. Read ONLY through App\Services\Spatial\ChainRegistry\ChainRegistry, which validates
| every rule at load time and refuses a malformed file. Nothing in the application consumes the
| registry yet: no Location DNA, Ask AI, Buyer/Tenant matching or brand-query wiring exists.
|
| Design and every locked decision: docs/spatial/overture-chain-registry-design.md.
| Factual basis: docs/spatial/overture-corpus-v2-design.md (PR 0, Overture 2026-08-19.0) and the
| real-data census docs/spatial/overture-chain-registry-census.md, whose findings the v2 decisions
| of 2026-09-22 (numbered "v2 decision N" below) act on.
|
| EVIDENCE — every rule-bearing entry carries one:
|   M  measured in PR 0 or in the census (a count, QID or observed name)
|   D  a locked decision (v2 design §1/§7, or the chain-registry decisions of 2026-09-22)
|   P  proposed; unmeasured; fail-closed until PR 3 accounting confirms it
|
| This file holds ONLY `provisional`. Promotion to `supported` is a separate validation record
| keyed to the rule hash (design §14); it never lives here.
|
| Any change to what a row matches is a registry-version bump. The rule-hash pin in
| tests/Unit/Spatial/ChainRegistry/ChainRegistryConfigTest.php fails until that happens.
|
| No env() anywhere: this is data, identical on every host.
*/

return [

    'registry_version' => 'chain-registry-v2',
    'normalizer_version' => 'chain-name-norm-v1',
    'taxonomy_map_version' => 'overture-taxonomy-v2.0',

    'global' => [

        // Never a valid identity category for ANY chain. The corpus keeps shopping_center for
        // Location DNA; a plaza named for its anchor is not the anchor.
        'excluded_categories' => [
            'shopping_center' => 'D: chain-registry decision 1 (2026-09-22)',
        ],

        // Embedded-service identities. A row carrying one is REJECTED whatever its name says:
        // "PUBLIX #1029" with Western Union's QID is a Western Union counter.
        'exclusion_wikidata_ids' => [
            'Q861042'   => ['label' => 'Western Union', 'evidence' => 'M: 2,948 name-matched rows (v2 §6)'],
            'Q857063'   => ['label' => 'Citibank', 'evidence' => 'M: 410 rows, ATMs incl. "ATM Walgreens" (v2 §6)'],
            'Q4835981'  => ['label' => 'BMO', 'evidence' => 'M: 158 rows, ATMs (v2 §6)'],
            'Q38928'    => ['label' => 'PNC Bank', 'evidence' => 'M: 29 rows, ATMs (v2 §6)'],
            'Q5835668'  => ['label' => 'Santander', 'evidence' => 'M: ATMs (v2 §6)'],
            'Q59773555' => ['label' => 'Electrify America', 'evidence' => 'M: EV chargers, 13 at Walmart (v2 §3, §6)'],
            'Q7271456'  => ['label' => 'Quest Diagnostics', 'evidence' => 'M: lab rows (v2 §6)'],
        ],

        // Matched against the NORMALISED name and brand, anywhere in the string: rejecting on an
        // infix is the safe direction. Never a match route.
        'exclusion_name_patterns' => [
            'atm'               => ['pattern' => '/\batm\b/', 'evidence' => 'M: "ATM Walgreens" 198, "ATM 7ELEVEN-FCTI" 54 (v2 §3)'],
            'western_union'     => ['pattern' => '/\bwestern union\b/', 'evidence' => 'M: money-transfer counters (v2 §3)'],
            'money_center'      => ['pattern' => '/\bmoney center\b/', 'evidence' => 'M: "Walmart Money Center" (v2 §3)'],
            'money_transfer'    => ['pattern' => '/\bmoney transfer\b/', 'evidence' => 'P: money_transfer_service family'],
            'fcti'              => ['pattern' => '/\bfcti\b/', 'evidence' => 'M: "ATM 7ELEVEN-FCTI" (v2 §3)'],
            'ev_charging'       => ['pattern' => '/\bev charg/', 'evidence' => 'P: ev_charging_station family'],
            'recharge'          => ['pattern' => '/\brecharge\b/', 'evidence' => 'M: "Shell Recharge" 10 (v2 §3)'],
            'locker'            => ['pattern' => '/\blockers?\b/', 'evidence' => 'P: package_locker family (v2 §3)'],
            'clinic'            => ['pattern' => '/\bclinics?\b/', 'evidence' => 'M: "UHealth Clinic at Walgreens" 13 (v2 §3)'],
            'minute_clinic'     => ['pattern' => '/\bminute ?clinic\b/', 'evidence' => 'M: "MinuteClinic at CVS" 23, "Minute Clinic" 33 (v2 §3)'],
            'labcorp'           => ['pattern' => '/\blabcorp\b/', 'evidence' => 'M: "Labcorp at Walgreens" (v2 §3)'],
            'village_medical'   => ['pattern' => '/\bvillage medical\b/', 'evidence' => 'M: "Village Medical at Walgreens" 30 (v2 §3)'],
            'quest_diagnostics' => ['pattern' => '/\bquest diagnostics\b/', 'evidence' => 'P: name form of the measured Quest QID'],
            'optical'           => ['pattern' => '/\boptical\b/', 'evidence' => 'M: "Target Optical" 8 (v2 §3)'],
            'photo_center'      => ['pattern' => '/\bphoto center\b/', 'evidence' => 'M: "Walmart Photo Center" 138 (v2 §3)'],
            'catering'          => ['pattern' => '/\bcatering\b/', 'evidence' => 'M: "Publix Catering", Chick-fil-A catering (v2 §3)'],
            'real_estate'       => ['pattern' => '/\breal estate\b/', 'evidence' => 'M: "Royal Shell Real Estate" (v2 §3)'],
            'charities'         => ['pattern' => '/\bcharities\b/', 'evidence' => 'M: "Ronald McDonald House Charities" (v2 §3)'],
            'ronald_mcdonald'   => ['pattern' => '/\bronald mcdonald\b/', 'evidence' => 'M: "Ronald McDonald House Charities" (v2 §3)'],
            // v2 decision 5 — corporate / logistics sites reached by a name prefix. Each measured
            // on every chain-identified census row before adoption: every hit a false positive.
            'office'              => ['pattern' => '/\boffices?\b/', 'evidence' => 'M: 8 census rows, all offices ("Walgreens District Office", "Publix Downtown Office")'],
            'warehouse'           => ['pattern' => '/\bwarehouses?\b/', 'evidence' => 'M: 3 census rows ("Publix Grocery Warehouse", "Walmart Warehouse Dc")'],
            'distribution_center' => ['pattern' => '/\bdistribution cent(er|re)s?\b/', 'evidence' => 'M: 7 census rows, Publix / Walmart / Walgreens / Whole Foods DCs'],
            'support_center'      => ['pattern' => '/\bsupport cent(er|re)s?\b/', 'evidence' => 'M: 2 census rows, "RaceTrac Support Center"'],
            'careers'             => ['pattern' => '/\bcareers?\b/', 'evidence' => 'M: 2 census rows, brand "Whole Foods Market Careers"'],
        ],

        'closed_name_patterns' => [
            'closed' => ['pattern' => '/\b(permanently )?closed\b/', 'evidence' => 'D: v2 §4 ("7-Eleven - Closed")'],
        ],

        // Fuel brands are DIAGNOSTIC ONLY. A fuel-brand QID or name never creates a chain
        // membership, never removes one, and is never read as a foreign store identity.
        // Fuel identity is not store-chain identity (v2 §6; chain-registry decision 5).
        'fuel_brands' => [
            'mobil' => [
                'label' => 'Mobil',
                'wikidata_ids' => ['Q109676002'],
                'names' => ['mobil'],
                'evidence' => 'M: Q109676002 on 73 "7-Eleven" gas rows, all <0.90 (v2 §6); name P',
            ],
            'arco' => [
                'label' => 'Arco',
                'wikidata_ids' => ['Q304769'],
                'names' => ['arco'],
                'evidence' => 'M: Q304769 on 1 Shell-named row (v2 §6); name P',
            ],
        ],
    ],

    'chains' => [

        // ── Grocery ─────────────────────────────────────────────────────────────────────────

        'publix' => [
            'display_name' => 'Publix',
            'aliases' => [
                ['value' => 'publix', 'match' => 'prefix', 'evidence' => 'M: "PUBLIX #1029" observed (on Western Union rows, v2 §3)'],
            ],
            'brand_aliases' => [
                ['value' => 'publix', 'evidence' => 'P'],
            ],
            'own_wikidata_ids' => [
                'Q672170' => 'M: 36 rows, 1.3% fill (v2 §6)',
            ],
            'exclusion_name_patterns' => [
                'liquor' => ['pattern' => '/\bliquors?\b/', 'evidence' => 'M: Publix liquor 22 rows; liquor excluded (v2 decision 5)'],
            ],
            'allowed_categories' => [
                'grocery_store' => ['role' => 'storefront', 'evidence' => 'P: narrowest correct set; PR 3 matrix confirms'],
                'pharmacy'      => ['role' => 'department', 'evidence' => 'P: in-store pharmacy'],
            ],
            'formats' => [
                'supermarket'         => ['categories' => ['grocery_store'], 'role' => 'storefront', 'evidence' => 'P'],
                'pharmacy_department' => ['categories' => ['pharmacy'], 'role' => 'department', 'evidence' => 'P'],
            ],
            'default_format' => 'supermarket',
            'validation_status' => 'provisional',
            'notes' => ['GreenWise Market is unmeasured and deliberately not aliased (decision 9).'],
        ],

        'aldi' => [
            'display_name' => 'Aldi',
            'aliases' => [['value' => 'aldi', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'aldi', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q41171672' => 'M: 59 rows, 4.8% fill (v2 §6)'],
            'allowed_categories' => [
                'grocery_store' => ['role' => 'storefront', 'evidence' => 'P'],
            ],
            'formats' => [
                'store' => ['categories' => ['grocery_store'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
        ],

        'winn_dixie' => [
            'display_name' => 'Winn-Dixie',
            'aliases' => [['value' => 'winn dixie', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'winn dixie', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q1264366' => 'M: 1 row, thin (v2 §6)'],
            'exclusion_name_patterns' => [
                'liquor'       => ['pattern' => '/\bliquors?\b/', 'evidence' => 'M: "Winn-Dixie Liquor" 24 (v2 §3)'],
                'wine_spirits' => ['pattern' => '/\bwine and spirits\b/', 'evidence' => 'M: "Winn-Dixie Wine & Spirits" (v2 §3)'],
            ],
            'allowed_categories' => [
                'grocery_store' => ['role' => 'storefront', 'evidence' => 'P'],
                'pharmacy'      => ['role' => 'department', 'evidence' => 'P: in-store pharmacy'],
                'drugstore'     => ['role' => 'storefront', 'identity' => 'strong', 'evidence' => 'M: 8 census rows named "Winn-Dixie" / "Winn-Dixie at <plaza>"; v2 decision 8, provisional'],
            ],
            'formats' => [
                'store'               => ['categories' => ['drugstore', 'grocery_store'], 'role' => 'storefront', 'evidence' => 'P'],
                'pharmacy_department' => ['categories' => ['pharmacy'], 'role' => 'department', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
        ],

        'whole_foods' => [
            'display_name' => 'Whole Foods Market',
            'aliases' => [['value' => 'whole foods', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [
                ['value' => 'whole foods market', 'evidence' => 'P'],
                ['value' => 'whole foods', 'evidence' => 'P'],
            ],
            'own_wikidata_ids' => [],
            'allowed_categories' => [
                'grocery_store' => ['role' => 'storefront', 'evidence' => 'P'],
            ],
            'formats' => [
                'store' => ['categories' => ['grocery_store'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
            'notes' => ['Weak identity: no own QID observed, 36 eligible rows (v2 §7, §10-3).'],
        ],

        'trader_joes' => [
            'display_name' => "Trader Joe's",
            'aliases' => [['value' => 'trader joes', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'trader joes', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q688825' => 'M: 6 rows, 9.5% fill (v2 §6)'],
            'allowed_categories' => [
                'grocery_store' => ['role' => 'storefront', 'evidence' => 'P'],
            ],
            'formats' => [
                'store' => ['categories' => ['grocery_store'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
        ],

        // ── Mass merchants ──────────────────────────────────────────────────────────────────

        'walmart' => [
            'display_name' => 'Walmart',
            'aliases' => [
                ['value' => 'walmart', 'match' => 'prefix', 'evidence' => 'M: "Walmart Money Center", "Walmart Bakery" observed (v2 §3)'],
                ['value' => 'wal mart', 'match' => 'prefix', 'evidence' => 'P'],
            ],
            'brand_aliases' => [['value' => 'walmart', 'evidence' => 'P']],
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => [],
            'exclusion_name_patterns' => [
                'vision'    => ['pattern' => '/\bvision\b/', 'evidence' => 'M: "Walmart Vision & Glasses" 125 (v2 §3)'],
                'bakery'    => ['pattern' => '/\bbakery\b/', 'evidence' => 'M: "Walmart Bakery" 217 (v2 §3)'],
                'health'    => ['pattern' => '/\bhealth\b/', 'evidence' => 'M: "Walmart Health" 12 (v2 §3)'],
                'auto_care' => ['pattern' => '/\bauto care\b/', 'evidence' => 'P'],
                'tire'      => ['pattern' => '/\btires?\b/', 'evidence' => 'P'],
                // v2 decisions 4 and 5.
                'pickup_delivery' => ['pattern' => '/\b(pickup|pick up|delivery)\b/', 'evidence' => 'M: 11 Walmart census rows ("Walmart Grocery Pickup and Delivery", "Curbside Pickup")'],
                'dc'              => ['pattern' => '/\bdc\b/', 'evidence' => 'M: 3 census rows, all Walmart distribution centres ("Walmart DC 6099"); Walmart-only, v2 decision 5'],
            ],
            'allowed_categories' => [
                'superstore'       => ['role' => 'storefront', 'evidence' => 'D: v2 §7 Walmart formats'],
                'department_store' => ['role' => 'storefront', 'evidence' => 'D: v2 §7 Walmart formats'],
                // Department by DEFAULT: grocery rows inside a Supercenter are departments (v2 §7).
                // Only the neighborhood_market format may make a grocery row a storefront.
                // v2 decision 4: an explicit "Neighborhood Market" or "Supercenter" name makes a
                // grocery row a storefront; a plain "Walmart" stays a department, i.e.
                // storefront_unconfirmed until de-duplication places it.
                'grocery_store'    => ['role' => 'department', 'evidence' => 'D: v2 §7; chain-registry v2 decision 4'],
                'pharmacy'         => ['role' => 'department', 'evidence' => 'D: v2 §7'],
            ],
            'formats' => [
                'neighborhood_market' => [
                    'categories' => ['grocery_store'],
                    'name_patterns' => ['/^neighborhood market\b/'],
                    'role' => 'storefront',
                    'user_visible' => true,
                    'label' => 'Neighborhood Market',
                    'evidence' => 'D: v2 decision 3a',
                ],
                'supercenter' => [
                    // grocery_store: v2 decision 4 — 9 census rows named "Walmart Supercenter".
                    'categories' => ['grocery_store', 'superstore', 'department_store'],
                    'name_patterns' => ['/^supercenter\b/'],
                    'role' => 'storefront',
                    'user_visible' => true,
                    'label' => 'Supercenter',
                    'evidence' => 'D: v2 §7',
                ],
                'store'               => ['categories' => ['superstore', 'department_store'], 'role' => 'storefront', 'evidence' => 'D: v2 §7'],
                'grocery_department'  => ['categories' => ['grocery_store'], 'role' => 'department', 'evidence' => 'D: v2 §7'],
                'pharmacy_department' => ['categories' => ['pharmacy'], 'role' => 'department', 'evidence' => 'D: v2 §7'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
            'notes' => [
                'Weak identity: no own QID observed; departments dominate (v2 §7, §10-3).',
                'No gas_station: Walmart fuel is unmeasured (decision 7).',
            ],
        ],

        'target' => [
            'display_name' => 'Target',
            'aliases' => [
                // Exact only: "target" is an ordinary English word.
                ['value' => 'target', 'match' => 'exact', 'evidence' => 'P'],
                ['value' => 'target store', 'match' => 'exact', 'evidence' => 'P'],
            ],
            'brand_aliases' => [['value' => 'target', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q1046951' => 'M: 1 row, thin (v2 §6)'],
            'department_wikidata_ids' => [
                'Q19903688' => ['label' => 'Target Optical', 'evidence' => 'M: 4 rows, eyewear_store (v2 §6)'],
            ],
            'exclusion_name_patterns' => [
                'pharmacy' => ['pattern' => '/\bpharmacy\b/', 'evidence' => 'P: a pharmacy in Target is CVS (v2 decision 3b)'],
            ],
            'allowed_categories' => [
                'department_store' => ['role' => 'storefront', 'evidence' => 'D: v2 §7 Target formats'],
                'superstore'       => ['role' => 'storefront', 'evidence' => 'D: v2 §7 Target formats'],
            ],
            'excluded_categories' => [
                'pharmacy'      => 'D: v2 decision 3b (CVS inside Target answers cvs)',
                'drugstore'     => 'D: v2 decision 3b',
                'grocery_store' => 'P',
            ],
            'formats' => [
                'store' => ['categories' => ['department_store', 'superstore'], 'role' => 'storefront', 'evidence' => 'D: v2 §7'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
        ],

        // ── Pharmacy ────────────────────────────────────────────────────────────────────────

        'walgreens' => [
            'display_name' => 'Walgreens',
            'aliases' => [
                ['value' => 'walgreens', 'match' => 'prefix', 'evidence' => 'M: "ATM Walgreens", "Labcorp at Walgreens" observed and must fail (v2 §3)'],
            ],
            'brand_aliases' => [['value' => 'walgreens', 'evidence' => 'M: brand name on 28/855 eligible rows (v2 §7)']],
            // v2 decision 6: 13 "… Community Pharmacy" rows carry brand "Walgreens".
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q1591889' => 'M: 2 rows, thin (v2 §6)'],
            'allowed_categories' => [
                'pharmacy'  => ['role' => 'storefront', 'evidence' => 'P'],
                'drugstore' => ['role' => 'storefront', 'evidence' => 'P: drugstore is 0.0% branded, so name-only (v2 §2)'],
            ],
            'formats' => [
                'store' => ['categories' => ['pharmacy', 'drugstore'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
            'notes' => ['Weak identity (v2 §7, §10-3).'],
        ],

        'cvs' => [
            'display_name' => 'CVS',
            'aliases' => [['value' => 'cvs', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [
                ['value' => 'cvs pharmacy', 'evidence' => 'M: brand on "Minute Clinic" rows (v2 §3)'],
                ['value' => 'cvs', 'evidence' => 'P'],
            ],
            // v2 decision 6: "Jenny's Your Friendly Pharmacy", "Omnicare" carry brand "CVS Pharmacy".
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q2078880' => 'M: 5 rows, 0.3% fill (v2 §6)'],
            'exclusion_name_patterns' => [
                'beauty' => ['pattern' => '/\bbeauty\b/', 'evidence' => 'M: "CVS Beauty" 64 rows in shopping (v2 §3)'],
                'photo'  => ['pattern' => '/\bphoto\b/', 'evidence' => 'M: 17 "CVS Photo" census rows (6 matched as stores in v1); v2 decision 5'],
            ],
            'allowed_categories' => [
                'pharmacy'          => ['role' => 'storefront', 'evidence' => 'P'],
                'drugstore'         => ['role' => 'storefront', 'evidence' => 'P'],
                'convenience_store' => ['role' => 'storefront', 'identity' => 'strong', 'evidence' => 'M: 40 census "CVS Pharmacy" rows, all > 150 m from any matched CVS; v2 decision 1'],
            ],
            'formats' => [
                'store' => ['categories' => ['convenience_store', 'drugstore', 'pharmacy'], 'role' => 'storefront', 'evidence' => 'P'],
                'store_in_target' => [
                    // convenience_store: 1 census "CVS Pharmacy inside Target Store" row there fell
                    // to the generic `store` format; the same name in pharmacy / shopping did not.
                    'categories' => ['convenience_store', 'pharmacy', 'drugstore'],
                    'name_patterns' => ['/\b(inside|at|in) target\b/'],
                    'role' => 'storefront',
                    // v2 decision 7: Target identity beside a CVS-named pharmacy row is the host
                    // store, not a conflict — only in the host categories, only with CVS in the
                    // place's own name. Target stays a conflict everywhere else, including a
                    // Target-branded convenience_store row: no census row measured that shape, so
                    // there the name pattern selects the format and the host exception does not.
                    'host_chains' => ['target'],
                    'host_categories' => ['pharmacy', 'drugstore'],
                    'evidence' => 'D: v2 decision 3b; chain-registry v2 decision 7; M: 2 census "CVS Pharmacy" rows with brand "Target", 1 "CVS Pharmacy inside Target Store" in convenience_store',
                ],
            ],
            'default_format' => 'store',
            // v2 decision 1: `shopping` is NOT added to the import list. A strongly identified CVS
            // row there is admitted for CVS alone, as a drugstore storefront — never into Location
            // DNA, whose pharmacy key does not include drugstore (v2 decision 4).
            'source_category_rescues' => [
                'shopping' => [
                    'as_category' => 'drugstore',
                    'exclusion_name_patterns' => [
                        // Beauty (chain-level), MinuteClinic and clinic (global) already refuse.
                        'specialty' => ['pattern' => '/\bspecialty\b/', 'evidence' => 'M: "CVS Specialty Pharmacy", "CVS Pharmacy Specialty Services" in shopping; rescue-only — globally it would refuse a real Publix pharmacy'],
                    ],
                    'evidence' => 'M: 150 real CVS storefronts in shopping, all > 150 m from any matched CVS; v2 decision 1',
                ],
            ],
            'validation_status' => 'provisional',
        ],

        // ── Coffee ──────────────────────────────────────────────────────────────────────────

        'starbucks' => [
            'display_name' => 'Starbucks',
            'aliases' => [['value' => 'starbucks', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'starbucks', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q37158' => 'M: 41 rows, 1.0% fill (v2 §6)'],
            'allowed_categories' => [
                'coffee_shop' => ['role' => 'storefront', 'evidence' => 'P'],
                'cafe'        => ['role' => 'storefront', 'evidence' => 'P: cafe is its own key (v2 decision 1)'],
            ],
            'excluded_categories' => [
                'restaurant' => 'D: chain-registry decision 4 (2026-09-22)',
            ],
            'formats' => [
                'store' => ['categories' => ['coffee_shop', 'cafe'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'validation_status' => 'provisional',
            'notes' => ['Licensed-store variants are unmeasured and not aliased (decision 9).'],
        ],

        // ── Convenience / fuel ──────────────────────────────────────────────────────────────

        'seven_eleven' => [
            'display_name' => '7-Eleven',
            'aliases' => [
                ['value' => '7 eleven', 'match' => 'prefix', 'evidence' => 'M: "7-ELEVEN/SPEEDWAY #46807" observed (v2 §3)'],
                ['value' => '7eleven', 'match' => 'prefix', 'evidence' => 'M: "ATM 7ELEVEN-FCTI" observed and must fail (v2 §3)'],
                ['value' => 'seven eleven', 'match' => 'prefix', 'evidence' => 'P'],
            ],
            'brand_aliases' => [['value' => '7 eleven', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q259340' => 'M: 12 rows, 0.3% fill (v2 §6)'],
            'fuel_brands' => ['mobil'],
            'allowed_categories' => [
                'convenience_store' => ['role' => 'storefront', 'evidence' => 'D: v2 §7 7-Eleven formats'],
                'gas_station'       => ['role' => 'fuel', 'evidence' => 'D: v2 §7 ("7-Eleven Fuel")'],
            ],
            'formats' => [
                'store' => ['categories' => ['convenience_store'], 'role' => 'storefront', 'evidence' => 'D: v2 §7'],
                'fuel'  => ['categories' => ['gas_station'], 'role' => 'fuel', 'evidence' => 'D: v2 §7'],
            ],
            'default_format' => 'store',
            'fuel_sites' => [
                'store_with_fuel' => true,
                'fuel_only' => true,
                'evidence' => 'D: v2 decision 3d; chain-registry decisions 6 and 10',
            ],
            'co_brands' => [
                'speedway' => [
                    'evidence_rule' => 'co_brand_evidence',
                    'compound_names' => ['7 eleven speedway', 'speedway 7 eleven'],
                    'evidence' => 'D: v2 decision 3c; the combined name was observed only on Western Union rows (v2 §7)',
                ],
            ],
            'validation_status' => 'provisional',
        ],

        'wawa' => [
            'display_name' => 'Wawa',
            'aliases' => [['value' => 'wawa', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'wawa', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q5936320' => 'M: 326 rows, all <0.90, so 0 eligible (v2 §6)'],
            'allowed_categories' => [
                'convenience_store' => ['role' => 'storefront', 'evidence' => 'P'],
                'gas_station'       => ['role' => 'fuel', 'evidence' => 'P'],
            ],
            'formats' => [
                'store' => ['categories' => ['convenience_store'], 'role' => 'storefront', 'evidence' => 'P'],
                'fuel'  => ['categories' => ['gas_station'], 'role' => 'fuel', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            'fuel_sites' => ['store_with_fuel' => true, 'fuel_only' => false, 'evidence' => 'D: chain-registry decision 6'],
            'validation_status' => 'provisional',
        ],

        'racetrac' => [
            'display_name' => 'RaceTrac',
            'aliases' => [
                ['value' => 'racetrac', 'match' => 'prefix', 'evidence' => 'P'],
                ['value' => 'race trac', 'match' => 'prefix', 'evidence' => 'P'],
            ],
            'brand_aliases' => [
                ['value' => 'racetrac', 'evidence' => 'M: identity for location-only names ("Polo", "Lake Mary") (v2 §7); in chain-registry-v2 only with corroboration'],
            ],
            // v2 decision 6: 5 "RaceWay" / "Race Way" / "Raceway 6847" convenience rows carry brand
            // "RaceTrac". Every census RaceTrac row whose brand alone would lose it is one of them.
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q735942' => 'M: 224 rows, mostly <0.90 gas rows (v2 §6)'],
            'exclusion_name_patterns' => [
                'truck'     => ['pattern' => '/\btruck\b/', 'evidence' => 'M: RaceTrac truck lanes 19 (v2 §3, decision 5)'],
                // v2 decision 5: this exact shape, never a global "petroleum".
                'petroleum' => ['pattern' => '/^race ?trac petroleum\b/', 'evidence' => 'M: 1 census row "Racetrac Petroleum"'],
            ],
            'allowed_categories' => [
                'convenience_store' => ['role' => 'storefront', 'evidence' => 'P'],
                'gas_station'       => ['role' => 'fuel', 'identity' => 'strong', 'evidence' => 'M: gas rows (v2 §6); strong identity, v2 decision 3'],
            ],
            'formats' => [
                'store' => ['categories' => ['convenience_store'], 'role' => 'storefront', 'evidence' => 'P'],
                'fuel'  => ['categories' => ['gas_station'], 'role' => 'fuel', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            // v2 decision 3: 36 of 42 census fuel rows have no RaceTrac store within 120 m. A fuel-only
            // site is labelled "Fuel only" and never claims a store was observed.
            'fuel_sites' => ['store_with_fuel' => true, 'fuel_only' => true, 'evidence' => 'D: chain-registry v2 decision 3 (supersedes decision 6 for RaceTrac)'],
            'validation_status' => 'provisional',
        ],

        'speedway' => [
            'display_name' => 'Speedway',
            'aliases' => [
                // Exact only: "speedway" is an ordinary word and a race-track name.
                ['value' => 'speedway', 'match' => 'exact', 'evidence' => 'M: "Daytona International Speedway" and 68 race-track rows must fail (v2 §3)'],
            ],
            'brand_aliases' => [['value' => 'speedway', 'evidence' => 'M: brand name on 9/172 eligible rows (v2 §7)']],
            'own_wikidata_ids' => [],
            'allowed_categories' => [
                'convenience_store' => ['role' => 'storefront', 'evidence' => 'P'],
                'gas_station'       => ['role' => 'fuel', 'identity' => 'strong', 'evidence' => 'M: 151 census gas rows, 148 named exactly "Speedway"; strong identity, v2 decision 3'],
            ],
            'formats' => [
                'store' => ['categories' => ['convenience_store'], 'role' => 'storefront', 'evidence' => 'P'],
                'fuel'  => ['categories' => ['gas_station'], 'role' => 'fuel', 'evidence' => 'P'],
            ],
            'default_format' => 'store',
            // v2 decision 3: 150 of 151 census fuel rows have no Speedway store within 120 m — about
            // 89% of Speedway's locations. Fuel-only, labelled "Fuel only"; no store claimed.
            'fuel_sites' => ['store_with_fuel' => true, 'fuel_only' => true, 'evidence' => 'D: chain-registry v2 decision 3 (supersedes decision 6 for Speedway)'],
            'co_brands' => [
                'seven_eleven' => [
                    'evidence_rule' => 'co_brand_evidence',
                    'compound_names' => ['7 eleven speedway', 'speedway 7 eleven'],
                    'evidence' => 'D: v2 decision 3c',
                ],
            ],
            'validation_status' => 'provisional',
            'notes' => ['Weak identity: no own QID observed (v2 §7, §10-3).'],
        ],

        'shell' => [
            'display_name' => 'Shell',
            'aliases' => [
                // Exact only: "shell" is an ordinary word ("Shell Island", "Royal Shell Real Estate").
                ['value' => 'shell', 'match' => 'exact', 'evidence' => 'P'],
            ],
            'brand_aliases' => [['value' => 'shell', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q110716465' => 'M: 22 rows, 0.3% fill (v2 §6)'],
            // No expected co-located fuel brand: Arco on a Shell row is recorded as a
            // fuel_brand_conflict DIAGNOSTIC and changes nothing (decision 5).
            'fuel_brands' => [],
            'allowed_categories' => [
                // For Shell the station IS the storefront.
                'gas_station'       => ['role' => 'storefront', 'evidence' => 'P'],
                'convenience_store' => ['role' => 'department', 'evidence' => 'P: the station shop'],
            ],
            'formats' => [
                'station'       => ['categories' => ['gas_station'], 'role' => 'storefront', 'evidence' => 'P'],
                'station_store' => ['categories' => ['convenience_store'], 'role' => 'department', 'evidence' => 'P'],
            ],
            'default_format' => 'station',
            'validation_status' => 'provisional',
        ],

        // ── Quick service ───────────────────────────────────────────────────────────────────

        'mcdonalds' => [
            'display_name' => "McDonald's",
            'aliases' => [['value' => 'mcdonalds', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'mcdonalds', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q38076' => 'M: 31 rows, 0.3% fill (v2 §6)'],
            'allowed_categories' => [
                'fast_food_restaurant' => ['role' => 'storefront', 'evidence' => 'P'],
                'burger_restaurant'    => ['role' => 'storefront', 'evidence' => 'P'],
                // v2 decision 2: 13 distinct "McDonald's" rows across these two in the census.
                'restaurant'           => ['role' => 'storefront', 'identity' => 'strong', 'evidence' => 'M: 3 census rows, 2 distinct; v2 decision 2'],
                'coffee_shop'          => ['role' => 'storefront', 'identity' => 'strong', 'evidence' => 'M: 12 census rows, 11 distinct; v2 decision 2'],
            ],
            'excluded_categories' => [
                'cafe' => 'D: chain-registry decision 3; M: zero McDonald\'s cafe rows in the census (v2 decision 2)',
            ],
            'formats' => [
                'restaurant' => ['categories' => ['burger_restaurant', 'coffee_shop', 'fast_food_restaurant', 'restaurant'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'restaurant',
            'validation_status' => 'provisional',
        ],

        'taco_bell' => [
            'display_name' => 'Taco Bell',
            'aliases' => [['value' => 'taco bell', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'taco bell', 'evidence' => 'P']],
            // v2 decision 6: a "KFC" row carries brand "Taco Bell".
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q752941' => 'M: 6 rows, 0.8% fill (v2 §6)'],
            'allowed_categories' => [
                'fast_food_restaurant' => ['role' => 'storefront', 'evidence' => 'P'],
                'taco_restaurant'      => ['role' => 'storefront', 'evidence' => 'P: mexican_restaurant is not imported (v2 decision 5)'],
            ],
            'excluded_categories' => ['restaurant' => 'D: chain-registry decision 4'],
            'formats' => [
                'restaurant' => ['categories' => ['fast_food_restaurant', 'taco_restaurant'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'restaurant',
            'validation_status' => 'provisional',
        ],

        'chick_fil_a' => [
            'display_name' => 'Chick-fil-A',
            'aliases' => [['value' => 'chick fil a', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'chick fil a', 'evidence' => 'P']],
            // v2 decision 6: "Hot Chick'n" carries brand "Chick-fil-A".
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q491516' => 'M: 27 rows, 2.9% fill (v2 §6)'],
            'allowed_categories' => [
                'fast_food_restaurant' => ['role' => 'storefront', 'evidence' => 'P'],
                'chicken_restaurant'   => ['role' => 'storefront', 'evidence' => 'P'],
            ],
            'excluded_categories' => ['restaurant' => 'D: chain-registry decision 4'],
            'formats' => [
                'restaurant' => ['categories' => ['fast_food_restaurant', 'chicken_restaurant'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'restaurant',
            'validation_status' => 'provisional',
        ],

        'wendys' => [
            'display_name' => "Wendy's",
            'aliases' => [['value' => 'wendys', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'wendys', 'evidence' => 'P']],
            'own_wikidata_ids' => ['Q550258' => 'M: 9 rows, 0.8% fill (v2 §6)'],
            'allowed_categories' => [
                'fast_food_restaurant' => ['role' => 'storefront', 'evidence' => 'P'],
                'burger_restaurant'    => ['role' => 'storefront', 'evidence' => 'P'],
            ],
            'excluded_categories' => ['restaurant' => 'D: chain-registry decision 4'],
            'formats' => [
                'restaurant' => ['categories' => ['fast_food_restaurant', 'burger_restaurant'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'restaurant',
            'validation_status' => 'provisional',
        ],

        'burger_king' => [
            'display_name' => 'Burger King',
            'aliases' => [['value' => 'burger king', 'match' => 'prefix', 'evidence' => 'P']],
            'brand_aliases' => [['value' => 'burger king', 'evidence' => 'P']],
            // v2 decision 6: "Burger and Philly", "Whopper Bar" carry brand "Burger King".
            'brand_alias_requires_corroboration' => true,
            'own_wikidata_ids' => ['Q177054' => 'M: 9 rows, 0.5% fill (v2 §6)'],
            'exclusion_name_patterns' => [
                // v2 decision 5, this measured shape only — never a global "holdings" or "llc".
                'capital_holdings' => ['pattern' => '/^burger king capital holdings\b/', 'evidence' => 'M: 1 census row "Burger King Capital Holdings, Llc" (restaurant), a franchisee entity reached through the v2 restaurant widening'],
            ],
            'allowed_categories' => [
                'fast_food_restaurant' => ['role' => 'storefront', 'evidence' => 'P: the 85 beverage_supplier rows are not imported (v2 §3)'],
                'burger_restaurant'    => ['role' => 'storefront', 'evidence' => 'P'],
                'restaurant'           => ['role' => 'storefront', 'identity' => 'strong', 'evidence' => 'M: 42 census "Burger King" rows, 41 distinct; v2 decision 2'],
            ],
            'formats' => [
                'restaurant' => ['categories' => ['burger_restaurant', 'fast_food_restaurant', 'restaurant'], 'role' => 'storefront', 'evidence' => 'P'],
            ],
            'default_format' => 'restaurant',
            'validation_status' => 'provisional',
        ],
    ],
];

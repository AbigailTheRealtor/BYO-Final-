<?php

/*
|--------------------------------------------------------------------------
| Location data attribution — the SSOT for what we owe upstream, and to whom
|--------------------------------------------------------------------------
|
| One descriptor per DATA SOURCE we publish to end users, and the mapping from
| the provider ids in `config/location_providers.php` onto those sources.
|
| WHY THIS IS A SEPARATE FILE FROM location_providers.php
| -------------------------------------------------------
| `location_providers.php` answers a ROUTING question: which adapter should be
| asked for this category. This file answers a LICENSING one: given that a row
| reached a user, whose notice must travel with it. Those are not the same list
| and they do not change together. Several providers can share one source (a
| future second Overture adapter owes the identical notice), one provider can owe
| several (the Places theme is three licenses at once), and a source can be owed
| attribution long after the provider that fetched it was switched off, because
| the rows it wrote are still on the page.
|
| Merging them would also put a licensing obligation behind a routing flag —
| `enabled => false` would read as "no attribution owed", which is exactly
| backwards for rows already persisted.
|
| EXACTLY TWO READERS, AND A TEST ASSERTS IT
| ------------------------------------------
|   App\Support\LocationDna\LocationDataAttribution   (resolution + NOTICE state)
|   routes/web.php                                     (locates the NOTICE file for
|                                                       the public licenses page)
|
| No Blade file reads this config. The licenses page is handed its sources by the
| route; the Location DNA component resolves its own through the support class. So
| the "which sources does this page owe" decision lives in one place rather than in
| each template that happens to render a POI, and a template edit cannot change an
| attribution claim.
|
| NOT THE MLS/STELLAR ATTRIBUTION. `resources/views/offer-listing/partials/
| _mls_attribution.blade.php` discharges a different obligation, from a different
| agreement (the Bridge/Stellar IDX terms), about different content (the listing
| itself). The two are deliberately kept apart: a listing page can owe both at
| once, and collapsing them into one generic "data attribution" block would let a
| reader take an MLS provenance claim as covering the POIs beside it, or the
| reverse. Same page, same visual language, separate statements.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    | key              stable identifier; referenced by `provider_sources` below
    | name             display name, shown to users
    | url              the source's own home, shown to users
    | statement        the one-line attribution sentence rendered in the compact
    |                  component. Kept here rather than in Blade so the wording a
    |                  license requires cannot be edited by a template tweak.
    | licenses         one or more { id, name, url } — a source may be an
    |                  aggregate (Overture Places is three at once)
    | attribution_required  bool. Whether a license COMPELS the notice, as opposed
    |                  to us giving it because it is useful.
    | notice_required  bool. Whether a license additionally obliges us to reproduce
    |                  the upstream's own NOTICE file (Apache-2.0 §4(d)).
    | notice_path      repo-relative path where those verbatim bytes live.
    | notice_verified  bool. Whether `notice_path` currently holds the real upstream
    |                  text. FALSE MEANS THE OBLIGATION IS OUTSTANDING and the
    |                  provider(s) mapped to this source must not publish to users.
    */
    'sources' => [

        'overture_places' => [
            'name'      => 'Overture Maps Foundation — Places',
            'url'       => 'https://overturemaps.org/',
            /*
            | Carries the Foursquare copyright because the upstream NOTICE requires
            | attribution to Foursquare be preserved, and because we cannot identify
            | which rows are theirs — so the credit goes on all of them. Overture's own
            | required citation form ("Overture Maps Foundation, overturemaps.org") is
            | satisfied by the first clause.
            */
            'statement' => 'Places data © Overture Maps Foundation, overturemaps.org. '
                . 'Includes data from Foursquare — © Foursquare Labs, Inc., all rights reserved, '
                . 'used under Apache License 2.0 and modified.',

            /*
            | THREE LICENSES, NOT ONE, AND `odbl` IS THE WRONG ANSWER.
            |
            | Four of Overture's six themes are ODbL. Places is not one of them —
            | see https://docs.overturemaps.org/attribution/ and the LICENSE note in
            | config/location_providers.php, which carries the same finding at the
            | routing layer. The corpus as imported cannot say which member supplied
            | any given row (`sources[].dataset` is counted and discarded by the
            | normalizer), so every published row is treated as though it could be
            | the Foursquare/Apache slice. The strictest obligation governs.
            */
            'licenses' => [
                [
                    'id'   => 'cdla-permissive-2.0',
                    'name' => 'Community Data License Agreement — Permissive 2.0',
                    'url'  => 'https://cdla.dev/permissive-2-0/',
                ],
                [
                    'id'   => 'apache-2.0',
                    'name' => 'Apache License 2.0 (Foursquare Open Source Places slice)',
                    'url'  => 'https://www.apache.org/licenses/LICENSE-2.0',
                ],
                [
                    'id'   => 'cc0-1.0',
                    'name' => 'CC0 1.0 Universal (AllThePlaces slice)',
                    'url'  => 'https://creativecommons.org/publicdomain/zero/1.0/',
                ],
            ],

            'attribution_required' => true,
            'notice_required'      => true,

            /*
            | THE THREE ARTIFACTS THE APACHE-2.0 SLICE OBLIGES US TO CARRY.
            |
            | The upstream NOTICE itself requires all three: a copy of the License,
            | a prominent notice of our changes, and preservation of the full NOTICE
            | content. They are separate files on purpose — merging our change notice
            | into Foursquare's NOTICE is permitted by that NOTICE, but it would leave
            | a reader unable to tell which sentences are theirs, and preserving the
            | NOTICE *as theirs* is the obligation.
            */
            'notice_path'        => 'resources/legal/foursquare-os-places-NOTICE.txt',
            'license_path'       => 'resources/legal/apache-2.0-LICENSE.txt',
            'modifications_path' => 'resources/legal/overture-corpus-MODIFICATIONS.txt',

            /*
            | Provenance of the NOTICE bytes, so a future reader can re-verify them
            | against the source rather than trusting this repository.
            |
            | Foursquare publishes the NOTICE as a web page, not as a downloadable
            | .txt — there is no raw artifact to checksum against, which is why the
            | tests assert on required markers rather than on a whole-file hash.
            */
            'notice_source_url' => 'https://opensource.foursquare.com/places-notice-txt/',
            'notice_retrieved'  => '2026-09-09',

            /*
            | TRUE — the verbatim upstream NOTICE, the full Apache-2.0 text, and our
            | notice of changes are all committed and asserted by
            | LocationDnaNoticeComplianceTest.
            |
            | THIS IS NOT ACTIVATION AUTHORIZATION. It says one prerequisite is met,
            | nothing more. The provider gates in config/overture_corpus_poi.php and
            | config/location_providers.php are both still false and are governed
            | separately; OvertureActivationReadinessTest asserts that this flag being
            | true has not moved either of them.
            */
            'notice_verified' => true,
        ],

        'google_places' => [
            'name'      => 'Google Places',
            'url'       => 'https://developers.google.com/maps/documentation/places/web-service/overview',
            'statement' => 'Some place information provided by Google.',
            'licenses'  => [
                [
                    'id'   => 'google-tos',
                    'name' => 'Google Maps Platform Terms of Service',
                    'url'  => 'https://cloud.google.com/maps-platform/terms',
                ],
            ],
            // Google's terms govern display and caching; they are terms, not an open
            // data license, so `notice_required` is false while attribution is still
            // shown. The distinction matters: there is no upstream NOTICE file to
            // reproduce, and pretending there is one would create a phantom blocker.
            'attribution_required' => true,
            'notice_required'      => false,
            'notice_path'          => null,
            'notice_verified'      => true,
        ],

        'census' => [
            'name'      => 'US Census Bureau — TIGER/Line & Geocoder',
            'url'       => 'https://www.census.gov/programs-surveys/geography.html',
            'statement' => 'Boundary and geocoding data from the US Census Bureau.',
            'licenses'  => [
                [
                    'id'   => 'public-domain',
                    'name' => 'Public domain (US Government work, 17 U.S.C. §105)',
                    'url'  => 'https://www.usa.gov/government-works',
                ],
            ],
            // Not compelled. Shown because naming the source of a boundary is useful
            // to a reader, and because a page that lists only the sources that force
            // its hand tells the reader less than it easily could.
            'attribution_required' => false,
            'notice_required'      => false,
            'notice_path'          => null,
            'notice_verified'      => true,
        ],

        'fema' => [
            'name'      => 'FEMA — National Flood Hazard Layer',
            'url'       => 'https://www.fema.gov/flood-maps/national-flood-hazard-layer',
            'statement' => 'Flood zone information from the FEMA National Flood Hazard Layer.',
            'licenses'  => [
                [
                    'id'   => 'public-domain',
                    'name' => 'Public domain (US Government work, 17 U.S.C. §105)',
                    'url'  => 'https://www.usa.gov/government-works',
                ],
            ],
            'attribution_required' => false,
            'notice_required'      => false,
            'notice_path'          => null,
            'notice_verified'      => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider → source mapping
    |--------------------------------------------------------------------------
    | Keys are provider ids from `config/location_providers.php`; values are lists
    | of `sources` keys above. A provider absent from this map owes nothing — which
    | is the honest answer for `stub` (fixture data) and for adapters that are not
    | implemented, and NOT a silent default: `LocationDataAttribution::forProvider()`
    | returns an empty list and the component renders nothing rather than guessing.
    |
    | `overture_corpus` maps to `overture_places` because the corpus IS the Places
    | theme, loaded locally. Self-hosting changes the delivery, not the license.
    */
    'provider_sources' => [
        'overture_corpus' => ['overture_places'],
        'google_places'   => ['google_places'],
        'census_tiger'    => ['census'],
        'fema'            => ['fema'],
    ],

    /*
    |--------------------------------------------------------------------------
    | The repository-root NOTICE file
    |--------------------------------------------------------------------------
    | Rendered in full on the data-sources page. Held here so the page and the
    | tests agree on one path rather than each hardcoding it.
    */
    'notice_path' => 'NOTICE',
];

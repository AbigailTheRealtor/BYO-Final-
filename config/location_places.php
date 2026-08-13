<?php

/**
 * Phase 1d-3 — the hand-maintained half of the commercial place layer.
 *
 * WHAT BELONGS IN THIS FILE
 * -------------------------
 * Neighbourhoods, and the parent links that put a neighbourhood inside a city. Nothing else.
 * Places and communities are DERIVED — `location:build-places` projects 32,188 rows from the
 * Census corpus and a further ~6,900 community names from the legacy USPS corpus.
 *
 * THIS FILE IS THE SOURCE OF TRUTH FOR CURATED ROWS. The builder rebuilds them from here on
 * every run, so `location_places` never holds a curated fact that this file does not state.
 * Editing a neighbourhood means editing this file and re-running the command.
 *
 * WHY NEIGHBOURHOODS CANNOT BE DERIVED
 * ------------------------------------
 * A neighbourhood's defining fact is its PARENT, and no dataset in this application states it.
 * The USPS corpus knows `Clearwater Beach` has ZIP 33767 in Pinellas County; it does not know,
 * and has no field to say, that Clearwater Beach is inside the City of Clearwater. The Census
 * does not publish it because a neighbourhood is not a unit of government. So the parent link is
 * a human assertion, and this file is where humans assert it.
 *
 * ⚠ CURATED ENTRIES REQUIRE LOCAL REVIEW BEFORE A MARKET GOES LIVE.
 * Everything below is a claim about the real world rather than a row copied from a published
 * file. The ZIPs have been verified to exist in the stated county against the Census ZCTA roster,
 * and the parents are well-known local geography — but boundary questions ("is Sand Key part of
 * Clearwater or of Belleair Shore?") are exactly the kind of thing a local agent should confirm.
 * Adding a market means adding its neighbourhoods here, not changing code.
 *
 * SHAPE
 * -----
 *   state         two-letter USPS abbreviation; resolved to a state GEOID at build time
 *   county        county name as the Census publishes it, e.g. "Pinellas County"
 *   name          the neighbourhood, as an agent would type it
 *   parent        the Census place it sits inside, by name; null for a standalone community
 *   type          neighborhood | community
 *   zips          ZIPs to associate; each is checked against the county's ZCTA roster at build
 *   latitude/longitude  optional; used for map surfaces, never for matching
 *
 * A row whose state, county or parent cannot be resolved is REPORTED AND SKIPPED by the builder,
 * never guessed at — a neighbourhood silently attached to the wrong city is worse than one that
 * is missing and named in the output.
 */

return [

    'neighborhoods' => [

        // ── Pinellas County, FL — the pilot market ────────────────────────────────
        //
        // Clearwater Beach is the case this whole layer was built for. The Phase 1d-1 audit
        // found it as the single Pinellas name the Census corpus could not supply: a barrier
        // island inside the City of Clearwater, with its own ZIP, its own market, and no
        // government of its own.
        [
            'state'     => 'FL',
            'county'    => 'Pinellas County',
            'name'      => 'Clearwater Beach',
            'parent'    => 'Clearwater',
            'type'      => 'neighborhood',
            'zips'      => ['33767'],
            'latitude'  => 27.9598470,
            'longitude' => -82.8286250,
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Sand Key',
            'parent' => 'Clearwater',
            'type'   => 'neighborhood',
            'zips'   => ['33767'],
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Island Estates',
            'parent' => 'Clearwater',
            'type'   => 'neighborhood',
            'zips'   => ['33767'],
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Pass-a-Grille',
            'parent' => 'St. Pete Beach',
            'type'   => 'neighborhood',
            'zips'   => ['33706'],
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Snell Isle',
            'parent' => 'St. Petersburg',
            'type'   => 'neighborhood',
            'zips'   => ['33704'],
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Historic Old Northeast',
            'parent' => 'St. Petersburg',
            'type'   => 'neighborhood',
            'zips'   => ['33701', '33704'],
        ],
        [
            'state'  => 'FL',
            'county' => 'Pinellas County',
            'name'   => 'Downtown St. Petersburg',
            'parent' => 'St. Petersburg',
            'type'   => 'neighborhood',
            'zips'   => ['33701'],
        ],
    ],

    /**
     * Aliases: a name agents type that IS an existing place under another spelling.
     *
     * NOT the same thing as a neighbourhood, and deliberately not stored as one. The Phase 1d-3
     * gap report found 106 legacy names that are punctuation or Mc-spacing variants of a Census
     * place (`Mc Grath` → `McGrath`) and 62 that differ by a trailing generic (`Boise` →
     * `Boise City`). Inserting those as places would put the same town in the list twice.
     *
     * The leading-`Saint` case is already handled by {@see App\Services\LocationDna\Places\PlaceNameKey}
     * and needs no entry here. Mid-name `Saint` is not folded by design, so `Port Saint Joe` does
     * need one.
     *
     * Left EMPTY on purpose for now: alias resolution is not yet wired into the cascade, and
     * shipping data for an unused code path invites it being trusted before it is tested. The
     * shape is fixed here so the follow-up phase has somewhere to put them.
     */
    'aliases' => [
        // ['state' => 'FL', 'alias' => 'Port Saint Joe', 'canonical' => 'Port St. Joe'],
    ],
];

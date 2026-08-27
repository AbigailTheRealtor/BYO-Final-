<?php

namespace App\Support\Location;

/**
 * One U.S. state, expressed the way the MLS feed expresses it.
 *
 * WHAT THIS IS FOR
 * ----------------
 * Buyer and Tenant criteria persist a single Preferred State as whatever the map
 * widget's free-text input received — the datalist offers full names, so the
 * common stored value is `Florida`, but the field accepts anything a user types
 * and older records may hold `FL`, `fl` or a stray space. `bridge_properties`
 * stores RESO `StateOrProvince`, which is the two-letter uppercase code. Those
 * two are compared, so exactly one of them has to be translated, and this is the
 * only place that does it.
 *
 * WHY A NEW CLASS RATHER THAN REUSING WHAT EXISTS
 * ----------------------------------------------
 * Two full-name→code maps were already in the codebase when this was written and
 * neither could be called from here:
 *
 *   {@see \App\Services\Location\Coordinates\PropertyAddress::normalizeState()}
 *     is `private`, returns LOWERCASE, and belongs to the coordinate ladder. It
 *     says of itself "there is exactly one way to normalize a state here", and
 *     that remains true of the ladder — but it normalizes for comparing two
 *     geocoder answers, not for querying a feed column, and matching must not
 *     take a dependency on the ladder to borrow a string map.
 *
 *   {@see \App\Services\ListingImport\MlsListingImportService} carries a second
 *     copy as a `static` local inside one parser branch.
 *
 * `us_states` was the other candidate and is deliberately NOT used. It is
 * reference data that has to be seeded, `UsState::search()` relies on `ILIKE`
 * (PostgreSQL-only, so it does not run under the SQLite test connection), and a
 * table lookup inside the matching query builder would make state filtering fail
 * silently — as "no state was given" — in any environment where the reference
 * rows are absent. A pure in-memory map cannot fail that way.
 *
 * FAIL SAFE, NOT FAIL CLOSED
 * --------------------------
 * An unrecognised value returns null, and every caller treats null as "no state
 * criterion". That is the deliberate choice: the input is free text, and turning
 * a typo into a filter no listing can satisfy would show the user zero results
 * with nothing on screen explaining why. Widening on doubt is recoverable;
 * silently emptying a search is not.
 *
 * This class never writes. Stored criteria keep whatever string they hold —
 * `Florida` stays `Florida` — and translation happens on read, so nothing here
 * can corrupt a saved record.
 */
final class UsStateCode
{
    /**
     * Lowercased full name → USPS code. The 50 states plus DC, which is exactly
     * the list the Search Areas map widget offers.
     *
     * @var array<string, string>
     */
    private const NAMES = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
        'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT',
        'delaware' => 'DE', 'district of columbia' => 'DC', 'florida' => 'FL',
        'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY',
        'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
        'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
        'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT',
        'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH',
        'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
        'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA',
        'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD',
        'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT',
        'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
        'wisconsin' => 'WI', 'wyoming' => 'WY',
    ];

    /**
     * The two-letter code for a persisted state value, or null when there isn't
     * one to be had.
     *
     * Accepts a full name in any casing, a two-letter code in any casing, and
     * either with surrounding or repeated internal whitespace — the forms the
     * free-text input can actually produce. Anything else is null.
     *
     * A two-letter input is accepted only when it is a code this class knows,
     * so `ZZ` is rejected rather than passed through as a filter value that
     * would match nothing.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Collapse internal runs as well as trimming, so "new    york" is the
        // same state as "New York". The widget writes a trimmed value, but the
        // records this has to read predate the widget.
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            return null;
        }

        $lower = mb_strtolower($normalized);

        if (isset(self::NAMES[$lower])) {
            return self::NAMES[$lower];
        }

        // Already a code — validated against the same table rather than trusted
        // for being two characters long.
        if (strlen($normalized) === 2) {
            $upper = strtoupper($normalized);

            return in_array($upper, self::NAMES, true) ? $upper : null;
        }

        return null;
    }
}

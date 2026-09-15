<?php

namespace App\Services\Location\Lookup;

use App\Services\Location\AddressCorpus\StateFips;
use App\Services\Location\Coordinates\PropertyAddress;

/**
 * One line of typed text, turned into the address structure the ladder already
 * speaks.
 *
 * THIS IS A SPLITTER, NOT A PARSER, AND THE DIFFERENCE IS THE POINT
 * -----------------------------------------------------------------
 * It does not identify street types, expand abbreviations, correct spellings or
 * validate anything — {@see PropertyAddress} already owns every one of those
 * rules and would disagree with a second implementation eventually. All this
 * does is decide which of the words the user typed are the ZIP, which is the
 * state, which is the city, and which are the street, so that the resulting
 * {@see PropertyAddress} can answer `hasMinimumForLookup()` and
 * `coordinateLookupLine()` for itself.
 *
 * WHY IT NEVER GUESSES A CITY
 * ---------------------------
 * "11687 Oxford Street North" is a real street in several states. A splitter
 * that invented a locality tail to make the lookup succeed would produce a
 * confident coordinate for a property in the wrong one — the exact failure the
 * G4 geography check exists to catch, arrived at from our own side rather than
 * the provider's. So when there is no locality in the text, the fields stay
 * empty, `hasMinimumForLookup()` returns false, and the caller stops before a
 * request is spent. The user is asked for a city, state or ZIP instead.
 *
 * WHY STATE DETECTION BORROWS StateFips
 * -------------------------------------
 * {@see PropertyAddress::normalizedState()} normalizes a state but does not
 * validate one: it returns any two-character token unchanged, so "St" would
 * pass as a state and swallow the last word of a street. `StateFips::toFips()`
 * answers null for anything that is not a real USPS jurisdiction, which is the
 * membership test this needs, and it is already the application's list. Adding
 * a second copy of the fifty states here is how the two come to disagree.
 *
 * PURE. No container, no config, no I/O — so it can be exercised without an
 * application, and so nothing here can be the reason a lookup is slow.
 */
final class AddressLookupQuery
{
    /**
     * The longest input this will look at.
     *
     * Not a provider limit — the Census adapter mirrors the service's own
     * 100-character rule for itself and declines over-long lines before
     * spending a request. This is only a ceiling on how much text the splitter
     * below will walk, so an enormous paste cannot become work.
     */
    public const MAX_INPUT_LENGTH = 250;

    /** A trailing ZIP5 or ZIP+4, at the end of a fragment. */
    private const ZIP_PATTERN = '/\b(\d{5})(?:-\d{4})?\s*$/';

    /**
     * Split typed text into an address.
     *
     * Returns a {@see PropertyAddress} in every case, including for input that
     * cannot be resolved — the caller asks the address itself whether there is
     * enough to attempt anything, rather than this returning null and making
     * "unparseable" a second way of saying the same thing.
     */
    public static function parse(string $text): PropertyAddress
    {
        $text = self::collapse($text);

        if ($text === '') {
            return new PropertyAddress();
        }

        if (mb_strlen($text) > self::MAX_INPUT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_INPUT_LENGTH);
        }

        $parts = array_values(array_filter(
            array_map([self::class, 'collapse'], explode(',', $text)),
            static fn (string $p): bool => $p !== ''
        ));

        if ($parts === []) {
            return new PropertyAddress();
        }

        $zip   = '';
        $state = '';

        // Work from the tail inwards: ZIP, then state, then city. Each is taken
        // only when it is unambiguously there, and taking one shortens the
        // fragment the next is looked for in.
        $tail = array_pop($parts);

        if (preg_match(self::ZIP_PATTERN, $tail, $m) === 1) {
            $zip  = $m[1];
            $tail = self::collapse(preg_replace(self::ZIP_PATTERN, '', $tail) ?? '');
        }

        $stateToken = self::trailingStateToken($tail);

        if ($stateToken !== null) {
            $state = $stateToken['code'];
            $tail  = $stateToken['remainder'];
        }

        // The city is wherever the locality survived, and the two cases are
        // genuinely different fragments:
        //
        //   "…, Tampa FL 33602"   the state and ZIP were IN the last fragment,
        //                         so what is left of it is the city.
        //   "…, Tampa, FL 33602"  the last fragment was ENTIRELY state and ZIP,
        //                         so the city is the fragment before it.
        //
        // In both, a city is only taken when something earlier can still be the
        // street. The remainder of a one-fragment string is the whole address
        // the user typed; calling it a city would leave the street empty and
        // the lookup meaningless.
        $city = '';

        if ($tail !== '') {
            if ($parts !== []) {
                $city = $tail;
            } else {
                $parts[] = $tail;
            }
        } elseif (count($parts) >= 2) {
            $city = (string) array_pop($parts);
        }

        return new PropertyAddress(
            address: self::collapse(implode(' ', $parts)),
            city:    $city,
            state:   $state,
            zip:     $zip,
        );
    }

    /**
     * The state at the end of a fragment, if the last word really is one.
     *
     * Handles both "FL" and "Florida", and the two-word jurisdictions ("New
     * York", "West Virginia", "District of Columbia") by trying progressively
     * longer tails — a single-word test would read "New York" as the city
     * "New" plus the state "York", which is not a state at all.
     *
     * @return array{code: string, remainder: string}|null
     */
    private static function trailingStateToken(string $fragment): ?array
    {
        if ($fragment === '') {
            return null;
        }

        $words = explode(' ', $fragment);
        $count = count($words);

        // Longest first: "district of columbia" must win over "columbia".
        for ($take = min(3, $count); $take >= 1; $take--) {
            $candidate = implode(' ', array_slice($words, $count - $take));

            $code = (new PropertyAddress(state: $candidate))->normalizedState();

            // normalizedState() normalizes; StateFips validates. Both are
            // needed, and neither is sufficient alone.
            if ($code !== '' && StateFips::toFips($code) !== null) {
                return [
                    'code'      => $code,
                    'remainder' => self::collapse(implode(' ', array_slice($words, 0, $count - $take))),
                ];
            }
        }

        return null;
    }

    /** Trim, and collapse every run of whitespace to one space. */
    private static function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}

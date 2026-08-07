<?php

namespace App\Services\Location;

/**
 * Phase 0 — Street-address shape validation (Spatial UI Integration).
 *
 * Pure, side-effect-free, DB-free classifier for a user-typed street address.
 * It answers one narrow question: *does this string have the shape of a street
 * address?* It deliberately does NOT answer "does this address exist" — that is
 * geocoding, and it arrives in Phase 2 behind AddressResolverInterface.
 *
 * WHY THIS EXISTS
 * ---------------
 * Before this class the four Seller/Landlord address surfaces validated the
 * street field as `required|string` (Hire flows) or not at all (Create flows).
 * `43434` passed. So did `33708`, `Main`, and `.`. The only thing that ever
 * populated City/County/State/ZIP was a browser-side Google Places listener with
 * no server-side counterpart — so when the Google credential stopped working,
 * listings silently persisted a street number as a whole address.
 *
 * DESIGN
 * ------
 * Stays pure so it is trivially unit-testable and callable from a validation
 * rule, a Livewire component, a console command, or an import path without a
 * container. ZIP-shaped input is classified as ZIP_SHAPED but NOT resolved here;
 * deciding whether those five digits are a real US ZIP requires the
 * `us_zip_codes` table and belongs to {@see ZipCodeLookupService}. Callers that
 * want the sharper "that's a ZIP code, we've moved it for you" behaviour compose
 * the two — see {@see \App\Http\Livewire\Concerns\ValidatesPropertyAddress}.
 *
 * @see \App\Rules\ValidStreetAddress
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §1, §8 Phase 0
 */
class AddressShapeValidator
{
    /** Looks like a street address. */
    public const OK = 'ok';

    /** Nothing but whitespace. */
    public const EMPTY_VALUE = 'empty';

    /** Exactly 5 or 9 digits — a ZIP code typed into the street field. */
    public const ZIP_SHAPED = 'zip_shaped';

    /** Digits and punctuation only, and not ZIP-shaped. The `43434` case. */
    public const NUMBER_ONLY = 'number_only';

    /** Has content but no street-name word — e.g. `123 A`, `12 #4`. */
    public const NO_STREET_NAME = 'no_street_name';

    /** A single word with no house number — e.g. `Main`. */
    public const NO_STREET_NUMBER = 'no_street_number';

    /** Too little text to be any address at all. */
    public const TOO_SHORT = 'too_short';

    /**
     * Shortest string we will entertain, after normalisation. "1 Elm" is 5.
     */
    private const MIN_LENGTH = 5;

    /**
     * Classify the value. Always returns one of the class constants.
     */
    public function reasonFor(?string $value): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return self::EMPTY_VALUE;
        }

        // A bare ZIP is the single most common wrong-field mistake, and it is
        // recoverable (we can move it), so it is classified before the coarser
        // digits-only check that would otherwise swallow it.
        if (preg_match('/^\d{5}(?:-\d{4})?$/', $normalized) === 1) {
            return self::ZIP_SHAPED;
        }

        // No letters anywhere → there is no street name, whatever else is here.
        if (preg_match('/\p{L}/u', $normalized) !== 1) {
            return self::NUMBER_ONLY;
        }

        if (mb_strlen($normalized) < self::MIN_LENGTH) {
            return self::TOO_SHORT;
        }

        // A street name is a word of two or more letters. One-letter tokens are
        // directionals ("N", "E") or unit letters, never a street name on their own.
        if (preg_match('/\p{L}{2,}/u', $normalized) !== 1) {
            return self::NO_STREET_NAME;
        }

        // Require a house number somewhere. Written-out numbers ("One Beach Drive")
        // are accepted via the small word list rather than rejected, because they
        // are real and a user cannot work around a rule that refuses them.
        if (! $this->hasHouseNumber($normalized)) {
            return self::NO_STREET_NUMBER;
        }

        return self::OK;
    }

    /**
     * Convenience predicate. ZIP-shaped input is invalid *as a street address*.
     */
    public function isValid(?string $value): bool
    {
        return $this->reasonFor($value) === self::OK;
    }

    /**
     * User-facing message for a reason code. Every message names what is wrong
     * and what to do about it — never "invalid address".
     */
    public function messageFor(string $reason): string
    {
        return [
            self::EMPTY_VALUE => 'Enter the property street address.',
            self::ZIP_SHAPED => 'That looks like a ZIP code. Enter the street address here (for example, 100 2nd Ave N) and put the ZIP code in the ZIP Code field.',
            self::NUMBER_ONLY => 'That looks like a street number on its own. Enter the full street address, including the street name — for example, 43434 Main Street.',
            self::NO_STREET_NAME => 'Add the street name — for example, 100 2nd Ave N.',
            self::NO_STREET_NUMBER => 'Add the street number — for example, 100 2nd Ave N.',
            self::TOO_SHORT => 'That is too short to be a street address. Enter the full street address, including the street name.',
            self::OK => '',
        ][$reason] ?? 'Enter the full property street address, including the street name.';
    }

    /**
     * Message for a value, or an empty string when the value is fine.
     */
    public function messageForValue(?string $value): string
    {
        return $this->messageFor($this->reasonFor($value));
    }

    /**
     * Trim, collapse internal whitespace, and normalise non-breaking spaces so a
     * pasted address behaves the same as a typed one.
     */
    public function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\xc2\xa0", "\t", "\r", "\n"], ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * True when the string carries a house number, either as digits or as one of
     * the written-out forms that appear in real US addresses.
     */
    private function hasHouseNumber(string $normalized): bool
    {
        if (preg_match('/\d/', $normalized) === 1) {
            return true;
        }

        $written = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $first = mb_strtolower(explode(' ', $normalized)[0] ?? '');

        return in_array($first, $written, true);
    }
}

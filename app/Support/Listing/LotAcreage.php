<?php

namespace App\Support\Listing;

/**
 * One stored acreage value, read with its UNIT preserved.
 *
 * `total_acreage` / `min_acreage` are acreage fields. Every current writer stores one of the
 * form's acreage bands ("5 to less than 10 acres" — MLS imports band `LotSizeAcres` through
 * MlsFactVocabulary::acreageBand()), but older rows and other writers hold a bare number
 * ("5.2"). Read without a unit, that number was once restated as "5 square feet": rounded,
 * and in the wrong unit. This class is the one reading of those fields for the listing pages
 * and Ask AI, so both say the same thing:
 *
 *   - a form band               → the band, verbatim ("10 to less than 20 acres");
 *   - a band missing its suffix → the band ("5 to less than 10" is "5 to less than 10 acres");
 *   - a bare number             → that many ACRES, digits exactly as stored ("5.2 acres") —
 *                                 the field is an acreage field, so acres is its unit;
 *   - a number with a unit      → that unit, as written: acres stays acres, square feet
 *                                 stays square feet. Nothing is ever converted.
 *
 * Anything else — "Non-Applicable", zero, free text, a range it cannot read — is null, so a
 * caller states nothing rather than guessing. Pure: no container, no config.
 */
final class LotAcreage
{
    public const KIND_BAND        = 'band';
    public const KIND_ACRES       = 'acres';
    public const KIND_SQUARE_FEET = 'square_feet';

    private function __construct(
        public readonly string $kind,
        /** The band, or the number exactly as stored (commas kept). */
        public readonly string $value,
    ) {}

    public static function fromStored(mixed $raw): ?self
    {
        if (!is_scalar($raw) || is_bool($raw)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/', ' ', (string) $raw) ?? '');
        if ($text === '') {
            return null;
        }

        $bands = array_values(array_diff(MlsFactVocabulary::acreageBands(), ['Non-Applicable']));
        foreach ($bands as $band) {
            if (strcasecmp($text, $band) === 0) {
                return new self(self::KIND_BAND, $band);
            }
        }
        // Stellar spells its bands without the unit ("1/4 to less than 1/2").
        foreach ($bands as $band) {
            if (strcasecmp($text . ' acre', $band) === 0 || strcasecmp($text . ' acres', $band) === 0) {
                return new self(self::KIND_BAND, $band);
            }
        }

        $number = '(\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?|\.\d+)';
        if (preg_match('/^' . $number . '(?:\s*(acres?|ac\.?))?$/i', $text, $m) === 1) {
            return self::positive($m[1]) ? new self(self::KIND_ACRES, $m[1]) : null;
        }
        if (preg_match('/^' . $number . '\s*(?:sq\.?\s*ft\.?|sqft|square\s+feet|square\s+foot|sf)$/i', $text, $m) === 1) {
            return self::positive($m[1]) ? new self(self::KIND_SQUARE_FEET, $m[1]) : null;
        }

        return null;
    }

    /** The first of $candidates that reads as an acreage — the page's precedence order. */
    public static function firstOf(mixed ...$candidates): ?self
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || (is_string($candidate) && trim($candidate) === '')) {
                continue;
            }

            return self::fromStored($candidate);
        }

        return null;
    }

    /** "5.2 acres", "1 acre", "10 to less than 20 acres", "12,680 square feet". */
    public function display(): string
    {
        return match ($this->kind) {
            self::KIND_BAND        => $this->value,
            self::KIND_ACRES       => $this->value . ($this->isOne() ? ' acre' : ' acres'),
            self::KIND_SQUARE_FEET => $this->value . ($this->isOne() ? ' square foot' : ' square feet'),
        };
    }

    /**
     * A stored value for display: the unit-bearing reading when there is one, otherwise the
     * stored text unchanged (a page row never loses a value it printed before).
     */
    public static function displayStored(mixed $raw): string
    {
        $reading = self::fromStored($raw);
        if ($reading !== null) {
            return $reading->display();
        }

        return is_scalar($raw) && !is_bool($raw) ? trim((string) $raw) : '';
    }

    private function isOne(): bool
    {
        return (float) str_replace(',', '', $this->value) === 1.0;
    }

    private static function positive(string $number): bool
    {
        return (float) str_replace(',', '', $number) > 0.0;
    }
}

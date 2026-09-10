<?php

namespace App\Services\Location\Lookup;

use App\Services\Location\Coordinates\CoordinatePrecision;

/**
 * What a free-text address lookup answered, and the only shape of it the
 * browser is ever shown.
 *
 * TWO AUDIENCES, ONE OBJECT, AND THE SPLIT IS DELIBERATE
 * ------------------------------------------------------
 * The service and the log want the provider id and the machine-readable reason
 * a lookup failed. The browser wants a coordinate and a sentence. Both live
 * here, and {@see self::toResponse()} is the single place that decides which of
 * them crosses the wire — so "does the endpoint leak the provider's payload?"
 * is a question with one answer in one method, rather than a property of
 * whatever the controller happened to serialise that day.
 *
 * NOTHING FROM THE PROVIDER'S RESPONSE REACHES THIS OBJECT. It is built from a
 * {@see \App\Services\Location\Coordinates\PropertyCoordinateResult}, which is
 * itself built from four scalars the adapter extracted. There is no raw body,
 * no request URL, no header and no credential anywhere on this path — not
 * redacted, absent.
 *
 * THE MESSAGE IS ALWAYS THE SAME SENTENCE
 * ---------------------------------------
 * Every failure — a thin address, a no-match, an ambiguous match, a spent
 * budget, an open circuit, a provider outage — answers with
 * {@see self::FAILURE_MESSAGE}. That is not laziness about error reporting: the
 * distinctions are real, they are logged, and they matter to us, but to the
 * person typing they collapse into one actionable instruction, and a message
 * that varied with the provider's internal state would be telling a stranger
 * about our infrastructure. The machine-readable {@see self::$reason} carries
 * the distinction to the log.
 */
final class AddressLookupResult
{
    /**
     * The one sentence a failed lookup shows.
     *
     * Names the thing the user can act on. It deliberately does not say
     * "geocoder", "provider" or "service" — none of which is a thing they can
     * do anything about — and it never implies the address does not exist.
     */
    public const FAILURE_MESSAGE = 'Address could not be located. Try adding the city, state, or ZIP code.';

    private function __construct(
        public readonly bool $ok,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $address,
        public readonly ?string $precision,
        public readonly ?string $provider,
        public readonly ?string $reason,
    ) {
    }

    /**
     * A located address.
     *
     * `$address` is the provider's own matched line, presented for display —
     * see {@see self::present()}. It is what the map pin will be labelled with
     * and what the listing will store, so it must be the address that was
     * actually matched rather than the text that was typed: those differ, and
     * the difference is exactly what a user needs to see to notice a wrong
     * match before saving it.
     */
    public static function located(
        float $latitude,
        float $longitude,
        string $address,
        CoordinatePrecision $precision,
        ?string $provider = null,
    ): self {
        return new self(
            ok:        true,
            latitude:  $latitude,
            longitude: $longitude,
            address:   $address,
            precision: $precision->value,
            provider:  $provider,
            reason:    null,
        );
    }

    /**
     * No coordinate. `$reason` is for us; the user gets the sentence above.
     *
     * Carries no coordinate at all — not zero, not null-island, not a centroid.
     * A caller cannot accidentally read a location off a failure because there
     * is no location on it to read.
     */
    public static function notLocated(string $reason): self
    {
        return new self(
            ok:        false,
            latitude:  null,
            longitude: null,
            address:   null,
            precision: null,
            provider:  null,
            reason:    $reason,
        );
    }

    /**
     * The JSON the browser receives. Nothing else is ever sent.
     *
     * On success: the coordinate, the matched address, and the precision tier
     * — which the UI does not use today but which is the honest label for a
     * point that was interpolated along a street segment rather than observed
     * on a roof, and is cheap to carry now rather than retrofit later.
     *
     * The provider id is deliberately NOT included. It is useful to us and to
     * nobody using the form; a name in a payload is a name somebody eventually
     * branches on, and the point of the ladder is that callers do not know
     * which rung answered.
     */
    public function toResponse(): array
    {
        if (! $this->ok) {
            return [
                'ok'      => false,
                'message' => self::FAILURE_MESSAGE,
            ];
        }

        return [
            'ok'        => true,
            'address'   => $this->address,
            'lat'       => $this->latitude,
            'lng'       => $this->longitude,
            'precision' => $this->precision,
        ];
    }

    /**
     * Present a normalized lookup line for a human.
     *
     * The ladder records addresses in the normalized form the whole coordinate
     * subsystem compares on — lowercased, punctuation stripped, suffixes folded
     * ("315 madison st tampa fl 33602"). That form exists to make two spellings
     * of one address compare equal, and it is the right thing to store; it is
     * the wrong thing to put in an input box a person is reading.
     *
     * So this is presentation only and reverses nothing: it capitalises words,
     * leaves digit groups alone, and upper-cases a two-letter state sitting in
     * front of a trailing ZIP. It does not re-insert the commas or the words
     * normalization removed, because inventing punctuation the provider did not
     * return would be dressing a guess up as a formatted address.
     */
    public static function present(string $normalizedLine): string
    {
        $words = array_values(array_filter(explode(' ', trim($normalizedLine)), static fn ($w) => $w !== ''));

        if ($words === []) {
            return '';
        }

        $last = count($words) - 1;

        foreach ($words as $i => $word) {
            if (preg_match('/^\d+$/', $word) === 1) {
                continue;                       // house numbers, ZIPs
            }

            // The state slot: two letters immediately before a trailing ZIP, or
            // two letters at the very end when no ZIP was returned.
            $beforeZip = $i === $last - 1 && preg_match('/^\d{5}$/', $words[$last]) === 1;

            if (strlen($word) === 2 && ($beforeZip || $i === $last)) {
                $words[$i] = strtoupper($word);
                continue;
            }

            $words[$i] = ucfirst($word);
        }

        return implode(' ', $words);
    }
}

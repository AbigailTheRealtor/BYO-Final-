<?php

namespace App\Services\Stellar\Matching;

use App\Support\Geo\GreatCircleDistance;

/**
 * Does an MLS listing sit within the distance a client asked for from each of their Important Places?
 *
 * An Important Place ("Work · Within 3 miles") is a private location CONSTRAINT: its address and
 * coordinate belong to the client and are shown to nobody else (see ImportantPlacesService::publicRows()).
 * This class is the one place that coordinate is consumed for matching. It measures a straight line
 * (GreatCircleDistance) from the listing's own MLS coordinate to the place's stored coordinate — local
 * arithmetic, no provider, no per-property fee — and returns rows that say what KIND of place, how far,
 * and whether that is within the request. The rows never carry the place's address or coordinate, so
 * nothing built from them can publish either.
 *
 * Each place is judged independently. What the rows do to a score is BuyerMatchScorer's decision; they
 * never select or exclude a listing.
 *
 * Only MILES rows are requirements. A historical "within N minutes" row is a travel-time preference this
 * application cannot measure; its number is minutes, and reading it as miles would invent a constraint
 * the client never set — so it is left out, not converted. A row with no positive miles is not a
 * requirement either.
 */
final class ImportantPlaceMatcher
{
    public const WITHIN      = 'within';
    public const OUTSIDE     = 'outside';
    public const UNAVAILABLE = 'distance_unavailable';

    public const REASON_PLACE_NOT_LOCATED    = 'place_not_located';
    public const REASON_PROPERTY_NOT_LOCATED = 'property_not_located';

    /**
     * Distances are compared at micro-mile precision (~1.6 mm). A listing exactly at the requested
     * distance is WITHIN it, and floating-point noise in the last bits must not flip that verdict.
     */
    private const COMPARE_DECIMALS = 6;

    /**
     * @param  float|null $propertyLat  the listing's MLS latitude, or null when it has none
     * @param  float|null $propertyLng  the listing's MLS longitude, or null when it has none
     * @param  array      $places       rows from ImportantPlacesService::normalize() — MAY carry the
     *                                  private address and coordinate; none of it is returned
     * @return array<int, array{type: string, label: string, required_miles: float, actual_miles: ?float,
     *                          status: string, matches: ?bool, reason: ?string}>
     */
    public static function evaluate(?float $propertyLat, ?float $propertyLng, array $places): array
    {
        $propertyLocated = $propertyLat !== null && $propertyLng !== null
            && GreatCircleDistance::isCoordinate($propertyLat, $propertyLng);

        $rows = [];
        foreach ($places as $place) {
            if (!is_array($place)) {
                continue;
            }

            $required = self::requiredMiles($place);
            if ($required === null) {
                continue;
            }

            $type = trim((string) ($place['type'] ?? ''));
            $row  = [
                'type'           => $type !== '' ? $type : 'Important place',
                'label'          => self::label($place),
                'required_miles' => $required,
                'actual_miles'   => null,
                'status'         => self::UNAVAILABLE,
                'matches'        => null,
                'reason'         => null,
            ];

            if (!GreatCircleDistance::isCoordinate($place['lat'] ?? null, $place['lng'] ?? null)) {
                // The client's address was never located. Nothing can be measured from it — for this
                // listing or any other — so it is neither a pass nor a fail.
                $row['reason'] = self::REASON_PLACE_NOT_LOCATED;
            } elseif (!$propertyLocated) {
                // The listing has no MLS coordinate. Unavailable, never a match: a distance that
                // cannot be measured must not be reported as satisfied.
                $row['reason'] = self::REASON_PROPERTY_NOT_LOCATED;
            } else {
                $miles   = round(GreatCircleDistance::miles($propertyLat, $propertyLng, (float) $place['lat'], (float) $place['lng']), self::COMPARE_DECIMALS);
                $matches = $miles <= round($required, self::COMPARE_DECIMALS);

                $row['actual_miles'] = $miles;
                $row['status']       = $matches ? self::WITHIN : self::OUTSIDE;
                $row['matches']      = $matches;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** The requested maximum in miles, or null when the row is not a miles requirement. */
    public static function requiredMiles(array $place): ?float
    {
        if (($place['distance_pref'] ?? 'miles') !== 'miles') {
            return null;
        }

        $value = $place['distance_value'] ?? null;
        if (!is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) && $value > 0 ? $value : null;
    }

    /**
     * Share of the MEASURED requirements this listing meets, or null when none could be measured.
     * A requirement that could not be measured is left out of both sides of the fraction: it is
     * neither a pass nor a fail.
     */
    public static function satisfiedShare(array $rows): ?float
    {
        $measured = array_filter($rows, fn (array $row) => $row['matches'] !== null);
        if ($measured === []) {
            return null;
        }

        $met = count(array_filter($measured, fn (array $row) => $row['matches'] === true));

        return $met / count($measured);
    }

    /**
     * Rows for a page: the category, the distance and the verdict, written out by hand so that no key
     * the matcher did not name — and no future key a caller adds to a row — can reach the output.
     *
     * @return array<int, array{type: string, label: string, status: string, matches: ?bool,
     *                          actual_display: ?string, required_display: string}>
     */
    public static function present(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['required_miles'])) {
                continue;
            }

            $matches = $row['matches'] ?? null;
            $actual  = isset($row['actual_miles']) && is_numeric($row['actual_miles']) ? (float) $row['actual_miles'] : null;

            $out[] = [
                'type'             => (string) ($row['type'] ?? 'Important place'),
                'label'            => (string) ($row['label'] ?? 'Important place'),
                'status'           => (string) ($row['status'] ?? self::UNAVAILABLE),
                'matches'          => is_bool($matches) ? $matches : null,
                'actual_display'   => $actual !== null ? self::actualText($actual, (float) $row['required_miles'], $matches) : null,
                'required_display' => 'within ' . self::milesText((float) $row['required_miles']),
            ];
        }

        return $out;
    }

    /** "Work", or the client's own label for an "Other" place ("Publix"). */
    private static function label(array $place): string
    {
        $type = trim((string) ($place['type'] ?? ''));
        if ($type === 'Other') {
            $other = trim((string) ($place['type_other'] ?? ''));

            return $other !== '' ? $other : 'Other';
        }

        return $type !== '' ? $type : 'Important place';
    }

    /** "3 mi", "2.5 mi" — a requested distance, as the client typed it. */
    private static function milesText(float $miles): string
    {
        return rtrim(rtrim(number_format($miles, 2, '.', ''), '0'), '.') . ' mi';
    }

    /**
     * "2.1 mi". One decimal, unless rounding would contradict the verdict — 3.04 mi is OUTSIDE a
     * 3 mi request and must not read as "3.0 mi" beside a cross.
     */
    private static function actualText(float $actual, float $required, $matches): string
    {
        $oneDecimal = round($actual, 1);
        $contradicts = ($matches === false && $oneDecimal <= $required)
            || ($matches === true && $oneDecimal > $required);

        return number_format($actual, $contradicts ? 2 : 1, '.', '') . ' mi';
    }
}

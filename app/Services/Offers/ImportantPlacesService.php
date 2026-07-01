<?php

namespace App\Services\Offers;

/**
 * Phase 9C — Important Places (Buyer / Tenant Search Areas).
 *
 * Pure, side-effect-free normalizer + validator for the repeatable "Important Places"
 * rows stored in the ADDITIVE `important_places_json` meta key. This is deliberately
 * separate from the legacy commute fields (`commute_destination_zip`,
 * `max_commute_minutes`, `commute_mode`) — those are neither migrated nor removed.
 *
 * Canonical row shape (one object per place):
 *   {
 *     "type":           "Work",            // one of TYPES; "" while a draft row is empty
 *     "type_other":     "",                // free text, required only when type === "Other"
 *     "address":        "123 Main St, ...",// Exact Address (geocoded client-side)
 *     "lat":            27.95,             // geocode result (nullable; map convenience only)
 *     "lng":            -82.45,
 *     "distance_pref":  "miles",           // "miles" (radius circle) | "minutes" (travel time)
 *     "distance_value": 5,                 // > 0 — miles OR minutes depending on distance_pref
 *     "travel_mode":    "driving"          // driving | walking | bicycling | transit
 *   }
 *
 * The map deliberately draws a real geocoded PIN for every located place and a radius
 * CIRCLE only for the "miles" preference. "minutes" (travel-time) rows get a pin but NO
 * circle — an accurate isochrone cannot be drawn, and a plain radius would be a fake
 * travel-time circle, which the audit forbids.
 */
class ImportantPlacesService
{
    /** Selectable place types. "Other" unlocks the free-text companion field. */
    public const TYPES = [
        'Work',
        'School',
        'Daycare',
        'Family/Friends',
        'Place of Worship',
        'Gym/Fitness',
        'Medical/Hospital',
        'Airport',
        'Grocery',
        'Other',
    ];

    /** Distance preference axis: a radius in miles, or a travel time in minutes. */
    public const DISTANCE_PREFS = ['miles', 'minutes'];

    /** Google Distance-Matrix travel modes (stored lowercase). */
    public const TRAVEL_MODES = ['driving', 'walking', 'bicycling', 'transit'];

    /** Decode a raw JSON string (or pass an array through) into a list of row arrays. */
    public function decode($raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * True when a row carries no meaningful user input and can be dropped silently.
     * (An entirely blank repeatable row is expected — it must never trip validation.)
     */
    public function isRowEmpty(array $row): bool
    {
        foreach (['type', 'type_other', 'address', 'distance_value'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop fully-empty rows and coerce every surviving row to the canonical shape.
     * Partially-completed rows are PRESERVED here (so a draft never loses in-progress
     * work); it is validate() that rejects them on a full submit.
     */
    public function normalize($raw): array
    {
        $rows = is_array($raw) ? array_values($raw) : $this->decode($raw);
        $out  = [];

        foreach ($rows as $row) {
            if (!is_array($row) || $this->isRowEmpty($row)) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            $pref = $row['distance_pref'] ?? '';
            $mode = $row['travel_mode'] ?? '';

            $out[] = [
                'type'           => $type,
                'type_other'     => $type === 'Other' ? trim((string) ($row['type_other'] ?? '')) : '',
                'address'        => trim((string) ($row['address'] ?? '')),
                'lat'            => isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null,
                'lng'            => isset($row['lng']) && is_numeric($row['lng']) ? (float) $row['lng'] : null,
                'distance_pref'  => in_array($pref, self::DISTANCE_PREFS, true) ? $pref : 'miles',
                'distance_value' => is_numeric($row['distance_value'] ?? null) ? (float) $row['distance_value'] : null,
                'travel_mode'    => in_array($mode, self::TRAVEL_MODES, true) ? $mode : 'driving',
            ];
        }

        return $out;
    }

    /**
     * Validation messages for partially-completed rows. Fully-empty rows are dropped by
     * normalize() first, so anything returned here is a row the user started but left
     * incomplete. Returns a flat list of human-readable messages (empty = valid).
     */
    public function validate($raw): array
    {
        $rows   = $this->normalize($raw);
        $errors = [];

        foreach ($rows as $i => $row) {
            $n = $i + 1;

            if ($row['type'] === '') {
                $errors[] = "Important Place #{$n}: choose a place type.";
            } elseif ($row['type'] === 'Other' && $row['type_other'] === '') {
                $errors[] = "Important Place #{$n}: enter the custom place type.";
            }

            if ($row['address'] === '') {
                $errors[] = "Important Place #{$n}: enter an address.";
            }

            if ($row['distance_value'] === null || $row['distance_value'] <= 0) {
                $label = $row['distance_pref'] === 'minutes' ? 'travel time' : 'distance';
                $errors[] = "Important Place #{$n}: enter a {$label} greater than zero.";
            }
        }

        return $errors;
    }

    /** Normalized rows re-encoded to a compact JSON string for meta storage. */
    public function encode($raw): string
    {
        return json_encode($this->normalize($raw));
    }
}

<?php

namespace App\Support\LocationDna;

use App\Services\Offers\ImportantPlacesService;

/**
 * LocationDnaCriteriaDisplay — what a Buyer or Tenant search map is showing, in words.
 *
 * WHY THIS EXISTS. The detail map draws radius circles, custom areas and Important Place
 * pins and rings; the only text beside it was analyzer prose ("Searching within a defined
 * radius from a preferred location"), so a reader was shown circles with nothing saying
 * what they were centred on or how far they reached. This turns the SAME stored rows the
 * map draws into the rows a reader needs: the address, the distance, the place type.
 *
 * ONE READING, SHARED. Buyer and Tenant detail pages both render it through the shared
 * `x-location-dna-map` component, so neither role grows its own parser. Radius rows are
 * read through `RadiusSearchRow` (the same reading the map and the lookup services use);
 * Important Places arrive already normalised by `ImportantPlacesService`.
 *
 * WHAT IT MUST NOT DO — each of these is a separate system with its own owner:
 *   - serialize or alter anything; it returns display rows and nothing else
 *   - reinterpret matching; it says what was saved, never what it will match
 *   - print coordinates, JSON or provider payloads; a row with no address says so in
 *     words rather than falling back to the numbers
 *   - convert minutes to miles; a historical travel-time place is described as minutes,
 *     and as a pin without a ring, which is exactly what the map draws for it
 *
 * Seller and Landlord listings do not use this: they carry one property pin, not search
 * criteria, and there is nothing for it to describe.
 */
final class LocationDnaCriteriaDisplay
{
    /** @var array<int, array{title: string, drawn: bool, distance: ?string}> */
    public array $radiusSearches = [];

    /** @var array<int, array{type: string, address: string, distance: ?string, on_map: string, legacy_minutes: bool}> */
    public array $importantPlaces = [];

    public int $customAreaCount = 0;

    /** @var array<string, string[]> label => values, in display order, empty groups omitted */
    public array $areas = [];

    public bool $flexible = false;

    public string $notes = '';

    /**
     * @param  array|null  $preferences      decoded `location_dna_preferences`
     * @param  array       $importantPlaces  rows from ImportantPlacesService::normalize()
     * @param  array       $legacyLocation   the detail controllers' legacy cities/counties/states/zip_codes
     */
    public static function from(?array $preferences, array $importantPlaces = [], array $legacyLocation = []): self
    {
        $prefs   = is_array($preferences) ? $preferences : [];
        $display = new self();

        /* Only rows the map can actually draw — an array with a usable centre AND radius. The
         * text explains the map; a row that draws nothing would be a circle described in words
         * with no circle beside it. */
        $radiusRows = is_array($prefs['radius_searches'] ?? null) ? $prefs['radius_searches'] : [];
        foreach ($radiusRows as $row) {
            $circle = RadiusSearchRow::circle($row);
            if ($circle === null) {
                continue;
            }

            $description = RadiusSearchRow::description($row);

            $display->radiusSearches[] = [
                'title'    => $description !== '' ? $description : 'Area drawn on the map',
                'drawn'    => !isset($row['address']) || trim((string) $row['address']) === '',
                'distance' => 'Within ' . self::miles($circle['radius_miles']),
            ];
        }

        foreach ($importantPlaces as $place) {
            if (is_array($place)) {
                $display->importantPlaces[] = self::place($place);
            }
        }

        $display->customAreaCount = count(array_filter(
            self::list($prefs['polygons'] ?? []),
            fn ($poly) => is_array($poly) && is_array($poly['path'] ?? null) && count($poly['path']) >= 3
        ));

        $display->areas = array_filter([
            'State'         => self::strings(array_merge(self::list($prefs['state'] ?? []), self::list($legacyLocation['states'] ?? []))),
            'Counties'      => self::strings(array_merge(self::list($prefs['counties'] ?? []), self::list($legacyLocation['counties'] ?? []))),
            'Cities'        => self::strings(array_merge(self::list($prefs['cities'] ?? []), self::list($legacyLocation['cities'] ?? []))),
            'ZIP codes'     => self::strings(array_merge(self::list($prefs['zip_codes'] ?? []), self::list($legacyLocation['zip_codes'] ?? []))),
            'Neighborhoods' => self::strings(self::list($prefs['neighborhoods'] ?? [])),
        ]);

        $display->flexible = filter_var($prefs['flexible_location'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $display->notes    = is_string($prefs['location_notes'] ?? null) ? trim($prefs['location_notes']) : '';

        return $display;
    }

    /** True when any drawn geometry or Important Place is present — the rows the map needs explained. */
    public function hasMappedCriteria(): bool
    {
        return $this->radiusSearches !== [] || $this->importantPlaces !== [] || $this->customAreaCount > 0;
    }

    /** True when there is nothing at all to say. */
    public function isEmpty(): bool
    {
        return !$this->hasMappedCriteria() && $this->areas === [] && !$this->flexible && $this->notes === '';
    }

    private static function place(array $place): array
    {
        $type = trim((string) ($place['type'] ?? ''));
        if ($type === 'Other') {
            $other = trim((string) ($place['type_other'] ?? ''));
            $type  = $other !== '' ? $other : 'Other';
        }

        $value   = is_numeric($place['distance_value'] ?? null) ? (float) $place['distance_value'] : null;
        $minutes = ($place['distance_pref'] ?? 'miles') === 'minutes';
        $located = is_numeric($place['lat'] ?? null) && is_numeric($place['lng'] ?? null);

        $distance = null;
        if ($value !== null && $value > 0) {
            $distance = $minutes
                ? 'Within ' . self::number($value) . ' minutes (travel time)'
                : 'Within ' . self::miles($value);
        }

        if (!$located) {
            $onMap = 'none';
        } elseif (!$minutes && $distance !== null) {
            $onMap = 'pin_and_ring';
        } else {
            $onMap = 'pin';
        }

        return [
            'type'           => $type !== '' ? $type : 'Important place',
            'address'        => trim((string) ($place['address'] ?? '')),
            'distance'       => $distance,
            'on_map'         => $onMap,
            'legacy_minutes' => $minutes,
        ];
    }

    private static function miles(float $miles): string
    {
        return self::number($miles) . ($miles == 1.0 ? ' mile' : ' miles');
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function list($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return is_string($value) && trim($value) !== '' ? [$value] : [];
    }

    private static function strings(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '' && !in_array($value, $out, true)) {
                    $out[] = $value;
                }
            }
        }

        return $out;
    }
}

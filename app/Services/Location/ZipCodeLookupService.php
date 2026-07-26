<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 0 — ZIP → City / County / State / coordinates, from data we already own.
 *
 * Reads the `us_zip_codes` gazetteer (34,741 rows: zip_code, city, county,
 * state_abbrev, state_name, latitude, longitude) that has been in the
 * application database since 2025-12 and, until now, powered nothing but a
 * type-ahead chip list.
 *
 * WHY THIS EXISTS
 * ---------------
 * City/County/State autofill was a browser-side side effect of Google Places.
 * When the Google credential stopped working the autofill went with it. This
 * service restores the majority of that behaviour with **zero external calls,
 * zero credentials, and zero cost** — it is the cheapest possible answer to the
 * question the audit posed, and it is Google-free by construction (SIA-D25).
 *
 * WHAT IT IS NOT
 * --------------
 * Not a geocoder. A ZIP centroid is not a property location — it is the centre
 * of a postal area that can span several miles. `centroidFor()` is offered for
 * map framing and coarse fallbacks only and is labelled `zip_centroid` at every
 * call site so nothing downstream can mistake it for a rooftop coordinate.
 * Real per-property coordinates arrive in Phase 2.
 *
 * @see \App\Services\Location\AddressShapeValidator
 * @see docs/spatial-ui-integration-audit-2026-07-25.md §4.1, §8 Phase 0
 */
class ZipCodeLookupService
{
    /** Geocode provenance tag written alongside any coordinate this class returns. */
    public const SOURCE = 'zip_centroid';

    private const TABLE = 'us_zip_codes';

    /** Lookups are stable reference data; an hour of cache is conservative. */
    private const CACHE_TTL = 3600;

    /**
     * Resolve a five-digit ZIP to its canonical location row.
     *
     * @return array{zip:string,city:string,county:string,state:string,state_name:string,lat:?float,lng:?float}|null
     */
    public function find(?string $zip): ?array
    {
        $zip = $this->normalizeZip($zip);

        if ($zip === null) {
            return null;
        }

        return Cache::remember(
            "us_zip:{$zip}",
            self::CACHE_TTL,
            function () use ($zip) {
                // This runs on the address field's keystroke path. A missing or
                // unreadable gazetteer must degrade to "unknown ZIP", never throw
                // — a 500 here would break the whole wizard round trip.
                try {
                    $row = DB::table(self::TABLE)->where('zip_code', $zip)->first();
                } catch (\Throwable $e) {
                    return null;
                }

                if ($row === null) {
                    return null;
                }

                return [
                    'zip'        => (string) $row->zip_code,
                    'city'       => (string) $row->city,
                    'county'     => (string) $row->county,
                    'state'      => (string) $row->state_abbrev,
                    'state_name' => (string) $row->state_name,
                    'lat'        => $row->latitude !== null ? (float) $row->latitude : null,
                    'lng'        => $row->longitude !== null ? (float) $row->longitude : null,
                ];
            }
        );
    }

    /**
     * True when the digits are a real US ZIP. Used to tell `33708` (a ZIP typed
     * into the street field, recoverable) apart from `43434` (a street number on
     * its own, not recoverable) — the exact distinction the audit's scenarios 1
     * and 2 require.
     */
    public function isKnownZip(?string $zip): bool
    {
        return $this->find($zip) !== null;
    }

    /**
     * True when the gazetteer is present and populated.
     *
     * Callers use this to tell "these five digits are not a US ZIP" apart from
     * "we cannot currently tell". The difference matters for the message shown to
     * a user who typed a ZIP into the street field: with no gazetteer we must not
     * assert the digits are a street number, because we do not know that.
     */
    public function isAvailable(): bool
    {
        return Cache::remember('us_zip:available', self::CACHE_TTL, function () {
            try {
                return DB::table(self::TABLE)->limit(1)->exists();
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /**
     * ZIP centroid, tagged with its provenance so no caller can mistake it for a
     * geocoded property location.
     *
     * @return array{lat:float,lng:float,source:string}|null
     */
    public function centroidFor(?string $zip): ?array
    {
        $row = $this->find($zip);

        if ($row === null || $row['lat'] === null || $row['lng'] === null) {
            return null;
        }

        return ['lat' => $row['lat'], 'lng' => $row['lng'], 'source' => self::SOURCE];
    }

    /**
     * ZIP codes beginning with the given digits, for the existing type-ahead.
     *
     * @return array<int,string>
     */
    public function suggest(?string $prefix, int $limit = 10): array
    {
        $prefix = preg_replace('/\D/', '', (string) $prefix) ?? '';

        if ($prefix === '') {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('zip_code', 'like', $prefix . '%')
            ->orderBy('zip_code')
            ->limit(max(1, $limit))
            ->pluck('zip_code')
            ->map(fn ($z) => (string) $z)
            ->all();
    }

    /**
     * Reduce any user input to a bare five-digit ZIP, or null when it cannot be
     * one. Accepts `33708`, `33708-1234`, and ` 33708 `.
     */
    public function normalizeZip(?string $zip): ?string
    {
        $zip = trim((string) $zip);

        if (preg_match('/^(\d{5})(?:-\d{4})?$/', $zip, $m) !== 1) {
            return null;
        }

        return $m[1];
    }
}

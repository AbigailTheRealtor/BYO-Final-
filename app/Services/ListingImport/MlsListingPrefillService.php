<?php

namespace App\Services\ListingImport;

use App\Services\Property\PropertyCandidate;

/**
 * Turns a normalized {@see PropertyCandidate} into the canonical-key array the
 * Seller/Landlord import preview already knows how to display and apply.
 *
 * This is Use Case A of docs/mls-direct-import-design-and-plan.md — the
 * Seller/Landlord prefill consumer. It is the counterpart to
 * {@see MlsListingImportService}, which produces the same result shape from a
 * scraped URL or pasted text, and the two are interchangeable from the trait's
 * point of view precisely because the shape matches.
 *
 * THIS CLASS IS THE FACTS-ONLY BOUNDARY
 * -------------------------------------
 * `PropertyCandidate` carries `$raw` — the untouched Bridge record, which for a
 * Stellar listing contains PublicRemarks, PrivateRemarks, ListAgent* and
 * ListOffice* fields, showing instructions and media URLs. The DTO documents
 * that it enforces no allow-list and that the launch rule is the prefill
 * consumer's job. This is that consumer, and this is that rule.
 *
 * The enforcement is structural rather than a filter: {@see ALLOWED_FIELDS} is
 * an explicit map from a PropertyCandidate property name to a canonical import
 * key, and build() reads ONLY the properties named in it. `$raw` is never read,
 * not for a fallback, not for one missing field, not for a "harmless" extra.
 *
 * That distinction matters more than it looks. A denylist of forbidden keys
 * would have to be updated every time the feed adds a field, and would fail
 * open — a new `AgentNotes` column would flow into a published listing until
 * somebody noticed. An allow-list fails closed: an unrecognised field simply
 * never arrives. The compliance rule here is a Stellar MLS licensing boundary
 * around photo/remarks reuse, so failing closed is the only acceptable
 * direction.
 *
 * Adding a field to ALLOWED_FIELDS is therefore a licensing decision, not a
 * mapping tweak. The guard test asserts the constant's exact contents so a new
 * entry cannot be added without the test — and a reviewer — seeing it.
 *
 * WHAT IS DELIBERATELY NOT DERIVED
 * --------------------------------
 * The address is passed through as the feed's own `UnparsedAddress`, not split
 * into street / unit components. See buildAddress() for why.
 */
class MlsListingPrefillService
{
    /**
     * The complete set of fields that may reach a listing form, as
     * PropertyCandidate property => canonical import key.
     *
     * Every entry is an objective, publicly-advertised property fact. Nothing
     * here is authored prose, imagery, or a person's contact details.
     *
     * `listingKey` is not a form field — it has no input and is never shown as
     * an editable value. It rides along so the apply step can persist it as
     * listing meta, which is what lets the coordinate ladder's Bridge rung find
     * this property's feed record later. See HasMlsImport::applyImportedFields().
     */
    public const ALLOWED_FIELDS = [
        // ── Identity (not user-editable; carried for provenance/meta) ────────
        'mlsNumber'       => 'mls_number',
        'listingKey'      => 'mls_listing_key',

        // ── Address ─────────────────────────────────────────────────────────
        'unparsedAddress' => 'address',
        'city'            => 'city',
        'stateOrProvince' => 'state',
        'postalCode'      => 'zip',
        'countyOrParish'  => 'county',

        // ── Coordinates ─────────────────────────────────────────────────────
        'latitude'        => 'latitude',
        'longitude'       => 'longitude',

        // ── Structure / size ────────────────────────────────────────────────
        'bedrooms'        => 'bedrooms',
        'bathrooms'       => 'bathrooms',
        'livingAreaSqft'  => 'heated_sqft',
        'lotSizeSqft'     => 'lot_size_sqft',
        'yearBuilt'       => 'year_built',

        // ── Classification ──────────────────────────────────────────────────
        'propertyType'    => 'property_type',
        'propertySubType' => 'property_sub_type',
        'mlsStatus'       => 'mls_status',

        // ── Price ───────────────────────────────────────────────────────────
        'listPrice'       => 'price',
    ];

    /**
     * Canonical keys that carry data but must never be offered to the user as a
     * checkbox row in the preview.
     *
     * `mls_listing_key` and `mls_number` are record handles, not property facts
     * — there is no form input for either, and showing an opaque RESO key in a
     * "review what will be imported" table invites someone to uncheck the one
     * value that makes the coordinate lookup work later.
     *
     * They still travel in the returned data and are still persisted by the
     * apply step; they are simply not presented as editable fields. The preview
     * builder in HasMlsImport already skips keys with no field-map entry, so
     * this list documents the intent rather than creating a second mechanism.
     */
    public const NON_PREVIEW_KEYS = [
        'mls_number',
        'mls_listing_key',
    ];

    /**
     * @return array{success: bool, data: array<string,string>, error: string}
     *         Identical in shape to MlsListingImportService::import(), so the
     *         HasMlsImport preview/apply machinery consumes either one.
     */
    public function fromCandidate(?PropertyCandidate $candidate): array
    {
        if ($candidate === null) {
            return $this->failure('No listing was supplied to import.');
        }

        $data = [];

        foreach (self::ALLOWED_FIELDS as $property => $canonicalKey) {
            // Coordinates are decided together, below — a half-pair is not a
            // location and must never reach the form.
            if ($canonicalKey === 'latitude' || $canonicalKey === 'longitude') {
                continue;
            }

            // Reading the named property directly — never $candidate->raw, and
            // never a dynamic lookup that could reach a field not listed above.
            $value = $candidate->{$property} ?? null;

            $normalized = $this->stringify($value);

            if ($normalized !== null) {
                $data[$canonicalKey] = $normalized;
            }
        }

        $coordinates = $this->buildCoordinatePair($candidate);
        if ($coordinates !== null) {
            $data += $coordinates;
        }

        // An import that produced only record handles has told the user nothing
        // about their property; treat it as a failed import rather than showing
        // an empty preview.
        if (empty(array_diff_key($data, array_flip(self::NON_PREVIEW_KEYS)))) {
            return $this->failure(
                'That MLS listing was found but contains no property details we can import.'
            );
        }

        return [
            'success' => true,
            'data'    => $data,
            'error'   => '',
        ];
    }

    /**
     * Normalize one allowed value to the string form the preview/apply pipeline
     * expects, or null to omit the field entirely.
     *
     * Everything downstream — the preview table, the checkbox state, the
     * assignment into Livewire props — is string-based, matching what the
     * URL/text parser emits. Casting here keeps that contract in one place.
     */
    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_float($value) || is_int($value)) {
            // Every numeric field in this allow-list other than the coordinates
            // (price, sqft, year, beds, baths) is a whole number in practice, and
            // a trailing ".0" would read as corrupted data in the preview table.
            return (string) (is_float($value) && floor($value) === $value
                ? (int) $value
                : $value);
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Both coordinates, or neither.
     *
     * WHY THE PAIR IS ATOMIC
     * ----------------------
     * A latitude without a longitude is not a partial location, it is not a
     * location. Worse, it is actively harmful here: `property_lat` being
     * populated is exactly what suppresses the address-geocoding fallback in
     * HasMlsImport, so importing half a pair would both fail to locate the
     * property and prevent anything else from locating it either. Returning
     * null for the pair leaves that fallback intact.
     *
     * A zero is treated as absent rather than as Null Island — in a feed column
     * it means "never populated", which is the same reading
     * {@see \App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter}
     * takes of the same data.
     *
     * @return array{latitude: string, longitude: string}|null
     */
    private function buildCoordinatePair(PropertyCandidate $candidate): ?array
    {
        $latitude  = $candidate->latitude;
        $longitude = $candidate->longitude;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $latitude  = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude === 0.0 || $longitude === 0.0) {
            return null;
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            return null;
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        return [
            'latitude'  => $this->formatCoordinate($latitude),
            'longitude' => $this->formatCoordinate($longitude),
        ];
    }

    /**
     * Fixed to the 7 decimal places `bridge_properties` stores, then trimmed of
     * trailing zeroes so the preview shows "27.9506" rather than "27.9506000".
     */
    private function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }

    /**
     * @return array{success: bool, data: array, error: string}
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'data'    => [],
            'error'   => $message,
        ];
    }
}

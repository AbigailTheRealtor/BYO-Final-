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

        // ── Owner-side disclosures (Master Phase 1) ─────────────────────────
        // Objective, publicly-advertised facts about the property itself, not
        // about the transaction or the people in it. Every one of these already
        // had a Seller AND Landlord form target in MlsFieldMap before this
        // change; the only thing that was missing was permission to read the
        // PropertyCandidate property the adapter has always populated.
        //
        // These four are property-type-neutral: their form targets live on the
        // Tax / Legal / HOA tab, which renders no property_type conditional at
        // all, so they are legitimate for all seven types.
        'taxAnnualAmount' => 'annual_taxes',
        'association'     => 'has_hoa',
        'associationFee'  => 'association_fee_amount',
        'cdd'             => 'has_cdd',

        // ── Physical characteristics (Master Phase 1) ───────────────────────
        // `waterfront` renders for every property type. `pool` and `garage`
        // do NOT — see MlsFieldMap::propertyTypeApplicability(), which stops
        // them being offered for a type whose form never shows them.
        'waterfront'      => 'waterfront',
        'pool'            => 'pool',
        'garage'          => 'garage',

        // ── Construction, systems, land (Bridge reconciliation) ─────────────
        // Objective, publicly-advertised characteristics of the building. Every
        // one of these already had a canonical BidYourOffer field AND an
        // MlsFieldMap target for BOTH Seller and Landlord before this entry was
        // added, and every target was confirmed rendered on the live form with
        // no property-type restriction. The fact was being fetched from the feed
        // and then thrown away for want of a line here.
        //
        // The list-valued ones flatten to a comma-joined string in stringify(),
        // which is the shape the preview table and MlsQuickImportDraftWriter
        // already split back into an array for a `*` multi-select target. That
        // is the URL parser's long-standing contract, matched deliberately
        // rather than invented.
        'appliances'            => 'appliances',
        'constructionMaterials' => 'exterior_construction',
        'cooling'               => 'air_conditioning',
        'heating'               => 'heating_fuel',
        'foundationDetails'     => 'foundation',
        'interiorFeatures'      => 'interior_features',
        'roof'                  => 'roof_type',
        'sewer'                 => 'sewer',
        'utilities'             => 'utilities',
        'waterSource'           => 'water',
        'waterfrontFeatures'    => 'water_access',

        // ── Tax / legal / parcel ────────────────────────────────────────────
        // Public record data. The Tax, Legal & HOA tab has a field for each and
        // renders no property-type conditional, so all seven types are covered.
        'parcelNumber'          => 'tax_id',
        'taxLegalDescription'   => 'legal_description',
        'taxYear'               => 'tax_year',

        // ── Size ────────────────────────────────────────────────────────────
        // Seller-only in practice: `building_size_sqft` has a Seller
        // MlsFieldMap target and no Landlord one, and the draft writer skips a
        // canonical key its role's map does not contain. The role split is
        // therefore enforced by the map, not duplicated here.
        'buildingAreaTotal'     => 'building_size_sqft',

        // ── Hazard ──────────────────────────────────────────────────────────
        'floodZoneCode'         => 'flood_zone_code',

        // DELIBERATELY ABSENT — `OccupantType`.
        // It is an objective MLS fact, and a matching canonical field
        // (`occupant_status`) exists — but that field lives on the Sale Terms /
        // Leasing Terms tab, which is the user's statement of how they intend to
        // transact. Those surfaces were just made canonical and are deliberately
        // user-controlled; the feed's view of who is in the property today is
        // not the same claim as the seller's declaration of occupancy at
        // closing. It also has no MlsFieldMap target on either role, so importing
        // it would mean inventing one. Left for an explicit product decision.
        //
        // DELIBERATELY ABSENT — `Flooring` and `SubdivisionName`.
        // Both are permitted facts with real form fields, but neither has an
        // MlsFieldMap entry for either role. Adding the mapping is a separate,
        // reviewable change; silently minting new canonical routes inside a
        // licensing allow-list is not.
        //
        // DELIBERATELY ABSENT — `Furnished` and `BuildingFeatures`.
        // Their targets diverge by role (`building_features` on Seller,
        // `tenant_require` on Landlord) and the Seller side MERGES rather than
        // replaces, with an explicit exclusion for "Unfurnished" — see the
        // furnished branch in HasMlsImport::applyImportedFields(). The quick
        // import draft writer has no equivalent merge step, so routing these
        // through it would replace a user's building_features array instead of
        // adding to it. Needs the merge to exist on the write path first.

        // DELIBERATELY ABSENT — `petsAllowed`.
        // The candidate carries it and BridgePropertyNormalizer now preserves
        // the complete policy, but the Landlord target it maps to (`pet_policy`)
        // has NO wire:model binding in any Create Offer tab. Importing it would
        // write a value the user can neither see nor correct. The normalizer
        // fidelity fix ships here so the data is right the day the field is
        // wired; the mapping does not. Same reason `rent_includes`,
        // `tenant_pays`, `building_features_list` and `current_use_list` are
        // absent — see MlsCoverageReporter's `no_form_binding` rows.
        //
        // DELIBERATELY ABSENT — `associationName`.
        // Not carried by the candidate at all, and the feed's AssociationName is
        // frequently a named individual sitting beside an AssociationPhone. That
        // is contact data, which is outside the facts-only boundary regardless
        // of the fact that a form field with a matching name exists.
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

        // RESO list fields arrive as arrays and travel onward as the
        // comma-joined string every consumer of this pipeline already expects:
        // the preview table renders it, and both apply paths split it back into
        // an array for a `*` multi-select target.
        //
        // Empty members are dropped so a feed that sends ["Slab", ""] does not
        // produce a trailing empty option. A member containing a comma would
        // split into two on the way back — no RESO enumeration in this
        // allow-list contains one, and this is the same contract the URL parser
        // has always used.
        if (is_array($value)) {
            $items = array_values(array_filter(
                array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value),
                static fn (string $v) => $v !== '',
            ));

            return $items === [] ? null : implode(', ', $items);
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

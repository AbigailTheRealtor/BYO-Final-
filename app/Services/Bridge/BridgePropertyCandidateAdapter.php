<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
use App\Services\Property\PropertyCandidate;

/**
 * Maps a persisted BridgeProperty (the local MLS cache row, native columns plus
 * the untouched `raw_json` blob) into the provider-agnostic PropertyCandidate.
 *
 * This is the ONLY place that knows the Bridge/Stellar column shape. Adding a
 * future MLS source means writing a sibling adapter — no consumer changes.
 *
 * Boolean feature flags and lat/lng are already cast by the model; this adapter
 * still null-guards and re-casts numeric columns defensively so the resulting
 * DTO is strongly typed regardless of the underlying driver's return types.
 */
class BridgePropertyCandidateAdapter
{
    public function fromModel(BridgeProperty $p): PropertyCandidate
    {
        $raw = [];
        if (!empty($p->raw_json)) {
            $decoded = json_decode($p->raw_json, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        return new PropertyCandidate(
            source:            'bridge',
            sourceRecordId:    $p->id !== null ? (string) $p->id : null,

            mlsNumber:         $p->listing_id,
            listingKey:        $p->listing_key,
            standardStatus:    $p->standard_status,
            mlsStatus:         $p->mls_status,
            propertyType:      $p->property_type,
            propertySubType:   $p->property_sub_type,

            listPrice:         $this->toFloat($p->list_price),

            unparsedAddress:   $p->unparsed_address,
            city:              $p->city,
            stateOrProvince:   $p->state_or_province,
            postalCode:        $p->postal_code,
            countyOrParish:    $p->county_or_parish,

            bedrooms:          $this->toInt($p->bedrooms_total),
            bathrooms:         $this->toInt($p->bathrooms_total_integer),
            livingAreaSqft:    $this->toInt($p->living_area),
            lotSizeSqft:       $this->toInt($p->lot_size_sqft),
            yearBuilt:         $this->toInt($p->year_built),

            latitude:          $this->toFloat($p->latitude),
            longitude:         $this->toFloat($p->longitude),

            associationFee:    $this->toFloat($p->association_fee),
            taxAnnualAmount:   $this->toFloat($p->tax_annual_amount),

            petsAllowed:       $p->pets_allowed,
            pool:              $this->toBool($p->pool_private_yn),
            garage:            $this->toBool($p->garage_yn),
            waterfront:        $this->toBool($p->waterfront_yn),
            view:              $this->toBool($p->view_yn),
            waterView:         $this->toBool($p->water_view_yn),
            seniorCommunity:   $this->toBool($p->senior_community_yn),
            association:       $this->toBool($p->association_yn),
            newConstruction:   $this->toBool($p->new_construction_yn),
            cdd:               $this->toBool($p->cdd_yn),

            modificationTimestamp: $p->modification_timestamp !== null
                ? (string) $p->modification_timestamp
                : null,
            raw: $raw,

            // ── Facts that live only in raw_json ────────────────────────────
            //
            // bridge_properties has native columns for the fields the search
            // path filters on; these are not among them, so the feed's own
            // record is the only place they exist locally. Reading them here —
            // in the ONE class that is allowed to know the Bridge column shape —
            // keeps every consumer working against the typed DTO instead of
            // reaching into $raw itself, which is exactly the boundary the
            // prefill service depends on.
            //
            // Named keys only. No loop over $raw, no prefix match, nothing that
            // could pick up a field the feed adds tomorrow.
            appliances:            $this->toList($raw, 'Appliances'),
            constructionMaterials: $this->toList($raw, 'ConstructionMaterials'),
            cooling:               $this->toList($raw, 'Cooling'),
            heating:               $this->toList($raw, 'Heating'),
            foundationDetails:     $this->toList($raw, 'FoundationDetails'),
            interiorFeatures:      $this->toList($raw, 'InteriorFeatures'),
            roof:                  $this->toList($raw, 'Roof'),
            sewer:                 $this->toList($raw, 'Sewer'),
            utilities:             $this->toList($raw, 'Utilities'),
            waterSource:           $this->toList($raw, 'WaterSource'),
            waterfrontFeatures:    $this->toList($raw, 'WaterfrontFeatures'),

            parcelNumber:          $this->toText($raw, 'ParcelNumber'),
            taxLegalDescription:   $this->toText($raw, 'TaxLegalDescription'),
            taxYear:               $this->toText($raw, 'TaxYear'),

            buildingAreaTotal:     $this->toInt($raw['BuildingAreaTotal'] ?? null),

            // Stellar prefixes its local extensions; this is the flood zone the
            // Tax/Legal tab already has a field for.
            floodZoneCode:         $this->toText($raw, 'STELLAR_FloodZoneCode'),

            flooring:              $this->toList($raw, 'Flooring'),
            furnished:             $this->toText($raw, 'Furnished')
                                       ?? $this->toText($raw, 'FurnishedYN'),

            // ── Parity additions (2026-09-04 payload audit) ─────────────────
            //
            // Same discipline as the block above: NAMED KEYS ONLY. No loop over
            // $raw, no prefix match, nothing that could pick up a field the feed
            // adds tomorrow. Each one is a fact the feed populates and a Create
            // Offer control already exists for.
            lotSizeAcres:            $this->toFloat($raw['LotSizeAcres'] ?? null),
            lotDimensions:           $this->toText($raw, 'LotSizeDimensions'),
            zoning:                  $this->toText($raw, 'Zoning'),
            carport:                 $this->rawBool($raw, 'CarportYN'),
            additionalParcels:       $this->rawBool($raw, 'AdditionalParcelsYN'),
            floodZonePanel:          $this->toText($raw, 'STELLAR_FloodZonePanel'),
            floodZoneDate:           $this->toDate($raw, 'STELLAR_FloodZoneDate'),
            livingAreaSource:        $this->toText($raw, 'LivingAreaSource'),
            associationFeeFrequency: $this->toText($raw, 'AssociationFeeFrequency'),
            waterfrontFeet:          $this->toInt($raw['STELLAR_WaterfrontFeetTotal'] ?? null),
            numberOfLots:            $this->toInt($raw['NumberOfLots'] ?? null),

            // One feed value, two destinations. See the note on the DTO.
            availabilityDate:        $this->toDate($raw, 'AvailabilityDate'),
            leaseAvailabilityDate:   $this->toDate($raw, 'AvailabilityDate'),
            leaseAmountFrequency:    $this->toText($raw, 'LeaseAmountFrequency'),
            securityDeposit:         $this->toInt($raw['STELLAR_SecurityDeposit'] ?? null),
            officeRetailSqft:        $this->toInt($raw['STELLAR_OfficeRetailSpaceSqFt'] ?? null),

            // GrossIncome is the actual figure; GrossScheduledIncome is what the
            // property would collect fully let. Preferring the actual is the
            // conservative read — a listing that reports both should not have the
            // optimistic number copied into the owner's own income field.
            grossAnnualIncome:       $this->toInt($raw['GrossIncome'] ?? $raw['GrossScheduledIncome'] ?? null),
            annualOperatingExpenses: $this->toInt($raw['STELLAR_AnnualExpenses'] ?? null),
            businessType:            $this->toList($raw, 'BusinessType'),
        );
    }

    /**
     * A RESO list field as a clean list of non-empty strings, or null.
     *
     * The feed sends these as JSON arrays. A single string is accepted too,
     * because some fields are single-valued on some records and the caller
     * should not have to care. Null when absent or empty, so the prefill
     * service omits the field rather than offering the user an empty row.
     *
     * @return list<string>|null
     */
    private function toList(array $raw, string $key): ?array
    {
        $value = $raw[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $items = array_values(array_filter(
            array_map(
                static fn ($v) => is_scalar($v) ? trim((string) $v) : '',
                is_array($value) ? $value : [$value],
            ),
            static fn (string $v) => $v !== '',
        ));

        return $items === [] ? null : $items;
    }

    /** A scalar RESO field as a trimmed string, or null when absent or empty. */
    private function toText(array $raw, string $key): ?string
    {
        $value = $raw[$key] ?? null;

        if ($value === null || is_array($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }


    /**
     * A boolean straight off the raw record, or null when the key is absent.
     *
     * Distinct from toBool(), which casts an already-typed column. A feed sends
     * these as JSON booleans and occasionally as the strings "true"/"false"; a
     * naive (bool) cast turns the string "false" into true, which for
     * CarportYN means telling a seller their property has a carport it does not.
     */
    private function rawBool(array $raw, string $key): ?bool
    {
        if (! array_key_exists($key, $raw) || $raw[$key] === null || $raw[$key] === '') {
            return null;
        }

        return filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * A feed date as `Y-m-d`.
     *
     * The destinations are `<input type="date">`, which silently renders blank
     * for anything that is not `Y-m-d` — so a value passed through untouched
     * would look exactly like a field the import failed to fill. Stellar sends
     * both plain dates and full ISO-8601 timestamps in these columns.
     * Unparseable values return null rather than a guess.
     */
    private function toDate(array $raw, string $key): ?string
    {
        $value = $this->toText($raw, $key);

        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function toInt(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    private function toFloat(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }

    private function toBool(mixed $v): ?bool
    {
        return $v === null ? null : (bool) $v;
    }
}

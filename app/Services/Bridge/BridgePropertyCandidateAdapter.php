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
        );
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

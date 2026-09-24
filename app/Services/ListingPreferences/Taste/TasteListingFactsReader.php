<?php

namespace App\Services\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Services\ListingImport\Mls\MlsDisplayPermissions;
use App\Support\ListingPreferences\Taste\TasteListingFacts;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagState;

/**
 * The governed structured characteristics of the homes a customer chose — in
 * batch, and ONLY the characteristics Taste DNA may learn.
 *
 * AN ALLOW-LIST OF COLUMNS AND META KEYS. It selects bedrooms, bathrooms,
 * living area, lot size, property sub-type and resolved PRESENT Smart Tags,
 * and nothing else. No address, city, ZIP, county, subdivision, school,
 * coordinate or remarks column is selected, so none can reach the learner
 * however the deriver is later changed. TasteDnaArchitectureGuardTest scans
 * this file for those names.
 *
 * THE SAME PUBLICATION RULES AS THE MANAGEMENT PAGE. A Bridge row the feed
 * refuses to publish (`listingDisplayable()` false) and a native listing that is
 * archived or a draft contribute NO facts — exactly the rows
 * ListingPreferenceListingHydrator shows as unavailable. The customer's own
 * stated reasons about those homes still count; the platform just stops
 * describing the property.
 *
 * CURRENT FACTS, NOT A SNAPSHOT. What is read is what the platform publishes
 * for the listing NOW, not what it said when the customer chose. That is the
 * Phase 4 rule (TasteDnaDeriver, "Historical reasons, current facts"): an
 * observed characteristic is a correlation against current canonical facts,
 * and an unavailable listing or blank fact yields no entry rather than a
 * remembered or guessed one. The customer's own reasons are historical and are
 * not read here at all.
 *
 * QUERY SHAPE: one query per listing type present (at most three, plus one
 * eager-loaded meta query per native type) and one Smart Tag query. A customer
 * with more homes costs no more queries.
 */
class TasteListingFactsReader
{
    private const SQFT_PER_ACRE = 43_560.0;

    /** The only Bridge columns read — the allow-list, in one place. */
    private const BRIDGE_COLUMNS = ['id', 'raw_json', 'bedrooms_total', 'bathrooms_total_integer', 'living_area', 'lot_size_sqft', 'property_sub_type'];

    /**
     * @param  list<string> $refKeys "<listing_type>:<listing_id>"
     * @return array<string, TasteListingFacts> keyed by the same ref key; an
     *                                          unavailable listing has no entry
     */
    public function for(array $refKeys): array
    {
        /** @var array<string, array<int, int>> $idsByType */
        $idsByType = [];

        foreach ($refKeys as $refKey) {
            [$type, $id] = array_pad(explode(':', (string) $refKey, 2), 2, '');

            if (SmartTagListingType::tryFrom($type) !== null && ctype_digit($id) && (int) $id > 0) {
                $idsByType[$type][(int) $id] = (int) $id;
            }
        }

        if ($idsByType === []) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $raw ref key => facts fields */
        $raw = [];

        foreach ($idsByType as $type => $ids) {
            $raw += match (SmartTagListingType::from($type)) {
                SmartTagListingType::Bridge        => $this->bridge(array_values($ids)),
                SmartTagListingType::SellerAgent   => $this->native(SellerAgentAuction::class, SmartTagListingType::SellerAgent, array_values($ids)),
                SmartTagListingType::LandlordAgent => $this->native(LandlordAgentAuction::class, SmartTagListingType::LandlordAgent, array_values($ids)),
            };
        }

        return $this->assemble($raw);
    }

    /**
     * @param  array<string, array<string, mixed>> $raw ref key => facts fields
     * @return array<string, TasteListingFacts>
     */
    private function assemble(array $raw): array
    {
        $tags = $this->presentTags(array_keys($raw));

        $out = [];

        foreach ($raw as $refKey => $fields) {
            $out[$refKey] = new TasteListingFacts(
                tagKeys:    $tags[$refKey] ?? [],
                bedrooms:   $fields['bedrooms'],
                bathrooms:  $fields['bathrooms'],
                livingArea: $fields['living_area'],
                lotAcres:   $fields['lot_acres'],
                subtypes:   $fields['subtypes'],
            );
        }

        ksort($out, SORT_STRING);

        return $out;
    }

    /**
     * Facts for Bridge rows the caller ALREADY HOLDS — the Phase 5 results
     * page, whose candidates were loaded by the matcher moments earlier.
     *
     * Same publication rule, same columns, same Smart Tag read as `for()`; the
     * only difference is that the rows are not fetched again. The cost is ONE
     * query (the resolved tags) however many candidates there are, which is
     * what keeps reranking free of per-listing queries.
     *
     * @param  iterable<BridgeProperty>        $rows
     * @return array<string, TasteListingFacts> keyed "bridge:<id>"; an unpublishable row has no entry
     */
    public function forBridgeRows(iterable $rows): array
    {
        return $this->assemble($this->bridgeFields($rows));
    }

    /**
     * @param  list<int> $ids
     * @return array<string, array<string, mixed>>
     */
    private function bridge(array $ids): array
    {
        return $this->bridgeFields(
            BridgeProperty::query()
                ->whereIn('id', $ids)
                ->get(self::BRIDGE_COLUMNS)
        );
    }

    /**
     * @param  iterable<BridgeProperty> $rows
     * @return array<string, array<string, mixed>>
     */
    private function bridgeFields(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            // The management page's rule, not a second one: decoded as the
            // hydrator decodes it, and a listing the feed refuses to publish
            // contributes nothing.
            $record = is_string($row->raw_json) ? (json_decode($row->raw_json, true) ?: []) : [];

            if (! MlsDisplayPermissions::fromRecord(is_array($record) ? $record : [])->listingDisplayable()) {
                continue;
            }

            $lotSqft = $this->number($row->lot_size_sqft);

            $out[SmartTagListingType::Bridge->value . ':' . (int) $row->id] = [
                'bedrooms'    => $this->number($row->bedrooms_total),
                'bathrooms'   => $this->number($row->bathrooms_total_integer),
                'living_area' => $this->number($row->living_area),
                'lot_acres'   => $lotSqft === null ? null : $lotSqft / self::SQFT_PER_ACRE,
                'subtypes'    => is_string($row->property_sub_type) ? [$row->property_sub_type] : [],
            ];
        }

        return $out;
    }

    /**
     * Seller and Landlord Offer Listings share the meta keys MlsFieldMap writes:
     * `bedrooms`, `bathrooms`, `minimum_heated_square` (the heated-area field;
     * `square_feet` is the older key the management card also reads),
     * `total_acreage` and the `property_items` multi-select. Its "Other" free
     * text (`other_property_items`) is never read.
     *
     * @param  class-string<SellerAgentAuction|LandlordAgentAuction> $model
     * @param  list<int>                                             $ids
     * @return array<string, array<string, mixed>>
     */
    private function native(string $model, SmartTagListingType $type, array $ids): array
    {
        $out  = [];
        $keys = ['bedrooms', 'bathrooms', 'minimum_heated_square', 'square_feet', 'total_acreage', 'property_items'];

        $rows = $model::query()
            ->with(['meta' => static fn ($q) => $q->whereIn('meta_key', $keys)])
            ->whereIn('id', $ids)
            ->get();

        foreach ($rows as $row) {
            if ((bool) ($row->is_archived ?? false) || (bool) ($row->is_draft ?? false)) {
                continue;
            }

            $meta = [];

            foreach ($row->meta ?? [] as $m) {
                $meta[(string) $m->meta_key] = $m->meta_value;
            }

            $out[$type->value . ':' . (int) $row->id] = [
                'bedrooms'    => $this->number($meta['bedrooms'] ?? null),
                'bathrooms'   => $this->number($meta['bathrooms'] ?? null),
                'living_area' => $this->number($meta['minimum_heated_square'] ?? null) ?? $this->number($meta['square_feet'] ?? null),
                'lot_acres'   => $this->number($meta['total_acreage'] ?? null),
                'subtypes'    => $this->list($meta['property_items'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * The resolved PRESENT Smart Tags for the given listings, in one query.
     *
     * Only `smart_tag_assignments` — the one canonical answer per listing × tag
     * that the Smart Tag resolver already produced. Evidence rows, prose and
     * descriptions are never read. Whether a tag may be LEARNED is the
     * deriver's question (`isSeekerSelectable()`), asked of the taxonomy.
     *
     * @param  list<string> $refKeys
     * @return array<string, list<string>>
     */
    private function presentTags(array $refKeys): array
    {
        if ($refKeys === []) {
            return [];
        }

        /** @var array<string, list<int>> $byType */
        $byType = [];

        foreach ($refKeys as $refKey) {
            [$type, $id] = explode(':', $refKey, 2);
            $byType[$type][] = (int) $id;
        }

        $rows = SmartTagAssignment::query()
            ->where('state', SmartTagState::Present->value)
            ->where(function ($q) use ($byType): void {
                foreach ($byType as $type => $ids) {
                    $q->orWhere(static fn ($inner) => $inner->where('listing_type', $type)->whereIn('listing_id', $ids));
                }
            })
            ->orderBy('tag_key')
            ->get(['listing_type', 'listing_id', 'tag_key']);

        $out = [];

        foreach ($rows as $row) {
            $out[$row->listing_type . ':' . (int) $row->listing_id][] = (string) $row->tag_key;
        }

        return $out;
    }

    private function number(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (is_string($decoded)) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded)) {
            return is_string($value) && trim($value) !== '' && json_decode($value) === null ? [$value] : [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}

<?php

namespace App\Services\Canonical\Adapters;

use App\Services\Canonical\CanonicalListing;
use App\Services\Pets\PetFeeNormalizer;

/**
 * ByoListingAdapter — maps a first-party Bid Your Offer role listing (with its
 * EAV meta) onto the canonical vocabulary (§F1).
 *
 * GOVERNANCE:
 *   - Read-only. Never writes to any auction/meta table.
 *   - No AI, no external API calls, deterministic.
 *   - Reads EAV via the role model's info() getter (returns the raw meta_value
 *     string, or boolean false when the key is absent).
 *
 * Scope (Wave 1 / Phase 2 slice): only the Pet-Friendliness canonical fields
 * are mapped. The MAP is intentionally structured so additional fields/roles
 * are added by extending the arrays, not by changing logic — this is the
 * "formalize, don't duplicate" approach: the four {Role}FieldMap registries
 * remain the source of truth for human-label → meta_key; this adapter adds the
 * meta_key → canonical-field layer on top for the fields a score consumes.
 */
class ByoListingAdapter
{
    /** Self-reported first-party data: reliable but not certain. */
    public const SOURCE_RELIABILITY = 90;

    /**
     * listing_type => [ canonical_key => [meta_key, type] ].
     * type ∈ bool | number | array | string.
     *
     * Property side (policy) for landlord/seller; demand side (profile) for
     * tenant/buyer. Both sides use overlapping BYO meta keys but with different
     * semantics, which is exactly why the canonical layer exists.
     */
    /** #2 Part B — the legacy amount keys the canonical pet fee supersedes. */
    private const LEGACY_PET_FEE_KEYS = [
        'pet.policy.deposit_amount',
        'pet.policy.monthly_fee',
        'pet.policy.rent',
        'pet.policy.fee',
    ];

    /** #2 Part B — canonical keys derived (not mapped 1:1 from a meta key). */
    private const DERIVED_PET_FEE_KEYS = [
        'pet.policy.has_fee',
        'pet.policy.fee_other_amount',
        'pet.policy.fee_other_text',
    ];

    private const MAP = [
        // ── Property side (landlord/seller describe a real property) ──────────
        'landlord_agent' => self::PROPERTY_FIELDS + [
            // pet POLICY (Phase 2)
            'pet.policy.pets_allowed'            => ['pets', 'bool'],
            'pet.policy.max_weight_lbs'          => ['pet_max_weight_lbs', 'number'],
            'pet.policy.species_allowed'         => ['pet_species_allowed', 'array'],
            'pet.policy.has_breed_restrictions'  => ['has_breed_restrictions', 'bool'],
            'pet.policy.deposit_amount'          => ['pet_deposit_amount', 'number'],
            'pet.policy.monthly_fee'             => ['pet_monthly_fee', 'number'],
            'pet.policy.rent'                    => ['pet_rent', 'number'],
            'pet.policy.fee'                     => ['pet_fee', 'number'],
            // #2 Part B — canonical pet fee. Mapped raw here; applyPetFeePrecedence()
            // then rewrites the four legacy amount keys above from it so the existing
            // pet.policy.* contract keeps working for every downstream consumer.
            'pet.policy.fee_type'                => ['pet_fee_type', 'string'],
            'pet.policy.fee_amount'              => ['pet_fee_amount', 'number'],
            'pet.policy.fee_other'               => ['pet_fee_other', 'string'],
        ],
        'seller_agent' => self::PROPERTY_FIELDS + [
            'pet.policy.pets_allowed'            => ['pets', 'bool'],
        ],

        // ── Demand side (buyer/tenant describe criteria/preferences) ──────────
        'tenant_agent' => self::DEMAND_FIELDS + [
            // pet PROFILE (Phase 2)
            'pet.profile.has_pets'   => ['pets', 'bool'],
            'pet.profile.count'      => ['number_of_pets', 'number'],
            'pet.profile.weight_lbs' => ['weight_of_pets', 'number'],
            'pet.profile.species'    => ['type_of_pets', 'array'],
            'pet.profile.breed'      => ['breed_of_pets', 'string'],
        ],
        'buyer_agent' => self::DEMAND_FIELDS + [
            'pet.profile.has_pets'   => ['pets', 'bool'],
            'pet.profile.count'      => ['number_of_pets', 'number'],
            'pet.profile.weight_lbs' => ['weight_of_pets', 'number'],
            'pet.profile.species'    => ['type_of_pets', 'array'],
            'pet.profile.breed'      => ['breed_of_pets', 'string'],
        ],
    ];

    /**
     * Shared, score-agnostic PROPERTY canonical fields (Phase 3). Mapped once,
     * consumed by many scores (Lock-and-Leave, Waterfront-Lifestyle, …) — the
     * point of the canonical layer: map a meta key once, reuse everywhere.
     */
    private const PROPERTY_FIELDS = [
        'property.structure_type'      => ['property_items', 'array'],
        'property.hoa_fee_includes'    => ['association_fee_includes', 'array'],
        'property.community_amenities' => ['association_amenities', 'array'],
        'property.lot_acreage'         => ['total_acreage', 'number'],
        'property.condition'           => ['condition_prop', 'string'],
        'property.waterfront'          => ['waterfront', 'bool'],
        'property.water_access'        => ['water_access', 'array'],
        'property.water_view'          => ['water_view', 'array'],
        'property.water_frontage_feet' => ['waterfront_feet', 'number'],
        'property.view_preference'     => ['view_preference', 'array'],
    ];

    /** Shared, score-agnostic DEMAND canonical fields (Phase 3). */
    private const DEMAND_FIELDS = [
        'demand.current_status'   => ['current_status', 'string'],
        'demand.purchase_purpose' => ['purchase_purpose', 'string'],
        'demand.age_targeted'     => ['leasing_55_plus', 'bool'],
        'demand.view_preference'  => ['view_preference', 'array'],
    ];

    /**
     * Build a CanonicalListing from a role auction model.
     *
     * @param object $model a *AgentAuction model exposing info($key) + updated_at
     */
    public function fromModel(object $model, string $listingType, int $listingId): CanonicalListing
    {
        $fields = [];
        $meta   = [];

        $freshness = isset($model->updated_at) && $model->updated_at
            ? (string) $model->updated_at->toIso8601String()
            : null;

        foreach (self::MAP[$listingType] ?? [] as $canonicalKey => [$metaKey, $type]) {
            $raw = $model->info($metaKey);

            // info() returns boolean false when the meta key is absent.
            if ($raw === false) {
                continue;
            }

            $value = $this->normalize($raw, $type);
            if ($value === null) {
                continue; // present but empty/unparseable → treat as absent
            }

            $fields[$canonicalKey] = $value;
            $meta[$canonicalKey] = [
                'source'             => 'byo:' . $listingType,
                'source_field'       => $metaKey,
                'source_reliability' => self::SOURCE_RELIABILITY,
                'freshness'          => $freshness,
            ];
        }

        $this->applyPetFeePrecedence($fields, $meta, $listingType, $freshness);

        return new CanonicalListing($listingType, $listingId, $fields, $meta);
    }

    /**
     * #2 Part B — canonical pet fee wins over the retired legacy amount keys.
     *
     * A record that has been saved through the redesigned Landlord form carries
     * pet_fee_type. Its legacy rows are still in storage (deliberately never deleted), so
     * without this the stale legacy amounts would keep populating pet.policy.* and the
     * new value would be ignored. When pet_fee_type is present we therefore REPLACE the
     * four legacy amount keys with values derived from it, preserving the existing
     * contract — and crucially the recurring-vs-one-time distinction — for every
     * downstream consumer (PetFriendlinessScoreService above all).
     *
     * When pet_fee_type is absent the legacy mapping is left exactly as it was, so legacy
     * records score and display precisely as they did before this change.
     *
     * @param array<string,mixed> $fields
     * @param array<string,array> $meta
     */
    private function applyPetFeePrecedence(array &$fields, array &$meta, string $listingType, ?string $freshness): void
    {
        $type = $fields['pet.policy.fee_type'] ?? null;
        if ($listingType !== 'landlord_agent' || !is_string($type) || $type === '') {
            return; // legacy path — untouched
        }

        $amount    = $fields['pet.policy.fee_amount'] ?? null;
        $otherText = $fields['pet.policy.fee_other'] ?? null;

        // Stale legacy rows must not outrank the canonical answer.
        foreach (self::LEGACY_PET_FEE_KEYS as $legacyKey) {
            unset($fields[$legacyKey], $meta[$legacyKey]);
        }

        $stamp = function (string $key, $value) use (&$fields, &$meta, $listingType, $freshness): void {
            $fields[$key] = $value;
            $meta[$key]   = [
                'source'             => 'byo:' . $listingType,
                'source_field'       => PetFeeNormalizer::KEY_TYPE,
                'source_reliability' => self::SOURCE_RELIABILITY,
                'freshness'          => $freshness,
            ];
        };

        $hasAmount = is_numeric($amount) && (float) $amount > 0;

        switch ($type) {
            case PetFeeNormalizer::TYPE_ONE_TIME_REFUNDABLE:
                if ($hasAmount) {
                    $stamp('pet.policy.deposit_amount', (float) $amount); // one-time
                }
                break;

            case PetFeeNormalizer::TYPE_NON_REFUNDABLE:
                if ($hasAmount) {
                    $stamp('pet.policy.fee', (float) $amount);            // one-time
                }
                break;

            case PetFeeNormalizer::TYPE_MONTHLY:
                if ($hasAmount) {
                    // Monthly maps to monthly_fee ONLY. It is deliberately not duplicated
                    // into pet.policy.rent, which would double-count the same charge.
                    $stamp('pet.policy.monthly_fee', (float) $amount);    // recurring
                }
                break;

            case PetFeeNormalizer::TYPE_NONE:
                // No amount keys populated at all, and has_fee stays false.
                break;

            case PetFeeNormalizer::TYPE_OTHER:
                // The charge is real but its recurring/one-time nature is NOT knowable
                // from the stored text. We refuse to guess, so it is deliberately NOT
                // placed into a legacy bucket — it is preserved in dedicated keys and
                // flagged via has_fee so fee DETECTION still works.
                if ($hasAmount) {
                    $stamp('pet.policy.fee_other_amount', (float) $amount);
                }
                if (is_string($otherText) && $otherText !== '') {
                    $stamp('pet.policy.fee_other_text', $otherText);
                }
                break;
        }

        $stamp('pet.policy.has_fee', $type !== PetFeeNormalizer::TYPE_NONE
            && ($hasAmount || (is_string($otherText) && $otherText !== '')));
    }

    /** @return list<string> canonical keys this adapter can populate for a type. */
    public function canonicalKeysFor(string $listingType): array
    {
        $keys = array_keys(self::MAP[$listingType] ?? []);

        if ($listingType === 'landlord_agent') {
            $keys = array_merge($keys, self::DERIVED_PET_FEE_KEYS);
        }

        return $keys;
    }

    /** @param mixed $raw @return mixed normalized value or null */
    private function normalize($raw, string $type)
    {
        switch ($type) {
            case 'bool':
                return $this->toBool($raw);
            case 'number':
                return $this->toNumber($raw);
            case 'array':
                return $this->toArray($raw);
            case 'string':
            default:
                $s = trim((string) $raw);
                return $s === '' ? null : $s;
        }
    }

    /** @param mixed $raw @return bool|null */
    private function toBool($raw): ?bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        $s = strtolower(trim((string) $raw));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['1', 'true', 'yes', 'y', 'on', 'allowed', 'allow', 'permitted'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'false', 'no', 'n', 'off', 'not allowed', 'not_allowed', 'none', 'prohibited'], true)) {
            return false;
        }
        if (is_numeric($s)) {
            return ((float) $s) > 0;
        }
        return null; // unknown token → absent rather than a wrong guess
    }

    /** @param mixed $raw @return float|null */
    private function toNumber($raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = str_replace([',', '$'], '', trim((string) $raw));
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }
        return (float) $s;
    }

    /** @param mixed $raw @return array<int,string>|null */
    private function toArray($raw): ?array
    {
        if (is_array($raw)) {
            $out = array_values(array_filter(array_map('strval', $raw), fn ($v) => trim($v) !== ''));
            return $out === [] ? null : $out;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $decoded = json_decode($s, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $out = array_values(array_filter(array_map('strval', $decoded), fn ($v) => trim($v) !== ''));
            return $out === [] ? null : $out;
        }
        // Comma-separated fallback.
        $parts = array_values(array_filter(array_map('trim', explode(',', $s)), fn ($v) => $v !== ''));
        return $parts === [] ? null : $parts;
    }
}

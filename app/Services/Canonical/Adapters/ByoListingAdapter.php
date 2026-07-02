<?php

namespace App\Services\Canonical\Adapters;

use App\Services\Canonical\CanonicalListing;

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
    private const MAP = [
        // ── Property side: pet POLICY ────────────────────────────────────────
        'landlord_agent' => [
            'pet.policy.pets_allowed'            => ['pets', 'bool'],
            'pet.policy.max_weight_lbs'          => ['pet_max_weight_lbs', 'number'],
            'pet.policy.species_allowed'         => ['pet_species_allowed', 'array'],
            'pet.policy.has_breed_restrictions'  => ['has_breed_restrictions', 'bool'],
            'pet.policy.deposit_amount'          => ['pet_deposit_amount', 'number'],
            'pet.policy.monthly_fee'             => ['pet_monthly_fee', 'number'],
            'pet.policy.rent'                    => ['pet_rent', 'number'],
            'pet.policy.fee'                     => ['pet_fee', 'number'],
        ],
        'seller_agent' => [
            'pet.policy.pets_allowed'            => ['pets', 'bool'],
        ],

        // ── Demand side: pet PROFILE ─────────────────────────────────────────
        'tenant_agent' => [
            'pet.profile.has_pets'   => ['pets', 'bool'],
            'pet.profile.count'      => ['number_of_pets', 'number'],
            'pet.profile.weight_lbs' => ['weight_of_pets', 'number'],
            'pet.profile.species'    => ['type_of_pets', 'array'],
            'pet.profile.breed'      => ['breed_of_pets', 'string'],
        ],
        'buyer_agent' => [
            'pet.profile.has_pets'   => ['pets', 'bool'],
            'pet.profile.count'      => ['number_of_pets', 'number'],
            'pet.profile.weight_lbs' => ['weight_of_pets', 'number'],
            'pet.profile.species'    => ['type_of_pets', 'array'],
            'pet.profile.breed'      => ['breed_of_pets', 'string'],
        ],
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

        return new CanonicalListing($listingType, $listingId, $fields, $meta);
    }

    /** @return list<string> canonical keys this adapter can populate for a type. */
    public function canonicalKeysFor(string $listingType): array
    {
        return array_keys(self::MAP[$listingType] ?? []);
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

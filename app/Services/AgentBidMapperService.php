<?php

namespace App\Services;

use App\Models\AgentDefaultProfile;

/**
 * AgentBidMapperService
 *
 * Centralises the mapping from an AgentDefaultProfile's profile_data array
 * to the normalised bid-field array used by all four bid form Livewire
 * components (Buyer, Seller, Landlord, Tenant) and by the Phase-2
 * auto-bid creation flow (Hire Me).
 *
 * Rules
 * ─────
 * • Scalar text fields always present; default to empty string so callers
 *   can assign without extra null-checks.
 * • Array fields (reviews_links, website_link, social_media, promoMaterials,
 *   services, other_services) always present; default to empty array.
 * • Credential fields (first_name … nar_id) included in the map.  Callers
 *   apply them with an !empty() guard so they only override when the profile
 *   actually contains a value — preserving the existing component behaviour.
 * • Services contract: 'services' and 'other_services' are required keys.
 *   Buyer, Seller, Tenant, and Landlord bid form mount() methods all depend
 *   on these keys being present.  Each component applies its own catalog
 *   filter (filterServicesToCurrentCatalog) before assigning to $this->services.
 * • No DB writes, no side-effects.  Pure transformation.
 */
class AgentBidMapperService
{
    /**
     * Map a raw profile_data array (from AgentDefaultProfile::$profile_data)
     * to a normalised bid-field array.
     *
     * @param  array  $profileData  The decoded profile_data array.
     * @return array<string, mixed> Normalised bid fields ready for persistence
     *                              or Livewire component property assignment.
     */
    public static function mapFromProfile(array $profileData): array
    {
        return [
            // ── Agent overview ──────────────────────────────────────────────
            'bio'                       => $profileData['bio']                ?? '',
            'why_hire_you'              => $profileData['why_hire_you']        ?? '',
            'what_sets_you_apart'       => $profileData['what_sets_you_apart'] ?? '',
            'marketing_plan'            => $profileData['marketing_plan']      ?? '',
            'year_licensed'             => $profileData['year_licensed']       ?? '',
            'additional_details'        => $profileData['additional_details']  ?? '',

            // ── Agent credentials (applied only when non-empty by callers) ──
            'first_name'                => $profileData['first_name']          ?? '',
            'last_name'                 => $profileData['last_name']           ?? '',
            'phone'                     => $profileData['phone']               ?? '',
            'email'                     => $profileData['email']               ?? '',
            'brokerage'                 => $profileData['brokerage']           ?? '',
            'license_no'                => $profileData['license_no']          ?? '',
            'nar_id'                    => $profileData['nar_id']              ?? '',

            // ── Media / link fields ─────────────────────────────────────────
            'presentation_link'         => $profileData['presentation_link']          ?? '',
            'business_card_link'        => $profileData['business_card_link']         ?? '',
            'business_card_stored_path' => $profileData['business_card_stored_path']  ?? '',

            // ── Services (agent's standard offering from the preset editor) ─
            'services'                  => $profileData['services']       ?? [],
            'other_services'            => $profileData['other_services'] ?? [],

            // ── Array fields ────────────────────────────────────────────────
            'reviews_links'             => $profileData['reviews_links']  ?? [],
            'website_link'              => $profileData['website_link']   ?? [],
            'social_media'              => $profileData['social_media']   ?? [],
            'promoMaterials'            => $profileData['promoMaterials'] ?? [],
        ];
    }

    /**
     * Look up the best matching AgentDefaultProfile for the given user/role/
     * property-type combination (with role-default fallback), then return the
     * normalised bid-field array, or null when no profile exists.
     *
     * This is the primary entry-point for both:
     *   1. Livewire bid-form mount() — pre-fill new bid forms.
     *   2. Phase-2 Hire-Me auto-bid creation — seed bid meta from profile.
     *
     * @param  int     $userId        Authenticated agent user ID.
     * @param  string  $role          One of: 'buyer' | 'seller' | 'landlord' | 'tenant'.
     * @param  string  $propertyType  Short property-type string (e.g. 'residential').
     * @return array<string, mixed>|null  Mapped fields, or null if no profile found.
     */
    public static function findAndMap(int $userId, string $role, string $propertyType): ?array
    {
        $profile = AgentDefaultProfile::findForAgentWithFallback($userId, $role, $propertyType);

        if (!$profile) {
            return null;
        }

        return static::mapFromProfile($profile->profile_data ?? []);
    }
}

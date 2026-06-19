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
            'presentation_upload_path'  => $profileData['presentation_upload_path']   ?? '',
            'business_card_link'        => $profileData['business_card_link']         ?? '',
            'business_card_stored_path' => $profileData['business_card_stored_path']  ?? '',
            'business_card_upload_path' => $profileData['business_card_upload_path']  ?? '',

            // ── Services (agent's standard offering from the preset editor) ─
            'services'                  => $profileData['services']       ?? [],
            'other_services'            => $profileData['other_services'] ?? [],

            // ── Testimonials / client reviews (text fields) ─────────────────
            'review_1'                  => $profileData['review_1'] ?? '',
            'review_2'                  => $profileData['review_2'] ?? '',
            'review_3'                  => $profileData['review_3'] ?? '',

            // ── Array fields ────────────────────────────────────────────────
            'reviews_links'             => $profileData['reviews_links']  ?? [],
            'website_link'              => $profileData['website_link']   ?? [],
            'social_media'              => $profileData['social_media']   ?? [],
            'promoMaterials'            => $profileData['promoMaterials'] ?? [],

            // ── Broker Compensation & Agency Agreement Terms (all roles) ────

            // Shared
            'protection_period'                  => $profileData['protection_period']                  ?? '',
            'early_termination_fee_option'        => $profileData['early_termination_fee_option']        ?? '',
            'early_termination_fee_amount'        => $profileData['early_termination_fee_amount']        ?? '',
            'agency_agreement_timeframe'          => $profileData['agency_agreement_timeframe']          ?? '',
            'agency_agreement_custom'             => $profileData['agency_agreement_custom']             ?? '',
            'interested_lease_option_agreement'   => $profileData['interested_lease_option_agreement']   ?? '',
            'lease_type'                          => $profileData['lease_type']                          ?? '',
            'lease_value'                         => $profileData['lease_value']                         ?? '',
            'purchase_type'                       => $profileData['purchase_type']                       ?? '',
            'purchase_value'                      => $profileData['purchase_value']                      ?? '',

            // Buyer / Tenant / Landlord / Seller shared compensation fields
            'commission_structure'                => $profileData['commission_structure']                ?? '',
            'purchase_fee_type'                   => $profileData['purchase_fee_type']                   ?? '',
            'purchase_fee_flat'                   => $profileData['purchase_fee_flat']                   ?? '',
            'purchase_fee_percentage'             => $profileData['purchase_fee_percentage']             ?? '',
            'purchase_fee_percentage_combo'       => $profileData['purchase_fee_percentage_combo']       ?? '',
            'purchase_fee_flat_combo'             => $profileData['purchase_fee_flat_combo']             ?? '',
            'purchase_fee_other'                  => $profileData['purchase_fee_other']                  ?? '',
            'retainer_fee_option'                 => $profileData['retainer_fee_option']                 ?? '',
            'retainer_fee_amount'                 => $profileData['retainer_fee_amount']                 ?? '',
            'retainer_fee_application'            => $profileData['retainer_fee_application']            ?? '',

            // Buyer / Tenant: lease fee
            'interested_lease_option'             => $profileData['interested_lease_option']             ?? '',
            'lease_fee_type'                      => $profileData['lease_fee_type']                      ?? '',
            'lease_fee_flat'                      => $profileData['lease_fee_flat']                      ?? '',
            'lease_fee_percentage'                => $profileData['lease_fee_percentage']                ?? '',
            'lease_fee_percentage_monthly_rent'   => $profileData['lease_fee_percentage_monthly_rent']   ?? '',
            'lease_fee_percentage_monthly_number' => $profileData['lease_fee_percentage_monthly_number'] ?? '',
            'lease_fee_flat_combo'                => $profileData['lease_fee_flat_combo']                ?? '',
            'lease_fee_percentage_combo'          => $profileData['lease_fee_percentage_combo']          ?? '',
            'lease_fee_percentage_net'            => $profileData['lease_fee_percentage_net']            ?? '',
            'lease_fee_flat_combo_net'            => $profileData['lease_fee_flat_combo_net']            ?? '',
            'lease_fee_percentage_combo_net'      => $profileData['lease_fee_percentage_combo_net']      ?? '',
            'lease_fee_other'                     => $profileData['lease_fee_other']                     ?? '',

            // Seller-specific
            'nominal'                                    => $profileData['nominal']                                    ?? '',
            'commission_structure_type'                  => $profileData['commission_structure_type']                  ?? '',
            'commission_structure_type_fee_flat'         => $profileData['commission_structure_type_fee_flat']         ?? '',
            'commission_structure_type_fee_flat_combo'   => $profileData['commission_structure_type_fee_flat_combo']   ?? '',
            'commission_structure_type_fee_percentage'   => $profileData['commission_structure_type_fee_percentage']   ?? '',
            'commission_structure_type_fee_percentage_combo' => $profileData['commission_structure_type_fee_percentage_combo'] ?? '',
            'commission_structure_type_fee_other'        => $profileData['commission_structure_type_fee_other']        ?? '',
            'interested_purchase_fee_type'               => $profileData['interested_purchase_fee_type']               ?? '',
            'seller_leasing_fee_type'                    => $profileData['seller_leasing_fee_type']                    ?? '',
            'seller_leasing_gross'                       => $profileData['seller_leasing_gross']                       ?? '',
            'seller_leasing_gross_rental'                => $profileData['seller_leasing_gross_rental']                ?? '',
            'seller_leasing_gross_month_rent'            => $profileData['seller_leasing_gross_month_rent']            ?? '',
            'sales_tax_option_gross'                     => $profileData['sales_tax_option_gross']                     ?? '',

            // Landlord: residential lease fee sub-fields
            'purchase_fee_rental_period'          => $profileData['purchase_fee_rental_period']          ?? '',

            // Landlord: commercial lease fee sub-fields
            'purchase_fee_net_aggregate'          => $profileData['purchase_fee_net_aggregate']          ?? '',
            'purchase_fee_gross_rent'             => $profileData['purchase_fee_gross_rent']             ?? '',
            'purchase_fee_monthly_percentage'     => $profileData['purchase_fee_monthly_percentage']     ?? '',
            'purchase_fee_months'                 => $profileData['purchase_fee_months']                 ?? '',
            'sales_tax_option_monthly'            => $profileData['sales_tax_option_monthly']            ?? '',
            'purchase_fee_flat_commercial'        => $profileData['purchase_fee_flat_commercial']        ?? '',
            'sales_tax_option_flat'               => $profileData['sales_tax_option_flat']               ?? '',
            'purchase_fee_other_commercial'       => $profileData['purchase_fee_other_commercial']       ?? '',

            // Landlord: tenant broker commission (residential)
            'tenant_broker_commission_structure'  => $profileData['tenant_broker_commission_structure']  ?? '',
            'tenant_broker_fee_structure'         => $profileData['tenant_broker_fee_structure']         ?? '',
            'tenant_broker_percentage'            => $profileData['tenant_broker_percentage']            ?? '',
            'tenant_broker_gross_lease'           => $profileData['tenant_broker_gross_lease']           ?? '',
            'tenant_broker_first_month_rent'      => $profileData['tenant_broker_first_month_rent']      ?? '',
            'tenant_broker_flat_fee'              => $profileData['tenant_broker_flat_fee']              ?? '',
            'tenant_broker_other'                 => $profileData['tenant_broker_other']                 ?? '',

            // Landlord + Tenant: broker fee timing
            'broker_fee_timing'                   => $profileData['broker_fee_timing']                   ?? '',
            'broker_fee_days_from_rent'           => $profileData['broker_fee_days_from_rent']           ?? '',
            'broker_fee_days_after_lease'         => $profileData['broker_fee_days_after_lease']         ?? '',
            'broker_fee_days_after_rent'          => $profileData['broker_fee_days_after_rent']          ?? '',
            'broker_fee_timing_other'             => $profileData['broker_fee_timing_other']             ?? '',

            // Landlord: split payment due
            'split_payment_due'                   => $profileData['split_payment_due']                   ?? '',
            'split_payment_due_other'             => $profileData['split_payment_due_other']             ?? '',
            'broker_fee_days_after_due_event'     => $profileData['broker_fee_days_after_due_event']     ?? '',

            // Landlord: lease renewal/extension fee
            'renewal_fee_type'                    => $profileData['renewal_fee_type']                    ?? '',
            'renewal_fee_percentage'              => $profileData['renewal_fee_percentage']              ?? '',
            'renewal_fee_lease_value'             => $profileData['renewal_fee_lease_value']             ?? '',
            'renewal_fee_first_month'             => $profileData['renewal_fee_first_month']             ?? '',
            'renewal_fee_flat_fee'                => $profileData['renewal_fee_flat_fee'] ?? $profileData['renewal_fee_flat_free'] ?? '',
            'renewal_fee_custom'                  => $profileData['renewal_fee_custom']                  ?? '',
            'renewal_fee_sales_tax_lease_value'   => $profileData['renewal_fee_sales_tax_lease_value']   ?? '',
            'renewal_fee_no_of_months'            => $profileData['renewal_fee_no_of_months']            ?? '',
            'renewal_fee_sales_tax_first_month'   => $profileData['renewal_fee_sales_tax_first_month']   ?? '',
            'renewal_fee_sales_tax_flat_fee'      => $profileData['renewal_fee_sales_tax_flat_fee']      ?? '',

            // Landlord: expansion commission (commercial)
            'expansion_commission_percentage'     => $profileData['expansion_commission_percentage']     ?? '',

            // Landlord: property management
            'interested_in_property_management'                      => $profileData['interested_in_property_management']                      ?? '',
            'interested_in_property_management_fee'                  => $profileData['interested_in_property_management_fee']                  ?? '',
            'interested_in_property_management_fee_gross_lease'      => $profileData['interested_in_property_management_fee_gross_lease']      ?? '',
            'interested_in_property_management_fee_rental_periord'   => $profileData['interested_in_property_management_fee_rental_periord']   ?? '',
            'interested_in_property_management_fee_flate_free'       => $profileData['interested_in_property_management_fee_flate_free']       ?? '',
            'interested_in_property_management_fee_other'            => $profileData['interested_in_property_management_fee_other']            ?? '',

            // Landlord: interested in selling
            'interested_in_selling'               => $profileData['interested_in_selling']               ?? '',
            'interested_in_selling_type'          => $profileData['interested_in_selling_type']          ?? '',
            'landlord_broker_purchase_price'      => $profileData['landlord_broker_purchase_price']      ?? '',
            'landlord_broker_percentage_price'    => $profileData['landlord_broker_percentage_price']    ?? '',
            'landlord_broker_dollar_price'        => $profileData['landlord_broker_dollar_price']        ?? '',
            'landlord_broker_flate_fee'           => $profileData['landlord_broker_flate_fee']           ?? '',
            'landlord_broker_other'               => $profileData['landlord_broker_other']               ?? '',

            // Landlord: commercial lease fee additional sub-field
            'purchase_fee_purchase_price'         => $profileData['purchase_fee_purchase_price']         ?? '',

            // All roles: brokerage relationship, additional terms, retained deposits
            'brokerage_relationship'              => $profileData['brokerage_relationship']              ?? '',
            'additional_details_broker'           => $profileData['additional_details_broker']           ?? '',
            'retained_deposits'                   => $profileData['retained_deposits']                   ?? '',

            // All roles: availability (used by auto-bid stamp and public profile display)
            'avg_response_time'                   => $profileData['avg_response_time']                   ?? '',
            'availability_status'                 => $profileData['availability_status']                 ?? '',
            'evenings_available'                  => $profileData['evenings_available']                  ?? '',
            'weekends_available'                  => $profileData['weekends_available']                  ?? '',

            // All roles: experience & track record
            'years_experience'                    => $profileData['years_experience']                    ?? '',
            'transactions_last_12_months'         => $profileData['transactions_last_12_months']         ?? '',
            'is_full_time'                        => $profileData['is_full_time']                        ?? '',
            'primary_areas_served'                => $profileData['primary_areas_served']                ?? '',

            // All roles: service areas (geographic coverage)
            'cities_served'                       => $profileData['cities_served']                       ?? '',
            'counties_served'                     => $profileData['counties_served']                     ?? '',
            'neighborhoods_served'                => $profileData['neighborhoods_served']                ?? '',
            'areas_notes'                         => $profileData['areas_notes']                         ?? '',

            // All roles: agent-to-agent referral fee
            'referral_fee_percent'                => $profileData['referral_fee_percent']                ?? '',

            // Seller: leasing sub-fields (exact keys from SellerAgentAuctionBid component)
            'seller_leasing_gross_other'                   => $profileData['seller_leasing_gross_other']                   ?? '',
            'seller_leasing_gross_percentage'              => $profileData['seller_leasing_gross_percentage']              ?? '',
            'seller_leasing_gross_purchase_fee_flat_amount'=> $profileData['seller_leasing_gross_purchase_fee_flat_amount'] ?? '',
            'seller_leasing_gross_purchase_fee_other'      => $profileData['seller_leasing_gross_purchase_fee_other']      ?? '',
            'seller_leasing_each_rental'                   => $profileData['seller_leasing_each_rental']                   ?? '',
            'seller_leasing_gross_no_of_months'            => $profileData['seller_leasing_gross_no_of_months']            ?? '',
            'seller_leasing_gross_flat_combo'              => $profileData['seller_leasing_gross_flat_combo']              ?? '',
            'seller_leasing_gross_percentage_combo'        => $profileData['seller_leasing_gross_percentage_combo']        ?? '',
            'seller_leasing_gross_flat_net_combo'          => $profileData['seller_leasing_gross_flat_net_combo']          ?? '',
            'seller_leasing_gross_percentage_net_combo'    => $profileData['seller_leasing_gross_percentage_net_combo']    ?? '',
            'seller_leasing_gross_sales_tax_first_month'   => $profileData['seller_leasing_gross_sales_tax_first_month']   ?? '',
            'seller_leasing_gross_sales_tax_option_gross'  => $profileData['seller_leasing_gross_sales_tax_option_gross']  ?? '',
            'seller_leasing_gross_sales_tax_flat_free_gross'=> $profileData['seller_leasing_gross_sales_tax_flat_free_gross'] ?? '',
        ];
    }

    /**
     * Extract the compatibility_preferences sub-array from a raw profile_data array.
     *
     * Returns an array keyed by the 7 canonical section names containing their
     * respective field arrays.  Returns an empty array when profile_data contains
     * no compatibility_preferences key or when the stored value is not an array.
     *
     * No DB writes, no side-effects.  Pure transformation.
     *
     * @param  array  $profileData  The decoded profile_data array from AgentDefaultProfile.
     * @return array<string, array> Compatibility sections, each being an assoc array of fields.
     */
    public static function mapCompatibilityFromProfile(array $profileData): array
    {
        $raw = $profileData['compatibility_preferences'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $allowed = [
            'communication_preferences',
            'negotiation_approach',
            'guidance_style',
            'collaboration_preferences',
            'transaction_strategy',
            'representation_philosophy',
            'representation_priorities',
        ];

        $result = [];
        foreach ($allowed as $section) {
            if (isset($raw[$section]) && is_array($raw[$section])) {
                $result[$section] = $raw[$section];
            }
        }

        return $result;
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

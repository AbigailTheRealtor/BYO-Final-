<?php

namespace App\Support\AskAi;

/**
 * AskAiPageFactDisposition — every meta key a PUBLIC listing page reads that Ask AI does not
 * answer, and why. The page-side half of the coverage contract.
 *
 * WHY THIS EXISTS
 * ---------------
 * AskAiFieldDisposition accounts for every field of Ask AI's own context map. That proved
 * nothing about facts the page shows from meta the context never read — Full/Half Bathrooms,
 * rent frequency, CAM/NNN, frontage, the offered sale terms — which were lost without anything
 * saying so. The universal coverage audit (2026-09-24) started from the PAGE instead:
 * AskAiPageFactCoverageContractTest reads every meta key each public view reads and fails,
 * naming the key, unless it feeds a public Ask AI field / criteria source, or is listed here
 * under a category with a reason. **Silence fails.**
 *
 * CATEGORIES (the audit's own vocabulary)
 * ---------------------------------------
 *   PRIVATE         the page may print it, but it describes a person or a party's position
 *                   (contact details, screening, qualification, money a party brings, a
 *                   motivation) — never restated by Ask AI.
 *   PROHIBITED      Fair Housing or lending-disclosure exposure: 55+, breed limits, credit
 *                   trigger terms. Never restated, whatever the page does.
 *   INTERNAL        listing bookkeeping, media links, live auction state, compliance-restricted
 *                   money (deposits) — not a property fact to answer.
 *   NOT_APPLICABLE  no fact to answer: a legacy key no form writes, a write-in whose selection
 *                   is answered instead, metadata about a fact, a name whose meaning differs.
 *   DUPLICATE_DERIVED  the same fact another answered key already states (a second key, or text
 *                   the answered row folds in).
 *   PAGE_BUG        the page prints it in a way that is itself wrong; restating it would repeat
 *                   the defect. Reported for a page fix.
 *   COVERAGE_GAP    a legitimate public fact NOT yet answered. Must be EMPTY: the contract test
 *                   fails on any entry (universal coverage audit, 2026-09-24).
 *
 * Pure: no container, no I/O.
 */
final class AskAiPageFactDisposition
{
    public const PRIVATE        = 'PRIVATE';
    public const PROHIBITED     = 'PROHIBITED';
    public const INTERNAL       = 'INTERNAL';
    public const NOT_APPLICABLE = 'NOT_APPLICABLE';
    public const DUPLICATE_DERIVED = 'DUPLICATE_DERIVED';
    public const PAGE_BUG       = 'PAGE_BUG';
    public const COVERAGE_GAP   = 'COVERAGE_GAP';

    /**
     * category => reason => role => keys. Grouped so each reason is written once.
     *
     * @var array<string, array<string, array<string, list<string>>>>
     */
    private const GROUPS = [
        self::PRIVATE => [
            'Contact details of the owner or their agent. Ask AI never restates a person\'s name, email, phone or licence; the page\'s own contact block is the channel.' => [
                'seller'   => ['first_name', 'last_name', 'email', 'phone_number', 'agent_brokerage', 'agent_license_number', 'agent_nar_member_id'],
                'landlord' => ['first_name', 'last_name', 'email', 'phone_number', 'agent_brokerage', 'agent_license_number', 'agent_nar_member_id'],
                'buyer'    => ['first_name', 'last_name', 'email', 'phone_number', 'agent_brokerage', 'agent_license_number', 'agent_nar_member_id', 'working_with_agent'],
                'tenant'   => ['first_name', 'last_name', 'email', 'phone_number', 'agent_brokerage', 'agent_license_number', 'agent_nar_member_id', 'photo'],
            ],
            'Location identifiers. Address visibility is the page\'s and the PII screen\'s decision (AskAiFieldDisposition::DELIBERATELY_NOT_ASKED); a question would be a second place that decision lives. A parcel number resolves to owner records; a coordinate locates the property exactly.' => [
                'seller'   => ['address', 'formatted_address', 'unit_address', 'unit', 'zip_code', 'property_city', 'property_county', 'property_state', 'property_zip', 'property_lat', 'property_lng', 'additional_parcel_ids'],
                'landlord' => ['address', 'formatted_address', 'unit_address', 'zip_code', 'property_city', 'property_county', 'property_state', 'property_lat', 'property_lng', 'additional_parcel_ids', 'parcel_id', 'legal_description', 'unit_number'],
                'buyer'    => ['property_city', 'property_county', 'unit_number'],
                'tenant'   => ['address', 'unit_number', 'property_zip'],
            ],
            'A commute anchor locates the client\'s workplace or home. Commute Preferences are retired and have no consumer; the stored values are shown to nobody by Ask AI.' => [
                'buyer'  => ['commute_destination_zip', 'commute_mode', 'max_commute_minutes'],
                'tenant' => ['commute_destination_zip', 'commute_mode', 'max_commute_minutes'],
            ],
            'Applicant screening and qualification (decision D4, Fair Housing Phases 2–3): what a landlord requires of a PERSON — credit, income, evictions, criminal history, references, occupants, guests, guarantees, move-in timing, approval prose.' => [
                'landlord' => ['bankruptcy_requirement', 'custom_bankruptcy_requirement', 'commercial_approval_conditions', 'credit_score_flexibility', 'credit_scroe_rating', 'criminal_background_requirement', 'custom_criminal_background_requirement', 'custom_credit_score_requirement', 'custom_eviction_requirement', 'custom_income_requirement', 'custom_preferred_move_in_timeframe', 'custom_reference_requirement', 'custom_smoking_policy_requirement', 'eviction_explanation', 'eviction_history_requirement', 'guests_allowed', 'income_qualification_method', 'income_verification_requirement', 'landlord_approval_conditions', 'min_credit_score', 'min_income_requirement', 'min_monthly_income_fixed', 'monthly_income', 'number_occupant', 'number_of_occupants_allowed', 'personal_guarantee_requirement', 'preferance_details', 'preferred_move_in_timeframe', 'prior_eviction', 'prior_felony', 'prior_felony_explanation', 'reference_requirement', 'smoking_policy_requirement', 'pet_policy_requirement', 'other_preferences'],
            ],
            'What the client discloses about THEMSELVES (decision D2): income, credit, household, occupants, accessibility and assistance animals, their own pets, current housing, screening concerns, purpose, and the free prose they wrote. The criteria allowlists admit only what describes the property sought.' => [
                'buyer'  => ['additional_details', 'purchase_purpose', 'preferance_details', 'number_occupant'],
                'tenant' => ['accessibility_requirements', 'credit_score_range', 'current_status', 'emotional_support_animal', 'service_animal', 'support_animal', 'monthly_income', 'minimum_annual_net_income', 'number_occupant', 'number_occupants', 'occupancy_status', 'occupied_until', 'prior_eviction', 'prior_felony', 'screening_concerns', 'rental_purpose', 'tenant_conditions', 'personal_guarantee_preference', 'smoking_preference', 'pet_information', 'number_of_pets', 'weight_of_pets', 'type_of_pets', 'has_breed_restrictions', 'additional_details', 'additional_tenant_lease_terms', 'commercial_approval_conditions', 'renewal_option_details'],
            ],
            'Money a client BRINGS — pre-approval, cash, down payment, deposits offered, rates and payments they propose, amounts in crypto/NFT/exchange (decision D2: what a counterparty would use to judge them, not what they seek). Their own lease-to-own financing likewise.' => [
                'buyer'  => ['additional_cash', 'assumable_bridge_gap_cash', 'cash_budget', 'cash_percentage_crypto', 'cash_percentage_nft', 'crypto_percentage', 'down_payment_amount', 'down_payment_type', 'earnest_money_amount', 'earnest_money_timing', 'earnest_money_type', 'exchange_item_value', 'has_option_fee', 'option_fee_amount', 'lease_option_fee_credit_percentage', 'lease_option_payment', 'lease_option_price', 'lease_purchase_deposit', 'lease_purchase_payment', 'lease_purchase_price', 'nft_percentage', 'pre_approval_amount', 'pre_approved', 'purchase_price', 'offered_financing', 'other_financing', 'assignment_fee_amount', 'assignment_fee_type'],
                'tenant' => ['cryptocurrency_type', 'down_payment_amount', 'interest_rate', 'lease_option_price', 'lease_purchase_price', 'loan_duration', 'offered_financing', 'other_financing', 'move_in_budget_upfront', 'move_in_funds_available', 'security_deposit_budget', 'first_month_rent_available', 'last_month_rent_available'],
            ],
            'The buyer\'s OWN home sale behind a home-sale contingency: its address, target date, notes and contract status describe the buyer\'s property and position, not the property sought. The contingency and its period are answered.' => [
                'buyer' => ['home_sale_contingency_address', 'home_sale_contingency_date', 'home_sale_contingency_details', 'home_sale_contingency_under_contract'],
            ],
            'A buyer\'s-qualification field that sits on the seller form (the seller page prints "Buyer Pre-Approved for a Loan"). Answered as a seller fact it would describe a buyer who does not exist.' => [
                'seller' => ['cash_budget', 'pre_approved', 'pre_approval_amount', 'monthly_income'],
            ],
            'The seller\'s motivation (Batch 4 decision: motivation and negotiating posture are excluded).' => [
                'seller' => ['reason_for_sale', 'other_reason_for_sale'],
            ],
        ],

        self::PROHIBITED => [
            '55+ / 62+ housing is a Fair Housing compliance gate (Smart Tags governance: leasing_55_plus is never a source). It is stated by the page and never restated by Ask AI.' => [
                'seller' => ['leasing_55_plus'], 'landlord' => ['leasing_55_plus'], 'buyer' => ['leasing_55_plus'], 'tenant' => ['leasing_55_plus'],
            ],
            'Breed limits and restriction prose are a recognised Fair Housing proxy (the documented seller.pet_restrictions exclusion). Structured pet limits are answered instead.' => [
                'seller'   => ['breed_of_pets', 'breed_restrictions', 'pet_restrictions_detail'],
                'landlord' => ['breed_of_pets', 'pet_restrictions', 'pet_restrictions_detail'],
                'tenant'   => ['breed_of_pets', 'breed_restrictions'],
            ],
            'Seller-financing and assumable-loan terms: rates, payments, down payments, balloon, amortization, term, balances, loan particulars. Credit trigger terms carry advertising-disclosure obligations (SnapshotFactVisibility RESTRICTED seller_financing_*); the offered financing TYPES are answered.' => [
                'seller' => ['assumable_fee_amount', 'assumable_fee_type', 'assumable_loan_origination_date', 'assumable_loan_servicer', 'assumable_loan_term_remaining', 'assumable_loan_type', 'assumable_monthly_escrow', 'assumable_occupancy_requirement', 'assumable_occupancy_other', 'assumable_terms', 'assumption_fee_responsibility', 'balloon_payment', 'balloon_payment_amount', 'balloon_payment_date', 'down_payment_amount', 'down_payment_type', 'gap_payment_amount', 'gap_payment_type', 'interest_rate', 'loan_duration', 'max_assumable_rate', 'max_monthly_payment', 'outstanding_balance', 'prepayment_penalty', 'prepayment_penalty_amount', 'purchase_price', 'seller_amortization_type', 'seller_amortization_other', 'seller_down_payment_amount', 'seller_financing_type', 'seller_late_fee_amount', 'seller_payment_frequency', 'seller_payment_frequency_other'],
                'buyer'  => ['assumable_interest', 'assumable_max_interest_rate', 'assumable_max_monthly_payment', 'balloon_payment', 'balloon_payment_amount', 'balloon_payment_date', 'interest_rate', 'loan_duration', 'prepayment_penalty_amount'],
            ],
        ],

        self::INTERNAL => [
            'Listing bookkeeping, timers and media links — the page\'s own panels present them; not a property fact.' => [
                'seller'   => ['additional_documents', 'doc_rows', 'auction_time', 'auction_type', 'expiration_date', 'hired_agent_id', 'listing_date', 'listing_title', 'video_link', 'video_tour_url', 'virtual_tour_url', 'property_photos'],
                'landlord' => ['auction_time', 'auction_type', 'expiration_date', 'hired_agent_id', 'listing_date', 'listing_status', 'listing_title', 'video_link', 'video_tour_url', 'virtual_tour_url', 'property_photos'],
                'buyer'    => ['auction_time', 'auction_type', 'expiration_date', 'listing_date', 'listing_status', 'listing_title', 'desired_agent_hire_date', 'property_photos'],
                'tenant'   => ['auction_time', 'auction_type', 'expiration_date', 'listing_date', 'listing_status', 'listing_title', 'property_photos', 'video', 'video_link'],
            ],
            'Live bidding-period prices. The page\'s bidding panel owns them and they move with the auction; the Desired Sale Price / rent question answers a non-bidding listing.' => [
                'seller'   => ['buy_now_price', 'starting_price', 'reserve_price', 'reserve_price_public'],
                'landlord' => ['reserve_rent'],
            ],
            'Deposits and move-in money. Security deposits carry a disclosure obligation (SnapshotFactVisibility RESTRICTED); a sale deposit request is treated the same way.' => [
                'seller'   => ['initial_deposit_requested', 'initial_deposit_timeframe', 'initial_deposit_timeframe_other', 'initial_deposit_type', 'additional_deposit_requested', 'additional_deposit_timeframe', 'additional_deposit_timeframe_other', 'additional_deposit_type', 'lease_purchase_deposit'],
                'landlord' => ['security_deposit_amount', 'security_deposit_required', 'last_month_rent_required', 'total_move_in_funds_required'],
            ],
        ],

        self::NOT_APPLICABLE => [
            'A write-in: the free text beside an "Other" selection. The selection is answered; the write-in is not restated on its own, because free text beside a structured list is where the prose policies apply and a curated question reads it only where it declares a companion.' => [
                'seller'   => ['other_air_conditioning', 'other_appliances', 'other_building_features', 'other_current_adjacent_use', 'other_current_use', 'other_easements', 'other_electrical_service', 'other_exterior_construction', 'other_fences', 'other_foundation', 'other_heating_and_fuel', 'other_lease_type', 'other_licenses', 'other_property_items', 'other_road_frontage', 'other_road_surface_type', 'other_sale_includes', 'other_sewer', 'other_utilities', 'other_vegetation', 'other_water', 'association_amenities_other', 'electric_available_other', 'gas_available_other', 'sewer_available_other', 'telecom_available_other', 'water_available_other', 'sale_provision_other', 'number_of_unit_other', 'custom_bathrooms', 'custom_bedrooms', 'other_non_negotiable_amenities', 'other_preferences'],
                'landlord' => ['appliances_other', 'custom_lease_term', 'min_lease_period_other', 'number_of_unit_other', 'other_carport_needed', 'other_garage_needed', 'other_lease_term', 'other_owner_pays', 'other_property_items', 'other_rent_include', 'other_tenant_pays', 'other_non_negotiable_amenities', 'other_lease_for'],
                'buyer'    => ['custom_bathrooms', 'custom_bedrooms', 'number_of_unit_type_other', 'other_property_condition', 'other_property_items', 'sale_provision_other', 'unit_size_other', 'number_of_unit_other'],
                'tenant'   => ['custom_bathrooms', 'custom_bedrooms', 'custom_lease_term', 'other_lease_for', 'other_owner_pays', 'other_property_condition', 'other_rent_include', 'other_tenant_pays', 'other_parking_space_wrapper'],
            ],
            'A legacy key no current form input writes (the input is commented out or was removed). The page prints a stored value when one survives; the field that replaced it is answered.' => [
                'seller'   => ['buyer_sell_contract', 'carport_spaces', 'garage_spaces', 'custom_enhancement', 'number_of_unit', 'number_of_units', 'unit_type_description', 'garage_parking_spaces_option', 'non_negotiable_amenities'],
                'landlord' => ['garage_parking_spaces', 'lease_by', 'lease_date', 'lease_for', 'min_acreage', 'non_negotiable_amenities', 'pool_type'],
                'buyer'    => ['buyer_sell_contract', 'leasing_space', 'min_acreage', 'minimum_heated_sqft', 'minimum_leaseable', 'number_of_unit', 'property_criteria', 'pool_type'],
                'tenant'   => ['carport_spaces', 'garage_spaces', 'min_acreage', 'total_square_feet', 'sqft_heated_source', 'parking_needed', 'lease_length', 'tenant_desired_lease_length', 'zip_codes'],
            ],
            'Where a figure came from (e.g. "Public Records") — metadata about a fact, not a property fact.' => [
                'seller' => ['sqft_heated_source'], 'landlord' => ['sqft_heated_source'],
            ],
            'A name that matches and a meaning that does not: a seller\'s desired MINIMUM cap rate / net income is not the property\'s figure (CLAUDE.md, MLS import).' => [
                'seller' => ['minimum_annual_net_income', 'minimum_cap_rate'],
            ],
            'One key, two meanings: the Income form stores a TOTAL UNIT COUNT in unit_number while the page prints it as the address\'s "Unit / Apt / Suite #". Either answer could state the wrong one.' => [
                'seller' => ['unit_number'],
            ],
            'Read by the view only to decide whether a section or badge renders; never printed as a row of its own.' => [
                'landlord' => ['commercial_parking_terms'],
                'buyer'    => ['inspection_period_other'],
            ],
        ],

        self::DUPLICATE_DERIVED => [
            'The same fact as an answered field under a second key: the answered field\'s question states it (and refuses when the two disagree).' => [
                'landlord' => ['lease_available_date'],
                'buyer'    => ['buyer_budget', 'max_purchase_price'],
            ],
            'Text the answered row folds in: the buyer page prints the "Other" possession wording AS the Possession Preference, and so does its answer.' => [
                'buyer' => ['possession_preference_other'],
            ],
        ],

        // PAGE_BUG is empty: seller real_estate_purchase printed inside the Seller Financing
        // block and was moved to the page's Business section (2026-09-25), then answered.
        self::PAGE_BUG => [],

        // COVERAGE_GAP stays EMPTY. Every gap the audit named was resolved on 2026-09-24:
        // assignment fee, buyer contingency periods and possession details, and the tenant's
        // separate carport / garage requirements are answered.
        self::COVERAGE_GAP => [],
    ];

    /** @return array<string, array{category: string, reason: string}> key => disposition */
    public static function forRole(string $role): array
    {
        $out = [];
        foreach (self::GROUPS as $category => $reasons) {
            foreach ($reasons as $reason => $roles) {
                foreach ($roles[$role] ?? [] as $key) {
                    $out[$key] = ['category' => $category, 'reason' => $reason];
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return [self::PRIVATE, self::PROHIBITED, self::INTERNAL, self::NOT_APPLICABLE, self::DUPLICATE_DERIVED, self::PAGE_BUG, self::COVERAGE_GAP];
    }

    /** @return array<string, array<string, array<string, list<string>>>> */
    public static function groups(): array
    {
        return self::GROUPS;
    }
}

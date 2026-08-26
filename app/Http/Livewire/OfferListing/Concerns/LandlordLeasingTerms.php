<?php

namespace App\Http\Livewire\OfferListing\Concerns;

/**
 * The canonical Landlord Leasing Terms field set — ONE definition, three consumers.
 *
 * WHAT THIS IS
 * ------------
 * Every landlord-controlled term the Leasing Terms tab asks about, declared
 * once. The tab's markup, labels, option vocabularies, help text and conditional
 * sections already lived in exactly one place —
 * `offer-landlord-tabs/commission-based/lease-terms.blade.php` — shared by
 * Create and Edit. What did not was the property surface it binds to: Create
 * declared it, Edit declared it again, and MLS Quick Import declared a third,
 * far smaller approximation in LandlordMlsQuickImport::questionSchema().
 *
 * HOW LANDLORD DIFFERS FROM SELLER
 * --------------------------------
 * Worth stating plainly, because the Seller equivalent was extracted from a
 * genuinely broken surface and this one was not. Landlord Create and Edit were
 * measured field-by-field before this trait existed and found to agree
 * completely: all 62 fields declared with identical defaults, all 62 persisted
 * with identical transforms, all 62 rehydrated. There was NO save gap, NO load
 * gap and NO transform drift to repair — unlike Seller, where Create silently
 * discarded fifteen fields and never read back twenty-seven.
 *
 * So this extraction is prevention, not repair. It removes the third list
 * (Quick Import's) and collapses the two identical ones so they cannot drift
 * apart later the way Seller's did.
 *
 * WHO USES IT
 * -----------
 *   - LandlordOfferListing      (manual Create)
 *   - LandlordOfferListingEdit  (manual Edit)
 *   - LandlordMlsQuickImport    (MLS Quick Import "Your Terms")
 *
 * All three render the SAME canonical partial. Quick Import does not carry a
 * copy of the markup and must never be given one; if a term is missing there,
 * the fix belongs in the partial or in this trait.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * Fields whose canonical home is a different Landlord tab — pet_policy and
 * number_of_occupants_allowed (Applicant Requirements), parking_terms (Property
 * Preferences), desired_lease_length (the Hire Landlord Agent flow). Quick
 * Import asks those as supplementary questions; they are real fields, but they
 * are not Leasing Terms and listing them here would make this set a fiction.
 *
 * included_storage_space, maintenance_handler and storage_space appear only
 * inside Blade comments in the canonical tab. They are not rendered, so they are
 * not part of the active surface and are left exactly as they are.
 *
 * PET FEE
 * -------
 * pet_fee_amount and pet_fee_other are not stored straight from the property.
 * They go through canonicalPetFeeValues() in {@see HasCanonicalPetFee}, which
 * resolves the amount/other pair against pet_fee_type so that a "None" or
 * "Other" selection cannot leave a contradictory figure behind. Any consumer of
 * this trait must also use HasCanonicalPetFee.
 */
trait LandlordLeasingTerms
{
    /**
     * Canonical Leasing Terms fields, rendered by the shared partial.
     *
     * @return list<string>
     */
    public static function landlordLeasingTermsFields(): array
    {
        return [
            'lease_amount_frequency',
            'desired_rental_amount',
            'starting_rent',
            'reserve_rent',
            'lease_now_price',
            'terms_of_lease',
            'owner_pays',
            'lease_available_date',
            'last_month_rent_required',
            'total_move_in_funds_required',
            'available_date',
            'pet_fee_type',
            'pet_fee_amount',
            'pet_fee_other',
            'll_maintenance_responsibility',
            'renewal_option_offered',
            'renewal_option_details',
            'additional_landlord_lease_terms',
            'commercial_lease_type',
            'commercial_lease_type_other',
            'cam_nnn_additional_rent_charges',
            'rent_escalation_terms',
            'tenant_improvement_buildout_terms',
            'permitted_use_restrictions',
            'signage_rights',
            'commercial_parking_terms',
            'personal_guarantee_requirement',
            'commercial_approval_conditions',
            'smoking_policy',
            'security_deposit_amount',
            'subletting_policy',
            'occupant_status',
            'occupant_tenant',
            'leasing_spaces',
            'restrictions',
            'maintenance_by',
            'maintenance_response_time',
            'included_storage_space_res_both',
            'storage_space_res_both',
            'guests_allowed',
            'common_areas_access',
            'utilities',
            'common_areas_cleaning',
            'included_storage_space_res_single',
            'storage_space_res_single',
            'bathroom_facilities',
            'room_size',
            'included_storage_space_com_entire',
            'storage_space_com_entire',
            'shared_amenities',
            'building_hours',
            // Added after the property-type fix: the canonical partial binds
            // these through $wire.entangle() and renders desired_lease_length as
            // a lease-term select. The original audit scanned wire:model and
            // JS-bridged ids only, so all three were missed — they were invisible
            // because the conditional sections holding them never opened.
            'rent_includes',
            'tenant_pays',
            'desired_lease_length',
            'access_24_7',
            'zoning_allows',
            'space_features',
            'neighboring_tenants',
            'included_storage_space_com_single',
            'storage_space_com_single',
            'other_tenant_pays',
            'other_owner_pays',
            'custom_lease_term',
            'other_lease_term',
            'other_rent_include',
        ];
    }

    /**
     * Canonical fields that are deliberately NOT stored.
     *
     * Empty, and it should stay that way: every field the landlord can enter a
     * value into is persisted. It exists so the parity test can assert that each
     * canonical field has an intentional disposition rather than merely counting
     * map entries.
     *
     * @return list<string>
     */
    public static function landlordLeasingTermsNotPersisted(): array
    {
        return [];
    }

    /**
     * How each field is written to EAV meta, transcribed from the saveMeta lines
     * Create and Edit already used — which were, field for field, identical.
     *
     * The transforms are not cosmetic and must not be "tidied":
     *   money          — stripCommas(), so "1,250" stores as 1250
     *   json_array     — ensureArray() then json_encode(), the stored shape every
     *                    reader expects for a multi-select
     *   pet_fee_amount — resolved against pet_fee_type, see the class note
     *   pet_fee_other  — likewise
     *
     * @return array<string, string>
     */
    public static function landlordLeasingTermsMetaMap(): array
    {
        return [
            'lease_amount_frequency' => 'raw',
            'desired_rental_amount' => 'money',
            'starting_rent' => 'money',
            'reserve_rent' => 'money',
            'lease_now_price' => 'money',
            'terms_of_lease' => 'json_array',
            'owner_pays' => 'json_array',
            'lease_available_date' => 'raw',
            'last_month_rent_required' => 'raw',
            'total_move_in_funds_required' => 'money',
            'available_date' => 'raw',
            'pet_fee_type' => 'raw',
            'pet_fee_amount' => 'pet_fee_amount',
            'pet_fee_other' => 'pet_fee_other',
            'll_maintenance_responsibility' => 'raw',
            'renewal_option_offered' => 'raw',
            'renewal_option_details' => 'raw',
            'additional_landlord_lease_terms' => 'raw',
            'commercial_lease_type' => 'raw',
            'commercial_lease_type_other' => 'raw',
            'cam_nnn_additional_rent_charges' => 'raw',
            'rent_escalation_terms' => 'raw',
            'tenant_improvement_buildout_terms' => 'raw',
            'permitted_use_restrictions' => 'raw',
            'signage_rights' => 'raw',
            'commercial_parking_terms' => 'raw',
            'personal_guarantee_requirement' => 'raw',
            'commercial_approval_conditions' => 'raw',
            'smoking_policy' => 'raw',
            'security_deposit_amount' => 'raw',
            'subletting_policy' => 'raw',
            'occupant_status' => 'raw',
            'occupant_tenant' => 'raw',
            'leasing_spaces' => 'raw',
            'restrictions' => 'raw',
            'maintenance_by' => 'raw',
            'maintenance_response_time' => 'raw',
            'included_storage_space_res_both' => 'raw',
            'storage_space_res_both' => 'raw',
            'guests_allowed' => 'raw',
            'common_areas_access' => 'raw',
            'utilities' => 'raw',
            'common_areas_cleaning' => 'raw',
            'included_storage_space_res_single' => 'raw',
            'storage_space_res_single' => 'raw',
            'bathroom_facilities' => 'raw',
            'room_size' => 'raw',
            'included_storage_space_com_entire' => 'raw',
            'storage_space_com_entire' => 'raw',
            'shared_amenities' => 'raw',
            'building_hours' => 'raw',
            'rent_includes' => 'json_array',
            'tenant_pays' => 'json_array',
            'desired_lease_length' => 'json_array',
            'access_24_7' => 'raw',
            'zoning_allows' => 'raw',
            'space_features' => 'raw',
            'neighboring_tenants' => 'raw',
            'included_storage_space_com_single' => 'raw',
            'storage_space_com_single' => 'raw',
            'other_tenant_pays' => 'raw',
            'other_owner_pays' => 'raw',
            'custom_lease_term' => 'raw',
            'other_lease_term' => 'raw',
            'other_rent_include' => 'raw',
        ];
    }

    /**
     * Write every canonical Leasing Terms answer to the listing's meta.
     *
     * Replaces the hand-written run of saveMeta() calls in saveAllMetadata() on
     * both manual flows. Same keys, same transforms — the point is that Create,
     * Edit and Quick Import cannot disagree about them.
     */
    protected function saveLandlordLeasingTermsMeta(object $auction): void
    {
        // Resolved once: the pair is derived together from pet_fee_type, so
        // computing it per-field would risk the two halves disagreeing.
        [$petFeeAmount, $petFeeOther] = $this->canonicalPetFeeValues();

        foreach (static::landlordLeasingTermsMetaMap() as $field => $kind) {
            switch ($kind) {
                case 'money':
                    $auction->saveMeta($field, $this->stripCommas($this->{$field}));
                    break;

                case 'json_array':
                    $auction->saveMeta($field, json_encode($this->ensureArray($this->{$field})));
                    break;

                case 'pet_fee_amount':
                    $auction->saveMeta($field, $petFeeAmount);
                    break;

                case 'pet_fee_other':
                    $auction->saveMeta($field, $petFeeOther);
                    break;

                default:
                    $auction->saveMeta($field, $this->{$field});
            }
        }
    }

    /**
     * Money inputs arrive comma-grouped ("1,250") and must be stored bare.
     *
     * LandlordOfferListing and LandlordOfferListingEdit each already declare an
     * identical stripCommas(); a method a class declares itself takes precedence
     * over the trait's, so both keep running their own copy and nothing about
     * them changes. This declaration exists for LandlordMlsQuickImport, which
     * had no need for one until it started writing money fields through
     * saveLandlordLeasingTermsMeta().
     */
    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace(',', '', $value);
    }

    /**
     * Multi-selects come back from meta as a JSON string and must be a list
     * again before re-encoding. Copied verbatim from the manual flows, and
     * likewise overridden by their own identical declarations.
     */
    protected function ensureArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    // ─── Canonical Leasing Terms properties ──────────────────────────────────
    //
    // Moved verbatim from LandlordOfferListing, defaults included. Create and
    // Edit declared all 62 identically — verified before the move — so both keep
    // their exact previous behaviour.

    public $lease_amount_frequency = '';
    public $desired_rental_amount = '';
    public $starting_rent = '';
    public $reserve_rent = '';
    public $lease_now_price = '';
    public $terms_of_lease = []; // Commercial only
    public $owner_pays = []; // Commercial only
    public $lease_available_date = '';
    public $last_month_rent_required = '';
    public $total_move_in_funds_required = '';
    public $available_date = '';
    public $pet_fee_type = '';
    public $pet_fee_amount = '';
    public $pet_fee_other = '';
    public $ll_maintenance_responsibility = '';
    public $renewal_option_offered = '';
    public $renewal_option_details = '';
    public $additional_landlord_lease_terms = '';
    public $commercial_lease_type = '';
    public $commercial_lease_type_other = '';
    public $cam_nnn_additional_rent_charges = '';
    public $rent_escalation_terms = '';
    public $tenant_improvement_buildout_terms = '';
    public $permitted_use_restrictions = '';
    public $signage_rights = '';
    public $commercial_parking_terms = '';
    public $personal_guarantee_requirement = '';
    public $commercial_approval_conditions = '';
    public $smoking_policy = '';
    public $security_deposit_amount = '';
    public $subletting_policy = '';
    public $occupant_status = '';
    public $occupant_tenant = '';
    public $leasing_spaces = '';
    public $restrictions = '';
    public $maintenance_by = '';
    public $maintenance_response_time = '';
    public $included_storage_space_res_both = '';
    public $storage_space_res_both = '';
    public $guests_allowed = '';
    public $common_areas_access = '';
    public $utilities = '';
    public $common_areas_cleaning = '';
    public $included_storage_space_res_single = '';
    public $storage_space_res_single = '';
    public $bathroom_facilities = '';
    public $room_size = '';
    public $included_storage_space_com_entire = '';
    public $storage_space_com_entire = '';
    public $shared_amenities = '';
    public $building_hours = '';
    public $rent_includes = [];          // Residential only
    public $tenant_pays = [];            // Commercial only
    public $desired_lease_length = [];
    public $access_24_7 = '';
    public $zoning_allows = '';
    public $space_features = '';
    public $neighboring_tenants = '';
    public $included_storage_space_com_single = '';
    public $storage_space_com_single = '';
    public $other_tenant_pays = '';
    public $other_owner_pays = '';
    public $custom_lease_term = '';
    public $other_lease_term = '';
    public $other_rent_include = '';
}

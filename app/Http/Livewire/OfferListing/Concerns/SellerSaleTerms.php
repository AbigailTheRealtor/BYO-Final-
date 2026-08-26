<?php

namespace App\Http\Livewire\OfferListing\Concerns;

/**
 * The canonical Seller Sale Terms field set — ONE definition, three consumers.
 *
 * WHAT THIS IS
 * ------------
 * Every seller-controlled term the Sale Terms tab asks about, declared once.
 * The tab's markup, labels, option lists, help text and conditional sections
 * already lived in exactly one place —
 * `offer-seller-tabs/commission-based/seller-terms.blade.php` — shared by Create
 * and Edit. What did NOT live in one place was the property surface that partial
 * binds to: Create declared it, Edit declared it again, and MLS Quick Import
 * declared a THIRD, much smaller approximation of it in
 * SellerMlsQuickImport::questionSchema() — 19 hand-copied fields with their own
 * labels and their own option lists.
 *
 * Three lists is two lists too many, and they had already drifted (see the
 * PERSISTENCE DIVERGENCE note below). This trait is the single list. A field
 * added to the Sale Terms tab is added here, and every consumer gets it.
 *
 * WHO USES IT
 * -----------
 *   - SellerOfferListing        (manual Create)
 *   - SellerOfferListingEdit    (manual Edit)
 *   - SellerMlsQuickImport      (MLS Quick Import "Your Terms")
 *
 * All three render the SAME canonical partial. Quick Import does not carry a
 * copy of the markup and must never be given one; if a term is missing there,
 * the fix belongs in the partial or in this trait, never in a quick-import-only
 * field list.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * `$assignment_fee_type`. Create defaults it to '' and Edit to '$'. A trait
 * property may not be redeclared with a different initial value, and silently
 * normalising the two would change one of the two manual flows — which this
 * extraction is not allowed to do. It stays declared per class, and
 * sellerSaleTermsFields() still lists it so parity checks continue to see it.
 *
 * `$auction_type` and `$property_type` are read by the partial but are listing
 * -level fields owned by the Listing Details tab, not seller terms. They are not
 * moved here.
 *
 * PERSISTENCE DIVERGENCE (PRE-EXISTING — NOT INTRODUCED BY THIS TRAIT)
 * -------------------------------------------------------------------
 * Manual Create writes 117 of these fields. Manual Edit writes 132. The 15 in
 * SELLER_SALE_TERMS_UNPERSISTED_ON_CREATE are rendered by the canonical tab on
 * BOTH screens, but saveAllMetadata() on Create has never had a saveMeta line
 * for them — so a value typed into "Lease Option Maintenance" or "Outstanding
 * Balance" while creating a listing is silently discarded, then persists
 * normally if the same field is filled from Edit afterwards.
 *
 * That is a real data-loss bug, and it is exactly the drift that having three
 * lists produced. It is recorded here rather than fixed here: fixing it changes
 * what manual Create writes, which is a product decision and a separate commit.
 * Quick Import is a CREATION path and therefore deliberately matches Create's
 * behaviour exactly, bug included — one flow, one result. Delete the constant
 * and its use in the map when Create is repaired.
 */
trait SellerSaleTerms
{
    /**
     * Canonical Sale Terms fields, rendered by the shared partial.
     *
     * Includes $assignment_fee_type, which the trait cannot declare (see the
     * class note) but which is still part of the tab.
     *
     * @return list<string>
     */
    public static function sellerSaleTermsFields(): array
    {
        return [
            'additional_cash',
            'additional_deposit_requested',
            'additional_deposit_timeframe',
            'additional_deposit_timeframe_other',
            'additional_deposit_type',
            'additional_seller_sale_terms',
            'appraisal_contingency_period',
            'appraisal_contingency_preference',
            'assignment_fee_amount',
            'assignment_fee_type',
            'assumable_fee_amount',
            'assumable_fee_type',
            'assumable_loan_origination_date',
            'assumable_loan_servicer',
            'assumable_loan_term_remaining',
            'assumable_loan_type',
            'assumable_monthly_escrow',
            'assumable_occupancy_other',
            'assumable_occupancy_requirement',
            'assumable_terms',
            'assumption_fee_responsibility',
            'balloon_payment',
            'balloon_payment_amount',
            'balloon_payment_date',
            'buy_now_price',
            'buyer_sell_contract',
            'cash_budget',
            'cash_percentage_crypto',
            'cash_percentage_nft',
            'crypto_custodian_wallet',
            'crypto_exchange_method',
            'crypto_percentage',
            'crypto_transaction_fees',
            'crypto_transfer_timing',
            'crypto_transfer_timing_other',
            'cryptocurrency_type',
            'down_payment_amount',
            'down_payment_type',
            'escrow_agent_preference',
            'exchange_inspection_rights',
            'exchange_item',
            'exchange_item_condition',
            'exchange_item_value',
            'exchange_liens_details',
            'exchange_liens_disclosure',
            'exchange_transfer_method',
            'excluded_items',
            'financing_contingency_period',
            'financing_contingency_preference',
            'gap_payment_amount',
            'gap_payment_type',
            'has_option_fee',
            'hoa_condo_association_terms',
            'home_warranty_amount_details',
            'home_warranty_offered',
            'included_personal_property',
            'initial_deposit_requested',
            'initial_deposit_timeframe',
            'initial_deposit_timeframe_other',
            'initial_deposit_type',
            'inspection_contingency_preference',
            'interest_rate',
            'lease_option_conditions',
            'lease_option_duration',
            'lease_option_extension_terms',
            'lease_option_fee_credit',
            'lease_option_fee_credit_percentage',
            'lease_option_maintenance',
            'lease_option_payment',
            'lease_option_price',
            'lease_option_terms',
            'lease_purchase_conditions',
            'lease_purchase_deposit',
            'lease_purchase_duration',
            'lease_purchase_extension_terms',
            'lease_purchase_maintenance',
            'lease_purchase_payment',
            'lease_purchase_price',
            'lease_purchase_rent_credit',
            'lease_purchase_rent_credit_amount',
            'lease_purchase_terms',
            'loan_duration',
            'max_assumable_rate',
            'max_monthly_payment',
            'maximum_budget',
            'nft_description',
            'nft_gas_fees',
            'nft_percentage',
            'nft_transfer_method',
            'nft_valuation_method',
            'occupant_status',
            'occupant_tenant',
            'offered_financing',
            'option_fee_amount',
            'other_exchange_item',
            'other_financing',
            'outstanding_balance',
            'payment_annual_property_taxes',
            'payment_down_payment_pct',
            'payment_hoa_fee_amount',
            'payment_hoa_fee_frequency',
            'payment_interest_rate',
            'payment_loan_term',
            'payment_monthly_insurance',
            'payment_pmi_rate',
            'payment_show_buydown_options',
            'possession_details',
            'possession_preference',
            'pre_approval_amount',
            'pre_approved',
            'preferred_inspection_period',
            'prepayment_penalty',
            'prepayment_penalty_amount',
            'purchase_price',
            'reserve_price',
            'sale_of_buyer_property_contingency',
            'sale_of_buyer_property_period',
            'sale_provision',
            'sale_provision_assignment',
            'sale_provision_other',
            'seller_amortization_other',
            'seller_amortization_type',
            'seller_contribution_amount_details',
            'seller_contribution_credit_offered',
            'seller_down_payment_amount',
            'seller_financing_type',
            'seller_late_fee_amount',
            'seller_payment_frequency',
            'seller_payment_frequency_other',
            'showPaymentAssumptions',
            'starting_price',
            'target_closing_date',
            'value_determination',
        ];
    }

    /**
     * Canonical fields that are deliberately NOT stored.
     *
     * UI state only — something the tab needs in order to draw itself, which is
     * not an answer the seller gave. This is not, and must not become, a place
     * to park a field that is genuinely supported but inconvenient to persist:
     * a field the seller can type into belongs in sellerSaleTermsMetaMap().
     *
     * It previously held fifteen real fields, under the name
     * sellerSaleTermsUnpersistedOnCreate(), recording a Create-only data-loss
     * bug. Those are repaired and gone from here.
     *
     * @return list<string>
     */
    public static function sellerSaleTermsNotPersisted(): array
    {
        return [
            // The Estimated Payment Assumptions expander. Open or closed, not an answer.
            'showPaymentAssumptions',
        ];
    }

    /**
     * How each field is written to EAV meta, transcribed from the saveMeta
     * lines SellerOfferListing::saveAllMetadata() already used.
     *
     * The transforms are not cosmetic and must not be "tidied":
     *   money         — stripCommas(), so "1,250,000" stores as 1250000
     *   json          — json_encode(), the stored shape every reader expects
     *   bool01        — '1' / '0', not true / false
     *   exchange_item — filtered + reindexed before encoding
     *
     * @return array<string, string>
     */
    public static function sellerSaleTermsMetaMap(): array
    {
        return [
            'sale_provision' => 'raw',
            'sale_provision_other' => 'raw',
            'sale_provision_assignment' => 'raw',
            'assignment_fee_type' => 'raw',
            'assignment_fee_amount' => 'money',
            'buyer_sell_contract' => 'raw',
            'occupant_status' => 'raw',
            'target_closing_date' => 'raw',
            'maximum_budget' => 'money',
            'starting_price' => 'money',
            'reserve_price' => 'money',
            'buy_now_price' => 'money',
            'offered_financing' => 'json',
            'other_financing' => 'raw',
            'cash_budget' => 'raw',
            'pre_approved' => 'raw',
            'pre_approval_amount' => 'raw',
            'purchase_price' => 'money',
            'down_payment_type' => 'raw',
            'down_payment_amount' => 'money',
            'seller_financing_type' => 'raw',
            'seller_down_payment_amount' => 'money',
            'seller_late_fee_amount' => 'money',
            'interest_rate' => 'raw',
            'loan_duration' => 'raw',
            'prepayment_penalty' => 'raw',
            'prepayment_penalty_amount' => 'money',
            'balloon_payment_amount' => 'money',
            'balloon_payment_date' => 'raw',
            'assumable_terms' => 'raw',
            'assumable_loan_type' => 'raw',
            'max_assumable_rate' => 'money',
            'assumable_monthly_escrow' => 'money',
            'assumable_loan_term_remaining' => 'raw',
            'assumable_loan_origination_date' => 'raw',
            'assumable_loan_servicer' => 'raw',
            'assumable_fee_type' => 'raw',
            'assumable_fee_amount' => 'money',
            'assumption_fee_responsibility' => 'raw',
            'assumable_occupancy_requirement' => 'raw',
            'assumable_occupancy_other' => 'raw',
            'max_monthly_payment' => 'money',
            'gap_payment_type' => 'raw',
            'gap_payment_amount' => 'money',
            'exchange_item' => 'exchange_item',
            'other_exchange_item' => 'raw',
            'exchange_item_value' => 'money',
            'exchange_item_condition' => 'raw',
            'additional_cash' => 'money',
            'value_determination' => 'raw',
            'exchange_transfer_method' => 'raw',
            'exchange_liens_disclosure' => 'raw',
            'exchange_liens_details' => 'raw',
            'exchange_inspection_rights' => 'raw',
            'lease_option_price' => 'money',
            'lease_option_terms' => 'raw',
            'lease_option_duration' => 'raw',
            'lease_option_payment' => 'money',
            'lease_option_conditions' => 'raw',
            'has_option_fee' => 'raw',
            'option_fee_amount' => 'money',
            'lease_purchase_price' => 'money',
            'lease_purchase_terms' => 'raw',
            'lease_purchase_duration' => 'raw',
            'lease_purchase_payment' => 'money',
            'lease_purchase_conditions' => 'raw',
            'seller_amortization_type' => 'raw',
            'seller_amortization_other' => 'raw',
            'seller_payment_frequency' => 'raw',
            'seller_payment_frequency_other' => 'raw',
            'crypto_transfer_timing' => 'raw',
            'crypto_transfer_timing_other' => 'raw',
            'crypto_exchange_method' => 'raw',
            'crypto_custodian_wallet' => 'raw',
            'crypto_transaction_fees' => 'raw',
            'cryptocurrency_type' => 'raw',
            'crypto_percentage' => 'money',
            'cash_percentage_crypto' => 'money',
            'nft_description' => 'raw',
            'nft_percentage' => 'raw',
            'cash_percentage_nft' => 'raw',
            'initial_deposit_type' => 'raw',
            'initial_deposit_requested' => 'raw',
            'initial_deposit_timeframe' => 'raw',
            'initial_deposit_timeframe_other' => 'raw',
            'additional_deposit_type' => 'raw',
            'additional_deposit_requested' => 'raw',
            'additional_deposit_timeframe' => 'raw',
            'additional_deposit_timeframe_other' => 'raw',
            'escrow_agent_preference' => 'raw',
            'preferred_inspection_period' => 'raw',
            'inspection_contingency_preference' => 'raw',
            'appraisal_contingency_preference' => 'raw',
            'appraisal_contingency_period' => 'raw',
            'financing_contingency_preference' => 'raw',
            'financing_contingency_period' => 'raw',
            'sale_of_buyer_property_contingency' => 'raw',
            'sale_of_buyer_property_period' => 'raw',
            'seller_contribution_credit_offered' => 'raw',
            'seller_contribution_amount_details' => 'raw',
            'possession_preference' => 'raw',
            'possession_details' => 'raw',
            'included_personal_property' => 'raw',
            'excluded_items' => 'raw',
            'home_warranty_offered' => 'raw',
            'home_warranty_amount_details' => 'raw',
            'hoa_condo_association_terms' => 'raw',
            'additional_seller_sale_terms' => 'raw',
            // ── Repaired: rendered by the canonical tab on Create as well as
            //    Edit, but Create had no saveMeta line for any of them, so a
            //    value typed while CREATING a listing was silently discarded and
            //    the same value typed while EDITING saved normally. Transforms
            //    are Edit's, which were already correct and already round-trip.
            'occupant_tenant' => 'raw',
            'balloon_payment' => 'raw',
            'outstanding_balance' => 'raw',
            'lease_option_fee_credit' => 'raw',
            'lease_option_fee_credit_percentage' => 'raw',
            'lease_option_maintenance' => 'raw',
            'lease_option_extension_terms' => 'raw',
            'lease_purchase_rent_credit' => 'raw',
            'lease_purchase_rent_credit_amount' => 'money',
            'lease_purchase_deposit' => 'money',
            'lease_purchase_maintenance' => 'raw',
            'lease_purchase_extension_terms' => 'raw',
            'nft_gas_fees' => 'raw',
            'nft_transfer_method' => 'raw',
            'nft_valuation_method' => 'raw',

            'payment_down_payment_pct' => 'money',
            'payment_interest_rate' => 'money',
            'payment_loan_term' => 'money',
            'payment_annual_property_taxes' => 'money',
            'payment_monthly_insurance' => 'money',
            'payment_hoa_fee_amount' => 'money',
            'payment_hoa_fee_frequency' => 'raw',
            'payment_pmi_rate' => 'money',
            'payment_show_buydown_options' => 'bool01',
        ];
    }

    /**
     * Write every canonical Sale Terms answer to the listing's meta.
     *
     * Replaces the hand-written run of saveMeta() calls that used to sit in
     * saveAllMetadata(). Same keys, same transforms, same order — the point is
     * that Create, Edit and Quick Import cannot disagree about them any more.
     */
    protected function saveSellerSaleTermsMeta(object $auction): void
    {
        foreach (static::sellerSaleTermsMetaMap() as $field => $kind) {
            $value = $this->{$field} ?? '';

            switch ($kind) {
                case 'money':
                    $auction->saveMeta($field, $this->stripCommas($value));
                    break;

                case 'json':
                    $auction->saveMeta($field, json_encode($value));
                    break;

                case 'bool01':
                    $auction->saveMeta($field, $value ? '1' : '0');
                    break;

                case 'exchange_item':
                    // Normalised before encoding, exactly as the hand-written
                    // line did. The property is an array while the form is open
                    // but comes back from meta as a JSON STRING, and casting
                    // that string to an array yields a one-element array whose
                    // only member is the raw JSON — which is how a re-saved
                    // listing would corrupt its own exchange items.
                    if ($value === null) {
                        $value = [];
                    }

                    if (is_string($value)) {
                        $value = json_decode($value, true) ?? [];
                    }

                    $auction->saveMeta(
                        $field,
                        json_encode(array_values(array_filter((array) $value)))
                    );
                    break;

                default:
                    $auction->saveMeta($field, $value);
            }
        }
    }

    /**
     * Read every canonical Sale Terms answer back off the listing's meta.
     *
     * The other half of persistence, and the half Create was also missing.
     * Create wrote a value and then, on resuming the draft, never read it back —
     * so 27 of these fields came up blank on the form and the next save wrote
     * that blank over the stored answer. Saving without loading is data loss on
     * a delay.
     *
     * ADDITIVE BY DESIGN. Callers invoke this BEFORE their own hand-written
     * hydration lines, so any field a class already loads is simply re-assigned
     * to the same value a moment later and that class's existing behaviour wins
     * untouched. What changes is only the fields nobody was loading at all.
     *
     * The three array fields and the one boolean are normalised exactly as the
     * hand-written lines normalise them: values come back from EAV meta as JSON
     * strings, and casting a JSON string with (array) yields a one-element array
     * holding the raw JSON rather than the list that was stored.
     */
    protected function loadSellerSaleTermsMeta(object $auction): void
    {
        $meta = $auction->get;

        foreach (static::sellerSaleTermsMetaMap() as $field => $kind) {
            $raw = $meta->{$field} ?? null;

            switch ($kind) {
                case 'json':
                case 'exchange_item':
                    if (is_string($raw)) {
                        $decoded = json_decode($raw, true);
                        $this->{$field} = is_array($decoded)
                            ? $decoded
                            : ($raw !== '' ? [$raw] : []);
                    } else {
                        $this->{$field} = (array) ($raw ?? []);
                    }
                    break;

                case 'bool01':
                    // Absent means "never answered", which for this flag is true
                    // — the buydown options are shown by default.
                    $this->{$field} = ($raw === null)
                        ? true
                        : ($raw !== '0' && $raw !== 'false');
                    break;

                default:
                    if ($raw !== null) {
                        $this->{$field} = $raw;
                    }
            }
        }
    }

    // ─── Canonical Sale Terms properties ─────────────────────────────────────
    //
    // Moved verbatim from SellerOfferListing, defaults included. Create and Edit
    // declared these identically, so both keep their exact previous behaviour.

    public $sale_provision = [];
    public $sale_provision_other = '';
    public $sale_provision_assignment = '';
    public $assignment_fee_amount = '';
    public $buyer_sell_contract = '';
    public $occupant_status = '';
    public $occupant_tenant = '';
    public $target_closing_date = '';
    public $maximum_budget = '';
    public $starting_price = '';
    public $reserve_price = '';
    public $buy_now_price = '';
    public $offered_financing = [];
    public $other_financing = '';
    public $cash_budget = '';
    public $pre_approved = '';
    public $pre_approval_amount = '';
    public $purchase_price = '';
    public $down_payment_type = '%';
    public $down_payment_amount = '';
    public $seller_financing_type = '$';
    public $seller_down_payment_amount = '';
    public $seller_late_fee_amount = '';
    public $interest_rate = '';
    public $loan_duration = '';
    public $prepayment_penalty = '';
    public $prepayment_penalty_amount = '';
    public $balloon_payment = '';
    public $balloon_payment_amount = '';
    public $balloon_payment_date = '';
    public $assumable_terms = '';
    public $assumable_loan_type = '';
    public $outstanding_balance = '';
    public $max_assumable_rate = '';
    public $assumable_monthly_escrow = '';
    public $assumable_loan_term_remaining = '';
    public $assumable_loan_origination_date = '';
    public $assumable_loan_servicer = '';
    public $assumable_fee_type = '$';
    public $assumable_fee_amount = '';
    public $assumption_fee_responsibility = ''; // A6.31-A6.34: who pays the assumption fee (Buyer/Seller/Split)
    public $assumable_occupancy_requirement = '';
    public $assumable_occupancy_other = '';
    public $max_monthly_payment = '';
    public $gap_payment_type = '$';
    public $gap_payment_amount = '';
    public $exchange_item = [];
    public $other_exchange_item = '';
    public $exchange_item_value = '';
    public $exchange_item_condition = '';
    public $additional_cash = '';
    public $value_determination = '';
    public $exchange_transfer_method = '';
    public $exchange_liens_disclosure = '';
    public $exchange_liens_details = '';
    public $exchange_inspection_rights = '';
    public $lease_option_price = '';
    public $lease_option_terms = '';
    public $lease_option_duration = '';
    public $lease_option_payment = '';
    public $lease_option_conditions = '';
    public $has_option_fee = '';
    public $option_fee_amount = '';
    public $lease_option_fee_credit = '';
    public $lease_option_fee_credit_percentage = '';
    public $lease_option_maintenance = '';
    public $lease_option_extension_terms = '';
    public $lease_purchase_price = '';
    public $lease_purchase_terms = '';
    public $lease_purchase_duration = '';
    public $lease_purchase_payment = '';
    public $lease_purchase_conditions = '';
    public $lease_purchase_rent_credit = '';
    public $lease_purchase_rent_credit_amount = '';
    public $lease_purchase_deposit = '';
    public $lease_purchase_maintenance = '';
    public $lease_purchase_extension_terms = '';
    public $seller_amortization_type = '';
    public $seller_amortization_other = '';
    public $seller_payment_frequency = '';
    public $seller_payment_frequency_other = '';
    public $crypto_transfer_timing = '';
    public $crypto_transfer_timing_other = '';
    public $crypto_exchange_method = '';
    public $crypto_custodian_wallet = '';
    public $crypto_transaction_fees = '';
    public $cryptocurrency_type = '';
    public $crypto_percentage = '';
    public $cash_percentage_crypto = '';
    public $nft_description = '';
    public $nft_percentage = '';
    public $cash_percentage_nft = '';
    public $nft_gas_fees = '';
    public $nft_transfer_method = '';
    public $nft_valuation_method = '';
    public $showPaymentAssumptions = false;
    public $initial_deposit_type = '$';
    public $initial_deposit_requested = '';
    public $initial_deposit_timeframe = '';
    public $initial_deposit_timeframe_other = '';
    public $additional_deposit_type = '$';
    public $additional_deposit_requested = '';
    public $additional_deposit_timeframe = '';
    public $additional_deposit_timeframe_other = '';
    public $escrow_agent_preference = '';
    public $preferred_inspection_period = '';
    public $inspection_contingency_preference = '';
    public $appraisal_contingency_preference = '';
    public $appraisal_contingency_period = '';
    public $financing_contingency_preference = '';
    public $financing_contingency_period = '';
    public $sale_of_buyer_property_contingency = '';
    public $sale_of_buyer_property_period = '';
    public $seller_contribution_credit_offered = '';
    public $seller_contribution_amount_details = '';
    public $possession_preference = '';
    public $possession_details = '';
    public $included_personal_property = '';
    public $excluded_items = '';
    public $home_warranty_offered = '';
    public $home_warranty_amount_details = '';
    public $hoa_condo_association_terms = '';
    public $additional_seller_sale_terms = '';
    public $payment_down_payment_pct = '';
    public $payment_interest_rate = '';
    public $payment_loan_term = '';
    public $payment_annual_property_taxes = '';
    public $payment_monthly_insurance = '';
    public $payment_hoa_fee_amount = '';
    public $payment_hoa_fee_frequency = '';
    public $payment_pmi_rate = '';
    public $payment_show_buydown_options = true;

    // ─── Sale Terms tab behaviour ────────────────────────────────────────────
    //
    // The $ / % toggles the canonical partial drives. Create and Edit had
    // byte-identical copies of all four; the differing methods
    // (updatedOfferedFinancing and the sale-provision handlers) are NOT moved,
    // because they genuinely differ between the two and unifying them would
    // change one flow's behaviour.

    public function setDownPaymentType($type)
    {
        $this->down_payment_type = $type;
    }

    public function setSellerFinancingType($type)
    {
        $this->seller_financing_type = $type;
    }

    public function setGapPaymentType($type)
    {
        $this->gap_payment_type = $type;
    }

    public function setAssignmentFeeType($type)
    {
        $this->assignment_fee_type = $type;
    }

    /**
     * Money inputs arrive comma-grouped ("1,250,000") and must be stored bare.
     *
     * SellerOfferListing and SellerOfferListingEdit each already declare an
     * identical stripCommas(); a method a class declares itself takes precedence
     * over the trait's, so both keep running their own copy and nothing about
     * them changes. This declaration exists for SellerMlsQuickImport, which had
     * no need for one until it started writing money fields through
     * saveSellerSaleTermsMeta().
     */
    protected function stripCommas($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace(',', '', $value);
    }
}

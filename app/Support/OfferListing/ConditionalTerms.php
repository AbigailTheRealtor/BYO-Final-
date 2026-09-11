<?php

namespace App\Support\OfferListing;

/**
 * The rules a listing page follows when it prints a term that has follow-up
 * questions underneath it.
 *
 * WHY A CLASS AND NOT FOUR BLADE CLOSURES
 * ---------------------------------------
 * "Your Terms" is one field set with one storage shape, written by three entry
 * paths (manual Create, manual Edit, MLS Quick Import) and read by the seller
 * and landlord listing pages. The parent/child rule — show a follow-up answer
 * only when the parent selection makes it applicable AND the listing actually
 * holds an answer — was previously spelled out inline at each of the ~40 places
 * a branch is rendered, in slightly different words each time. That is how the
 * page came to show an Assumable Mortgage block for a cash-only listing whose
 * seller had once, briefly, selected "Assumable": the condition asked
 * `$hasAssumable || $str('assumable_loan_type')`, so ANY surviving child value
 * re-opened a branch the seller had since closed.
 *
 * The rule now lives here, once:
 *
 *   THE PARENT DECIDES WHETHER A BRANCH IS SHOWN AT ALL.
 *   A child value alone never re-opens a branch. A stale answer left behind by
 *   a previous parent selection is data we still hold and deliberately do not
 *   publish — the seller's current answer is the one the page states.
 *
 *   THE CHILD DECIDES WHETHER ITS OWN ROW IS SHOWN.
 *   Null, empty string, whitespace, an empty array and an unanswered select all
 *   render nothing, so an applicable branch never prints a blank row.
 *
 * Nothing here reads or writes storage. It is display logic only, and the
 * canonical fields keep their values whatever it decides.
 */
final class ConditionalTerms
{
    /**
     * Toggles that open a panel of OPTIONAL inputs without asking anything.
     *
     * "Estimated Payment Assumptions" is a collapsible card: collapsing it hides
     * the inputs and leaves their values in force (they pre-fill the public
     * listing's payment calculator). It is therefore not a parent — nothing under
     * it becomes inapplicable when it is closed — and it is never an answer.
     */
    public const DISCLOSURE_TOGGLES = ['showPaymentAssumptions'];

    /*
     * WHICH ANSWER OPENS WHICH FOLLOW-UP QUESTION.
     *
     * The rule above says the parent decides. These lists name the parents: every
     * Your Terms field the canonical tab asks ONLY after another answer, and the
     * answer(s) that open it. They are read off the canonical partials
     * (offer-seller-tabs/…/seller-terms, offer-landlord-tabs/…/lease-terms) — the
     * `wire:ignore.self` sections, the `@if` blocks and the Alpine / derived
     * reveals that wrap each field — and MlsQuickImportReviewPresentationTest
     * re-derives them from that markup in BOTH directions: a follow-up added to
     * the tab without a line here, or a line here the tab does not back, fails
     * the build. They are a transcription of the form, not a second opinion.
     *
     * Shape: field => list of ALTERNATIVES (any one opens it); an alternative is a
     * list of CLAUSES (all must hold); a clause is [parent field, operator,
     * answers]. A parent that is itself a follow-up must also be open, so a closed
     * branch closes everything beneath it however deep.
     *
     *   is       the parent's answer is — or, for a multi-select, includes — one of `answers`
     *   not      the parent's answer is none of `answers`
     *   other    the parent chose "Other" and this field is the text typed for it;
     *            it is published INSIDE the parent's answer (withOther()), never as
     *            a row of its own
     *   accepts  a contingency preference whose seller-facing label is one of
     *            `answers` — the tab's own $__showsPeriod() test
     *
     * Where the listing pages gate a branch they already use chose() on the same
     * parent and option — the financing sections, the assignment follow-ups,
     * prepayment and balloon. They do not read these lists yet, and some of their
     * rows still publish a follow-up without asking its parent (Occupied Until,
     * the contingency periods, the Seller Financing purchase price and down
     * payment, the landlord leasing-space and storage rows). Moving the listing
     * pages onto these lists is a listing-page change and is not made here.
     */

    private const FIN_ASSUMABLE = [[['offered_financing', 'is', ['Assumable']]]];
    private const FIN_CRYPTO    = [[['offered_financing', 'is', ['Cryptocurrency']]]];
    private const FIN_EXCHANGE  = [[['offered_financing', 'is', ['Exchange/Trade']]]];
    private const FIN_LEASE_OPT = [[['offered_financing', 'is', ['Lease Option']]]];
    private const FIN_LEASE_PUR = [[['offered_financing', 'is', ['Lease Purchase']]]];
    private const FIN_NFT       = [[['offered_financing', 'is', ['Non-Fungible Token (NFT)']]]];
    private const FIN_SELLER    = [[['offered_financing', 'is', ['Seller Financing']]]];
    private const BIDDING       = [[['auction_type', 'is', ['Bidding Period']]]];
    private const NOT_BIDDING   = [[['auction_type', 'not', ['Bidding Period']]]];

    private const SELLER_FOLLOW_UPS = [
        // Special Sale Provision
        'sale_provision_other'      => [[['sale_provision', 'other', []]]],
        'sale_provision_assignment' => [[['sale_provision', 'is', ['Assignment Contract']]]],
        'assignment_fee_type'       => [[['sale_provision_assignment', 'is', ['Yes']]]],
        // The amount input is rendered only once a fee structure is chosen.
        'assignment_fee_amount'     => [[['sale_provision_assignment', 'is', ['Yes']], ['assignment_fee_type', 'is', ['$', '%']]]],

        // Occupancy — the whole question is withheld for Vacant Land.
        'occupant_status' => [[['property_type', 'not', ['Vacant Land']]]],
        'occupant_tenant' => [[['occupant_status', 'is', ['Tenant']]]],

        // Pricing follows the listing method.
        'starting_price' => self::BIDDING,
        'reserve_price'  => self::BIDDING,
        'buy_now_price'  => self::BIDDING,
        'maximum_budget' => self::NOT_BIDDING,

        // Offered Financing/Currency
        'other_financing' => [[['offered_financing', 'other', []]]],

        'assumable_terms'                 => self::FIN_ASSUMABLE,
        'assumable_loan_type'             => self::FIN_ASSUMABLE,
        'max_assumable_rate'              => self::FIN_ASSUMABLE,
        'max_monthly_payment'             => self::FIN_ASSUMABLE,
        'assumable_monthly_escrow'        => self::FIN_ASSUMABLE,
        'outstanding_balance'             => self::FIN_ASSUMABLE,
        'gap_payment_type'                => self::FIN_ASSUMABLE,
        'gap_payment_amount'              => self::FIN_ASSUMABLE,
        'assumable_loan_term_remaining'   => self::FIN_ASSUMABLE,
        'assumable_loan_origination_date' => self::FIN_ASSUMABLE,
        'assumable_loan_servicer'         => self::FIN_ASSUMABLE,
        'assumable_fee_type'              => self::FIN_ASSUMABLE,
        'assumable_fee_amount'            => self::FIN_ASSUMABLE,
        'assumption_fee_responsibility'   => self::FIN_ASSUMABLE,
        'assumable_occupancy_requirement' => self::FIN_ASSUMABLE,
        'assumable_occupancy_other'       => [[['assumable_occupancy_requirement', 'other', []]]],

        'cryptocurrency_type'          => self::FIN_CRYPTO,
        'crypto_percentage'            => self::FIN_CRYPTO,
        'cash_percentage_crypto'       => self::FIN_CRYPTO,
        'crypto_exchange_method'       => self::FIN_CRYPTO,
        'crypto_custodian_wallet'      => self::FIN_CRYPTO,
        'crypto_transaction_fees'      => self::FIN_CRYPTO,
        'crypto_transfer_timing'       => self::FIN_CRYPTO,
        'crypto_transfer_timing_other' => [[['crypto_transfer_timing', 'other', []]]],

        'exchange_item'              => self::FIN_EXCHANGE,
        'other_exchange_item'        => [[['exchange_item', 'other', []]]],
        'exchange_item_value'        => self::FIN_EXCHANGE,
        'exchange_item_condition'    => self::FIN_EXCHANGE,
        'additional_cash'            => self::FIN_EXCHANGE,
        'value_determination'        => self::FIN_EXCHANGE,
        'exchange_transfer_method'   => self::FIN_EXCHANGE,
        'exchange_liens_disclosure'  => self::FIN_EXCHANGE,
        'exchange_liens_details'     => [[['exchange_liens_disclosure', 'is', ['Yes']]]],
        'exchange_inspection_rights' => self::FIN_EXCHANGE,

        'lease_option_price'                 => self::FIN_LEASE_OPT,
        'lease_option_payment'               => self::FIN_LEASE_OPT,
        'lease_option_duration'              => self::FIN_LEASE_OPT,
        'has_option_fee'                     => self::FIN_LEASE_OPT,
        'option_fee_amount'                  => [[['has_option_fee', 'is', ['Yes']]]],
        'lease_option_fee_credit'            => self::FIN_LEASE_OPT,
        'lease_option_fee_credit_percentage' => [[['lease_option_fee_credit', 'is', ['Partial']]]],
        'lease_option_conditions'            => self::FIN_LEASE_OPT,
        'lease_option_terms'                 => self::FIN_LEASE_OPT,
        'lease_option_maintenance'           => self::FIN_LEASE_OPT,
        'lease_option_extension_terms'       => self::FIN_LEASE_OPT,

        'lease_purchase_price'              => self::FIN_LEASE_PUR,
        'lease_purchase_payment'            => self::FIN_LEASE_PUR,
        'lease_purchase_duration'           => self::FIN_LEASE_PUR,
        'lease_purchase_rent_credit'        => self::FIN_LEASE_PUR,
        'lease_purchase_rent_credit_amount' => [[['lease_purchase_rent_credit', 'is', ['Yes', 'Partial']]]],
        'lease_purchase_deposit'            => self::FIN_LEASE_PUR,
        'lease_purchase_conditions'         => self::FIN_LEASE_PUR,
        'lease_purchase_terms'              => self::FIN_LEASE_PUR,
        'lease_purchase_maintenance'        => self::FIN_LEASE_PUR,
        'lease_purchase_extension_terms'    => self::FIN_LEASE_PUR,

        'nft_description'      => self::FIN_NFT,
        'nft_percentage'       => self::FIN_NFT,
        'cash_percentage_nft'  => self::FIN_NFT,
        'nft_valuation_method' => self::FIN_NFT,
        'nft_transfer_method'  => self::FIN_NFT,
        'nft_gas_fees'         => self::FIN_NFT,

        'purchase_price'                 => self::FIN_SELLER,
        'down_payment_type'              => self::FIN_SELLER,
        'down_payment_amount'            => self::FIN_SELLER,
        'seller_financing_type'          => self::FIN_SELLER,
        'seller_down_payment_amount'     => self::FIN_SELLER,
        'interest_rate'                  => self::FIN_SELLER,
        'loan_duration'                  => self::FIN_SELLER,
        'prepayment_penalty'             => self::FIN_SELLER,
        'prepayment_penalty_amount'      => [[['prepayment_penalty', 'is', ['Yes']]]],
        'balloon_payment'                => self::FIN_SELLER,
        'balloon_payment_amount'         => [[['balloon_payment', 'is', ['Yes']]]],
        'balloon_payment_date'           => [[['balloon_payment', 'is', ['Yes']]]],
        'seller_amortization_type'       => self::FIN_SELLER,
        'seller_amortization_other'      => [[['seller_amortization_type', 'other', []]]],
        'seller_payment_frequency'       => self::FIN_SELLER,
        'seller_payment_frequency_other' => [[['seller_payment_frequency', 'other', []]]],
        'seller_late_fee_amount'         => self::FIN_SELLER,

        // Deposits
        'initial_deposit_timeframe_other'    => [[['initial_deposit_timeframe', 'other', []]]],
        'additional_deposit_timeframe_other' => [[['additional_deposit_timeframe', 'other', []]]],

        // Contingency periods — asked only when the contingency is accepted or negotiable.
        'preferred_inspection_period'   => [[['inspection_contingency_preference', 'accepts', ['Accepted', 'Negotiable']]]],
        'appraisal_contingency_period'  => [[['appraisal_contingency_preference', 'accepts', ['Accepted', 'Negotiable']]]],
        'financing_contingency_period'  => [[['financing_contingency_preference', 'accepts', ['Accepted', 'Negotiable']]]],
        'sale_of_buyer_property_period' => [[['sale_of_buyer_property_contingency', 'accepts', ['Accepted', 'Negotiable']]]],

        // Seller Sale Terms details
        'seller_contribution_amount_details' => [[['seller_contribution_credit_offered', 'is', ['Yes']]]],
        'possession_details'                 => [[['possession_preference', 'is', ['Seller Rent Back', 'Other']]]],
        'home_warranty_amount_details'       => [[['home_warranty_offered', 'is', ['Yes']]]],
    ];

    private const RESIDENTIAL = ['property_type', 'is', ['Residential Property']];
    private const COMMERCIAL  = ['property_type', 'is', ['Commercial Property']];
    private const ENTIRE_RES  = ['leasing_spaces', 'is', ['Entire Property', 'Accessory Unit / Guest Suite (ADU)']];
    private const ENTIRE_COM  = ['leasing_spaces', 'is', ['Entire Property']];
    private const SINGLE_ROOM = ['leasing_spaces', 'is', ['Single Room']];

    /** Asked for every leasing space the tab offers, residential and commercial. */
    private const ANY_SPACE = [
        [self::RESIDENTIAL, ['leasing_spaces', 'is', ['Entire Property', 'Accessory Unit / Guest Suite (ADU)', 'Single Room']]],
        [self::COMMERCIAL, ['leasing_spaces', 'is', ['Entire Property', 'Single Room']]],
    ];

    /** Asked only when a single room is being let. */
    private const SINGLE_ROOM_ANY = [[self::RESIDENTIAL, self::SINGLE_ROOM], [self::COMMERCIAL, self::SINGLE_ROOM]];

    private const LANDLORD_FOLLOW_UPS = [
        'occupant_tenant' => [[['occupant_status', 'is', ['Tenant', 'Owner']]]],

        // Leasing space
        'restrictions'              => self::ANY_SPACE,
        'maintenance_by'            => self::ANY_SPACE,
        'maintenance_response_time' => self::ANY_SPACE,

        'guests_allowed'        => self::SINGLE_ROOM_ANY,
        'common_areas_access'   => self::SINGLE_ROOM_ANY,
        'utilities'             => self::SINGLE_ROOM_ANY,
        'common_areas_cleaning' => self::SINGLE_ROOM_ANY,
        'bathroom_facilities'   => self::SINGLE_ROOM_ANY,
        'room_size'             => self::SINGLE_ROOM_ANY,

        'included_storage_space_res_both'   => [[self::RESIDENTIAL, self::ENTIRE_RES]],
        'storage_space_res_both'            => [[['included_storage_space_res_both', 'is', ['Yes']]]],
        'included_storage_space_res_single' => [[self::RESIDENTIAL, self::SINGLE_ROOM]],
        'storage_space_res_single'          => [[['included_storage_space_res_single', 'is', ['Yes']]]],
        'included_storage_space_com_entire' => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'storage_space_com_entire'          => [[['included_storage_space_com_entire', 'is', ['Yes']]]],
        'included_storage_space_com_single' => [[self::COMMERCIAL, self::SINGLE_ROOM]],
        'storage_space_com_single'          => [[['included_storage_space_com_single', 'is', ['Yes']]]],

        'shared_amenities'    => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'building_hours'      => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'access_24_7'         => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'zoning_allows'       => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'space_features'      => [[self::COMMERCIAL, self::ENTIRE_COM]],
        'neighboring_tenants' => [[self::COMMERCIAL, self::ENTIRE_COM]],

        // Commercial utilities and lease structure
        'tenant_pays'       => [[self::COMMERCIAL]],
        'other_tenant_pays' => [[['tenant_pays', 'other', []]]],
        'owner_pays'        => [[self::COMMERCIAL]],
        'other_owner_pays'  => [[['owner_pays', 'other', []]]],
        'terms_of_lease'    => [[self::COMMERCIAL]],
        'custom_lease_term' => [[['terms_of_lease', 'other', []]]],

        // Pricing follows the listing method.
        'starting_rent'         => self::BIDDING,
        'reserve_rent'          => self::BIDDING,
        'lease_now_price'       => self::BIDDING,
        'desired_rental_amount' => self::NOT_BIDDING,

        'other_lease_term' => [[['desired_lease_length', 'other', []]]],

        // Pet fee. pet_fee_type is an ANSWER, not a unit: it is the parent of the
        // amount (every fee type that carries one) and of the free-text details
        // (Other only). The details are a separate row on the listing page, so
        // they are 'is', not 'other'.
        'pet_fee_amount' => [[['pet_fee_type', 'is', ['One Time Fee Refundable', 'Non Refundable', 'Monthly Pet Fee', 'Other']]]],
        'pet_fee_other'  => [[['pet_fee_type', 'is', ['Other']]]],

        'renewal_option_details' => [[['renewal_option_offered', 'is', ['Yes', 'Negotiable']]]],

        'commercial_lease_type'             => [[self::COMMERCIAL]],
        'commercial_lease_type_other'       => [[['commercial_lease_type', 'other', []]]],
        'cam_nnn_additional_rent_charges'   => [[self::COMMERCIAL]],
        'rent_escalation_terms'             => [[self::COMMERCIAL]],
        'tenant_improvement_buildout_terms' => [[self::COMMERCIAL]],
        'permitted_use_restrictions'        => [[self::COMMERCIAL]],
        'signage_rights'                    => [[self::COMMERCIAL]],
        'commercial_parking_terms'          => [[self::COMMERCIAL]],
        'personal_guarantee_requirement'    => [[self::COMMERCIAL]],
        'commercial_approval_conditions'    => [[self::COMMERCIAL]],

        'rent_includes'      => [[self::RESIDENTIAL]],
        'other_rent_include' => [[['rent_includes', 'other', []]]],
    ];

    /**
     * The follow-up list for a role; empty for a role that has none.
     *
     * @return array<string, list<list<array{0:string, 1:string, 2:list<string>}>>>
     */
    public static function followUps(string $role): array
    {
        return match ($role) {
            'seller'   => self::SELLER_FOLLOW_UPS,
            'landlord' => self::LANDLORD_FOLLOW_UPS,
            default    => [],
        };
    }

    /**
     * Is this field's question open, given the current answers?
     *
     * A field that is not a follow-up is always open. A follow-up is open when
     * one of its alternatives holds in full AND every parent it names is itself
     * open. `$read` maps a field name to the value it currently holds.
     *
     * @param callable(string):mixed $read
     */
    public static function applies(string $role, string $field, callable $read): bool
    {
        return self::opens(self::followUps($role), $field, $read, []);
    }

    /**
     * Free-text fields that exist only to say what "Other" meant, and the parent
     * whose answer they complete.
     *
     * @return array<string, string> write-in field => parent field
     */
    public static function writeIns(string $role): array
    {
        $out = [];

        foreach (self::followUps($role) as $field => $alternatives) {
            foreach ($alternatives as $clauses) {
                foreach ($clauses as $clause) {
                    if ($clause[1] === 'other') {
                        $out[$field] = $clause[0];
                    }
                }
            }
        }

        return $out;
    }

    /** @param array<string, array> $map */
    private static function opens(array $map, string $field, callable $read, array $seen): bool
    {
        if (! isset($map[$field])) {
            return true;
        }

        // A field reached again along its own chain means the list is broken.
        // Closed is the safe reading: an unpublished answer is recoverable, a
        // published stale one is what this class exists to prevent.
        if (isset($seen[$field])) {
            return false;
        }

        $seen[$field] = true;

        foreach ($map[$field] as $clauses) {
            foreach ($clauses as [$parent, $op, $answers]) {
                if (! self::holds($read($parent), $op, $answers)
                    || ! self::opens($map, $parent, $read, $seen)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** @param list<string> $answers */
    private static function holds(mixed $value, string $op, array $answers): bool
    {
        return match ($op) {
            'is'      => self::choseAny($value, $answers),
            'not'     => ! self::choseAny($value, $answers),
            'other'   => self::chose($value, 'Other'),
            'accepts' => in_array(
                \App\Helpers\ContingencyOptionHelper::sellerDisplay(self::text($value)),
                $answers,
                true
            ),
            // An operator nobody defined opens nothing.
            default   => false,
        };
    }

    /**
     * Did the seller/landlord actually choose this option?
     *
     * Multi-selects arrive as arrays from a live component, as JSON strings from
     * EAV meta, and occasionally as a bare string from an older row. All three
     * mean the same thing and all three are accepted; nothing else is.
     */
    public static function chose(mixed $selection, string $option): bool
    {
        foreach (self::toList($selection) as $value) {
            if (strcasecmp(trim($value), $option) === 0) {
                return true;
            }
        }

        return false;
    }

    /** True when any of the given options was chosen. */
    public static function choseAny(mixed $selection, array $options): bool
    {
        foreach ($options as $option) {
            if (self::chose($selection, (string) $option)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A multi-select's answers with "Other" replaced by what was typed for it.
     *
     * The "Other" free-text box is the most common sub-question in this form,
     * and printing the literal word "Other" instead of the answer is the same
     * bug in forty places. When nothing was typed the literal option survives —
     * the seller did choose it, and we do not know what they meant.
     *
     * @return list<string>
     */
    public static function withOther(mixed $selection, mixed $otherText): array
    {
        $other = self::text($otherText);

        return array_values(array_map(
            static fn (string $value) => ($other !== '' && strcasecmp(trim($value), 'Other') === 0)
                ? $other
                : $value,
            self::toList($selection),
        ));
    }

    /**
     * A single-select's answer with "Other" replaced by what was typed.
     *
     * The scalar sibling of withOther(). Returns '' when nothing was answered,
     * so it drops out of a row helper on its own.
     */
    public static function valueWithOther(mixed $selection, mixed $otherText): string
    {
        $value = self::text($selection);
        $other = self::text($otherText);

        return (strcasecmp($value, 'Other') === 0 && $other !== '') ? $other : $value;
    }

    /**
     * Is this child answer worth printing?
     *
     * The second half of the rule: an applicable branch still prints nothing for
     * a question the user skipped.
     */
    public static function answered(mixed $value): bool
    {
        // Routed through toList() rather than a truthiness test so the stored
        // shapes agree: an empty multi-select reaches a view as [], as the string
        // '[]', or as ''. All three mean "not answered", and a plain emptiness
        // check on the string form would publish an empty section heading.
        return self::toList($value) !== [];
    }

    /**
     * A money-or-percentage answer, formatted by the $ / % toggle beside it.
     *
     * Deposits, assignment fees, assumption fees, gap payments and seller
     * financing all pair an amount with a type control, and the page used to
     * format several of them as dollars unconditionally — so a seller asking for
     * a 3% initial deposit published a request for $3.
     *
     * Returns null when there is no amount, which is what a row helper needs in
     * order to omit the row entirely.
     */
    public static function amount(mixed $value, mixed $type = null): ?string
    {
        $raw = self::text($value);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9.]/', '', $raw);

        if ($digits === '' || ! is_numeric($digits)) {
            return $raw;
        }

        $number = (float) $digits;

        if (self::text($type) === '%') {
            return (floor($number) == $number ? (string) (int) $number : (string) $number) . '%';
        }

        return '$' . number_format($number, 0);
    }

    /**
     * Normalise any of the three stored shapes into a list of non-empty strings.
     *
     * @return list<string>
     */
    public static function toList(mixed $selection): array
    {
        if ($selection === null) {
            return [];
        }

        if (is_string($selection)) {
            $trimmed = trim($selection);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            // Meta can hold a doubly-encoded array; one more pass is enough and
            // anything still not an array is treated as the single answer it is.
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $selection = is_array($decoded) ? $decoded : [$trimmed];
        }

        if (! is_array($selection)) {
            $selection = [$selection];
        }

        $out = [];

        foreach ($selection as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $text = self::text($value);

            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function text(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
            return is_bool($value) ? ($value ? 'Yes' : '') : '';
        }

        return trim((string) $value);
    }
}

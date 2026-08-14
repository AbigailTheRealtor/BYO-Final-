<?php

namespace App\Http\Livewire\OfferListing\QuickImport;

/**
 * Seller's shortened MLS creation path.
 *
 * Everything here is the Seller's half of the split the whole feature is built
 * on: MLS supplies the property facts, BidYourOffer asks how the seller wants to
 * transact. So this class contains no property questions at all — only the sale
 * terms the MLS does not answer.
 *
 * EVERY FIELD BELOW ALREADY EXISTS.
 * Each one names a public property on {@see \App\Http\Livewire\OfferListing\Seller\SellerOfferListing}
 * and is stored under the same EAV meta key, so a quick-imported listing and a
 * manually created one are the same listing to every downstream reader — the
 * view page, search, matching, the bid wizards, Ask AI. Nothing here is a new
 * field, a renamed field, or a parallel representation of an existing one.
 *
 * The financing vocabulary in particular is the canonical list from
 * `offer-seller-tabs/commission-based/seller-terms.blade.php`, copied exactly —
 * including the spellings that are easy to get subtly wrong ("Seller Financing",
 * not "Seller Finance"; "Exchange/Trade", not "Trade"). A near-miss here would
 * write a value the rest of the product does not recognise, which is worse than
 * not asking the question.
 */
class SellerMlsQuickImport extends MlsQuickImportComponent
{
    public function role(): string
    {
        return 'seller';
    }

    /**
     * "Desired Sale Price" — the Seller's asking price.
     *
     * `maximum_budget` is the meta key behind that input, which reads oddly but
     * is the existing storage key and is deliberately not renamed here. See the
     * note on the same mapping in MlsFieldMap::seller().
     */
    public function priceField(): string
    {
        return 'maximum_budget';
    }

    public function questionSchema(): array
    {
        return [
            'maximum_budget' => [
                'label'    => 'Desired Sale Price',
                'type'     => 'money',
                'section'  => 'Price',
                'required' => true,
                'help'     => 'Pre-filled from the MLS list price. Change it if you want to ask something different.',
            ],

            'offered_financing' => [
                'label'    => 'Financing You Will Accept',
                'type'     => 'multiselect',
                'section'  => 'Financing',
                'required' => true,
                'options'  => [
                    'Cash',
                    'Conventional',
                    'FHA',
                    'VA',
                    'USDA',
                    'Jumbo',
                    'Non-QM',
                    'No-Doc',
                    'Assumable',
                    'Seller Financing',
                    'Lease Option',
                    'Lease Purchase',
                    'Exchange/Trade',
                    'Cryptocurrency',
                    'Non-Fungible Token (NFT)',
                    'Other',
                ],
                'help'     => 'Select every method you are open to. MLS does not record this.',
            ],
            'other_financing' => [
                'label'   => 'Other Financing / Currency',
                'type'    => 'text',
                'section' => 'Financing',
                'when'    => 'offered_financing:Other',
            ],

            'initial_deposit_requested' => [
                'label'   => 'Earnest Money Requested',
                'type'    => 'money',
                'section' => 'Deposits',
            ],
            'initial_deposit_timeframe' => [
                'label'   => 'Earnest Money Due Within',
                'type'    => 'text',
                'section' => 'Deposits',
                'help'    => 'e.g. 3 business days after acceptance.',
            ],
            'escrow_agent_preference' => [
                'label'   => 'Preferred Escrow / Title Agent',
                'type'    => 'text',
                'section' => 'Deposits',
            ],

            'preferred_inspection_period' => [
                'label'   => 'Preferred Inspection Period (Days)',
                'type'    => 'number',
                'section' => 'Contingencies',
            ],
            'inspection_contingency_preference' => [
                'label'   => 'Inspection Contingency',
                'type'    => 'select',
                'section' => 'Contingencies',
                'options' => ['Required', 'Preferred', 'Waived Preferred', 'Negotiable'],
            ],
            'appraisal_contingency_preference' => [
                'label'   => 'Appraisal Contingency',
                'type'    => 'select',
                'section' => 'Contingencies',
                'options' => ['Required', 'Preferred', 'Waived Preferred', 'Negotiable'],
            ],
            'financing_contingency_preference' => [
                'label'   => 'Financing Contingency',
                'type'    => 'select',
                'section' => 'Contingencies',
                'options' => ['Required', 'Preferred', 'Waived Preferred', 'Negotiable'],
            ],

            'seller_contribution_credit_offered' => [
                'label'   => 'Seller Contribution / Credit Offered',
                'type'    => 'select',
                'section' => 'Concessions',
                'options' => ['Yes', 'No', 'Negotiable'],
            ],
            'seller_contribution_amount_details' => [
                'label'   => 'Contribution Details',
                'type'    => 'text',
                'section' => 'Concessions',
                'when'    => 'seller_contribution_credit_offered:Yes',
            ],
            'home_warranty_offered' => [
                'label'   => 'Home Warranty Offered',
                'type'    => 'select',
                'section' => 'Concessions',
                'options' => ['Yes', 'No', 'Negotiable'],
            ],

            'possession_preference' => [
                'label'   => 'Possession',
                'type'    => 'select',
                'section' => 'Closing & Possession',
                'options' => ['At Closing', 'Post-Closing Occupancy', 'Negotiable'],
            ],
            'possession_details' => [
                'label'   => 'Possession Details',
                'type'    => 'text',
                'section' => 'Closing & Possession',
                'help'    => 'Leaseback or post-closing occupancy terms, if any.',
            ],

            'included_personal_property' => [
                'label'   => 'Items Included in the Sale',
                'type'    => 'textarea',
                'section' => 'Included & Excluded',
            ],
            'excluded_items' => [
                'label'   => 'Items Excluded from the Sale',
                'type'    => 'textarea',
                'section' => 'Included & Excluded',
            ],

            'additional_seller_sale_terms' => [
                'label'   => 'Any Other Terms',
                'type'    => 'textarea',
                'section' => 'Other',
            ],
        ];
    }
}

<?php

namespace App\Http\Livewire\OfferListing\QuickImport;

/**
 * Landlord's shortened MLS creation path.
 *
 * The same architecture as the Seller flow — MLS supplies the property, the
 * landlord supplies the transaction — with the questions a rental actually has.
 *
 * PARITY IS STRUCTURAL, NOT COSMETIC.
 * Landlord shares the flow, the ownership model, the gallery handling and the
 * review-before-publish step with Seller, and diverges only where the
 * transaction genuinely differs. In particular there is NO financing question
 * here. `offered_financing` is a sale concept; a tenant does not obtain a
 * mortgage to rent a property, and asking a landlord which loan types they
 * accept would be a Seller field forced onto a workflow it does not belong to.
 * The brief calls that out specifically and this is where it is honoured.
 *
 * Every field below already exists on
 * {@see \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing} under the
 * same EAV meta key, so a quick-imported rental is an ordinary landlord listing
 * to every downstream reader.
 */
class LandlordMlsQuickImport extends MlsQuickImportComponent
{
    public function role(): string
    {
        return 'landlord';
    }

    /**
     * The asking rent.
     *
     * `maximum_budget` is the existing meta key behind the landlord form's rent
     * input, matching the Seller side's storage key. Not renamed here — a second
     * spelling would be a second field to every downstream reader.
     */
    public function priceField(): string
    {
        return 'maximum_budget';
    }

    public function questionSchema(): array
    {
        return [
            'maximum_budget' => [
                'label'    => 'Monthly Rent',
                'type'     => 'money',
                'section'  => 'Rent',
                'required' => true,
                'help'     => 'Pre-filled from the MLS lease amount. Change it if you want to ask something different.',
            ],
            'lease_amount_frequency' => [
                'label'   => 'Rent Frequency',
                'type'    => 'select',
                'section' => 'Rent',
                'options' => ['Monthly', 'Weekly', 'Annually', 'Seasonal'],
            ],

            'desired_lease_length' => [
                'label'    => 'Lease Lengths You Will Accept',
                'type'     => 'multiselect',
                'section'  => 'Lease Terms',
                'required' => true,
                'options'  => [
                    'Month-to-Month',
                    '3 Months',
                    '6 Months',
                    '9 Months',
                    '1 Year',
                    '2 Years',
                    '3 Years',
                    'Negotiable',
                ],
            ],
            'lease_available_date' => [
                'label'   => 'Available From',
                'type'    => 'date',
                'section' => 'Lease Terms',
            ],
            'renewal_option_offered' => [
                'label'   => 'Renewal Option Offered',
                'type'    => 'select',
                'section' => 'Lease Terms',
                'options' => ['Yes', 'No', 'Negotiable'],
            ],

            'security_deposit_amount' => [
                'label'   => 'Security Deposit',
                'type'    => 'money',
                'section' => 'Move-In Funds',
            ],
            'last_month_rent_required' => [
                'label'   => 'Last Month\'s Rent Required',
                'type'    => 'select',
                'section' => 'Move-In Funds',
                'options' => ['Yes', 'No'],
            ],
            'total_move_in_funds_required' => [
                'label'   => 'Total Move-In Funds',
                'type'    => 'money',
                'section' => 'Move-In Funds',
            ],

            'pet_policy' => [
                'label'   => 'Pet Policy',
                'type'    => 'select',
                'section' => 'Occupancy & Pets',
                'options' => ['No Pets', 'Cats Only', 'Dogs Only', 'Cats and Dogs', 'Case by Case'],
                'help'    => 'MLS pet data is inconsistent, so this is asked rather than imported.',
            ],
            'pet_fee_amount' => [
                'label'   => 'Pet Fee / Deposit',
                'type'    => 'money',
                'section' => 'Occupancy & Pets',
                'when'    => 'pet_policy:not:No Pets',
            ],
            'number_of_occupants_allowed' => [
                'label'   => 'Maximum Occupants',
                'type'    => 'number',
                'section' => 'Occupancy & Pets',
            ],

            'parking_terms' => [
                'label'   => 'Parking Terms',
                'type'    => 'text',
                'section' => 'Responsibilities',
            ],
            'll_maintenance_responsibility' => [
                'label'   => 'Maintenance Responsibility',
                'type'    => 'textarea',
                'section' => 'Responsibilities',
            ],
        ];
    }
}

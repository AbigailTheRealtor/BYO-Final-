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
     * `desired_rental_amount` — the SAME key the manual landlord wizard writes
     * (LandlordOfferListing::saveMeta('desired_rental_amount', …)) and the only
     * one the published landlord page reads, alongside starting_rent /
     * reserve_rent / lease_now_price.
     *
     * THIS WAS `maximum_budget`, AND THAT WAS THE BUG.
     * ------------------------------------------------
     * The previous docblock asserted `maximum_budget` was "the existing meta key
     * behind the landlord form's rent input". It is not. On the landlord side
     * `maximum_budget` is a separate field, and the landlord view reads it ZERO
     * times. So the rent a landlord typed was persisted somewhere nothing
     * displays, while the hero fell through to `desired_rental_amount` — which
     * the importer had filled with the MLS list price. A landlord entering
     * $4,321/mo published a page advertising $100,000/mo, taken from the sale
     * price of the property they imported.
     *
     * Copying the Seller side's key was the mistake: the two roles genuinely
     * differ here, and "matching the Seller storage key" is not a reason when
     * the landlord vocabulary already has its own.
     */
    public function priceField(): string
    {
        return 'desired_rental_amount';
    }

    /**
     * A list price may pre-fill the rent box ONLY when the record is a lease.
     *
     * On a RESO lease record (`PropertyType` "Residential Lease", "Commercial
     * Lease", …) ListPrice IS the periodic rent, so seeding it is a genuine
     * convenience. On a sale record the same field is a purchase price, and
     * putting it in a box labelled "Monthly Rent" invites exactly the outcome
     * that made this fix necessary.
     *
     * Anything this method cannot positively identify as a lease returns null —
     * the landlord types the rent. That is the fail-closed reading, and the cost
     * of being wrong in this direction is one number typed, against advertising
     * a property's sale price as its monthly rent.
     */
    protected function seededPrice(\App\Services\ListingImport\QuickImport\MlsQuickImportResult $result): ?string
    {
        $type = strtolower(trim((string) ($result->facts['property_type'] ?? '')));

        if ($type === '' || ! str_contains($type, 'lease')) {
            return null;
        }

        return parent::seededPrice($result);
    }

    public function questionSchema(): array
    {
        return [
            'desired_rental_amount' => [
                'label'    => 'Monthly Rent',
                'type'     => 'money',
                'section'  => 'Rent',
                'required' => true,
                'help'     => 'What you want to charge per month.',
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

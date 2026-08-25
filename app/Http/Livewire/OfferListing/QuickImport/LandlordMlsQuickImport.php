<?php

namespace App\Http\Livewire\OfferListing\QuickImport;

use App\Http\Livewire\OfferListing\Concerns\HasCanonicalPetFee;
use App\Http\Livewire\OfferListing\Concerns\LandlordLeasingTerms;

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
 * THE TERMS STEP IS THE MANUAL FLOW'S LEASING TERMS TAB.
 * ------------------------------------------------------
 * Not a copy of it, not a subset. `canonicalTermsPartial()` names the very blade
 * the manual Landlord Create and Edit screens render, and
 * {@see LandlordLeasingTerms} supplies the property surface it binds to.
 *
 * WHAT THIS REPLACES
 * ------------------
 * A hand-maintained questionSchema() of thirteen entries against a canonical tab
 * of sixty-two fields. Nine of the thirteen were canonical Leasing Terms; the
 * other four belong to other tabs entirely. Fifty-three canonical terms were
 * absent — among them smoking policy, subletting policy, occupant status,
 * utilities, maintenance responsibility and response time, renewal details, rent
 * escalation, storage, every commercial lease term, and the bidding-period rent
 * fields (starting_rent, reserve_rent, lease_now_price). A landlord arriving
 * through quick import was never asked any of them.
 *
 * THE RENT PROTECTION IS UNCHANGED AND MUST STAY THAT WAY.
 * priceField() is desired_rental_amount, seededPrice() refuses to pre-fill from
 * a non-lease record, and the rent remains required before review. Those three
 * together are what stop a sale ListPrice becoming a monthly rent. Rendering the
 * canonical tab does not relax any of them.
 */
class LandlordMlsQuickImport extends MlsQuickImportComponent
{
    use LandlordLeasingTerms;
    use HasCanonicalPetFee;  // pet_fee_amount / pet_fee_other are derived, not stored raw

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

    /**
     * The canonical Landlord Leasing Terms tab — the same partial
     * offer-landlord-listing.blade.php and offer-landlord-listing-edit.blade.php
     * include.
     */
    public function canonicalTermsPartial(): ?string
    {
        return 'livewire.offer-listing.offer-landlord-tabs.commission-based.lease-terms';
    }

    /**
     * Supplementary questions — NOT Leasing Terms.
     *
     * Each of these is a real Landlord field with a real meta key, but its
     * canonical home is a different tab: pet_policy and
     * number_of_occupants_allowed belong to Applicant Requirements,
     * parking_terms to Property Preferences, and desired_lease_length to the
     * Hire Landlord Agent flow. Quick Import has always asked them and dropping
     * them would lose answers landlords currently give, so they are kept — but
     * kept visibly apart from the canonical surface.
     *
     * This is not a Leasing Terms schema and must never become one. A parity
     * test asserts that nothing in here is a canonical Leasing Terms field; if a
     * term belongs on the tab, it goes on the tab.
     */
    public function questionSchema(): array
    {
        return [
            'desired_lease_length' => [
                'label'   => 'Lease Lengths You Will Accept',
                'type'    => 'multiselect',
                'section' => 'Other Details',
                'options' => [
                    'Month to Month',
                    '6 Months',
                    '1 Year',
                    '2 Years',
                    '3 Years',
                    'Other',
                ],
                'help'    => 'Asked here because the Leasing Terms tab does not carry this field.',
            ],
            'pet_policy' => [
                'label'   => 'Pet Policy',
                'type'    => 'select',
                'section' => 'Other Details',
                'options' => ['No Pets', 'Cats Only', 'Dogs Only', 'Cats and Dogs', 'Case by Case'],
                'help'    => 'Belongs to the Applicant Requirements tab.',
            ],
            'number_of_occupants_allowed' => [
                'label'   => 'Maximum Occupants',
                'type'    => 'number',
                'section' => 'Other Details',
                'help'    => 'Belongs to the Applicant Requirements tab.',
            ],
            'parking_terms' => [
                'label'   => 'Parking Terms',
                'type'    => 'text',
                'section' => 'Other Details',
                'help'    => 'Belongs to the Property Preferences tab.',
            ],
        ];
    }

    /**
     * Seed the MLS rent into the canonical rent property.
     *
     * The manual tab binds $desired_rental_amount directly, so the seeded figure
     * has to land there rather than in the schema-driven $terms bag. seededPrice()
     * has already refused to supply anything unless the record is a lease.
     */
    protected function applySeededPrice(string $seed): void
    {
        if ($this->desired_rental_amount === '' || $this->desired_rental_amount === null) {
            $this->desired_rental_amount = $seed;
        }
    }

    /**
     * Write the leasing terms exactly as the manual flows write them.
     */
    protected function persistCanonicalTerms(object $auction): void
    {
        $this->saveLandlordLeasingTermsMeta($auction);
    }

    /**
     * Required before review.
     *
     * The rent, and only the rent. This is the third leg of the sale-price-as-rent
     * protection: seededPrice() will not pre-fill it from a sale record, so
     * requiring it here is what forces the landlord to state a figure rather than
     * publishing with an empty or inherited one.
     *
     * It is a LISTING-DATA requirement, not a Leasing Terms rule — the canonical
     * publish rules (LandlordPublishValidation) impose no required rule on the
     * leasing terms themselves, and nothing here may grow into a second,
     * quick-import-only validation surface for them.
     *
     * @return list<string>
     */
    protected function missingCanonicalTerms(): array
    {
        return $this->missingRequiredListingData();
    }

    /** @return list<string> */
    private function missingRequiredListingData(): array
    {
        return trim((string) $this->desired_rental_amount) === ''
            ? ['Monthly Rent']
            : [];
    }

    /**
     * The terms summary on the review step.
     *
     * Reads the canonical field set, so a term added to the tab appears here
     * without anyone remembering to add it. Empty answers are omitted.
     *
     * @return array<string, string>
     */
    public function canonicalTermsReview(): array
    {
        $rows = [];

        foreach (static::landlordLeasingTermsFields() as $field) {
            $value = $this->{$field} ?? '';

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $rows[$this->humaniseTermField($field)] = $value;
        }

        return $rows;
    }

    /**
     * Field name → readable label for the review list.
     *
     * The canonical partial's labels live in markup rather than in a data
     * structure, so deriving the label from the field name keeps the review
     * honest about which stored key it is showing instead of inventing a second
     * label list that could disagree with the tab.
     */
    private function humaniseTermField(string $field): string
    {
        $label = str_replace('_', ' ', $field);
        $label = preg_replace('/\bcam nnn\b/i', 'CAM/NNN', $label);
        $label = preg_replace('/\bhoa\b/i', 'HOA', $label);
        $label = preg_replace('/\bcom\b/i', 'commercial', $label);
        $label = preg_replace('/\bres\b/i', 'residential', $label);
        $label = preg_replace('/\bll\b/i', 'Landlord', $label);
        $label = preg_replace('/\baccess 24 7\b/i', '24/7 access', $label);

        return ucfirst($label);
    }
}

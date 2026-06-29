<?php

namespace App\Http\Livewire\OfferListing\Concerns;

/**
 * BYO-H1 — Shared Landlord Offer-Listing publish validation.
 *
 * Single source of truth for the full required-field rules enforced when a
 * Landlord Offer Listing is PUBLISHED — used by both LandlordOfferListing::store()
 * (create) and LandlordOfferListingEdit::update() (edit publish). Drafts stay
 * intentionally lenient and never call these (saveDraft / saveDraftOnly).
 *
 * Previously these rules lived inline only in create's store(); edit's update()
 * validated only the nullable property-detail fields, so a user could edit a
 * published listing and re-publish with required contact / lease fields blank.
 */
trait LandlordPublishValidation
{
    /**
     * Full publish/submit validation rules (create store + edit update).
     */
    protected function getConditionalRules(): array
    {
        return [
            'first_name'           => 'required|string',
            'last_name'            => 'required|string',
            'phone_number'         => 'required|string',
            'email'                => 'required|email',
            'unit_address'         => 'nullable|string|max:100',
            'desired_lease_length' => 'required|array|min:1',
            'roof_type'                => 'nullable|array',
            'roof_type.*'              => 'string|in:Built-Up,Cement,Concrete,Membrane,Metal,Roof Over,Shake,Shingle,Slate,Tile,Other',
            'exterior_construction'    => 'nullable|array',
            'exterior_construction.*'  => 'string|in:Asbestos,Block,Brick,Cedar,Cement Siding,Concrete,HardiPlank Type,ICFs (Insulated Concrete Forms),Log,Metal Frame,Metal Siding,SIP (Structurally Insulated Panel),Stone,Stucco,Tilt up Walls,Vinyl Siding,Wood Frame,Wood Frame (FSC),Wood Siding,Other',
            'foundation'               => 'nullable|array',
            'foundation.*'             => 'string|in:Basement,Block,Brick/Mortar,Concrete Perimeter,Crawlspace,Pillar/Post/Pier,Slab,Stem Wall,Stilt/On Piling,Other',
            'other_roof_type'          => 'nullable|string|max:255',
            'other_exterior_construction' => 'nullable|string|max:255',
            'other_foundation'         => 'nullable|string|max:255',
        ];
    }

    /**
     * Custom messages for the publish/submit rules.
     */
    protected function getValidationMessages(): array
    {
        return [
            'first_name.required'           => 'First Name is required.',
            'last_name.required'            => 'Last Name is required.',
            'phone_number.required'         => 'Phone Number is required.',
            'email.required'                => 'Email Address is required.',
            'email.email'                   => 'Please enter a valid email address.',
            'desired_lease_length.required' => 'Desired Lease Term is required.',
            'desired_lease_length.min'      => 'Please select at least one Desired Lease Term.',
        ];
    }
}

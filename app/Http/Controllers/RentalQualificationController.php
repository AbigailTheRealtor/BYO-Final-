<?php

namespace App\Http\Controllers;

use App\Models\LandlordAgentAuction;
use App\Models\RentalQualificationCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RentalQualificationController extends Controller
{
    public function check(int|string $listing)
    {
        $auction = $this->resolveListing($listing);
        $meta    = $this->buildMeta($auction);

        return view('offer-listing.landlord.qualification.check', compact('auction', 'meta'));
    }

    public function store(Request $request, int|string $listing)
    {
        $auction = $this->resolveListing($listing);

        $validated = $request->validate([
            'name'                    => 'required|string|max:191',
            'email'                   => 'required|email|max:191',
            'phone'                   => 'nullable|string|max:64',
            'estimated_credit_score'  => 'nullable|string|max:50',
            'monthly_household_income'=> 'nullable|string|max:50',
            'employment_status'       => 'nullable|string|max:100',
            'eviction_history'        => 'nullable|string|max:100',
            'bankruptcy_history'      => 'nullable|string|max:100',
            'number_of_occupants'     => 'nullable|integer|min:1|max:50',
            'additional_notes'        => 'nullable|string|max:3000',
        ]);

        RentalQualificationCheck::create([
            'landlord_listing_id'     => $auction->id,
            'user_id'                 => Auth::id(),
            'name'                    => $validated['name'],
            'email'                   => $validated['email'],
            'phone'                   => $validated['phone'] ?? null,
            'estimated_credit_score'  => $validated['estimated_credit_score'] ?? null,
            'monthly_household_income'=> $validated['monthly_household_income'] ?? null,
            'employment_status'       => $validated['employment_status'] ?? null,
            'eviction_history'        => $validated['eviction_history'] ?? null,
            'bankruptcy_history'      => $validated['bankruptcy_history'] ?? null,
            'number_of_occupants'     => $validated['number_of_occupants'] ?? null,
            'additional_notes'        => $validated['additional_notes'] ?? null,
            'status'                  => 'submitted',
        ]);

        return redirect()
            ->route('offer.listing.landlord.qualification.check', ['listing' => $auction->id])
            ->with('qualification_submitted', true);
    }

    private function resolveListing(int|string $id): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::with('meta')->find($id);

        if (!$auction) {
            abort(404, 'Listing not found.');
        }

        $workflowType = $auction->info('workflow_type');

        if ($workflowType === 'hire_agent') {
            abort(404, 'Listing not found.');
        }

        if ($workflowType === 'offer_listing') {
            return $auction;
        }

        $isUnstamped = ($workflowType === null || $workflowType === false || $workflowType === '');
        if (!$isUnstamped) {
            abort(404, 'Listing not found.');
        }

        $isLegacyOfferListing =
            $auction->meta->contains('meta_key', 'desired_rental_amount')
            || $auction->meta->contains('meta_key', 'lease_amount_frequency')
            || $auction->meta->contains('meta_key', 'tenant_require')
            || $auction->meta->contains('meta_key', 'listing_date')
            || $auction->meta->contains('meta_key', 'auction_type')
            || $auction->meta->contains('meta_key', 'property_photos');

        if (!$isLegacyOfferListing) {
            abort(404, 'Listing not found.');
        }

        return $auction;
    }

    private function buildMeta(LandlordAgentAuction $auction): array
    {
        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }
        return $meta;
    }
}

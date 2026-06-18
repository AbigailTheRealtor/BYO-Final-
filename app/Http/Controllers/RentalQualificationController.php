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
            'name'                           => 'required|string|max:191',
            'email'                          => 'required|email|max:191',
            'phone'                          => 'nullable|string|max:64',
            'estimated_credit_score'         => 'nullable|string|max:50',
            'monthly_household_income'       => 'nullable|string|max:50',
            'employment_status'              => 'nullable|string|max:100',
            'employment_status_other'        => 'nullable|string|max:200',
            'income_source'                  => 'nullable|string|max:100',
            'has_pets'                       => 'nullable|in:Yes,No',
            'pet_details'                    => 'nullable|string|max:500',
            'smoking'                        => 'nullable|string|max:50',
            'eviction_history'               => 'nullable|string|max:100',
            'bankruptcy_history'             => 'nullable|string|max:100',
            'criminal_background'            => 'nullable|string|max:100',
            'criminal_background_other'      => 'nullable|string|max:500',
            'landlord_reference_available'   => 'nullable|string|max:50',
            'employment_verification_available' => 'nullable|in:Yes,No',
            'income_verification_available'  => 'nullable|in:Yes,No',
            'consent_to_screening'           => 'nullable|boolean',
            'number_of_occupants'            => 'nullable|integer|min:1|max:50',
            'desired_move_in_date'           => 'nullable|date',
            'applicant_profile'              => 'nullable|string|max:3000',
            'additional_notes'               => 'nullable|string|max:3000',
        ]);

        RentalQualificationCheck::create([
            'landlord_listing_id'            => $auction->id,
            'user_id'                        => Auth::id(),
            'name'                           => $validated['name'],
            'email'                          => $validated['email'],
            'phone'                          => $validated['phone'] ?? null,
            'estimated_credit_score'         => $validated['estimated_credit_score'] ?? null,
            'monthly_household_income'       => $validated['monthly_household_income'] ?? null,
            'employment_status'              => $validated['employment_status'] ?? null,
            'employment_status_other'        => $validated['employment_status_other'] ?? null,
            'income_source'                  => $validated['income_source'] ?? null,
            'has_pets'                       => $validated['has_pets'] ?? null,
            'pet_details'                    => $validated['pet_details'] ?? null,
            'smoking'                        => $validated['smoking'] ?? null,
            'eviction_history'               => $validated['eviction_history'] ?? null,
            'bankruptcy_history'             => $validated['bankruptcy_history'] ?? null,
            'criminal_background'            => $validated['criminal_background'] ?? null,
            'criminal_background_other'      => $validated['criminal_background_other'] ?? null,
            'landlord_reference_available'   => $validated['landlord_reference_available'] ?? null,
            'employment_verification_available' => $validated['employment_verification_available'] ?? null,
            'income_verification_available'  => $validated['income_verification_available'] ?? null,
            'consent_to_screening'           => isset($validated['consent_to_screening']) ? (bool) $validated['consent_to_screening'] : null,
            'number_of_occupants'            => $validated['number_of_occupants'] ?? null,
            'desired_move_in_date'           => $validated['desired_move_in_date'] ?? null,
            'applicant_profile'              => $validated['applicant_profile'] ?? null,
            'additional_notes'               => $validated['additional_notes'] ?? null,
            'status'                         => 'submitted',
        ]);

        return redirect()
            ->route('offer.listing.landlord.qualification.check', ['listing' => $auction->id])
            ->with('qualification_submitted', true);
    }

    /**
     * Landlord-facing: list all qualification check submissions for a listing.
     */
    public function submissions(int|string $listing)
    {
        $auction = $this->resolveListing($listing);

        $this->authoriseLandlord($auction);

        $submissions = RentalQualificationCheck::where('landlord_listing_id', $auction->id)
            ->orderByDesc('created_at')
            ->with('user')
            ->paginate(25);

        $meta = $this->buildMeta($auction);

        return view('offer-listing.landlord.qualification.submissions', compact('auction', 'meta', 'submissions'));
    }

    /**
     * Landlord-facing: review a single qualification check submission with comparison card.
     */
    public function reviewSubmission(int|string $listing, RentalQualificationCheck $check)
    {
        $auction = $this->resolveListing($listing);

        $this->authoriseLandlord($auction);

        if ($check->landlord_listing_id !== $auction->id) {
            abort(404, 'Qualification check not found for this listing.');
        }

        $meta = $this->buildMeta($auction);

        return view('offer-listing.landlord.qualification.review', compact('auction', 'meta', 'check'));
    }

    private function authoriseLandlord(LandlordAgentAuction $auction): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Authentication required.');
        }

        $isOwner = $auction->user_id === $user->id;
        $isAdmin = $user->is_super ?? false;

        if (!$isOwner && !$isAdmin) {
            abort(403, 'You are not authorised to view qualification submissions for this listing.');
        }
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

<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeLocationDna;
use App\Models\AcceptedBidSummary;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AgentLocationDnaController
 *
 * Handles the Generate/Refresh Location DNA action from agent offer listing pages.
 *
 * Listing type contract:
 *   'seller_agent'   → SellerAgentAuction (seller_agent_auctions table)
 *   'landlord_agent' → LandlordAgentAuction (landlord_agent_auctions table)
 *
 * These types map to the same listing records displayed by the Livewire offer-listing
 * pages, so authorization checks and dispatched job IDs are always consistent with the
 * page that triggered the request. The LocationDnaPipelineRunner resolves addresses
 * from the same models via dedicated 'seller_agent'/'landlord_agent' branches.
 *
 * Buyer and Tenant listing types are explicitly rejected with 404.
 */
class AgentLocationDnaController extends Controller
{
    private const ALLOWED_TYPES = ['seller_agent', 'landlord_agent'];

    public function generate(string $listingType, int $listingId)
    {
        if (! in_array($listingType, self::ALLOWED_TYPES, true)) {
            abort(404);
        }

        if ($listingType === 'seller_agent') {
            return $this->generateForSellerAgent($listingId);
        }

        return $this->generateForLandlordAgent($listingId);
    }

    private function isAuthorized(string $listingType, int $listingId, int $ownerId): bool
    {
        $userId = Auth::id();

        if ($userId === $ownerId) {
            return true;
        }

        return AcceptedBidSummary::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('agent_user_id', $userId)
            ->exists();
    }

    private function generateForSellerAgent(int $listingId)
    {
        $listing = SellerAgentAuction::find($listingId);

        if (! $listing) {
            abort(404);
        }

        if (! $this->isAuthorized('seller_agent', $listingId, (int) $listing->user_id)) {
            abort(403);
        }

        $address = $listing->info('address');
        $city    = $listing->info('property_city');
        $state   = $listing->info('property_state');

        if (empty($address) || empty($city) || empty($state)) {
            return back()->withErrors(['address' => 'Complete the listing address (street, city, and state) before generating Location DNA.']);
        }

        try {
            ComputeLocationDna::dispatch('seller_agent', $listingId);
        } catch (\Throwable $e) {
            Log::error('AgentLocationDnaController: failed to dispatch ComputeLocationDna', [
                'listing_type' => 'seller_agent',
                'listing_id'   => $listingId,
                'error'        => $e->getMessage(),
            ]);

            return back()->withErrors(['dna' => 'Failed to start Location DNA generation. Please try again.']);
        }

        return back()->with('dna_success', 'Location DNA generation has started.');
    }

    private function generateForLandlordAgent(int $listingId)
    {
        $listing = LandlordAgentAuction::find($listingId);

        if (! $listing) {
            abort(404);
        }

        if (! $this->isAuthorized('landlord_agent', $listingId, (int) $listing->user_id)) {
            abort(403);
        }

        $address = $listing->info('address');
        $city    = $listing->info('property_city');
        $state   = $listing->info('property_state');

        if (empty($address) || empty($city) || empty($state)) {
            return back()->withErrors(['address' => 'Complete the listing address (street, city, and state) before generating Location DNA.']);
        }

        try {
            ComputeLocationDna::dispatch('landlord_agent', $listingId);
        } catch (\Throwable $e) {
            Log::error('AgentLocationDnaController: failed to dispatch ComputeLocationDna', [
                'listing_type' => 'landlord_agent',
                'listing_id'   => $listingId,
                'error'        => $e->getMessage(),
            ]);

            return back()->withErrors(['dna' => 'Failed to start Location DNA generation. Please try again.']);
        }

        return back()->with('dna_success', 'Location DNA generation has started.');
    }
}

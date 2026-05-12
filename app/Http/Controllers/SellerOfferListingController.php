<?php

namespace App\Http\Controllers;

use App\Models\SellerAgentAuction;

class SellerOfferListingController extends Controller
{
    public function view($id)
    {
        $auction = SellerAgentAuction::with(['meta', 'bids.user'])->find($id);

        if (!$auction) {
            abort(404, 'Listing not found');
        }

        $workflowType = $auction->info('workflow_type');

        if ($workflowType === 'offer_listing') {
            // Primary check: workflow_type stamp (all records created after Task #833)
        } elseif (
            // Fallback for older Offer Listing records that pre-date the workflow_type stamp.
            // These meta keys only appear in Full Service Seller Offer Listings, not in
            // Hire Seller's Agent records, so their presence is a safe identifier.
            // Additive OR — any single match is sufficient to recognise an Offer Listing.
            $auction->info('parcel_id')                !== false ||
            $auction->info('flood_zone_code')          !== false ||
            $auction->info('annual_property_taxes')    !== false ||
            $auction->info('seller_disclosure_available') !== false ||
            $auction->info('property_photos')          !== false ||
            $auction->info('listing_documents')        !== false ||
            $auction->info('brokerage_relationship')   !== false ||
            $auction->info('association_type')         !== false ||
            $auction->info('auction_type')             !== false
        ) {
            // Fallback: presence of Offer Listing-specific meta keys
        } else {
            abort(404, 'Listing not found');
        }

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $page_data = [
            'title'   => $auction->address ?? ($meta['listing_title'] ?? 'Seller Offer Listing'),
            'id'      => $id,
            'auth_id' => auth()->id(),
        ];

        return view('offer-listing.seller.view', compact('auction', 'meta') + $page_data);
    }
}

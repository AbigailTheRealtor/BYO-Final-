<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Http\Livewire\Concerns\HasListingLifecycle;
use App\Models\OfferAuction as OfferAuctionModel;
use Illuminate\Support\Facades\Auth;

/**
 * OfferAuction — Phase 4 of the Workflow Engine.
 *
 * A clean, standalone Livewire component for Offer-mode listings.
 * Uses HasListingLifecycle for shared draft/status/flash infrastructure.
 * NO hire-agent fields, NO broker compensation, NO services section.
 */
class OfferAuction extends Component
{
    use HasListingLifecycle;

    // ── Routing / identity ────────────────────────────────────────────────
    public ?int    $auctionId   = null;
    public string  $workflow_type = 'offer';

    // ── Tab 1: Overview ───────────────────────────────────────────────────
    public string  $listing_title    = '';
    public string  $offer_type       = '';   // sale | rental | lease
    public string  $property_address = '';
    public string  $city             = '';
    public string  $state            = '';
    public string  $zip_code         = '';
    public string  $property_type    = '';   // house | condo | apartment | townhouse | commercial | land | other
    public string  $bedrooms         = '';
    public string  $bathrooms        = '';
    public string  $sqft             = '';

    // ── Tab 2: Financial Terms ────────────────────────────────────────────
    public string  $offer_price      = '';
    public string  $earnest_deposit  = '';
    public string  $financing_type   = 'conventional';  // cash | conventional | fha | va | other
    public bool    $financing_contingency      = false;
    public string  $financing_contingency_days = '';
    public string  $down_payment_percent       = '';
    public string  $monthly_rent              = '';   // rental/lease only
    public string  $security_deposit         = '';   // rental/lease only
    public string  $lease_term_months        = '';   // lease only

    // ── Tab 3: Contingencies & Dates ──────────────────────────────────────
    public bool    $inspection_contingency      = false;
    public string  $inspection_contingency_days = '';
    public bool    $appraisal_contingency       = false;
    public string  $closing_date                = '';
    public string  $possession_date             = '';
    public string  $listing_expiration          = '';

    // ── Tab 4: Custom Terms & Review ──────────────────────────────────────
    // listing_status is inherited from HasListingLifecycle (default: 'Active')
    public string  $custom_terms    = '';
    public string  $notes           = '';

    // ─────────────────────────────────────────────────────────────────────
    public function mount($offer_type = null, $listingId = null)
    {
        if (auth()->user()->user_type !== 'agent') {
            abort(403, 'Offer listings are restricted to agent accounts.');
        }

        // Pre-fill offer_type from URL param
        if ($offer_type && in_array($offer_type, ['sale', 'rental', 'lease'])) {
            $this->offer_type = $offer_type;
        }

        // Load draft if ID supplied
        if ($listingId) {
            $auction = OfferAuctionModel::with('metas')
                ->where('id', $listingId)
                ->where('user_id', Auth::id())
                ->first();
            if ($auction) {
                $this->loadFromRecord($auction);
            }
        }
    }

    // ── Save draft ────────────────────────────────────────────────────────
    public function saveDraft(): void
    {
        $this->saveRecord(draft: true);
        $this->dispatchBrowserEvent('offer-flash', ['type' => 'success', 'message' => 'Draft saved successfully.']);
    }

    // ── Publish (full validation) ─────────────────────────────────────────
    public function submitListing(): void
    {
        $this->validate([
            'offer_type'         => 'required',
            'property_address'   => 'required|string|min:5',
            'offer_price'        => 'required_if:offer_type,sale',
            'monthly_rent'       => 'required_if:offer_type,rental,lease',
            'closing_date'       => 'nullable|date',
            'listing_expiration' => 'nullable|date',
        ], [
            'offer_type.required'       => 'Please select an offer type (Sale, Rental, or Lease).',
            'property_address.required' => 'Property address is required.',
            'offer_price.required_if'   => 'Offer price is required for sale listings.',
            'monthly_rent.required_if'  => 'Monthly rent is required for rental and lease listings.',
        ]);

        $this->saveRecord(draft: false);
        $this->isDraft    = false;
        $this->isApproved = config('offer.default_auto_approve', true);

        $message = config('offer.default_auto_approve', true)
            ? 'Listing published successfully.'
            : 'Listing submitted for admin review. You will be notified once it is approved.';

        $this->dispatchBrowserEvent('offer-flash', ['type' => 'success', 'message' => $message]);
    }

    // ── Core save logic ───────────────────────────────────────────────────
    private function saveRecord(bool $draft): void
    {
        $title = $this->listing_title
            ?: ($this->property_address ?: 'Offer Listing');

        if ($this->auctionId) {
            $auction = OfferAuctionModel::where('id', $this->auctionId)
                ->where('user_id', Auth::id())
                ->firstOrFail();
        } else {
            $auction = new OfferAuctionModel();
            $auction->user_id = Auth::id();
        }

        $auction->title    = $title;
        $auction->is_draft = $draft;
        if (!$draft) {
            $auction->is_approved = config('offer.default_auto_approve', true);
        }
        $auction->save();

        $this->auctionId  = $auction->id;
        $this->isDraft    = $draft;
        $this->isSold     = false;
        $this->isApproved = (bool) $auction->is_approved;

        $this->saveAllMeta($auction);
    }

    private function saveAllMeta(OfferAuctionModel $auction): void
    {
        $auction->saveMeta('workflow_type',   $this->workflow_type);
        $auction->saveMeta('listing_title',   $this->listing_title);
        $auction->saveMeta('listing_status',  $this->listing_status);

        // Overview
        $auction->saveMeta('offer_type',       $this->offer_type);
        $auction->saveMeta('property_address', $this->property_address);
        $auction->saveMeta('city',             $this->city);
        $auction->saveMeta('state',            $this->state);
        $auction->saveMeta('zip_code',         $this->zip_code);
        $auction->saveMeta('property_type',    $this->property_type);
        $auction->saveMeta('bedrooms',         $this->bedrooms);
        $auction->saveMeta('bathrooms',        $this->bathrooms);
        $auction->saveMeta('sqft',             $this->sqft);

        // Financial
        $auction->saveMeta('offer_price',                $this->offer_price);
        $auction->saveMeta('earnest_deposit',            $this->earnest_deposit);
        $auction->saveMeta('financing_type',             $this->financing_type);
        $auction->saveMeta('financing_contingency',      $this->financing_contingency ? '1' : '0');
        $auction->saveMeta('financing_contingency_days', $this->financing_contingency_days);
        $auction->saveMeta('down_payment_percent',       $this->down_payment_percent);
        $auction->saveMeta('monthly_rent',               $this->monthly_rent);
        $auction->saveMeta('security_deposit',           $this->security_deposit);
        $auction->saveMeta('lease_term_months',          $this->lease_term_months);

        // Contingencies & Dates
        $auction->saveMeta('inspection_contingency',      $this->inspection_contingency ? '1' : '0');
        $auction->saveMeta('inspection_contingency_days', $this->inspection_contingency_days);
        $auction->saveMeta('appraisal_contingency',       $this->appraisal_contingency ? '1' : '0');
        $auction->saveMeta('closing_date',                $this->closing_date);
        $auction->saveMeta('possession_date',             $this->possession_date);
        $auction->saveMeta('listing_expiration',          $this->listing_expiration);

        // Custom Terms
        $auction->saveMeta('custom_terms', $this->custom_terms);
        $auction->saveMeta('notes',        $this->notes);
    }

    private function loadFromRecord(OfferAuctionModel $auction): void
    {
        $this->auctionId   = $auction->id;
        $this->isDraft     = (bool) $auction->is_draft;
        $this->isApproved  = (bool) $auction->is_approved;
        $this->isSold      = (bool) $auction->is_sold;

        $metas = $auction->metas->keyBy('meta_key');
        $val   = fn(string $key, string $default = '') => $metas->has($key)
            ? (string) $metas[$key]->meta_value
            : $default;

        $this->listing_title    = $val('listing_title', $auction->title ?? '');
        $this->listing_status   = $val('listing_status', 'Active');
        $this->offer_type       = $val('offer_type', $this->offer_type);
        $this->property_address = $val('property_address');
        $this->city             = $val('city');
        $this->state            = $val('state');
        $this->zip_code         = $val('zip_code');
        $this->property_type    = $val('property_type');
        $this->bedrooms         = $val('bedrooms');
        $this->bathrooms        = $val('bathrooms');
        $this->sqft             = $val('sqft');

        $this->offer_price                = $val('offer_price');
        $this->earnest_deposit            = $val('earnest_deposit');
        $this->financing_type             = $val('financing_type', 'conventional');
        $this->financing_contingency      = $val('financing_contingency', '0') === '1';
        $this->financing_contingency_days = $val('financing_contingency_days');
        $this->down_payment_percent       = $val('down_payment_percent');
        $this->monthly_rent               = $val('monthly_rent');
        $this->security_deposit           = $val('security_deposit');
        $this->lease_term_months          = $val('lease_term_months');

        $this->inspection_contingency      = $val('inspection_contingency', '0') === '1';
        $this->inspection_contingency_days = $val('inspection_contingency_days');
        $this->appraisal_contingency       = $val('appraisal_contingency', '0') === '1';
        $this->closing_date                = $val('closing_date');
        $this->possession_date             = $val('possession_date');
        $this->listing_expiration          = $val('listing_expiration');

        $this->custom_terms = $val('custom_terms');
        $this->notes        = $val('notes');
    }

    public function render()
    {
        return view('livewire.offer-auction')->extends('layouts.main')->section('content');
    }
}

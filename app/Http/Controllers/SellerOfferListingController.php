<?php

namespace App\Http\Controllers;

use App\Mail\SellerListingInquiryMail;
use App\Models\OfferAuction;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\SellerAgentAuction;
use App\Models\SellerListingInquiry;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SellerOfferListingController extends Controller
{
    /**
     * Meta keys that are exclusive to Seller Offer Listings (Full Service).
     * Used as a fallback identifier for records that pre-date the workflow_type stamp.
     */
    public const OFFER_LISTING_META_KEYS = [
        'parcel_id',
        'flood_zone_code',
        'annual_property_taxes',
        'seller_disclosure_available',
        'property_photos',
        'listing_documents',
        'brokerage_relationship',
        'association_type',
        'auction_type',
    ];

    /**
     * Resolve a SellerAgentAuction by ID and confirm it is a Seller Offer Listing.
     * Returns the auction on success or calls abort(404) when the record is absent
     * or belongs to a different workflow (e.g. Hire Agent).
     *
     * @param  int|string  $id
     * @param  bool        $withRelations  Load meta + bids.user when true (view page only).
     */
    private function resolveOfferListing($id, bool $withRelations = false): SellerAgentAuction
    {
        $query   = $withRelations
            ? SellerAgentAuction::with(['meta', 'bids.user'])
            : SellerAgentAuction::query();

        $auction = $query->find($id);

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
            $auction->info('parcel_id')                   !== false ||
            $auction->info('flood_zone_code')             !== false ||
            $auction->info('annual_property_taxes')       !== false ||
            $auction->info('seller_disclosure_available') !== false ||
            $auction->info('property_photos')             !== false ||
            $auction->info('listing_documents')           !== false ||
            $auction->info('brokerage_relationship')      !== false ||
            $auction->info('association_type')            !== false ||
            $auction->info('auction_type')                !== false
        ) {
            // Fallback: presence of Offer Listing-specific meta keys
        } else {
            abort(404, 'Listing not found');
        }

        return $auction;
    }

    public function view($id)
    {
        $auction = $this->resolveOfferListing($id, withRelations: true);

        $meta = [];
        foreach ($auction->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)))
                ? $decoded
                : $row->meta_value;
        }

        $offerAuction = $this->resolveOfferAuction($auction);
        $calcData     = $this->buildCalcData($meta);

        $askAiChipContext = app(AskAiContextBuilderService::class)->buildChipContext($auction, 'seller');

        $agentAiV2      = config('ask_ai.agent_ai_v2_enabled', false);
        $agentAiAgentId = (int) ($meta['hired_agent_id'] ?? 0);
        $agentAiScope   = 'public_listing_seller';

        $locationDna  = PropertyLocationDna::where('listing_type', 'seller_agent')
            ->where('listing_id', $auction->id)
            ->first();
        $locationPois = $locationDna
            ? PropertyLocationPoi::where('listing_type', 'seller_agent')
                ->where('listing_id', $auction->id)
                ->orderBy('poi_category')
                ->orderBy('rank')
                ->get()
            : collect();

        $page_data = [
            'title'   => $auction->address ?? ($meta['listing_title'] ?? 'Seller Offer Listing'),
            'id'      => $id,
            'auth_id' => auth()->id(),
        ];

        return view('offer-listing.seller.view', compact('auction', 'meta', 'offerAuction', 'calcData', 'askAiChipContext', 'agentAiV2', 'agentAiAgentId', 'agentAiScope', 'locationDna', 'locationPois') + $page_data);
    }

    /**
     * Build the $calcData array expected by seller_property._mortgage_calculator.
     *
     * Price priority: desired_sale_price → purchase_price → buy_now_price → starting_price → reserve_price
     * HOA normalized from association_fee_amount + association_fee_frequency.
     * Taxes from annual_property_taxes meta key.
     * Admin rate defaults via get_setting() with hardcoded fallbacks.
     *
     * @param  array  $meta  Flat meta array keyed by meta_key (already decoded by view()).
     */
    private function buildCalcData(array $meta): array
    {
        // --- Price ---
        $price       = null;
        $priceSource = 'estimated';
        foreach (['desired_sale_price', 'purchase_price', 'buy_now_price', 'starting_price', 'reserve_price'] as $pk) {
            $pv = $meta[$pk] ?? null;
            if ($pv !== null && $pv !== '' && (float) $pv > 0) {
                $price       = (float) $pv;
                $priceSource = 'from listing';
                break;
            }
        }

        // --- HOA ---
        $hoaMonthly = 0.0;
        $hoaSource  = 'estimated';
        $hoaAssumed = false;

        // Agent payment_hoa override takes priority over association_fee fields
        $paymentHoaAmt  = $meta['payment_hoa_fee_amount'] ?? null;
        $paymentHoaFreq = $meta['payment_hoa_fee_frequency'] ?? null;
        if ($paymentHoaAmt !== null && $paymentHoaAmt !== '' && (float) $paymentHoaAmt > 0) {
            $hoaAmt   = (float) $paymentHoaAmt;
            $schedule = strtolower((string) $paymentHoaFreq);
            if (str_contains($schedule, 'quarter')) {
                $hoaMonthly = $hoaAmt / 3;
            } elseif (str_contains($schedule, 'annual') || str_contains($schedule, 'year')) {
                $hoaMonthly = $hoaAmt / 12;
            } elseif (str_contains($schedule, 'month')) {
                $hoaMonthly = $hoaAmt;
            } else {
                $hoaMonthly = $hoaAmt;
                $hoaAssumed = true;
            }
            $hoaSource = 'agent override';
        } else {
            $hoaRaw = $meta['association_fee_amount'] ?? null;
            if ($hoaRaw && (float) $hoaRaw > 0) {
                $hoaAmt   = (float) $hoaRaw;
                $schedule = strtolower((string) ($meta['association_fee_frequency'] ?? ''));
                if (str_contains($schedule, 'quarter')) {
                    $hoaMonthly = $hoaAmt / 3;
                } elseif (str_contains($schedule, 'annual') || str_contains($schedule, 'year')) {
                    $hoaMonthly = $hoaAmt / 12;
                } elseif (str_contains($schedule, 'month')) {
                    $hoaMonthly = $hoaAmt;
                } else {
                    $hoaMonthly = $hoaAmt;
                    $hoaAssumed = true;
                }
                $hoaSource = 'from listing';
            }
        }

        // --- Taxes ---
        $taxesAnnual = 0.0;
        $taxesSource = 'estimated';

        // Agent payment_annual_property_taxes override takes priority
        $paymentTaxes = $meta['payment_annual_property_taxes'] ?? null;
        if ($paymentTaxes !== null && $paymentTaxes !== '' && (float) $paymentTaxes > 0) {
            $taxesAnnual = (float) $paymentTaxes;
            $taxesSource = 'agent override';
        } else {
            $taxRaw = $meta['annual_property_taxes'] ?? null;
            if ($taxRaw && (float) $taxRaw > 0) {
                $taxesAnnual = (float) $taxRaw;
                $taxesSource = 'from listing';
            }
        }

        // --- Admin defaults ---
        $interestRate  = (float) (get_setting('calc_interest_rate')    ?: 6.5);
        $downPct       = (float) (get_setting('calc_down_payment_pct') ?: 10);
        $loanTerm      = (int)   (get_setting('calc_loan_term')        ?: 30);
        $taxRate       = (float) (get_setting('calc_tax_rate')         ?: 1.1);
        $insuranceRate = (float) (get_setting('calc_insurance_rate')   ?: 0.5);
        $pmiRate       = (float) (get_setting('calc_pmi_rate')         ?: 0.85);

        // --- Agent payment_* overrides (take priority over admin defaults) ---
        $paymentDownPct = $meta['payment_down_payment_pct'] ?? null;
        if ($paymentDownPct !== null && $paymentDownPct !== '') {
            $downPct = (float) $paymentDownPct;
        }

        $paymentRate = $meta['payment_interest_rate'] ?? null;
        if ($paymentRate !== null && $paymentRate !== '') {
            $interestRate = (float) $paymentRate;
        }

        $paymentTerm = $meta['payment_loan_term'] ?? null;
        if ($paymentTerm !== null && $paymentTerm !== '') {
            $loanTerm = (int) $paymentTerm;
        }

        $paymentPmi = $meta['payment_pmi_rate'] ?? null;
        if ($paymentPmi !== null && $paymentPmi !== '') {
            $pmiRate = (float) $paymentPmi;
        }

        // PMI zeroes automatically when down payment >= 20%
        if ($downPct >= 20) {
            $pmiRate = 0.0;
        }

        // Agent monthly insurance override
        $insuranceMonthlyOverride = null;
        $insuranceSource          = 'estimated';
        $paymentIns = $meta['payment_monthly_insurance'] ?? null;
        if ($paymentIns !== null && $paymentIns !== '' && (float) $paymentIns > 0) {
            $insuranceMonthlyOverride = (float) $paymentIns;
            $insuranceSource          = 'agent override';
        }

        // --- show_buydown_options ---
        $showBuydownRaw  = $meta['payment_show_buydown_options'] ?? null;
        $showBuydownOpts = ($showBuydownRaw === null) || ($showBuydownRaw !== '0' && $showBuydownRaw !== 'false');

        return [
            'price'                      => $price,
            'price_source'               => $priceSource,
            'hoa_monthly'                => round($hoaMonthly, 2),
            'hoa_source'                 => $hoaSource,
            'hoa_assumed'                => $hoaAssumed,
            'taxes_annual'               => $taxesAnnual,
            'taxes_source'               => $taxesSource,
            'insurance_source'           => $insuranceSource,
            'interest_rate'              => $interestRate,
            'down_pct'                   => $downPct,
            'loan_term'                  => $loanTerm,
            'tax_rate'                   => $taxRate,
            'insurance_rate'             => $insuranceRate,
            'pmi_rate'                   => $pmiRate,
            'insurance_monthly_override' => $insuranceMonthlyOverride,
            'show_buydown_options'       => $showBuydownOpts,
        ];
    }

    /**
     * Read the OfferAuction record linked to this SellerAgentAuction.
     * Returns the linked OfferAuction or null when none is present.
     * Never writes to the database — creation is the responsibility of the
     * Livewire save/submit flow (ensureLinkedOfferAuction).
     */
    private function resolveOfferAuction(SellerAgentAuction $auction): ?OfferAuction
    {
        $linkedId = $auction->info('linked_offer_auction_id');

        if ($linkedId) {
            return OfferAuction::find((int) $linkedId) ?: null;
        }

        return null;
    }

    public function submitQuestion(Request $request, $auctionId)
    {
        // Honeypot check — silent discard for bots
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->back()->with('success', 'Your question has been sent.');
        }

        $auction = $this->resolveOfferListing($auctionId);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|max:191',
            'phone'    => 'nullable|string|max:64',
            'question' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'questionInquiry')
                ->withInput()
                ->with('open_modal', 'question');
        }

        $inquiry = SellerListingInquiry::create([
            'auction_id' => $auctionId,
            'type'       => 'question',
            'name'       => $request->input('name'),
            'email'      => $request->input('email'),
            'phone'      => $request->input('phone'),
            'question'   => $request->input('question'),
            'status'     => 'new',
            'source'     => 'public_listing',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->sendInquiryEmail($inquiry, $auction);

        return redirect()->back()->with('success', 'Your question has been sent.');
    }

    public function submitShowing(Request $request, $auctionId)
    {
        // Honeypot check — silent discard for bots
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->back()->with('success', 'Your showing request has been sent.');
        }

        $auction = $this->resolveOfferListing($auctionId);

        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:191',
            'email'          => 'required|email|max:191',
            'phone'          => 'nullable|string|max:64',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string|max:32',
            'message'        => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator, 'showingInquiry')
                ->withInput()
                ->with('open_modal', 'showing');
        }

        $inquiry = SellerListingInquiry::create([
            'auction_id'     => $auctionId,
            'type'           => 'showing',
            'name'           => $request->input('name'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'preferred_date' => $request->input('preferred_date'),
            'preferred_time' => $request->input('preferred_time'),
            'message'        => $request->input('message'),
            'status'         => 'new',
            'source'         => 'public_listing',
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        $this->sendInquiryEmail($inquiry, $auction);

        return redirect()->back()->with('success', 'Your showing request has been sent.');
    }

    /**
     * Resolve recipient and send the inquiry notification email.
     * Wrapped in try/catch — email failure is logged but never breaks the submission.
     */
    private function sendInquiryEmail(SellerListingInquiry $inquiry, SellerAgentAuction $auction): void
    {
        try {
            // Recipient resolution priority:
            // 1. Listing agent email stored in meta
            // 2. Contact email from meta
            // 3. config('mail.from.address') fallback
            $recipient = $auction->info('agent_email')
                ?: ($auction->info('contact_email')
                ?: ($auction->info('seller_email')
                ?: config('mail.from.address')));

            if (empty($recipient)) {
                Log::warning('SellerOfferListingController: no recipient resolved for inquiry', [
                    'inquiry_id' => $inquiry->id,
                    'auction_id' => $inquiry->auction_id,
                ]);
                return;
            }

            $listingTitle = $auction->address
                ?? ($auction->info('listing_title') ?: 'Seller Offer Listing #' . $auction->id);
            $listingUrl   = route('offer.listing.seller.view', ['id' => $auction->id]);

            Mail::to($recipient)->send(new SellerListingInquiryMail($inquiry, $listingTitle, $listingUrl));
        } catch (\Throwable $e) {
            Log::warning('SellerOfferListingController: inquiry email failed', [
                'inquiry_id' => $inquiry->id ?? null,
                'auction_id' => $auction->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function searchOfferListings(Request $request)
    {
        $page_data['title'] = 'Seller Listings';

        $auctions = SellerAgentAuction::query()
            ->selectRaw("*, (SELECT meta_value FROM seller_agent_auction_metas WHERE seller_agent_auction_metas.seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'ideal_price') as price")
            ->where('is_approved', true)
            ->where('is_draft', false)
            ->whereDoesntHave('meta', function ($m) {
                $m->where('meta_key', 'workflow_type')->where('meta_value', 'hire_agent');
            })
            ->where(function ($q) {
                // Primary: workflow_type = offer_listing
                $q->whereHas('meta', function ($m) {
                    $m->where('meta_key', 'workflow_type')->where('meta_value', 'offer_listing');
                })
                // Fallback: presence of any offer-listing-specific meta key
                ->orWhereHas('meta', function ($m) {
                    $m->whereIn('meta_key', self::OFFER_LISTING_META_KEYS);
                });
            });

        if ($request->title != '') {
            $auctions->where('address', 'like', '%' . $request->title . '%');
        }

        if ($request->bedrooms != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bedrooms')->where('meta_value', $request->bedrooms);
            });
        }

        if ($request->bathrooms != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'bathrooms')->where('meta_value', $request->bathrooms);
            });
        }

        if ($request->property_type != '') {
            $auctions->whereHas('meta', function ($meta) use ($request) {
                $meta->where('meta_key', 'property_type')->where('meta_value', $request->property_type);
            });
        }

        $sort = $request->sort ?? 'newest';
        if ($sort === 'most_viewed') {
            $auctions->orderByRaw('(SELECT COUNT(*) FROM seller_agent_auction_bids WHERE seller_agent_auction_bids.seller_agent_auction_id = seller_agent_auctions.id) DESC');
        } elseif ($sort === 'ending_soon') {
            $auctions->orderByRaw("
                CASE
                    WHEN NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '') IS NOT NULL
                        AND NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int > 0
                        AND (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int) > NOW()
                    THEN EXTRACT(EPOCH FROM (seller_agent_auctions.created_at + INTERVAL '1 day' * NULLIF(REGEXP_REPLACE(COALESCE(
                            (SELECT meta_value FROM seller_agent_auction_metas
                             WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'auction_time' LIMIT 1)
                        , ''), '[^0-9]', '', 'g'), '')::int))
                    WHEN COALESCE((SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1), '') <> ''
                        AND (SELECT meta_value FROM seller_agent_auction_metas
                            WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date >= CURRENT_DATE
                    THEN EXTRACT(EPOCH FROM (SELECT meta_value FROM seller_agent_auction_metas
                        WHERE seller_agent_auction_id = seller_agent_auctions.id AND meta_key = 'expiration_date' LIMIT 1)::date::timestamp)
                    ELSE 9999999999
                END ASC, seller_agent_auctions.created_at DESC
            ");
        } else {
            $auctions->orderBy('created_at', 'DESC');
        }

        $page_data['count'] = (clone $auctions)->count();
        $page_data['pAuctions'] = $auctions->paginate(12);

        return view('offer-listing.seller.search', $page_data);
    }
}

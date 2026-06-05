<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Notifications\Offers\OfferAcceptedNotification;
use App\Notifications\Offers\OfferCounteredNotification;
use App\Notifications\Offers\OfferRejectedNotification;
use App\Notifications\Offers\OfferSubmittedNotification;
use App\Notifications\Offers\OfferWithdrawnNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferTimelineBuilder;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferWorkflowFacade $facade,
        private readonly OfferAvailableActionsService $actionsService,
        private readonly OfferTimelineBuilder $timelineBuilder,
    ) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'offer_auction_id' => 'required|integer|exists:offer_auctions,id',
            'role'             => 'required|string|max:64',
            'listing_snapshot' => 'nullable|array',
            'expires_at'       => 'nullable|date',
        ]);

        $offer = Offer::create([
            'user_id'          => Auth::id(),
            'offer_auction_id' => $validated['offer_auction_id'],
            'role'             => $validated['role'],
            'listing_snapshot' => $validated['listing_snapshot'] ?? null,
            'expires_at'       => $validated['expires_at'] ?? null,
            'status'           => 'draft',
        ]);

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer);
        }

        return response()->json([
            'message' => 'Offer draft created.',
            'offer'   => $offer,
        ], 201);
    }

    public function submit(Request $request, Offer $offer): JsonResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_submit']) {
            return response()->json(['message' => $actions['reasons']['submit']], 422);
        }

        $result = $this->facade->submit($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            return response()->json(['message' => $result['reason']], 422);
        }

        Notification::send($offer->user, new OfferSubmittedNotification($offer));

        return response()->json([
            'message' => 'Offer submitted.',
            'result'  => $result,
        ]);
    }

    public function accept(Request $request, Offer $offer): JsonResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_accept']) {
            return response()->json(['message' => $actions['reasons']['accept']], 422);
        }

        $result = $this->facade->accept($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            return response()->json(['message' => $result['reason']], 422);
        }

        $offer->user->notify(new OfferAcceptedNotification($offer));

        return response()->json([
            'message' => 'Offer accepted.',
            'result'  => $result,
        ]);
    }

    public function reject(Request $request, Offer $offer): JsonResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_reject']) {
            return response()->json(['message' => $actions['reasons']['reject']], 422);
        }

        $result = $this->facade->reject($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            return response()->json(['message' => $result['reason']], 422);
        }

        $offer->user->notify(new OfferRejectedNotification($offer));

        return response()->json([
            'message' => 'Offer rejected.',
            'result'  => $result,
        ]);
    }

    public function withdraw(Request $request, Offer $offer): JsonResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_withdraw']) {
            return response()->json(['message' => $actions['reasons']['withdraw']], 422);
        }

        $result = $this->facade->withdraw($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            return response()->json(['message' => $result['reason']], 422);
        }

        $offer->user->notify(new OfferWithdrawnNotification($offer));

        return response()->json([
            'message' => 'Offer withdrawn.',
            'result'  => $result,
        ]);
    }

    public function counter(Request $request, Offer $offer): JsonResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_counter']) {
            return response()->json(['message' => $actions['reasons']['counter']], 422);
        }

        $validated = $request->validate([
            'listing_snapshot' => 'nullable|array',
            'expires_at'       => 'nullable|date',
        ]);

        $overrides = array_filter($validated, fn ($v) => $v !== null);

        $result = $this->facade->counter(
            $offer,
            $actorId,
            $actorRole,
            $overrides,
            ['source' => 'web'],
            $request->ip(),
        );

        if ($result['allowed'] === false) {
            return response()->json(['message' => $result['reason']], 422);
        }

        $offer->user->notify(new OfferCounteredNotification($offer, $result['counter_offer']));

        return response()->json([
            'message' => 'Counter offer created.',
            'result'  => $result,
        ]);
    }

    public function saveTerms(Request $request, Offer $offer)
    {
        if (Auth::id() !== $offer->user_id) {
            abort(403);
        }

        if ($offer->status !== 'draft') {
            abort(422, 'Offer terms can only be edited while the offer is in draft status.');
        }

        $offer->load('offerAuction.metas');
        $offerType = $offer->offerAuction?->info('offer_type') ?: 'sale';
        if (!in_array($offerType, ['sale', 'rental', 'lease'])) {
            $offerType = 'sale';
        }

        $commonRules = [
            'expires_at'   => 'nullable|date',
            'custom_terms' => 'nullable|string|max:5000',
            'notes'        => 'nullable|string|max:5000',
        ];

        $typeRules = [];
        if ($offerType === 'sale') {
            $typeRules = [
                'offer_price'                          => 'nullable|numeric|min:0',
                'earnest_deposit'                      => 'nullable|numeric|min:0',
                'financing_type'                       => 'nullable|in:Assumable,Cash,Conventional,FHA,Jumbo,VA,No-Doc,Non-QM,USDA,Cryptocurrency,Exchange/Trade,Lease Option,Lease Purchase,Non-Fungible Token (NFT),Seller Financing,Other',
                'financing_contingency'                => 'nullable|boolean',
                'financing_contingency_days'           => 'nullable|integer|min:1|max:365',
                'down_payment_percent'                 => 'nullable|numeric|min:0|max:100',
                'inspection_contingency'               => 'nullable|boolean',
                'inspection_contingency_days'          => 'nullable|integer|min:1|max:365',
                'appraisal_contingency'                => 'nullable|boolean',
                'closing_date'                         => 'nullable|date',
                'possession_date'                      => 'nullable|date',
                // Assumable sub-fields
                'assumable_terms'                      => 'nullable|string|max:1000',
                'assumable_loan_type'                  => 'nullable|in:FHA,VA,USDA',
                'assumable_interest_rate'              => 'nullable|numeric|min:0|max:100',
                'outstanding_balance'                  => 'nullable|numeric|min:0',
                'assumable_loan_term_remaining'        => 'nullable|string|max:200',
                // Cryptocurrency sub-fields
                'cryptocurrency_type'                  => 'nullable|string|max:200',
                'crypto_percentage'                    => 'nullable|numeric|min:0|max:100',
                'crypto_exchange_method'               => 'nullable|string|max:500',
                // Exchange/Trade sub-fields
                'exchange_item'                        => 'nullable|string|max:500',
                'exchange_item_value'                  => 'nullable|numeric|min:0',
                // Seller Financing sub-fields
                'seller_financing_amount'              => 'nullable|numeric|min:0',
                'seller_financing_rate'                => 'nullable|numeric|min:0|max:100',
                'seller_financing_term'                => 'nullable|string|max:200',
                // Purchase Terms
                'initial_deposit_amount'               => 'nullable|string|max:200',
                'initial_deposit_timeframe'            => 'nullable|string|max:100',
                'initial_deposit_timeframe_other'      => 'nullable|string|max:200',
                'additional_deposit_amount'            => 'nullable|string|max:200',
                'additional_deposit_timeframe'         => 'nullable|string|max:100',
                'additional_deposit_timeframe_other'   => 'nullable|string|max:200',
                'escrow_agent_preference'              => 'nullable|string|max:500',
                'preferred_inspection_period'          => 'nullable|integer|min:0|max:365',
                'appraisal_contingency_preference'     => 'nullable|in:Required,Preferred Waived,Negotiable,Not Applicable',
                'financing_contingency_preference'     => 'nullable|in:Required,Preferred Waived,Negotiable,Not Applicable',
                'sale_of_buyer_property_contingency'   => 'nullable|in:Accepted,Not Accepted,Negotiable',
                'possession_preference'                => 'nullable|in:At Closing,Day After Closing,Seller Rent Back,Negotiable,Other',
                'possession_details'                   => 'nullable|string|max:500',
                'seller_contribution_requested'        => 'nullable|in:Yes,No',
                'seller_contribution_details'          => 'nullable|string|max:1000',
                'included_personal_property'           => 'nullable|string|max:1000',
                'excluded_items'                       => 'nullable|string|max:1000',
                'home_warranty_requested'              => 'nullable|in:Yes,No',
                'home_warranty_details'                => 'nullable|string|max:1000',
                'hoa_condo_association_terms'          => 'nullable|string|max:1000',
                'additional_seller_sale_terms'         => 'nullable|string|max:5000',
            ];
        } elseif (in_array($offerType, ['rental', 'lease'])) {
            $typeRules = [
                'monthly_rent'     => 'nullable|numeric|min:0',
                'security_deposit' => 'nullable|numeric|min:0',
                'move_in_date'     => 'nullable|date',
            ];
            if ($offerType === 'lease') {
                $typeRules['lease_term_months'] = 'nullable|integer|min:1|max:360';
            }
        }

        $validated = $request->validate(array_merge($commonRules, $typeRules));

        $offer->load('metas');
        $offer->saveMeta('offer_type', $offerType);

        // expires_at is stored in both the native offers.expires_at column (used by the
        // workflow/state-machine for system-level expiration) and offer_metas (used by
        // the show view to read all buyer-entered terms from a single consistent source).
        // The meta value is buyer-controlled; the native column is system-controlled.
        $offer->saveMeta('expires_at',   $validated['expires_at'] ?? null);
        $offer->saveMeta('custom_terms', $validated['custom_terms'] ?? null);
        $offer->saveMeta('notes',        $validated['notes'] ?? null);

        if ($offerType === 'sale') {
            $offer->saveMeta('offer_price',                         $validated['offer_price'] ?? null);
            $offer->saveMeta('earnest_deposit',                     $validated['earnest_deposit'] ?? null);
            $offer->saveMeta('financing_type',                      $validated['financing_type'] ?? null);
            $offer->saveMeta('down_payment_percent',                $validated['down_payment_percent'] ?? null);
            $offer->saveMeta('financing_contingency',               $request->boolean('financing_contingency') ? 1 : 0);
            $offer->saveMeta('financing_contingency_days',          $validated['financing_contingency_days'] ?? null);
            $offer->saveMeta('inspection_contingency',              $request->boolean('inspection_contingency') ? 1 : 0);
            $offer->saveMeta('inspection_contingency_days',         $validated['inspection_contingency_days'] ?? null);
            $offer->saveMeta('appraisal_contingency',               $request->boolean('appraisal_contingency') ? 1 : 0);
            $offer->saveMeta('closing_date',                        $validated['closing_date'] ?? null);
            $offer->saveMeta('possession_date',                     $validated['possession_date'] ?? null);
            // Assumable sub-fields
            $offer->saveMeta('assumable_terms',                     $validated['assumable_terms'] ?? null);
            $offer->saveMeta('assumable_loan_type',                 $validated['assumable_loan_type'] ?? null);
            $offer->saveMeta('assumable_interest_rate',             $validated['assumable_interest_rate'] ?? null);
            $offer->saveMeta('outstanding_balance',                 $validated['outstanding_balance'] ?? null);
            $offer->saveMeta('assumable_loan_term_remaining',       $validated['assumable_loan_term_remaining'] ?? null);
            // Cryptocurrency sub-fields
            $offer->saveMeta('cryptocurrency_type',                 $validated['cryptocurrency_type'] ?? null);
            $offer->saveMeta('crypto_percentage',                   $validated['crypto_percentage'] ?? null);
            $offer->saveMeta('crypto_exchange_method',              $validated['crypto_exchange_method'] ?? null);
            // Exchange/Trade sub-fields
            $offer->saveMeta('exchange_item',                       $validated['exchange_item'] ?? null);
            $offer->saveMeta('exchange_item_value',                 $validated['exchange_item_value'] ?? null);
            // Seller Financing sub-fields
            $offer->saveMeta('seller_financing_amount',             $validated['seller_financing_amount'] ?? null);
            $offer->saveMeta('seller_financing_rate',               $validated['seller_financing_rate'] ?? null);
            $offer->saveMeta('seller_financing_term',               $validated['seller_financing_term'] ?? null);
            // Purchase Terms
            $offer->saveMeta('initial_deposit_amount',              $validated['initial_deposit_amount'] ?? null);
            $offer->saveMeta('initial_deposit_timeframe',           $validated['initial_deposit_timeframe'] ?? null);
            $offer->saveMeta('initial_deposit_timeframe_other',     $validated['initial_deposit_timeframe_other'] ?? null);
            $offer->saveMeta('additional_deposit_amount',           $validated['additional_deposit_amount'] ?? null);
            $offer->saveMeta('additional_deposit_timeframe',        $validated['additional_deposit_timeframe'] ?? null);
            $offer->saveMeta('additional_deposit_timeframe_other',  $validated['additional_deposit_timeframe_other'] ?? null);
            $offer->saveMeta('escrow_agent_preference',             $validated['escrow_agent_preference'] ?? null);
            $offer->saveMeta('preferred_inspection_period',         $validated['preferred_inspection_period'] ?? null);
            $offer->saveMeta('appraisal_contingency_preference',     $validated['appraisal_contingency_preference'] ?? null);
            $offer->saveMeta('financing_contingency_preference',     $validated['financing_contingency_preference'] ?? null);
            $offer->saveMeta('sale_of_buyer_property_contingency',  $validated['sale_of_buyer_property_contingency'] ?? null);
            $offer->saveMeta('possession_preference',               $validated['possession_preference'] ?? null);
            $offer->saveMeta('possession_details',                  $validated['possession_details'] ?? null);
            $offer->saveMeta('seller_contribution_requested',       $validated['seller_contribution_requested'] ?? null);
            $offer->saveMeta('seller_contribution_details',         $validated['seller_contribution_details'] ?? null);
            $offer->saveMeta('included_personal_property',          $validated['included_personal_property'] ?? null);
            $offer->saveMeta('excluded_items',                      $validated['excluded_items'] ?? null);
            $offer->saveMeta('home_warranty_requested',             $validated['home_warranty_requested'] ?? null);
            $offer->saveMeta('home_warranty_details',               $validated['home_warranty_details'] ?? null);
            $offer->saveMeta('hoa_condo_association_terms',         $validated['hoa_condo_association_terms'] ?? null);
            $offer->saveMeta('additional_seller_sale_terms',        $validated['additional_seller_sale_terms'] ?? null);
        } elseif (in_array($offerType, ['rental', 'lease'])) {
            $offer->saveMeta('monthly_rent',      $validated['monthly_rent'] ?? null);
            $offer->saveMeta('security_deposit',  $validated['security_deposit'] ?? null);
            $offer->saveMeta('move_in_date',      $validated['move_in_date'] ?? null);
            $offer->saveMeta('lease_term_months', $offerType === 'lease' ? ($validated['lease_term_months'] ?? null) : null);
        }

        return redirect()->route('offers.show', $offer)
            ->with('success', 'Offer terms saved successfully.');
    }

    public function show(Offer $offer)
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'system';
        $timeline  = $this->timelineBuilder->buildForOffer($offer);
        $actions   = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        $offer->load('metas', 'offerAuction.metas');

        $metas = $offer->metas->pluck('meta_value', 'meta_key');

        if ($metas->has('offer_type')) {
            $offerType = $metas->get('offer_type');
        } elseif ($offer->offerAuction) {
            $offerType = $offer->offerAuction->info('offer_type') ?: 'sale';
        } else {
            $offerType = 'sale';
        }

        if (!in_array($offerType, ['sale', 'rental', 'lease'])) {
            $offerType = 'sale';
        }

        return view('offers.show', compact('offer', 'timeline', 'actions', 'metas', 'offerType'));
    }
}

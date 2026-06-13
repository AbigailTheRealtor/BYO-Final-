<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Notifications\Offers\OfferAcceptedNotification;
use App\Notifications\Offers\OfferCounteredNotification;
use App\Notifications\Offers\OfferRejectedNotification;
use App\Notifications\Offers\OfferSubmittedNotification;
use App\Notifications\Offers\OfferWithdrawnNotification;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferNegotiationChainService;
use App\Services\Offers\OfferPermissionService;
use App\Services\Offers\OfferTimelineBuilder;
use App\Services\Offers\OfferWorkflowFacade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferWorkflowFacade $facade,
        private readonly OfferAvailableActionsService $actionsService,
        private readonly OfferTimelineBuilder $timelineBuilder,
        private readonly OfferPermissionService $permissionService,
        private readonly OfferNegotiationChainService $chainService,
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

        // Stamp offer_type=rental for landlord-role offers so resolveOfferType()
        // returns the correct type immediately, before any terms are saved.
        if ($validated['role'] === 'landlord') {
            $offer->saveMeta('offer_type', 'rental');
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer);
        }

        return response()->json([
            'message' => 'Offer draft created.',
            'offer'   => $offer,
        ], 201);
    }

    public function submit(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_submit']) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $actions['reasons']['submit']);
            }
            return response()->json(['message' => $actions['reasons']['submit']], 422);
        }

        // If the request carries terms fields (submitted via the "Save & Submit" button),
        // persist them before transitioning status.
        if ($request->boolean('_offer_terms_present')) {
            $offerType = $this->resolveOfferType($offer);
            $this->persistTermsMeta($request, $offer, $offerType);
        } else {
            // No terms in this request — ensure terms were already saved.
            $offer->load('metas', 'offerAuction.metas');
            $offerType = $this->resolveOfferType($offer);
            $hasSavedTerms = $offerType === 'sale'
                ? $offer->getMeta('offer_price') !== null
                : ($offer->getMeta('monthly_rent') !== null);

            if (!$hasSavedTerms) {
                $msg = 'Please save your offer terms before submitting.';
                if (!$request->expectsJson()) {
                    return redirect()->route('offers.show', $offer)->with('error', $msg);
                }
                return response()->json(['message' => $msg], 422);
            }
        }

        $result = $this->facade->submit($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $result['reason']);
            }
            return response()->json(['message' => $result['reason']], 422);
        }

        try {
            $offer->load('offerAuction.user');
            $recipient = $offer->offerAuction?->user;
            if ($recipient) {
                $recipient->notify(new OfferSubmittedNotification($offer));
            }
        } catch (\Throwable $e) {
            Log::error('OfferSubmittedNotification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer)
                ->with('success', 'Offer submitted successfully.');
        }

        return response()->json([
            'message' => 'Offer submitted.',
            'result'  => $result,
        ]);
    }

    public function accept(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_accept']) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $actions['reasons']['accept']);
            }
            return response()->json(['message' => $actions['reasons']['accept']], 422);
        }

        $result = $this->facade->accept($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $result['reason']);
            }
            return response()->json(['message' => $result['reason']], 422);
        }

        try {
            $offer->user->notify(new OfferAcceptedNotification($offer));
        } catch (\Throwable $e) {
            Log::error('OfferAcceptedNotification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer)
                ->with('success', 'Offer accepted successfully.');
        }

        return response()->json([
            'message' => 'Offer accepted.',
            'result'  => $result,
        ]);
    }

    public function reject(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_reject']) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $actions['reasons']['reject']);
            }
            return response()->json(['message' => $actions['reasons']['reject']], 422);
        }

        $result = $this->facade->reject($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $result['reason']);
            }
            return response()->json(['message' => $result['reason']], 422);
        }

        try {
            $offer->user->notify(new OfferRejectedNotification($offer));
        } catch (\Throwable $e) {
            Log::error('OfferRejectedNotification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer)
                ->with('success', 'Offer rejected successfully.');
        }

        return response()->json([
            'message' => 'Offer rejected.',
            'result'  => $result,
        ]);
    }

    public function withdraw(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_withdraw']) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $actions['reasons']['withdraw']);
            }
            return response()->json(['message' => $actions['reasons']['withdraw']], 422);
        }

        $result = $this->facade->withdraw($offer, $actorId, $actorRole, [], $request->ip());

        if ($result['allowed'] === false) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $result['reason']);
            }
            return response()->json(['message' => $result['reason']], 422);
        }

        try {
            $offer->user->notify(new OfferWithdrawnNotification($offer));
        } catch (\Throwable $e) {
            Log::error('OfferWithdrawnNotification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $offer)
                ->with('success', 'Offer withdrawn successfully.');
        }

        return response()->json([
            'message' => 'Offer withdrawn.',
            'result'  => $result,
        ]);
    }

    public function counter(Request $request, Offer $offer): JsonResponse|RedirectResponse
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'buyer';

        $actions = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        if (!$actions['can_counter']) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $actions['reasons']['counter']);
            }
            return response()->json(['message' => $actions['reasons']['counter']], 422);
        }

        $moneyFields = [
            'offer_price', 'earnest_deposit', 'down_payment_value',
            'additional_cash', 'exchange_item_value',
            'sf_purchase_price', 'sf_down_payment_amount', 'seller_financing_amount',
            'seller_financing_balloon_amount', 'prepayment_penalty_amount',
            'option_fee_amount', 'lease_option_price', 'lease_option_payment',
            'lease_purchase_price', 'lease_purchase_payment', 'lease_purchase_deposit',
            'lease_purchase_rent_credit_amount', 'initial_deposit_amount',
            'additional_deposit_amount',
            'assumable_max_monthly_payment', 'assumable_bridge_gap_cash',
            // Rental/Lease money fields
            'monthly_rent', 'security_deposit', 'move_in_funds', 'monthly_income',
        ];
        foreach ($moneyFields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $request->merge([$field => str_replace(',', '', (string) $request->input($field))]);
            }
        }

        $validated = $request->validate([
            'listing_snapshot'                     => 'nullable|array',
            'expires_at'                           => 'nullable|date',
            'notes'                                => 'nullable|string|max:5000',
            'custom_terms'                         => 'nullable|string|max:5000',
            // Sale-specific
            'offer_price'                          => 'nullable|numeric|min:0',
            'earnest_deposit'                      => 'nullable|numeric|min:0',
            'earnest_deposit_unit'                 => 'nullable|in:$,%',
            'financing_type'                       => 'nullable|in:Assumable,Cash,Conventional,FHA,Jumbo,VA,No-Doc,Non-QM,USDA,Cryptocurrency,Exchange/Trade,Lease Option,Lease Purchase,Non-Fungible Token (NFT),Seller Financing,Other',
            'financing_contingency'                => 'nullable|boolean',
            'financing_contingency_days'           => 'nullable|integer|min:1|max:365',
            'down_payment_value'                   => 'nullable|numeric|min:0',
            'down_payment_unit'                    => 'nullable|in:$,%',
            'inspection_contingency'               => 'nullable|boolean',
            'inspection_contingency_days'          => 'nullable|integer|min:1|max:365',
            'appraisal_contingency'                => 'nullable|boolean',
            'appraisal_contingency_days'           => 'nullable|integer|min:1|max:365',
            'closing_date'                         => 'nullable|date',
            'possession_date'                      => 'nullable|date',
            // Assumable sub-fields
            'assumable_interest'                   => 'nullable|in:Yes,No',
            'assumable_max_interest_rate'          => 'nullable|numeric|min:0|max:100',
            'assumable_max_monthly_payment'        => 'nullable|numeric|min:0',
            'assumable_bridge_gap_cash'            => 'nullable|numeric|min:0',
            // Cryptocurrency sub-fields
            'cryptocurrency_type'                  => 'nullable|string|max:200',
            'crypto_percentage'                    => 'nullable|numeric|min:0|max:100',
            'crypto_exchange_method'               => 'nullable|string|max:500',
            // Exchange/Trade sub-fields
            'exchange_item'                        => 'nullable|in:Another Home,Artwork,Boat,Jewelry,Motorhome,Vehicle,Other',
            'other_exchange_item'                  => 'nullable|string|max:500',
            'exchange_item_value'                  => 'nullable|numeric|min:0',
            'exchange_item_condition'              => 'nullable|in:New,Like New,Excellent,Very Good,Good,Fair,Repair,Salvage Condition',
            'additional_cash'                      => 'nullable|numeric|min:0',
            'value_determination'                  => 'nullable|string|max:500',
            'exchange_transfer_method'             => 'nullable|string|max:500',
            'exchange_liens'                       => 'nullable|in:Yes,No',
            'exchange_liens_details'               => 'nullable|string|max:500',
            'exchange_inspection_rights'           => 'nullable|in:Yes,No',
            // Seller Financing sub-fields
            'sf_purchase_price'                    => 'nullable|numeric|min:0',
            'sf_down_payment_type'                 => 'nullable|in:$,%',
            'sf_down_payment_amount'               => 'nullable|numeric|min:0',
            'seller_financing_amount_type'         => 'nullable|in:$,%',
            'seller_financing_amount'              => 'nullable|numeric|min:0',
            'seller_financing_rate'                => 'nullable|numeric|min:0|max:100',
            'seller_financing_term'                => 'nullable|string|max:200',
            'seller_financing_amortization'        => 'nullable|in:Fully Amortizing,Interest-Only,Other',
            'seller_financing_amortization_other'  => 'nullable|string|max:200',
            'seller_financing_payment_frequency'   => 'nullable|in:Monthly,Bi-Weekly,Quarterly,Annually,Other',
            'seller_financing_payment_frequency_other' => 'nullable|string|max:200',
            'seller_financing_balloon'             => 'nullable|in:Yes,No',
            'seller_financing_balloon_amount'      => 'nullable|numeric|min:0',
            'seller_financing_balloon_date'        => 'nullable|string|max:200',
            'prepayment_penalty'                   => 'nullable|in:Yes,No',
            'prepayment_penalty_amount'            => 'nullable|numeric|min:0',
            'seller_late_fee_amount'               => 'nullable|string|max:500',
            // Lease Option sub-fields
            'lease_option_price'                   => 'nullable|numeric|min:0',
            'lease_option_payment'                 => 'nullable|numeric|min:0',
            'lease_option_duration'                => 'nullable|integer|min:1',
            'has_option_fee'                       => 'nullable|in:Yes,No',
            'option_fee_amount'                    => 'nullable|numeric|min:0',
            'lease_option_fee_credit'              => 'nullable|in:Yes,No,Partial',
            'lease_option_fee_credit_pct'          => 'nullable|numeric|min:0|max:100',
            'lease_option_maintenance'             => 'nullable|in:Seller,Tenant-Buyer,Shared',
            'lease_option_conditions'              => 'nullable|string|max:1000',
            'lease_option_terms'                   => 'nullable|string|max:1000',
            'lease_option_extension_terms'         => 'nullable|string|max:1000',
            // Lease Purchase sub-fields
            'lease_purchase_price'                 => 'nullable|numeric|min:0',
            'lease_purchase_payment'               => 'nullable|numeric|min:0',
            'lease_purchase_duration'              => 'nullable|integer|min:1',
            'lease_purchase_rent_credit'           => 'nullable|in:Yes,No,Partial',
            'lease_purchase_rent_credit_amount'    => 'nullable|numeric|min:0',
            'lease_purchase_deposit'               => 'nullable|numeric|min:0',
            'lease_purchase_maintenance'           => 'nullable|in:Seller,Tenant-Buyer,Shared',
            'lease_purchase_conditions'            => 'nullable|string|max:1000',
            'lease_purchase_terms'                 => 'nullable|string|max:1000',
            'lease_purchase_extension_terms'       => 'nullable|string|max:1000',
            // NFT sub-fields
            'nft_description'                      => 'nullable|string|max:500',
            'nft_percentage'                       => 'nullable|numeric|min:0|max:100',
            'cash_percentage_nft'                  => 'nullable|numeric|min:0|max:100',
            'nft_valuation_method'                 => 'nullable|string|max:500',
            'nft_transfer_method'                  => 'nullable|string|max:500',
            'nft_gas_fees'                         => 'nullable|in:Buyer,Seller,Split',
            // Other financing
            'other_financing_details'              => 'nullable|string|max:2000',
            // Deposit details
            'initial_deposit_amount'               => 'nullable|numeric|min:0',
            'initial_deposit_amount_unit'          => 'nullable|in:$,%',
            'initial_deposit_timeframe'            => 'nullable|string|max:100',
            'initial_deposit_timeframe_other'      => 'nullable|string|max:200',
            'additional_deposit_amount'            => 'nullable|numeric|min:0',
            'additional_deposit_amount_unit'       => 'nullable|in:$,%',
            'additional_deposit_timeframe'         => 'nullable|string|max:100',
            'additional_deposit_timeframe_other'   => 'nullable|string|max:200',
            // Purchase terms
            'sale_of_buyer_property_contingency'      => 'nullable|boolean',
            'sale_of_buyer_property_contingency_days' => 'nullable|integer|min:1|max:365',
            'possession_notes'                        => 'nullable|string|max:2000',
            'seller_contribution_requested'        => 'nullable|in:Yes,No',
            'seller_contribution_details'          => 'nullable|string|max:1000',
            'included_personal_property'           => 'nullable|string|max:1000',
            'excluded_items'                       => 'nullable|string|max:1000',
            'home_warranty_requested'              => 'nullable|in:Yes,No',
            'home_warranty_details'                => 'nullable|string|max:1000',
            // Rental/Lease — pre-screening
            'num_occupants'                        => 'nullable|integer|min:1|max:99',
            'has_pets'                             => 'nullable|in:Yes,No,Negotiable',
            'pet_details'                          => 'nullable|string|max:500',
            'smoking_preference'                   => 'nullable|in:Yes,No',
            'monthly_income'                       => 'nullable|numeric|min:0',
            'credit_score_range'                   => 'nullable|string|max:50',
            'screening_notes'                      => 'nullable|string|max:2000',
            'screening_concerns'                   => 'nullable|in:Yes,No',
            'screening_concerns_details'           => 'nullable|string|max:2000',
            'message_to_landlord'                  => 'nullable|string|max:2000',
            // Rental/Lease — offer terms
            'monthly_rent'                         => 'nullable|numeric|min:0',
            'lease_term_months'                    => 'nullable|integer|min:1|max:360',
            'security_deposit'                     => 'nullable|numeric|min:0',
            'last_month_rent_offered'              => 'nullable|in:Yes,No',
            'move_in_funds'                        => 'nullable|numeric|min:0',
            'move_in_date'                         => 'nullable|date',
            'utilities_terms'                      => 'nullable|string|max:500',
            'maintenance_responsibility'           => 'nullable|in:Landlord,Tenant,Shared',
            'parking_terms'                        => 'nullable|string|max:500',
            'additional_lease_terms'               => 'nullable|string|max:2000',
        ]);

        $colOverrides = array_filter(
            array_intersect_key($validated, array_flip(['listing_snapshot', 'expires_at'])),
            fn ($v) => $v !== null,
        );

        $result = $this->facade->counter(
            $offer,
            $actorId,
            $actorRole,
            $colOverrides,
            ['source' => 'web'],
            $request->ip(),
        );

        if ($result['allowed'] === false) {
            if (!$request->expectsJson()) {
                return redirect()->route('offers.show', $offer)
                    ->with('error', $result['reason']);
            }
            return response()->json(['message' => $result['reason']], 422);
        }

        $child = $result['counter_offer'];
        $offer->load('metas');
        $parentMetas = $offer->metas->pluck('meta_value', 'meta_key');

        // All meta keys written by saveTerms() — carried forward from parent to child.
        $termMetaKeys = [
            'offer_type', 'expires_at', 'custom_terms', 'notes',
            'offer_price', 'earnest_deposit', 'earnest_deposit_unit',
            'financing_type', 'down_payment_value', 'down_payment_unit',
            'financing_contingency', 'financing_contingency_days',
            'inspection_contingency', 'inspection_contingency_days',
            'appraisal_contingency', 'appraisal_contingency_days',
            'closing_date', 'possession_date', 'possession_notes',
            // Assumable sub-fields
            'assumable_interest', 'assumable_max_interest_rate',
            'assumable_max_monthly_payment', 'assumable_bridge_gap_cash',
            // Cryptocurrency sub-fields
            'cryptocurrency_type', 'crypto_percentage', 'crypto_exchange_method',
            // Exchange/Trade sub-fields
            'exchange_item', 'other_exchange_item', 'exchange_item_value',
            'exchange_item_condition', 'additional_cash', 'value_determination',
            'exchange_transfer_method', 'exchange_liens', 'exchange_liens_details',
            'exchange_inspection_rights',
            // Seller Financing sub-fields
            'sf_purchase_price', 'sf_down_payment_type', 'sf_down_payment_amount',
            'seller_financing_amount_type', 'seller_financing_amount',
            'seller_financing_rate', 'seller_financing_term',
            'seller_financing_amortization', 'seller_financing_amortization_other',
            'seller_financing_payment_frequency', 'seller_financing_payment_frequency_other',
            'seller_financing_balloon', 'seller_financing_balloon_amount',
            'seller_financing_balloon_date', 'prepayment_penalty',
            'prepayment_penalty_amount', 'seller_late_fee_amount',
            // Lease Option sub-fields
            'lease_option_price', 'lease_option_payment', 'lease_option_duration',
            'has_option_fee', 'option_fee_amount', 'lease_option_fee_credit',
            'lease_option_fee_credit_pct', 'lease_option_maintenance',
            'lease_option_conditions', 'lease_option_terms', 'lease_option_extension_terms',
            // Lease Purchase sub-fields
            'lease_purchase_price', 'lease_purchase_payment', 'lease_purchase_duration',
            'lease_purchase_rent_credit', 'lease_purchase_rent_credit_amount',
            'lease_purchase_deposit', 'lease_purchase_maintenance',
            'lease_purchase_conditions', 'lease_purchase_terms', 'lease_purchase_extension_terms',
            // NFT sub-fields
            'nft_description', 'nft_percentage', 'cash_percentage_nft',
            'nft_valuation_method', 'nft_transfer_method', 'nft_gas_fees',
            // Other financing
            'other_financing_details',
            // Deposit details
            'initial_deposit_amount', 'initial_deposit_amount_unit',
            'initial_deposit_timeframe', 'initial_deposit_timeframe_other',
            'additional_deposit_amount', 'additional_deposit_amount_unit',
            'additional_deposit_timeframe', 'additional_deposit_timeframe_other',
            // Purchase terms
            'sale_of_buyer_property_contingency', 'sale_of_buyer_property_contingency_days',
            'seller_contribution_requested', 'seller_contribution_details',
            'included_personal_property', 'excluded_items',
            'home_warranty_requested', 'home_warranty_details',
            // Rental/Lease — pre-screening
            'num_occupants', 'has_pets', 'pet_details', 'smoking_preference',
            'monthly_income', 'credit_score_range', 'screening_notes',
            'screening_concerns', 'screening_concerns_details', 'message_to_landlord',
            // Rental/Lease — offer terms
            'monthly_rent', 'lease_term_months', 'security_deposit',
            'last_month_rent_offered', 'move_in_funds', 'move_in_date',
            'utilities_terms', 'maintenance_responsibility', 'parking_terms',
            'additional_lease_terms',
        ];
        foreach ($termMetaKeys as $key) {
            $parentVal = $parentMetas->get($key);
            if ($parentVal !== null) {
                $child->saveMeta($key, $parentVal);
            }
        }

        // Boolean contingency fields: HTML checkboxes omit the key when unchecked, so
        // $request->boolean() is the authoritative source — mirrors saveTerms() behaviour.
        // These are handled separately and excluded from the general loop below.
        $boolContingencyFields = [
            'financing_contingency',
            'inspection_contingency',
            'appraisal_contingency',
            'sale_of_buyer_property_contingency',
        ];
        foreach ($boolContingencyFields as $key) {
            $child->saveMeta($key, $request->boolean($key) ? 1 : 0);
        }

        // All other fields the counter party explicitly submitted override parent values.
        // Uses $request->exists() so intentionally-cleared fields (empty string → null)
        // are written through rather than silently keeping the parent value.
        $counterTermFields = [
            'expires_at', 'custom_terms', 'notes',
            'offer_price', 'earnest_deposit', 'earnest_deposit_unit',
            'financing_type', 'down_payment_value', 'down_payment_unit',
            'financing_contingency_days',
            'inspection_contingency_days',
            'appraisal_contingency_days',
            'closing_date', 'possession_date', 'possession_notes',
            'assumable_interest', 'assumable_max_interest_rate',
            'assumable_max_monthly_payment', 'assumable_bridge_gap_cash',
            'cryptocurrency_type', 'crypto_percentage', 'crypto_exchange_method',
            'exchange_item', 'other_exchange_item', 'exchange_item_value',
            'exchange_item_condition', 'additional_cash', 'value_determination',
            'exchange_transfer_method', 'exchange_liens', 'exchange_liens_details',
            'exchange_inspection_rights',
            'sf_purchase_price', 'sf_down_payment_type', 'sf_down_payment_amount',
            'seller_financing_amount_type', 'seller_financing_amount',
            'seller_financing_rate', 'seller_financing_term',
            'seller_financing_amortization', 'seller_financing_amortization_other',
            'seller_financing_payment_frequency', 'seller_financing_payment_frequency_other',
            'seller_financing_balloon', 'seller_financing_balloon_amount',
            'seller_financing_balloon_date', 'prepayment_penalty',
            'prepayment_penalty_amount', 'seller_late_fee_amount',
            'lease_option_price', 'lease_option_payment', 'lease_option_duration',
            'has_option_fee', 'option_fee_amount', 'lease_option_fee_credit',
            'lease_option_fee_credit_pct', 'lease_option_maintenance',
            'lease_option_conditions', 'lease_option_terms', 'lease_option_extension_terms',
            'lease_purchase_price', 'lease_purchase_payment', 'lease_purchase_duration',
            'lease_purchase_rent_credit', 'lease_purchase_rent_credit_amount',
            'lease_purchase_deposit', 'lease_purchase_maintenance',
            'lease_purchase_conditions', 'lease_purchase_terms', 'lease_purchase_extension_terms',
            'nft_description', 'nft_percentage', 'cash_percentage_nft',
            'nft_valuation_method', 'nft_transfer_method', 'nft_gas_fees',
            'other_financing_details',
            'initial_deposit_amount', 'initial_deposit_amount_unit',
            'initial_deposit_timeframe', 'initial_deposit_timeframe_other',
            'additional_deposit_amount', 'additional_deposit_amount_unit',
            'additional_deposit_timeframe', 'additional_deposit_timeframe_other',
            'sale_of_buyer_property_contingency_days',
            'seller_contribution_requested', 'seller_contribution_details',
            'included_personal_property', 'excluded_items',
            'home_warranty_requested', 'home_warranty_details',
            // Rental/Lease — pre-screening
            'num_occupants', 'has_pets', 'pet_details', 'smoking_preference',
            'monthly_income', 'credit_score_range', 'screening_notes',
            'screening_concerns', 'screening_concerns_details', 'message_to_landlord',
            // Rental/Lease — offer terms
            'monthly_rent', 'lease_term_months', 'security_deposit',
            'last_month_rent_offered', 'move_in_funds', 'move_in_date',
            'utilities_terms', 'maintenance_responsibility', 'parking_terms',
            'additional_lease_terms',
        ];
        foreach ($counterTermFields as $key) {
            // Write when the field was explicitly sent in the request (even as null),
            // overriding whatever the parent had.
            if ($request->exists($key) && array_key_exists($key, $validated)) {
                $child->saveMeta($key, $validated[$key]);
            }
        }

        try {
            // Notify the original submitter that their offer was countered.
            $offer->user->notify(new OfferCounteredNotification($offer, $child));
        } catch (\Throwable $e) {
            Log::error('OfferCounteredNotification failed', ['offer_id' => $offer->id, 'error' => $e->getMessage()]);
        }

        try {
            // Notify the same party that a new counter-offer now awaits their response
            // (the child is a freshly-submitted offer directed at them).
            $counterRecipient = $offer->user;
            if ($counterRecipient && $counterRecipient->id !== $actorId) {
                $counterRecipient->notify(new OfferSubmittedNotification($child));
            }
        } catch (\Throwable $e) {
            Log::error('OfferSubmittedNotification (counter) failed', ['offer_id' => $child->id, 'error' => $e->getMessage()]);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('offers.show', $child)
                ->with('success', 'Counter offer created successfully.');
        }

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
        $offerType = $this->resolveOfferType($offer);

        $this->persistTermsMeta($request, $offer, $offerType);

        return redirect()->route('offers.show', $offer)
            ->with('success', 'Offer terms saved successfully.');
    }

    /**
     * Download a PDF of any terminal-state offer detail page.
     * Accessible to any user who can view the offer.
     * Aborts with 404 if the offer chain has not yet reached a terminal state.
     */
    public function downloadPdf(Offer $offer): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user      = Auth::user();
        $actorId   = $user?->id;
        $actorRole = $user?->role ?? $user?->user_type ?? 'system';

        if (!$this->permissionService->canView($offer, $actorId, $actorRole)) {
            abort(403, 'You are not authorised to download this offer PDF.');
        }

        $terminalLeaf = $this->chainService->getTerminalLeaf($offer);
        if ($terminalLeaf === null) {
            abort(404, 'This offer has not reached a terminal state yet and cannot be downloaded.');
        }

        $terminalLeaf->loadMissing('metas');

        // Resolve offer type.
        $offerType = $terminalLeaf->getMeta('offer_type') ?: 'sale';
        if (!in_array($offerType, ['sale', 'rental', 'lease'])) {
            $offerType = 'sale';
        }

        // Negotiation chain — resolved first so chain-level fallback is available.
        $chainCollection = $this->chainService->getChainForOffer($terminalLeaf);
        $rootOffer       = $chainCollection->first();

        // Resolve final terms: accepted offers use the immutable snapshot first.
        $finalTerms      = collect();
        $snapshotMissing = false;
        if ($terminalLeaf->status === 'accepted') {
            // Priority 1: immutable snapshot on the terminal leaf itself.
            $snapshotJson = \App\Models\OfferMeta::where('offer_id', $terminalLeaf->id)
                ->where('meta_key', 'accepted_terms_snapshot')
                ->value('meta_value');
            if ($snapshotJson) {
                $decoded = json_decode($snapshotJson, true);
                if (is_array($decoded)) {
                    $finalTerms = collect($decoded);
                }
            }

            if ($finalTerms->isEmpty()) {
                // Priority 2: live metas on the terminal leaf itself.
                $fallbackTerms = $terminalLeaf->metas
                    ->filter(fn ($m) => $m->meta_key !== 'accepted_terms_snapshot')
                    ->pluck('meta_value', 'meta_key');
                if ($fallbackTerms->isNotEmpty()) {
                    $finalTerms = $fallbackTerms;
                }
            }

            if ($finalTerms->isEmpty()) {
                // Priority 3: walk the chain for the latest accepted offer that has terms
                // (handles legacy records where snapshot was written on a parent offer).
                $acceptedInChain = $chainCollection
                    ->where('status', 'accepted')
                    ->sortByDesc('id');
                foreach ($acceptedInChain as $chainOffer) {
                    if ($chainOffer->id === $terminalLeaf->id) {
                        continue;
                    }
                    $chainSnapshotJson = \App\Models\OfferMeta::where('offer_id', $chainOffer->id)
                        ->where('meta_key', 'accepted_terms_snapshot')
                        ->value('meta_value');
                    if ($chainSnapshotJson) {
                        $decoded = json_decode($chainSnapshotJson, true);
                        if (is_array($decoded)) {
                            $finalTerms = collect($decoded);
                            break;
                        }
                    }
                    // No snapshot — try the chain offer's own live metas.
                    $chainOffer->loadMissing('metas');
                    $chainMetas = $chainOffer->metas
                        ->filter(fn ($m) => $m->meta_key !== 'accepted_terms_snapshot')
                        ->pluck('meta_value', 'meta_key');
                    if ($chainMetas->isNotEmpty()) {
                        $finalTerms = $chainMetas;
                        break;
                    }
                }
            }

            if ($finalTerms->isEmpty()) {
                $snapshotMissing = true;
            }
        } else {
            $finalTerms = $terminalLeaf->metas->pluck('meta_value', 'meta_key');
        }

        // Terminal outcome timestamp from event log.
        $terminalOutcomeAt = null;
        $expectedEventType = 'offer_' . $terminalLeaf->status;
        $terminalEvent = \App\Models\OfferEventLog::where('offer_id', $terminalLeaf->id)
            ->where('event_type', $expectedEventType)
            ->orderByDesc('created_at')
            ->first();
        $terminalOutcomeAt = $terminalEvent?->created_at ?? $terminalLeaf->created_at;

        $html = view('offers.offer_detail_pdf', compact(
            'terminalLeaf', 'finalTerms', 'offerType',
            'chainCollection', 'rootOffer', 'terminalOutcomeAt', 'snapshotMissing',
        ))->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'offer-detail-' . $terminalLeaf->id . '.pdf';

        return $pdf->download($filename);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the offer type from metas or the linked auction.
     * Always returns one of: 'sale', 'rental', 'lease'.
     */
    private function resolveOfferType(Offer $offer): string
    {
        $fromMeta = $offer->getMeta('offer_type');
        if ($fromMeta && in_array($fromMeta, ['sale', 'rental', 'lease'])) {
            return $fromMeta;
        }

        if (!$offer->relationLoaded('offerAuction')) {
            $offer->load('offerAuction.metas');
        }

        $raw = $offer->offerAuction?->info('offer_type') ?: '';

        if (in_array($raw, ['sale', 'rental', 'lease'])) {
            return $raw;
        }

        // Final fallback: role=landlord means a rental application.
        if ($offer->role === 'landlord') {
            return 'rental';
        }

        return 'sale';
    }

    /**
     * Strip comma-formatting, validate, and persist all offer-terms meta keys.
     * Throws a ValidationException on invalid input (Laravel handles the redirect).
     * Called from both saveTerms() and submit() when terms are present in the request.
     */
    private function persistTermsMeta(Request $request, Offer $offer, string $offerType): void
    {
        $moneyFields = [
            'offer_price', 'earnest_deposit', 'down_payment_value',
            'additional_cash', 'exchange_item_value',
            'sf_purchase_price', 'sf_down_payment_amount', 'seller_financing_amount',
            'seller_financing_balloon_amount', 'prepayment_penalty_amount',
            'option_fee_amount', 'lease_option_price', 'lease_option_payment',
            'lease_purchase_price', 'lease_purchase_payment', 'lease_purchase_deposit',
            'lease_purchase_rent_credit_amount', 'initial_deposit_amount',
            'additional_deposit_amount',
            'assumable_max_monthly_payment', 'assumable_bridge_gap_cash',
        ];
        foreach ($moneyFields as $field) {
            if ($request->has($field) && $request->input($field) !== null && $request->input($field) !== '') {
                $request->merge([$field => str_replace(',', '', (string) $request->input($field))]);
            }
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
                'earnest_deposit_unit'                 => 'nullable|in:$,%',
                'financing_type'                       => 'nullable|in:Assumable,Cash,Conventional,FHA,Jumbo,VA,No-Doc,Non-QM,USDA,Cryptocurrency,Exchange/Trade,Lease Option,Lease Purchase,Non-Fungible Token (NFT),Seller Financing,Other',
                'financing_contingency'                => 'nullable|boolean',
                'financing_contingency_days'           => 'nullable|integer|min:1|max:365',
                'down_payment_value'                   => 'nullable|numeric|min:0',
                'down_payment_unit'                    => 'nullable|in:$,%',
                'inspection_contingency'               => 'nullable|boolean',
                'inspection_contingency_days'          => 'nullable|integer|min:1|max:365',
                'appraisal_contingency'                => 'nullable|boolean',
                'appraisal_contingency_days'           => 'nullable|integer|min:1|max:365',
                'closing_date'                         => 'nullable|date',
                'possession_date'                      => 'nullable|date',
                // Assumable sub-fields (buyer perspective)
                'assumable_interest'                   => 'nullable|in:Yes,No',
                'assumable_max_interest_rate'          => 'nullable|numeric|min:0|max:100',
                'assumable_max_monthly_payment'        => 'nullable|numeric|min:0',
                'assumable_bridge_gap_cash'            => 'nullable|numeric|min:0',
                // Cryptocurrency sub-fields
                'cryptocurrency_type'                  => 'nullable|string|max:200',
                'crypto_percentage'                    => 'nullable|numeric|min:0|max:100',
                'crypto_exchange_method'               => 'nullable|string|max:500',
                // Exchange/Trade sub-fields
                'exchange_item'                        => 'nullable|in:Another Home,Artwork,Boat,Jewelry,Motorhome,Vehicle,Other',
                'other_exchange_item'                  => 'nullable|string|max:500',
                'exchange_item_value'                  => 'nullable|numeric|min:0',
                'exchange_item_condition'              => 'nullable|in:New,Like New,Excellent,Very Good,Good,Fair,Repair,Salvage Condition',
                'additional_cash'                      => 'nullable|numeric|min:0',
                'value_determination'                  => 'nullable|string|max:500',
                'exchange_transfer_method'             => 'nullable|string|max:500',
                'exchange_liens'                       => 'nullable|in:Yes,No',
                'exchange_liens_details'               => 'nullable|string|max:500',
                'exchange_inspection_rights'           => 'nullable|in:Yes,No',
                // Seller Financing sub-fields
                'sf_purchase_price'                    => 'nullable|numeric|min:0',
                'sf_down_payment_type'                 => 'nullable|in:$,%',
                'sf_down_payment_amount'               => 'nullable|numeric|min:0',
                'seller_financing_amount_type'         => 'nullable|in:$,%',
                'seller_financing_amount'              => 'nullable|numeric|min:0',
                'seller_financing_rate'                => 'nullable|numeric|min:0|max:100',
                'seller_financing_term'                => 'nullable|string|max:200',
                'seller_financing_amortization'        => 'nullable|in:Fully Amortizing,Interest-Only,Other',
                'seller_financing_amortization_other'  => 'nullable|string|max:200',
                'seller_financing_payment_frequency'   => 'nullable|in:Monthly,Bi-Weekly,Quarterly,Annually,Other',
                'seller_financing_payment_frequency_other' => 'nullable|string|max:200',
                'seller_financing_balloon'             => 'nullable|in:Yes,No',
                'seller_financing_balloon_amount'      => 'nullable|numeric|min:0',
                'seller_financing_balloon_date'        => 'nullable|string|max:200',
                'prepayment_penalty'                   => 'nullable|in:Yes,No',
                'prepayment_penalty_amount'            => 'nullable|numeric|min:0',
                'seller_late_fee_amount'               => 'nullable|string|max:500',
                // Lease Option sub-fields
                'lease_option_price'                   => 'nullable|numeric|min:0',
                'lease_option_payment'                 => 'nullable|numeric|min:0',
                'lease_option_duration'                => 'nullable|integer|min:1',
                'has_option_fee'                       => 'nullable|in:Yes,No',
                'option_fee_amount'                    => 'nullable|numeric|min:0',
                'lease_option_fee_credit'              => 'nullable|in:Yes,No,Partial',
                'lease_option_fee_credit_pct'          => 'nullable|numeric|min:0|max:100',
                'lease_option_maintenance'             => 'nullable|in:Seller,Tenant-Buyer,Shared',
                'lease_option_conditions'              => 'nullable|string|max:1000',
                'lease_option_terms'                   => 'nullable|string|max:1000',
                'lease_option_extension_terms'         => 'nullable|string|max:1000',
                // Lease Purchase sub-fields
                'lease_purchase_price'                 => 'nullable|numeric|min:0',
                'lease_purchase_payment'               => 'nullable|numeric|min:0',
                'lease_purchase_duration'              => 'nullable|integer|min:1',
                'lease_purchase_rent_credit'           => 'nullable|in:Yes,No,Partial',
                'lease_purchase_rent_credit_amount'    => 'nullable|numeric|min:0',
                'lease_purchase_deposit'               => 'nullable|numeric|min:0',
                'lease_purchase_maintenance'           => 'nullable|in:Seller,Tenant-Buyer,Shared',
                'lease_purchase_conditions'            => 'nullable|string|max:1000',
                'lease_purchase_terms'                 => 'nullable|string|max:1000',
                'lease_purchase_extension_terms'       => 'nullable|string|max:1000',
                // NFT sub-fields
                'nft_description'                      => 'nullable|string|max:500',
                'nft_percentage'                       => 'nullable|numeric|min:0|max:100',
                'cash_percentage_nft'                  => 'nullable|numeric|min:0|max:100',
                'nft_valuation_method'                 => 'nullable|string|max:500',
                'nft_transfer_method'                  => 'nullable|string|max:500',
                'nft_gas_fees'                         => 'nullable|in:Buyer,Seller,Split',
                // Other financing
                'other_financing_details'              => 'nullable|string|max:2000',
                // Purchase Terms
                'initial_deposit_amount'               => 'nullable|numeric|min:0',
                'initial_deposit_amount_unit'          => 'nullable|in:$,%',
                'initial_deposit_timeframe'            => 'nullable|string|max:100',
                'initial_deposit_timeframe_other'      => 'nullable|string|max:200',
                'additional_deposit_amount'            => 'nullable|numeric|min:0',
                'additional_deposit_amount_unit'       => 'nullable|in:$,%',
                'additional_deposit_timeframe'         => 'nullable|string|max:100',
                'additional_deposit_timeframe_other'   => 'nullable|string|max:200',
                'sale_of_buyer_property_contingency'        => 'nullable|boolean',
                'sale_of_buyer_property_contingency_days'   => 'nullable|integer|min:1|max:365',
                'possession_notes'                          => 'nullable|string|max:2000',
                'seller_contribution_requested'        => 'nullable|in:Yes,No',
                'seller_contribution_details'          => 'nullable|string|max:1000',
                'included_personal_property'           => 'nullable|string|max:1000',
                'excluded_items'                       => 'nullable|string|max:1000',
                'home_warranty_requested'              => 'nullable|in:Yes,No',
                'home_warranty_details'                => 'nullable|string|max:1000',
            ];
        } elseif (in_array($offerType, ['rental', 'lease'])) {
            $typeRules = [
                // Pre-screening
                'num_occupants'              => 'nullable|integer|min:1|max:99',
                'has_pets'                   => 'nullable|in:Yes,No,Negotiable',
                'pet_details'                => 'nullable|string|max:500',
                'smoking_preference'         => 'nullable|in:Yes,No',
                'monthly_income'             => 'nullable|numeric|min:0',
                'credit_score_range'         => 'nullable|string|max:50',
                'screening_notes'            => 'nullable|string|max:2000',
                'screening_concerns'         => 'nullable|in:Yes,No',
                'screening_concerns_details' => 'nullable|string|max:2000',
                'message_to_landlord'        => 'nullable|string|max:2000',
                // Lease offer terms
                'monthly_rent'               => 'nullable|numeric|min:0',
                'lease_term_months'          => 'nullable|integer|min:1|max:360',
                'security_deposit'           => 'nullable|numeric|min:0',
                'last_month_rent_offered'    => 'nullable|in:Yes,No',
                'move_in_funds'              => 'nullable|numeric|min:0',
                'move_in_date'               => 'nullable|date',
                'utilities_terms'            => 'nullable|string|max:500',
                'maintenance_responsibility' => 'nullable|in:Landlord,Tenant,Shared',
                'parking_terms'              => 'nullable|string|max:500',
                'additional_lease_terms'     => 'nullable|string|max:2000',
            ];
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
            $offer->saveMeta('earnest_deposit_unit',                $validated['earnest_deposit_unit'] ?? '$');
            $offer->saveMeta('financing_type',                      $validated['financing_type'] ?? null);
            $offer->saveMeta('down_payment_value',                  $validated['down_payment_value'] ?? null);
            $offer->saveMeta('down_payment_unit',                   $validated['down_payment_unit'] ?? '%');
            $offer->saveMeta('financing_contingency',               $request->boolean('financing_contingency') ? 1 : 0);
            $offer->saveMeta('financing_contingency_days',          $validated['financing_contingency_days'] ?? null);
            $offer->saveMeta('inspection_contingency',              $request->boolean('inspection_contingency') ? 1 : 0);
            $offer->saveMeta('inspection_contingency_days',         $validated['inspection_contingency_days'] ?? null);
            $offer->saveMeta('appraisal_contingency',               $request->boolean('appraisal_contingency') ? 1 : 0);
            $offer->saveMeta('appraisal_contingency_days',          $validated['appraisal_contingency_days'] ?? null);
            $offer->saveMeta('closing_date',                        $validated['closing_date'] ?? null);
            $offer->saveMeta('possession_date',                     $validated['possession_date'] ?? null);
            // Assumable sub-fields (buyer perspective)
            $offer->saveMeta('assumable_interest',                  $validated['assumable_interest'] ?? null);
            $offer->saveMeta('assumable_max_interest_rate',         $validated['assumable_max_interest_rate'] ?? null);
            $offer->saveMeta('assumable_max_monthly_payment',       $validated['assumable_max_monthly_payment'] ?? null);
            $offer->saveMeta('assumable_bridge_gap_cash',           $validated['assumable_bridge_gap_cash'] ?? null);
            // Cryptocurrency sub-fields
            $offer->saveMeta('cryptocurrency_type',                 $validated['cryptocurrency_type'] ?? null);
            $offer->saveMeta('crypto_percentage',                   $validated['crypto_percentage'] ?? null);
            $offer->saveMeta('crypto_exchange_method',              $validated['crypto_exchange_method'] ?? null);
            // Exchange/Trade sub-fields
            $offer->saveMeta('exchange_item',                       $validated['exchange_item'] ?? null);
            $offer->saveMeta('other_exchange_item',                 $validated['other_exchange_item'] ?? null);
            $offer->saveMeta('exchange_item_value',                 $validated['exchange_item_value'] ?? null);
            $offer->saveMeta('exchange_item_condition',             $validated['exchange_item_condition'] ?? null);
            $offer->saveMeta('additional_cash',                     $validated['additional_cash'] ?? null);
            $offer->saveMeta('value_determination',                 $validated['value_determination'] ?? null);
            $offer->saveMeta('exchange_transfer_method',            $validated['exchange_transfer_method'] ?? null);
            $offer->saveMeta('exchange_liens',                      $validated['exchange_liens'] ?? null);
            $offer->saveMeta('exchange_liens_details',              $validated['exchange_liens_details'] ?? null);
            $offer->saveMeta('exchange_inspection_rights',          $validated['exchange_inspection_rights'] ?? null);
            // Seller Financing sub-fields
            $offer->saveMeta('sf_purchase_price',                   $validated['sf_purchase_price'] ?? null);
            $offer->saveMeta('sf_down_payment_type',                $validated['sf_down_payment_type'] ?? '$');
            $offer->saveMeta('sf_down_payment_amount',              $validated['sf_down_payment_amount'] ?? null);
            $offer->saveMeta('seller_financing_amount_type',        $validated['seller_financing_amount_type'] ?? '$');
            $offer->saveMeta('seller_financing_amount',             $validated['seller_financing_amount'] ?? null);
            $offer->saveMeta('seller_financing_rate',               $validated['seller_financing_rate'] ?? null);
            $offer->saveMeta('seller_financing_term',               $validated['seller_financing_term'] ?? null);
            $offer->saveMeta('seller_financing_amortization',       $validated['seller_financing_amortization'] ?? null);
            $offer->saveMeta('seller_financing_amortization_other', $validated['seller_financing_amortization_other'] ?? null);
            $offer->saveMeta('seller_financing_payment_frequency',  $validated['seller_financing_payment_frequency'] ?? null);
            $offer->saveMeta('seller_financing_payment_frequency_other', $validated['seller_financing_payment_frequency_other'] ?? null);
            $offer->saveMeta('seller_financing_balloon',            $validated['seller_financing_balloon'] ?? null);
            $offer->saveMeta('seller_financing_balloon_amount',     $validated['seller_financing_balloon_amount'] ?? null);
            $offer->saveMeta('seller_financing_balloon_date',       $validated['seller_financing_balloon_date'] ?? null);
            $offer->saveMeta('prepayment_penalty',                  $validated['prepayment_penalty'] ?? null);
            $offer->saveMeta('prepayment_penalty_amount',           $validated['prepayment_penalty_amount'] ?? null);
            $offer->saveMeta('seller_late_fee_amount',              $validated['seller_late_fee_amount'] ?? null);
            // Lease Option sub-fields
            $offer->saveMeta('lease_option_price',                  $validated['lease_option_price'] ?? null);
            $offer->saveMeta('lease_option_payment',                $validated['lease_option_payment'] ?? null);
            $offer->saveMeta('lease_option_duration',               $validated['lease_option_duration'] ?? null);
            $offer->saveMeta('has_option_fee',                      $validated['has_option_fee'] ?? null);
            $offer->saveMeta('option_fee_amount',                   $validated['option_fee_amount'] ?? null);
            $offer->saveMeta('lease_option_fee_credit',             $validated['lease_option_fee_credit'] ?? null);
            $offer->saveMeta('lease_option_fee_credit_pct',         $validated['lease_option_fee_credit_pct'] ?? null);
            $offer->saveMeta('lease_option_maintenance',            $validated['lease_option_maintenance'] ?? null);
            $offer->saveMeta('lease_option_conditions',             $validated['lease_option_conditions'] ?? null);
            $offer->saveMeta('lease_option_terms',                  $validated['lease_option_terms'] ?? null);
            $offer->saveMeta('lease_option_extension_terms',        $validated['lease_option_extension_terms'] ?? null);
            // Lease Purchase sub-fields
            $offer->saveMeta('lease_purchase_price',                $validated['lease_purchase_price'] ?? null);
            $offer->saveMeta('lease_purchase_payment',              $validated['lease_purchase_payment'] ?? null);
            $offer->saveMeta('lease_purchase_duration',             $validated['lease_purchase_duration'] ?? null);
            $offer->saveMeta('lease_purchase_rent_credit',          $validated['lease_purchase_rent_credit'] ?? null);
            $offer->saveMeta('lease_purchase_rent_credit_amount',   $validated['lease_purchase_rent_credit_amount'] ?? null);
            $offer->saveMeta('lease_purchase_deposit',              $validated['lease_purchase_deposit'] ?? null);
            $offer->saveMeta('lease_purchase_maintenance',          $validated['lease_purchase_maintenance'] ?? null);
            $offer->saveMeta('lease_purchase_conditions',           $validated['lease_purchase_conditions'] ?? null);
            $offer->saveMeta('lease_purchase_terms',                $validated['lease_purchase_terms'] ?? null);
            $offer->saveMeta('lease_purchase_extension_terms',      $validated['lease_purchase_extension_terms'] ?? null);
            // NFT sub-fields
            $offer->saveMeta('nft_description',                     $validated['nft_description'] ?? null);
            $offer->saveMeta('nft_percentage',                      $validated['nft_percentage'] ?? null);
            $offer->saveMeta('cash_percentage_nft',                 $validated['cash_percentage_nft'] ?? null);
            $offer->saveMeta('nft_valuation_method',                $validated['nft_valuation_method'] ?? null);
            $offer->saveMeta('nft_transfer_method',                 $validated['nft_transfer_method'] ?? null);
            $offer->saveMeta('nft_gas_fees',                        $validated['nft_gas_fees'] ?? null);
            // Other Financing
            $offer->saveMeta('other_financing_details',             $validated['other_financing_details'] ?? null);
            // Purchase Terms
            $offer->saveMeta('initial_deposit_amount',              $validated['initial_deposit_amount'] ?? null);
            $offer->saveMeta('initial_deposit_amount_unit',         $validated['initial_deposit_amount_unit'] ?? '$');
            $offer->saveMeta('initial_deposit_timeframe',           $validated['initial_deposit_timeframe'] ?? null);
            $offer->saveMeta('initial_deposit_timeframe_other',     $validated['initial_deposit_timeframe_other'] ?? null);
            $offer->saveMeta('additional_deposit_amount',           $validated['additional_deposit_amount'] ?? null);
            $offer->saveMeta('additional_deposit_amount_unit',      $validated['additional_deposit_amount_unit'] ?? '$');
            $offer->saveMeta('additional_deposit_timeframe',        $validated['additional_deposit_timeframe'] ?? null);
            $offer->saveMeta('additional_deposit_timeframe_other',  $validated['additional_deposit_timeframe_other'] ?? null);
            $offer->saveMeta('sale_of_buyer_property_contingency',      $request->boolean('sale_of_buyer_property_contingency') ? 1 : 0);
            $offer->saveMeta('sale_of_buyer_property_contingency_days', $validated['sale_of_buyer_property_contingency_days'] ?? null);
            $offer->saveMeta('possession_notes',                        $validated['possession_notes'] ?? null);
            $offer->saveMeta('seller_contribution_requested',       $validated['seller_contribution_requested'] ?? null);
            $offer->saveMeta('seller_contribution_details',         $validated['seller_contribution_details'] ?? null);
            $offer->saveMeta('included_personal_property',          $validated['included_personal_property'] ?? null);
            $offer->saveMeta('excluded_items',                      $validated['excluded_items'] ?? null);
            $offer->saveMeta('home_warranty_requested',             $validated['home_warranty_requested'] ?? null);
            $offer->saveMeta('home_warranty_details',               $validated['home_warranty_details'] ?? null);
        } elseif (in_array($offerType, ['rental', 'lease'])) {
            // Pre-screening fields
            $offer->saveMeta('num_occupants',              $validated['num_occupants'] ?? null);
            $offer->saveMeta('has_pets',                   $validated['has_pets'] ?? null);
            $offer->saveMeta('pet_details',                $validated['pet_details'] ?? null);
            $offer->saveMeta('smoking_preference',         $validated['smoking_preference'] ?? null);
            $offer->saveMeta('monthly_income',             $validated['monthly_income'] ?? null);
            $offer->saveMeta('credit_score_range',         $validated['credit_score_range'] ?? null);
            $offer->saveMeta('screening_notes',            $validated['screening_notes'] ?? null);
            $offer->saveMeta('screening_concerns',         $validated['screening_concerns'] ?? null);
            $offer->saveMeta('screening_concerns_details', $validated['screening_concerns_details'] ?? null);
            $offer->saveMeta('message_to_landlord',        $validated['message_to_landlord'] ?? null);
            // Lease offer terms
            $offer->saveMeta('monthly_rent',               $validated['monthly_rent'] ?? null);
            $offer->saveMeta('lease_term_months',          $validated['lease_term_months'] ?? null);
            $offer->saveMeta('security_deposit',           $validated['security_deposit'] ?? null);
            $offer->saveMeta('last_month_rent_offered',    $validated['last_month_rent_offered'] ?? null);
            $offer->saveMeta('move_in_funds',              $validated['move_in_funds'] ?? null);
            $offer->saveMeta('move_in_date',               $validated['move_in_date'] ?? null);
            $offer->saveMeta('utilities_terms',            $validated['utilities_terms'] ?? null);
            $offer->saveMeta('maintenance_responsibility', $validated['maintenance_responsibility'] ?? null);
            $offer->saveMeta('parking_terms',              $validated['parking_terms'] ?? null);
            $offer->saveMeta('additional_lease_terms',     $validated['additional_lease_terms'] ?? null);
        }
    }

    public function show(Offer $offer)
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? Auth::user()->user_type ?? 'system';

        if (!$this->permissionService->canView($offer, $actorId, $actorRole)) {
            abort(403, 'You are not authorised to view this offer.');
        }

        // ── Terminal chain guard (must run before the active-leaf redirect) ────
        // If this chain has already reached a terminal outcome, skip the
        // active-leaf redirect entirely. Historical offers show in-place with a
        // banner; the terminal offer shows the terminal UI directly.
        $terminalLeafEarly = $this->chainService->getTerminalLeaf($offer);
        $isInTerminalChain = $terminalLeafEarly !== null;

        if (!$isInTerminalChain) {
            // Redirect stale parents to the active leaf so both parties always
            // land on the offer they can actually act on.
            $activeLeaf = $this->chainService->getActiveLeaf($offer);
            if ($activeLeaf->id !== $offer->id) {
                return redirect()
                    ->route('offers.show', $activeLeaf)
                    ->with('info', 'You have been redirected to the latest counter offer in this negotiation.');
            }
        }

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

        // Walk the parent_offer_id chain to find the root (original) offer.
        // Used to pin the "Application Submitted" timestamp and display original
        // pre-screening data as read-only context in counter mode.
        $rootOffer = $offer;
        $visited   = [$offer->id => true];
        while ($rootOffer->parent_offer_id) {
            if (isset($visited[$rootOffer->parent_offer_id])) {
                break; // guard against circular references
            }
            $parent = Offer::with('metas')->find($rootOffer->parent_offer_id);
            if (!$parent) {
                break;
            }
            $visited[$parent->id] = true;
            $rootOffer = $parent;
        }
        if (!$rootOffer->relationLoaded('metas')) {
            $rootOffer->load('metas');
        }
        $rootMetas = $rootOffer->metas->pluck('meta_value', 'meta_key');

        $counterDefaults = $metas;

        // For landlord-role offers (rental applications), pre-populate form defaults
        // from the landlord listing's asking terms when no terms have been saved yet.
        if ($offer->role === 'landlord' && !$metas->has('monthly_rent')) {
            $linkedLandlordId = $offer->offerAuction?->info('linked_landlord_auction_id');
            if ($linkedLandlordId) {
                $landlordAuction = \App\Models\LandlordAgentAuction::with('meta')->find((int) $linkedLandlordId);
                if ($landlordAuction) {
                    $lMeta = fn($k) => $landlordAuction->meta->where('meta_key', $k)->first()?->meta_value;
                    $prefill = [];
                    // Financial terms
                    if ($v = $lMeta('desired_rental_amount'))       $prefill['monthly_rent']              = $v;
                    if ($v = $lMeta('security_deposit_amount'))     $prefill['security_deposit']          = $v;
                    if ($v = $lMeta('total_move_in_funds_required')) $prefill['move_in_funds']            = $v;
                    // Dates
                    if ($v = $lMeta('available_date') ?: $lMeta('lease_available_date') ?: $lMeta('lease_date')) {
                        $prefill['move_in_date'] = $v;
                    }
                    // Lease conditions that the landlord has already defined
                    if ($v = $lMeta('utilities'))                   $prefill['utilities_terms']           = $v;
                    if ($v = $lMeta('maintenance_by'))              $prefill['maintenance_responsibility'] = $v;
                    if ($v = $lMeta('parking_terms') ?: $lMeta('commercial_parking_terms')) {
                        $prefill['parking_terms'] = $v;
                    }
                    $counterDefaults = $metas->merge($prefill);
                }
            }
        }

        // ── Terminal negotiation state ─────────────────────────────────────────
        // Reuse the terminal leaf resolved above (before the redirect guard).
        $terminalLeaf = $terminalLeafEarly;
        $isTerminal   = $terminalLeaf !== null && $terminalLeaf->id === $offer->id;
        $isHistorical = $terminalLeaf !== null && $terminalLeaf->id !== $offer->id;

        // Chain summary: ordered array of [offer_id, status, created_at_formatted].
        $chainCollection = $this->chainService->getChainForOffer($offer);
        $chainSummary    = $chainCollection->map(function ($chainOffer) {
            return [
                'offer_id'             => $chainOffer->id,
                'status'               => $chainOffer->status,
                'created_at_formatted' => $chainOffer->created_at?->format('M j, Y g:i A'),
            ];
        })->all();

        // Terminal outcome timestamp — derived from the event log entry that recorded the
        // terminal transition (event_type = 'offer_accepted', 'offer_rejected', etc.).
        // Using the event log avoids drift caused by later updated_at mutations on the model.
        // Falls back to the offer's created_at only as a last resort for legacy records.
        $terminalOutcomeAt = null;
        if ($terminalLeaf !== null) {
            $expectedEventType = 'offer_' . $terminalLeaf->status; // e.g. offer_accepted
            $terminalEvent = \App\Models\OfferEventLog::where('offer_id', $terminalLeaf->id)
                ->where('event_type', $expectedEventType)
                ->orderByDesc('created_at')
                ->first();
            $terminalOutcomeAt = $terminalEvent?->created_at ?? $terminalLeaf->created_at;
        }

        // Final terms: for accepted offers read exclusively from the immutable snapshot —
        // live metas are never consulted so that post-acceptance edits cannot alter the record.
        // For other terminal statuses (rejected/withdrawn/expired) the offer's live metas are used.
        // $snapshotMissing is passed to the view so it can render a controlled notice when the
        // snapshot has not been written yet (e.g. very old records predating this feature).
        $finalTerms     = collect();
        $snapshotMissing = false;
        if ($terminalLeaf !== null) {
            if ($terminalLeaf->status === 'accepted') {
                // Priority 1: immutable snapshot on the terminal leaf itself.
                $snapshotJson = \App\Models\OfferMeta::where('offer_id', $terminalLeaf->id)
                    ->where('meta_key', 'accepted_terms_snapshot')
                    ->value('meta_value');
                if ($snapshotJson) {
                    $decoded = json_decode($snapshotJson, true);
                    if (is_array($decoded)) {
                        $finalTerms = collect($decoded);
                    }
                }

                if ($finalTerms->isEmpty()) {
                    // Priority 2: live metas on the terminal leaf itself.
                    $terminalLeaf->loadMissing('metas');
                    $fallbackTerms = $terminalLeaf->metas
                        ->filter(fn ($m) => $m->meta_key !== 'accepted_terms_snapshot')
                        ->pluck('meta_value', 'meta_key');
                    if ($fallbackTerms->isNotEmpty()) {
                        $finalTerms = $fallbackTerms;
                    }
                }

                if ($finalTerms->isEmpty()) {
                    // Priority 3: walk the chain for the latest accepted offer that has a snapshot
                    // (handles legacy records where the snapshot was written on a parent).
                    $acceptedInChain = $chainCollection
                        ->where('status', 'accepted')
                        ->sortByDesc('id');
                    foreach ($acceptedInChain as $chainOffer) {
                        if ($chainOffer->id === $terminalLeaf->id) {
                            continue;
                        }
                        $chainSnapshotJson = \App\Models\OfferMeta::where('offer_id', $chainOffer->id)
                            ->where('meta_key', 'accepted_terms_snapshot')
                            ->value('meta_value');
                        if ($chainSnapshotJson) {
                            $decoded = json_decode($chainSnapshotJson, true);
                            if (is_array($decoded)) {
                                $finalTerms = collect($decoded);
                                break;
                            }
                        }
                        // No snapshot on this chain offer — try its live metas.
                        $chainOffer->loadMissing('metas');
                        $chainMetas = $chainOffer->metas
                            ->filter(fn ($m) => $m->meta_key !== 'accepted_terms_snapshot')
                            ->pluck('meta_value', 'meta_key');
                        if ($chainMetas->isNotEmpty()) {
                            $finalTerms = $chainMetas;
                            break;
                        }
                    }
                }

                if ($finalTerms->isEmpty()) {
                    $snapshotMissing = true;
                }
            } else {
                $terminalLeaf->loadMissing('metas');
                $finalTerms = $terminalLeaf->metas->pluck('meta_value', 'meta_key');
            }
        }

        return view('offers.show', compact(
            'offer', 'timeline', 'actions', 'metas', 'offerType',
            'counterDefaults', 'rootOffer', 'rootMetas',
            'terminalLeaf', 'isTerminal', 'isHistorical', 'chainSummary',
            'finalTerms', 'snapshotMissing', 'terminalOutcomeAt'
        ));
    }
}

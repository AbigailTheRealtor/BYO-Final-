<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\Offers\OfferAvailableActionsService;
use App\Services\Offers\OfferTimelineBuilder;
use App\Services\Offers\OfferWorkflowFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends Controller
{
    public function __construct(
        private readonly OfferWorkflowFacade $facade,
        private readonly OfferAvailableActionsService $actionsService,
        private readonly OfferTimelineBuilder $timelineBuilder,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offer_auction_id' => 'required|integer',
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

        return response()->json([
            'message' => 'Counter offer created.',
            'result'  => $result,
        ]);
    }

    public function show(Offer $offer)
    {
        $actorId   = Auth::id();
        $actorRole = Auth::user()->role ?? 'system';
        $timeline  = $this->timelineBuilder->buildForOffer($offer);
        $actions   = $this->actionsService->forOffer($offer, $actorId, $actorRole);

        return view('offers.show', compact('offer', 'timeline', 'actions'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ListingPreferences\ListingPreferenceContextResolver;
use App\Services\ListingPreferences\ListingPreferenceMissing;
use App\Services\ListingPreferences\ListingPreferenceReader;
use App\Services\ListingPreferences\ListingPreferenceSubjectUnresolvable;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceAvailability;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Save | Maybe | Pass, for an authenticated Buyer or Tenant.
 *
 * WHAT THIS CONTROLLER IS NOT ALLOWED TO DECIDE. It does not touch a model, it
 * does not compute a subject key, it does not judge a reason, and it does not
 * read the feature flag. Those belong to ListingPreferenceWriter,
 * ListingPreferenceSubjectResolver, ListingPreferenceReasonPolicy and the
 * route's middleware respectively. It validates the request's SHAPE and
 * delegates.
 *
 * NOTHING IS TRUSTED FROM THE BROWSER
 * -----------------------------------
 *   listing_type  validated against SmartTagListingType, which is the only
 *                 producer of those strings
 *   listing_id    a positive integer; the LISTING is then resolved server-side
 *   subject_key   never accepted at all — it is derived from the listing by
 *                 ListingPreferenceSubjectResolver, so it cannot be spoofed to
 *                 point a preference at another property
 *   seeker_role   never accepted — taken from the authenticated account's
 *                 user_type, so a buyer cannot file tenant preferences
 *   reasons       chip keys only, re-projected through the policy server-side;
 *                 a Smart Tag that is not seeker-selectable is refused here
 *                 even if the browser offered it
 *   user_id       never accepted — always the authenticated user, so one
 *                 account cannot mutate another's preference
 *
 * The surface is likewise fixed server-side: this controller serves the
 * property-detail surface, and a browser cannot relabel its own event.
 */
class ListingPreferenceController extends Controller
{
    /** Phase 2 wires one surface. A later surface gets its own route, not a request field. */
    private const SURFACE = 'detail';

    public function __construct(
        private readonly ListingPreferenceWriter $writer,
        private readonly ListingPreferenceReader $reader,
        private readonly ListingPreferenceContextResolver $contexts,
    ) {
    }

    /** Record or change the state. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_type' => ['required', 'string', Rule::in(array_map(
                static fn (SmartTagListingType $t): string => $t->value,
                SmartTagListingType::cases(),
            ))],
            'listing_id' => ['required', 'integer', 'min:1'],
            'state'      => ['required', 'string', Rule::in(ListingPreferenceState::values())],
            'reasons'    => ['sometimes', 'array', 'max:40'],
            'reasons.*'  => ['string', 'max:64'],
        ]);

        return $this->guarded($request, $data, function (SmartTagListingRef $ref, int $userId, $role) use ($data) {
            return $this->writer->setState(
                userId:           $userId,
                role:             $role,
                ref:              $ref,
                state:            ListingPreferenceState::from($data['state']),
                requestedReasons: $data['reasons'] ?? [],
                surface:          self::SURFACE,
            );
        });
    }

    /**
     * Replace the reasons behind the existing state.
     *
     * One request carrying the final set — not one per chip click. Selecting
     * chips is browsing; pressing Done is the decision, and only the decision
     * earns a history event.
     */
    public function reasons(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_type' => ['required', 'string', Rule::in(array_map(
                static fn (SmartTagListingType $t): string => $t->value,
                SmartTagListingType::cases(),
            ))],
            'listing_id' => ['required', 'integer', 'min:1'],
            'reasons'    => ['present', 'array', 'max:40'],
            'reasons.*'  => ['string', 'max:64'],
        ]);

        return $this->guarded($request, $data, function (SmartTagListingRef $ref, int $userId, $role) use ($data) {
            return $this->writer->updateReasons(
                userId:           $userId,
                role:             $role,
                ref:              $ref,
                requestedReasons: $data['reasons'],
                surface:          self::SURFACE,
            );
        });
    }

    /** Undo: remove the current preference, keeping the history. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_type' => ['required', 'string', Rule::in(array_map(
                static fn (SmartTagListingType $t): string => $t->value,
                SmartTagListingType::cases(),
            ))],
            'listing_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->guarded($request, $data, function (SmartTagListingRef $ref, int $userId, $role) {
            return $this->writer->clear(
                userId:  $userId,
                role:    $role,
                ref:     $ref,
                surface: self::SURFACE,
            );
        });
    }

    /** The current state, for a surface refreshing itself. */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_type' => ['required', 'string', Rule::in(array_map(
                static fn (SmartTagListingType $t): string => $t->value,
                SmartTagListingType::cases(),
            ))],
            'listing_id' => ['required', 'integer', 'min:1'],
        ]);

        $ref          = $this->reference($data);
        $availability = $this->availabilityFor($request, $ref);

        if (! $availability->allowed) {
            return $this->refusal($availability);
        }

        return response()->json([
            'success' => true,
        ] + $this->reader->current((int) $request->user()->getAuthIdentifier(), $availability->seekerRole, $ref));
    }

    /**
     * The one authorization path: resolve the listing, decide availability from
     * the ACCOUNT and the listing's own context, then run the mutation.
     */
    private function guarded(Request $request, array $data, callable $mutation): JsonResponse
    {
        try {
            $ref = $this->reference($data);
        } catch (InvalidArgumentException) {
            return response()->json(['success' => false, 'error' => 'Unknown listing.'], 422);
        }

        $availability = $this->availabilityFor($request, $ref);

        if (! $availability->allowed) {
            return $this->refusal($availability);
        }

        try {
            $outcome = $mutation($ref, (int) $request->user()->getAuthIdentifier(), $availability->seekerRole);
        } catch (ListingPreferenceSubjectUnresolvable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (ListingPreferenceMissing $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true] + $outcome->toArray());
    }

    private function reference(array $data): SmartTagListingRef
    {
        return new SmartTagListingRef(
            SmartTagListingType::from($data['listing_type']),
            (int) $data['listing_id'],
        );
    }

    private function availabilityFor(Request $request, SmartTagListingRef $ref): ListingPreferenceAvailability
    {
        // PHASE 2'S CONTEXT OBLIGATION: resolve the listing's real context
        // before deciding anything. The null-context fallback in the reason
        // policy is reached only when this genuinely cannot answer.
        return ListingPreferenceAvailability::for($request->user(), $this->contexts->resolve($ref));
    }

    private function refusal(ListingPreferenceAvailability $availability): JsonResponse
    {
        $status = $availability->isGuest() ? 401 : 403;

        return response()->json([
            'success' => false,
            'reason'  => $availability->reason,
            'error'   => $availability->isGuest()
                ? 'Sign in to save, maybe or pass on a listing.'
                : 'This account cannot express a preference on this listing.',
        ], $status);
    }
}

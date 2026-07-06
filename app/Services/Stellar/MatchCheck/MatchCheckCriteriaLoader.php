<?php

namespace App\Services\Stellar\MatchCheck;

use App\Models\User;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;

/**
 * Match Check criteria-payload loader (Phase 4 · Wave 2 / C7).
 *
 * Fills the single seam C6 left open: MatchCheckResult::CRITERIA_NOT_LOADED. A
 * MatchCheckPreparation (C5) carries only the preferred-criteria *descriptor*
 * (array{id, type, label, created_at}) — enough to identify the record but not the
 * scorable payload. MatchCheckScorer / MatchCheckOrchestrator::evaluate() therefore accept a
 * BuyerCriteriaPayload from the outside and short-circuit to CRITERIA_NOT_LOADED when none is
 * supplied. This adapter is the producer of that payload.
 *
 * DELEGATION, NOT DUPLICATION. It performs no field mapping of its own. It reuses the exact
 * per-type criteria loaders the consumer results flow already uses
 * (StellarBuyerResultsController::loadCriteriaById), dispatched by the descriptor's `type`,
 * and constructs the DTO with the same `new BuyerCriteriaPayload($array)` call — guarded by
 * the same InvalidArgumentException catch that controller uses.
 *
 * READ-ONLY / INERT / UNWIRED BY DESIGN. It issues only the scoped SELECT that loadById()
 * already performs; it writes nothing, dispatches no queue, fires no event, calls no external
 * or Bridge API, and triggers no Location DNA work or scoring. It is not referenced by any
 * route, controller, Livewire, Blade, job, or other service — the composition that would call
 * it from evaluate() is a deliberately separate, later slice. While
 * config('mls_match_check.enabled') is OFF (its default) there is, additionally, no runtime
 * path that reaches this class at all.
 *
 * Fails closed: any inability to produce a valid payload (no descriptor, missing/inaccessible
 * record, or incomplete criteria the DTO rejects) returns null rather than throwing, leaving
 * the downstream scorer in its scoreless CRITERIA_NOT_LOADED / NO_CRITERIA state.
 */
final class MatchCheckCriteriaLoader
{
    public function __construct(
        private readonly BuyerCriteriaLoader $buyerCriteriaLoader,
        private readonly TenantCriteriaLoader $tenantCriteriaLoader,
        private readonly BuyerOfferListingCriteriaLoader $buyerOfferLoader,
        private readonly TenantOfferListingCriteriaLoader $tenantOfferLoader,
        private readonly CriteriaListingResolver $criteriaResolver,
    ) {
    }

    /**
     * Produce the scorable BuyerCriteriaPayload for a prepared (listing, user) decision, or
     * null when none can be produced. Read-only; never throws to its caller.
     */
    public function load(MatchCheckPreparation $preparation, User $user): ?BuyerCriteriaPayload
    {
        // Nothing to load: no accessible preferred record was resolved (empty state / agent).
        if (! $preparation->hasPreferredCriteria()) {
            return null;
        }

        $descriptor = $preparation->preferredCriteria;
        $type = (string) ($descriptor['type'] ?? '');
        $id = (int) ($descriptor['id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        // Same access scope the rest of the criteria stack uses: consumer -> [self];
        // agent -> self + client ids.
        $allowedUserIds = $this->criteriaResolver->resolveAllowedUserIds($user);

        // Dispatch by descriptor type to the existing loader — same per-type mapping as
        // StellarBuyerResultsController::loadCriteriaById() for the four real criteria types.
        // Each returns the flat array accepted by BuyerCriteriaPayload, or null when the
        // record is gone / not accessible / has unresolvable property_types. (Unlike the
        // controller's `default => buyer` fallback, an unrecognized type fails closed to null
        // here — an inert read adapter should never guess an engine for a bad descriptor.)
        $criteriaData = $this->loadFlatArray($type, $id, $allowedUserIds);

        if ($criteriaData === null) {
            return null;
        }

        // Same construction (and same failure handling) as the results controller: an
        // incomplete record makes the DTO throw; treat that as "no scorable payload".
        try {
            return new BuyerCriteriaPayload($criteriaData);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Delegate to the existing per-type loader. Same per-type dispatch as
     * StellarBuyerResultsController::loadCriteriaById() (unknown type -> null, fail-closed);
     * adds no mapping of its own.
     *
     * @param  int[]  $allowedUserIds
     */
    private function loadFlatArray(string $type, int $id, array $allowedUserIds): ?array
    {
        return match ($type) {
            'tenant'       => $this->tenantCriteriaLoader->loadById($id, $allowedUserIds),
            'buyer_offer'  => $this->buyerOfferLoader->loadById($id, $allowedUserIds),
            'tenant_offer' => $this->tenantOfferLoader->loadById($id, $allowedUserIds),
            'buyer'        => $this->buyerCriteriaLoader->loadById($id, $allowedUserIds),
            default        => null,
        };
    }
}

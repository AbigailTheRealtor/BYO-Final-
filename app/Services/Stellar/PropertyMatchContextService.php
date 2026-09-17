<?php

namespace App\Services\Stellar;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Scores a single BridgeProperty against the requesting user's criteria
 * and returns a Blade-safe match context array for the property detail page.
 *
 * Returns null when no criteria can be loaded (wrong owner, bad ID, etc.).
 * Failures inside scoring are re-thrown to the caller to handle gracefully.
 */
class PropertyMatchContextService
{
    public function __construct(
        private BuyerCriteriaLoader              $buyerLoader,
        private TenantCriteriaLoader             $tenantLoader,
        private BuyerOfferListingCriteriaLoader  $buyerOfferLoader,
        private TenantOfferListingCriteriaLoader $tenantOfferLoader,
        private BuyerMatchScorer                 $scorer,
        private BuyerResultViewMapper            $viewMapper,
    ) {}

    /**
     * Load criteria, score the listing, and return a Blade-safe match context,
     * or null when criteria cannot be found or the payload is invalid.
     *
     * @return array{total_score:int,score_display:string,category_bars:array,
     *               why_this_matches:array,tradeoffs:array,caution_flags:array,
     *               missing_data:array,important_places:array}|null
     */
    public function resolve(
        BridgeProperty $listing,
        string $criteriaType,
        int $criteriaId,
        User $user
    ): ?array {
        $criteriaData = $this->loadCriteria($criteriaType, $criteriaId, [$user->id]);
        if ($criteriaData === null) {
            return null;
        }

        try {
            $payload = new BuyerCriteriaPayload($criteriaData);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $matchResult = $this->scorer->score($listing, $payload);
        $mapped      = $this->viewMapper->mapOne($matchResult);

        return array_intersect_key($mapped, array_flip([
            'total_score',
            'score_display',
            'category_bars',
            'why_this_matches',
            'tradeoffs',
            'caution_flags',
            'missing_data',
            'important_places', // category + distance + verdict only (ImportantPlaceMatcher::present())
        ]));
    }

    /**
     * THE THIRD READER OF A CRITERIA TYPE, AND IT MUST AGREE WITH THE OTHER TWO.
     *
     * `criteria_type` reaches this service straight from the property-detail
     * page's query string, and StellarPropertyDetailController defaults it to
     * 'buyer' when absent. The old `default` arm therefore sent both a hand-typed
     * `?criteria_type=buyer` AND every request with no type at all into the legacy
     * BuyerCriteriaLoader — the loader that returns null for every record the
     * legacy form ever wrote.
     *
     * Retiring the legacy types from CriteriaListingResolver stops them being
     * OFFERED; it does not stop them being TYPED. Selection and loading have to
     * agree in every reader, so the guard is repeated here exactly as it is in
     * StellarBuyerResultsController and MatchCheckCriteriaLoader.
     *
     * Nothing real is lost by the `default => null`: a request that names no
     * criteria type cannot say which profile it means, and the legacy loader it
     * used to fall through to would have returned null anyway. The page simply
     * renders without a match-context block, which is what it already does for
     * any criteria it cannot load.
     *
     * The two legacy loaders stay injected so reviving either flow is one line.
     */
    private function loadCriteria(string $type, int $id, array $allowedUserIds): ?array
    {
        if (CriteriaListingResolver::isRetiredLegacyType($type)) {
            return null;
        }

        return match ($type) {
            'buyer_offer'  => $this->buyerOfferLoader->loadById($id, $allowedUserIds),
            'tenant_offer' => $this->tenantOfferLoader->loadById($id, $allowedUserIds),
            default        => null,
        };
    }
}

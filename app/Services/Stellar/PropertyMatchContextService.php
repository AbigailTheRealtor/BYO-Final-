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
     *               missing_data:array}|null
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
        ]));
    }

    private function loadCriteria(string $type, int $id, array $allowedUserIds): ?array
    {
        return match ($type) {
            'tenant'       => $this->tenantLoader->loadById($id, $allowedUserIds),
            'buyer_offer'  => $this->buyerOfferLoader->loadById($id, $allowedUserIds),
            'tenant_offer' => $this->tenantOfferLoader->loadById($id, $allowedUserIds),
            default        => $this->buyerLoader->loadById($id, $allowedUserIds),
        };
    }
}

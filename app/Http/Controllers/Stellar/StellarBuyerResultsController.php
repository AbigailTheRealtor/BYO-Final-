<?php

namespace App\Http\Controllers\Stellar;

use App\Http\Controllers\Controller;
use App\Models\BridgeProperty;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class StellarBuyerResultsController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private BuyerCriteriaLoader               $buyerCriteriaLoader,
        private TenantCriteriaLoader              $tenantCriteriaLoader,
        private BuyerOfferListingCriteriaLoader   $buyerOfferLoader,
        private TenantOfferListingCriteriaLoader  $tenantOfferLoader,
        private CriteriaListingResolver           $criteriaResolver,
        private BuyerMatchService                 $matchService,
        private BuyerResultViewMapper             $viewMapper
    ) {}

    public function index(Request $request)
    {
        $user           = $request->user();
        $userId         = $user->id;
        $allowedUserIds = $this->criteriaResolver->resolveAllowedUserIds($user);

        // -----------------------------------------------------------------------
        // Empty state: import pipeline never ran (table is empty)
        // -----------------------------------------------------------------------
        if (Schema::hasTable('bridge_properties') && BridgeProperty::count() === 0) {
            return $this->emptyView('import_unavailable');
        }

        // -----------------------------------------------------------------------
        // Empty state: no active residential inventory imported
        // -----------------------------------------------------------------------
        if (!BridgeProperty::where('standard_status', 'Active')
                ->where('property_type', 'Residential')
                ->exists()) {
            return $this->emptyView('no_inventory');
        }

        // -----------------------------------------------------------------------
        // Resolve criteria — explicit selection OR auto-detect via resolver
        // -----------------------------------------------------------------------
        $criteriaType = $request->input('criteria_type');
        $criteriaId   = $request->input('criteria_id');

        if ($criteriaType && $criteriaId) {
            $criteriaIdInt = (int) $criteriaId;
            $criteriaList  = $this->criteriaResolver->resolveAccessible($user);
            $criteriaData  = $this->loadCriteriaById($criteriaType, $criteriaIdInt, $allowedUserIds);

            if ($criteriaData === null) {
                return $this->emptyView('no_criteria_listings', ['criteriaList' => []]);
            }

            $selectedType  = $criteriaType;
            $selectedId    = $criteriaIdInt;
        } else {
            $criteriaList = $this->criteriaResolver->resolveAccessible($user);

            if (count($criteriaList) === 0) {
                if ($user->user_type === 'agent') {
                    return $this->emptyView('no_criteria_listings', ['criteriaList' => []]);
                }
                return $this->emptyView('no_criteria', ['criteriaList' => []]);
            }

            // Auto-select the preferred criteria.
            // Modern offer listing records (buyer_offer / tenant_offer) are preferred over
            // legacy criteria records (buyer / tenant) when both exist for the same user.
            // The criteria switcher strip remains visible for all records regardless.
            $selected     = $this->findPreferredCriteria($criteriaList);
            $selectedType = $selected['type'];
            $selectedId   = $selected['id'];
            $criteriaData = $this->loadCriteriaById($selectedType, $selectedId, $allowedUserIds);

            if ($criteriaData === null) {
                return $this->emptyView('no_criteria', [
                    'criteriaList'          => $criteriaList,
                    'selectedCriteriaType'  => $selectedType,
                    'selectedCriteriaId'    => $selectedId,
                    'selectedCriteriaLabel' => $selected['label'],
                ]);
            }
        }

        $selectedLabel = $this->findLabel($criteriaList, $selectedType, $selectedId);

        // -----------------------------------------------------------------------
        // Empty state: criteria data incomplete (property_types empty, etc.)
        // -----------------------------------------------------------------------
        try {
            $criteria = new BuyerCriteriaPayload($criteriaData);
        } catch (\InvalidArgumentException $e) {
            return $this->emptyView('no_criteria', [
                'criteriaList'          => $criteriaList,
                'selectedCriteriaType'  => $selectedType,
                'selectedCriteriaId'    => $selectedId,
                'selectedCriteriaLabel' => $selectedLabel,
            ]);
        }

        // -----------------------------------------------------------------------
        // Empty state: no location constraint
        // -----------------------------------------------------------------------
        $hasLocation = !empty($criteriaData['preferred_cities'])
            || !empty($criteriaData['preferred_zip_codes'])
            || !empty($criteriaData['preferred_counties'])
            || !empty($criteriaData['radius_searches'])
            || !empty($criteriaData['polygons']);

        if (!$hasLocation) {
            return $this->emptyView('no_location', [
                'criteriaList'          => $criteriaList,
                'selectedCriteriaType'  => $selectedType,
                'selectedCriteriaId'    => $selectedId,
                'selectedCriteriaLabel' => $selectedLabel,
            ]);
        }

        // -----------------------------------------------------------------------
        // Run the matching pipeline
        // -----------------------------------------------------------------------
        $matchedCollection = $this->matchService->match($criteria, 200);

        // -----------------------------------------------------------------------
        // Empty state: no matches
        // -----------------------------------------------------------------------
        if ($matchedCollection->isEmpty()) {
            return $this->emptyView('no_matches', [
                'criteriaList'          => $criteriaList,
                'selectedCriteriaType'  => $selectedType,
                'selectedCriteriaId'    => $selectedId,
                'selectedCriteriaLabel' => $selectedLabel,
            ]);
        }

        // -----------------------------------------------------------------------
        // Map to Blade-safe arrays and paginate in-memory
        // -----------------------------------------------------------------------
        $mapped    = $this->viewMapper->map($matchedCollection);
        $total     = count($mapped);
        $page      = max(1, (int) $request->get('page', 1));
        $offset    = ($page - 1) * self::PER_PAGE;
        $slice     = array_slice($mapped, $offset, self::PER_PAGE);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('stellar.buyer.results', [
            'emptyState'             => null,
            'results'                => $slice,
            'paginator'              => $paginator,
            'total'                  => $total,
            'criteriaList'           => $criteriaList,
            'selectedCriteriaType'   => $selectedType,
            'selectedCriteriaId'     => $selectedId,
            'selectedCriteriaLabel'  => $selectedLabel,
            'selectedCriteriaEditUrl' => $this->buildEditUrl($selectedType, $selectedId),
            'buyerCriteriaAddUrl'    => url('/offer-listing/buyer'),
            'tenantCriteriaAddUrl'   => url('/offer-listing/tenant/tenant'),
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Dispatch criteria loading to the correct loader by type token.
     *
     * Legacy types:  'buyer'  → BuyerCriteriaLoader
     *                'tenant' → TenantCriteriaLoader
     * Modern types:  'buyer_offer'  → BuyerOfferListingCriteriaLoader
     *                'tenant_offer' → TenantOfferListingCriteriaLoader
     *
     * @param  int[] $allowedUserIds  User IDs allowed to own this record.
     */
    private function loadCriteriaById(string $type, int $id, array $allowedUserIds): ?array
    {
        return match ($type) {
            'tenant'       => $this->tenantCriteriaLoader->loadById($id, $allowedUserIds),
            'buyer_offer'  => $this->buyerOfferLoader->loadById($id, $allowedUserIds),
            'tenant_offer' => $this->tenantOfferLoader->loadById($id, $allowedUserIds),
            default        => $this->buyerCriteriaLoader->loadById($id, $allowedUserIds),
        };
    }

    /**
     * Find the label for the selected criteria from the criteria list.
     */
    private function findLabel(array $criteriaList, string $type, int $id): string
    {
        foreach ($criteriaList as $item) {
            if ($item['type'] === $type && $item['id'] === $id) {
                return $item['label'];
            }
        }

        return ucfirst($type) . " Criteria #{$id}";
    }

    /**
     * Build a view response for an empty/error state with consistent defaults.
     */
    private function emptyView(string $state, array $extra = []): \Illuminate\View\View
    {
        $base = array_merge([
            'emptyState'             => $state,
            'results'                => null,
            'paginator'              => null,
            'criteriaList'           => [],
            'selectedCriteriaType'   => null,
            'selectedCriteriaId'     => null,
            'selectedCriteriaLabel'  => null,
            'selectedCriteriaEditUrl' => null,
            'buyerCriteriaAddUrl'    => url('/offer-listing/buyer'),
            'tenantCriteriaAddUrl'   => url('/offer-listing/tenant/tenant'),
        ], $extra);

        if (empty($base['selectedCriteriaEditUrl'])
            && !empty($base['selectedCriteriaId'])
            && !empty($base['selectedCriteriaType'])) {
            $base['selectedCriteriaEditUrl'] = $this->buildEditUrl(
                $base['selectedCriteriaType'],
                $base['selectedCriteriaId']
            );
        }

        return view('stellar.buyer.results', $base);
    }

    /**
     * Auto-select the best criteria record for a user who has not explicitly chosen one.
     *
     * Priority rule: modern offer listing records (buyer_offer / tenant_offer) are preferred
     * over legacy criteria records (buyer / tenant) when both exist. Within each tier the
     * list is already sorted newest-first by CriteriaListingResolver, so the first matching
     * entry is always the most recent one.
     *
     * This satisfies the Phase 1 requirement: "prefer the modern Offer Listing value when
     * both the modern Offer Listing workflow and the legacy criteria workflow exist for a user."
     */
    private function findPreferredCriteria(array $criteriaList): array
    {
        $modernTypes = ['buyer_offer', 'tenant_offer'];
        foreach ($criteriaList as $item) {
            if (in_array($item['type'], $modernTypes, true)) {
                return $item;
            }
        }
        return $criteriaList[0];
    }

    /**
     * Build the edit URL for a given criteria type and ID.
     *
     * Modern offer-listing records point to the modern edit routes.
     * Legacy criteria records point to the legacy edit routes.
     */
    private function buildEditUrl(string $type, int $id): string
    {
        return match ($type) {
            'tenant'       => url('/tenant/criteria/auction/edit/' . $id),
            'buyer_offer'  => url('/offer-listing/buyer/edit/' . $id),
            'tenant_offer' => url('/offer-listing/tenant/edit/' . $id),
            default        => url('/buyer-agent/auction/edit/' . $id),
        };
    }
}

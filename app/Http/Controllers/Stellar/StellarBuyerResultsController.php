<?php

namespace App\Http\Controllers\Stellar;

use App\Http\Controllers\Controller;
use App\Models\BridgeProperty;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\TenantCriteriaLoader;
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
        private BuyerCriteriaLoader    $buyerCriteriaLoader,
        private TenantCriteriaLoader   $tenantCriteriaLoader,
        private CriteriaListingResolver $criteriaResolver,
        private BuyerMatchService       $matchService,
        private BuyerResultViewMapper   $viewMapper
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
            // Explicit criteria selected via query params.
            // Always resolve the full list so the switcher strip is available on results pages.
            $criteriaIdInt = (int) $criteriaId;
            $criteriaList  = $this->criteriaResolver->resolveAccessible($user);
            $criteriaData  = $this->loadCriteriaById($criteriaType, $criteriaIdInt, $allowedUserIds);

            if ($criteriaData === null) {
                // Unauthorized, not found, or inactive — safe empty state with no data leak.
                return $this->emptyView('no_criteria_listings', ['criteriaList' => []]);
            }

            $selectedType  = $criteriaType;
            $selectedId    = $criteriaIdInt;
        } else {
            // No explicit selection — discover accessible criteria for this user
            $criteriaList = $this->criteriaResolver->resolveAccessible($user);

            if (count($criteriaList) === 0) {
                // Agents with no accessible client criteria see the multi-type empty state.
                // Non-agent buyers with no criteria preserve the original buyer-profile state
                // ("Your buyer profile isn't complete yet") so their experience is unchanged.
                if ($user->user_type === 'agent') {
                    return $this->emptyView('no_criteria_listings', ['criteriaList' => []]);
                }
                return $this->emptyView('no_criteria', ['criteriaList' => []]);
            }

            if (count($criteriaList) === 1) {
                // Auto-select the single available profile
                $selected      = $criteriaList[0];
                $selectedType  = $selected['type'];
                $selectedId    = $selected['id'];
                $criteriaData  = $this->loadCriteriaById($selectedType, $selectedId, $allowedUserIds);

                if ($criteriaData === null) {
                    return $this->emptyView('no_criteria', [
                        'criteriaList'         => $criteriaList,
                        'selectedCriteriaType' => $selectedType,
                        'selectedCriteriaId'   => $selectedId,
                        'selectedCriteriaLabel' => $selected['label'],
                    ]);
                }
            } else {
                // Multiple profiles available — show selector
                return $this->emptyView('select_criteria', ['criteriaList' => $criteriaList]);
            }
        }

        // Resolve the label for the selected criteria (for the selector strip)
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
            'buyerCriteriaAddUrl'    => url('/buyer-agent/auction/add'),
            'tenantCriteriaAddUrl'   => url('/tenant/criteria/auction/add'),
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Dispatch criteria loading to the correct loader by type.
     *
     * @param  int[] $allowedUserIds  User IDs allowed to own this record (own + clients for agents).
     */
    private function loadCriteriaById(string $type, int $id, array $allowedUserIds): ?array
    {
        if ($type === 'tenant') {
            return $this->tenantCriteriaLoader->loadById($id, $allowedUserIds);
        }

        return $this->buyerCriteriaLoader->loadById($id, $allowedUserIds);
    }

    /**
     * Find the label for the selected criteria from the criteria list.
     * Falls back to a type-based default if the list is empty (explicit-param path).
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
     * Build a view response for an empty/error state with consistent defaults
     * for all view variables.
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
            'buyerCriteriaAddUrl'    => url('/buyer-agent/auction/add'),
            'tenantCriteriaAddUrl'   => url('/tenant/criteria/auction/add'),
        ], $extra);

        // Compute edit URL from selectedCriteriaType + selectedCriteriaId if present
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
     * Build the edit URL for a given criteria type and ID.
     */
    private function buildEditUrl(string $type, int $id): string
    {
        if ($type === 'tenant') {
            return url('/tenant/criteria/auction/edit/' . $id);
        }

        return url('/buyer-agent/auction/edit/' . $id);
    }
}

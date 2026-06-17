<?php

namespace App\Http\Controllers\Stellar;

use App\Http\Controllers\Controller;
use App\Models\BridgeProperty;
use App\Services\Stellar\BuyerCriteriaLoader;
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
        private BuyerCriteriaLoader $criteriaLoader,
        private BuyerMatchService   $matchService,
        private BuyerResultViewMapper $viewMapper
    ) {}

    public function index(Request $request)
    {
        $user   = $request->user();
        $userId = $user->id;

        // -----------------------------------------------------------------------
        // Empty state 5: import pipeline never ran (table is empty)
        // -----------------------------------------------------------------------
        if (Schema::hasTable('bridge_properties') && BridgeProperty::count() === 0) {
            return view('stellar.buyer.results', [
                'emptyState' => 'import_unavailable',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        // -----------------------------------------------------------------------
        // Empty state 2: no active residential inventory imported
        // -----------------------------------------------------------------------
        if (!BridgeProperty::where('standard_status', 'Active')
                ->where('property_type', 'Residential')
                ->exists()) {
            return view('stellar.buyer.results', [
                'emptyState' => 'no_inventory',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        // -----------------------------------------------------------------------
        // Load buyer criteria
        // -----------------------------------------------------------------------
        $criteriaData = $this->criteriaLoader->load($userId);

        // Empty state 3: buyer criteria incomplete or property_types empty
        if ($criteriaData === null) {
            return view('stellar.buyer.results', [
                'emptyState' => 'no_criteria',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        // Empty state 4: no location constraint at all
        $hasLocation = !empty($criteriaData['preferred_cities'])
            || !empty($criteriaData['preferred_zip_codes'])
            || !empty($criteriaData['preferred_counties'])
            || !empty($criteriaData['radius_searches'])
            || !empty($criteriaData['polygons']);

        if (!$hasLocation) {
            return view('stellar.buyer.results', [
                'emptyState' => 'no_location',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        // -----------------------------------------------------------------------
        // Build payload and run the matching pipeline
        // -----------------------------------------------------------------------
        try {
            $criteria = new BuyerCriteriaPayload($criteriaData);
        } catch (\InvalidArgumentException $e) {
            return view('stellar.buyer.results', [
                'emptyState' => 'no_criteria',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        $matchedCollection = $this->matchService->match($criteria, 200);

        // Empty state 1: no matches
        if ($matchedCollection->isEmpty()) {
            return view('stellar.buyer.results', [
                'emptyState' => 'no_matches',
                'results'    => null,
                'paginator'  => null,
            ]);
        }

        // -----------------------------------------------------------------------
        // Map to Blade-safe arrays and paginate in-memory
        // -----------------------------------------------------------------------
        $mapped   = $this->viewMapper->map($matchedCollection);
        $total    = count($mapped);
        $page     = max(1, (int) $request->get('page', 1));
        $offset   = ($page - 1) * self::PER_PAGE;
        $slice    = array_slice($mapped, $offset, self::PER_PAGE);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('stellar.buyer.results', [
            'emptyState' => null,
            'results'    => $slice,
            'paginator'  => $paginator,
            'total'      => $total,
        ]);
    }
}

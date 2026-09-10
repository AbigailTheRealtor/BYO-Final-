<?php

namespace App\Http\Controllers\Explore;

use App\Http\Controllers\Controller;
use App\Services\Explore\ExploreCanonicalListingResolver;
use App\Services\Explore\ExploreListingProjector;
use App\Services\Explore\ExploreListingRepository;
use App\Services\Explore\ExploreTransactionType;
use App\Services\Explore\ExploreViewport;
use App\Services\Explore\Vow\VowAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The viewport listing API — the only route by which property data leaves the
 * server for Explore.
 *
 * EVERY DECISION IS MADE HERE OR BELOW HERE
 * -----------------------------------------
 * The client sends a bounding box and a filter. It does not send an access
 * tier, an eligibility claim, or a status. `?vow=true` would be ignored because
 * there is nothing to read it: the tier is resolved server-side from
 * {@see VowAvailability}, which refuses regardless of what any request or flag
 * says.
 *
 * A REFUSED BBOX IS A 422, NOT A CLAMPED ANSWER
 * ---------------------------------------------
 * Silently shrinking an over-large viewport returns markers for a region the
 * consumer is not looking at, and an empty-looking neighbourhood reads as
 * "nothing for sale here" — a false statement about a real market. The refusal
 * is explicit and the message tells the client to zoom in.
 *
 * STALE-RESPONSE GUARD
 * --------------------
 * Panning fires overlapping requests, and they do not necessarily return in
 * order; an older response landing last repaints the map with markers for a
 * viewport that has moved on. The client stamps each request with a
 * monotonically increasing `seq`, this endpoint echoes it back verbatim, and
 * the client discards any response whose `seq` is not the newest it has issued.
 * The echo lives here rather than being inferred client-side so the guard is
 * testable server-side and cannot be lost in a front-end refactor.
 *
 * RESPONSES ARE PROJECTIONS
 * -------------------------
 * Nothing else is serialisable from this action. There is no path from a
 * BridgeProperty or a raw_json array to the response body that does not pass
 * through {@see ExploreListingProjection}, which is an explicit allow-list.
 */
class ExploreListingApiController extends Controller
{
    public function __construct(
        private readonly ExploreListingRepository $repository,
        private readonly ExploreListingProjector $projector,
        private readonly ExploreCanonicalListingResolver $canonical,
        private readonly VowAvailability $vow,
    ) {}

    /** GET /api/explore/listings */
    public function index(Request $request): JsonResponse
    {
        $tier = $this->vow->decideTier($request->user());

        try {
            $viewport = ExploreViewport::fromString($request->query('bbox'));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'seq'   => $this->sequence($request),
            ], 422);
        }

        $filterInput = $request->query('transaction_type');

        // An unrecognised filter is refused rather than silently widened to
        // "all". A typo returning the full inventory looks like the filter
        // working, which is how a rental ends up presented as a sale.
        if (is_string($filterInput) && trim($filterInput) !== '' && trim(strtolower($filterInput)) !== 'all') {
            $filter = ExploreTransactionType::tryFromFilter($filterInput);

            if ($filter === null) {
                return response()->json([
                    'error' => 'transaction_type must be "sale", "rent" or "all".',
                    'seq'   => $this->sequence($request),
                ], 422);
            }
        } else {
            $filter = null;
        }

        $limit = $request->query('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $rows = $this->repository->inViewport($viewport, $tier, $filter, $limit);

        $typeByListingKey = [];

        foreach ($rows as $row) {
            $key = (string) ($row['listing']->listing_key ?? '');

            if ($key !== '') {
                $typeByListingKey[$key] = $row['type'];
            }
        }

        $canonicalLinks = $this->canonical->resolveMany($typeByListingKey);

        $listings = [];

        foreach ($rows as $row) {
            $key = (string) ($row['listing']->listing_key ?? '');

            $listings[] = $this->projector->project(
                $row['listing'],
                $row['raw'],
                $row['type'],
                $tier,
                $canonicalLinks[$key] ?? null,
                $this->detailUrl($request, $key),
            )->toArray();
        }

        return response()->json([
            'seq'              => $this->sequence($request),
            'bbox'             => $viewport->toArray(),
            'transaction_type' => $filter?->value,
            'access_tier'      => $tier->value,
            'count'            => count($listings),
            'limit'            => $this->repository->limit($limit),
            'truncated'        => count($listings) >= $this->repository->limit($limit),
            'attribution'      => ExploreListingProjector::ATTRIBUTION,
            'listings'         => $listings,
        ]);
    }

    /**
     * GET /api/explore/listings/{listingKey} — the quick panel.
     *
     * 404 for an ineligible listing, identical to the 404 for one that does not
     * exist. A distinguishable refusal would confirm to a prober that a
     * withheld listing is real.
     */
    public function show(Request $request, string $listingKey): JsonResponse
    {
        $tier = $this->vow->decideTier($request->user());
        $row  = $this->repository->findEligible($listingKey, $tier);

        if ($row === null) {
            return response()->json(['error' => 'Listing not found.'], 404);
        }

        $key = (string) ($row['listing']->listing_key ?? '');

        $canonical = $this->canonical->resolveMany([$key => $row['type']])[$key] ?? null;

        return response()->json([
            'access_tier' => $tier->value,
            'attribution' => ExploreListingProjector::ATTRIBUTION,
            'listing'     => $this->projector->project(
                $row['listing'],
                $row['raw'],
                $row['type'],
                $tier,
                $canonical,
                $this->detailUrl($request, $key),
            )->toArray(),
        ]);
    }

    /**
     * The full MLS detail page for a property with no BidYourOffer listing.
     *
     * Offered only to a signed-in visitor, because `/stellar/property/{key}`
     * sits inside the auth middleware group. Handing an anonymous visitor a
     * link that bounces them to a login screen is a dead button wearing a live
     * one's clothes, so they get no control at all.
     *
     * Explore never materialises a listing. This is a link to an existing
     * read-only page that creates nothing.
     */
    private function detailUrl(Request $request, string $listingKey): ?string
    {
        if ($listingKey === '' || $request->user() === null) {
            return null;
        }

        return route('stellar.property.show', ['listingKey' => $listingKey]);
    }

    /** The client's request stamp, echoed verbatim and never interpreted. */
    private function sequence(Request $request): ?int
    {
        $seq = $request->query('seq');

        return is_numeric($seq) ? (int) $seq : null;
    }
}

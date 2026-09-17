<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\Explore\ExploreAccessTier;
use App\Services\Explore\ExploreCanonicalListingResolver;
use App\Services\Explore\ExploreListingProjector;
use App\Services\Explore\ExploreListingRepository;
use App\Services\Explore\ExploreViewport;
use App\Services\Explore\Vow\VowAvailability;
use App\Support\VirtualDrive\VirtualDriveListingActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Listing data for the Virtual Drive provider proof (Apple Look Around vs
 * Google Street View). Development-only — see VirtualDriveProofGate.
 *
 * THE IMAGERY PROVIDER IS NOT THE LISTING SOURCE
 * ----------------------------------------------
 * Both provider pages ask THIS endpoint what is for sale or for rent; neither
 * provider is ever asked. Coordinates are the MLS's own — bridge_properties
 * latitude/longitude, normalised from the feed's Latitude/Longitude — and
 * nothing is geocoded again, by Apple, Google or anyone else.
 *
 * STORED DATA ONLY — NO PROVIDER REQUEST, EVER
 * --------------------------------------------
 * This reuses Explore's read path — repository, eligibility policy, projection
 * allow-list, canonical resolver — and deliberately NOT ExploreInventoryService.
 * A camera move in either provider can reach this endpoint, and a camera move
 * must never become a Bridge request. Nothing here holds a provider client, and
 * the tests fail on any statement that is not a read.
 *
 * NOTHING IS INVENTED
 * -------------------
 * A configured test key that is missing or ineligible is reported in
 * `unavailable_keys`, never replaced with a placeholder. Every listing field is
 * the Explore projection's, null wherever the feed gives no permitted answer.
 */
class VirtualDriveListingController extends Controller
{
    private const EARTH_RADIUS_METERS = 6371008.8;

    public function __construct(
        private readonly ExploreListingRepository $repository,
        private readonly ExploreListingProjector $projector,
        private readonly ExploreCanonicalListingResolver $canonical,
        private readonly VowAvailability $vow,
    ) {}

    /** GET /dev/virtual-drive/api/listings?set=test  |  ?lat=…&lng=…[&radius=…] */
    public function index(Request $request): JsonResponse
    {
        $tier = $this->vow->decideTier($request->user());

        if ($request->query('set') === 'test') {
            return $this->testSet($request, $tier);
        }

        return $this->nearby($request, $tier);
    }

    private function testSet(Request $request, ExploreAccessTier $tier): JsonResponse
    {
        $rows        = collect();
        $unavailable = [];

        foreach ((array) config('virtual_drive.test_listing_keys', []) as $key) {
            $key = is_string($key) ? trim($key) : '';

            if ($key === '') {
                continue;
            }

            // Same answer for "no such row" and "row not eligible": neither is
            // published, and the proof says so rather than filling the gap.
            $row = $this->repository->findEligible($key, $tier);

            if ($row === null) {
                $unavailable[] = $key;

                continue;
            }

            $rows->push($row);
        }

        return $this->respond($request, $tier, $rows, 'test_set', null, null, $unavailable);
    }

    private function nearby(Request $request, ExploreAccessTier $tier): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['error' => 'Send lat and lng, or set=test.'], 422);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 85.0 || abs($lng) > 180.0) {
            return response()->json(['error' => 'lat/lng are outside the coordinate range.'], 422);
        }

        $radius = $this->radius($request->query('radius'));

        $dLat = $radius / 111320.0;
        $dLng = $radius / (111320.0 * max(cos(deg2rad($lat)), 0.01));

        try {
            $viewport = ExploreViewport::fromString(implode(',', [$lat - $dLat, $lng - $dLng, $lat + $dLat, $lng + $dLng]));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Discovery is deliberately NOT passed: with no outcome, the repository
        // reads stored rows as they are and asks nobody whether they are current.
        // The box's corners reach past the radius, so the circle is applied here.
        $rows = $this->repository->inViewport($viewport, $tier)
            ->filter(fn (array $row) => $this->meters(
                $lat,
                $lng,
                (float) $row['listing']->latitude,
                (float) $row['listing']->longitude
            ) <= $radius)
            ->values();

        return $this->respond($request, $tier, $rows, 'nearby', ['latitude' => $lat, 'longitude' => $lng], $radius, []);
    }

    /**
     * @param  Collection<int,array{listing:\App\Models\BridgeProperty,raw:array<string,mixed>,type:\App\Services\Explore\ExploreTransactionType}>  $rows
     * @param  array{latitude:float,longitude:float}|null  $center
     * @param  list<string>  $unavailable
     */
    private function respond(
        Request $request,
        ExploreAccessTier $tier,
        Collection $rows,
        string $mode,
        ?array $center,
        ?int $radius,
        array $unavailable,
    ): JsonResponse {
        $typeByListingKey = [];

        foreach ($rows as $row) {
            $key = (string) ($row['listing']->listing_key ?? '');

            if ($key !== '') {
                $typeByListingKey[$key] = $row['type'];
            }
        }

        $canonicalLinks = $this->canonical->resolveMany($typeByListingKey);

        $listings = $rows->map(function (array $row) use ($request, $tier, $canonicalLinks, $center) {
            $key = (string) ($row['listing']->listing_key ?? '');

            $projected = $this->projector->project(
                $row['listing'],
                $row['raw'],
                $row['type'],
                $tier,
                $canonicalLinks[$key] ?? null,
                $this->detailUrl($request, $key),
            )->toArray();

            return $projected + [
                // What the sign says. Decided by ExploreTransactionType — an exact
                // PropertyType match — so a lease can never be signed FOR SALE.
                'sign_label' => mb_strtoupper($row['type']->consumerLabel()),
                'distance_m' => $center === null ? null : (int) round($this->meters(
                    $center['latitude'],
                    $center['longitude'],
                    $projected['latitude'],
                    $projected['longitude']
                )),
                'actions'    => VirtualDriveListingActions::for($projected),
            ];
        });

        if ($center !== null) {
            $listings = $listings->sortBy('distance_m');
        }

        $max = (int) config('virtual_drive.nearby.max_results', 12);

        $listings = $listings->take($max > 0 ? $max : 12)->values();

        return response()->json([
            'mode'              => $mode,
            'center'            => $center,
            'radius_m'          => $radius,
            'source'            => 'bridge_properties',
            // Structural, not measured: nothing on this path can send one. The
            // tests pin it by failing on any provider call or write.
            'provider_requests' => 0,
            'count'             => $listings->count(),
            'unavailable_keys'  => $unavailable,
            'attribution'       => ExploreListingProjector::ATTRIBUTION,
            'listings'          => $listings->all(),
        ]);
    }

    /**
     * The signed-in MLS detail page, for a property with no BidYourOffer listing.
     *
     * Explore's rule, for Explore's reason: /stellar/property/{key} sits inside
     * the auth group, so a guest would be handed a link to a login screen.
     */
    private function detailUrl(Request $request, string $listingKey): ?string
    {
        if ($listingKey === '' || $request->user() === null) {
            return null;
        }

        return route('stellar.property.show', ['listingKey' => $listingKey]);
    }

    private function radius(mixed $requested): int
    {
        $default = (int) config('virtual_drive.nearby.radius_meters', 400);
        $ceiling = (int) config('virtual_drive.nearby.max_radius_meters', 800);

        $ceiling = $ceiling > 0 ? $ceiling : 800;
        $value   = is_numeric($requested) ? (int) $requested : $default;

        return max(25, min($value > 0 ? $value : $default, $ceiling));
    }

    private function meters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($h)));
    }
}

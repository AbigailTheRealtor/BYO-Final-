<?php

namespace Tests\Feature\HireAgent;

use App\Jobs\ComputeLocationDna;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\PropertyCoordinatePersistenceService;
use App\Services\LocationDna\LocationDnaGeocodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * G6: Hire Agent coordinate resolution, against the real components and the real
 * models.
 *
 * WHY THIS DRIVES THE ACTUAL LIVEWIRE COMPONENTS
 * ----------------------------------------------
 * G5 asserted its behaviour against a hand-written stub exposing saveMeta()/
 * info(). That was reasonable for a service under test, but it cannot see the
 * one thing most likely to break this integration in practice: these models
 * write meta through `$this->meta()` (a query builder) and read it back through
 * `$this->meta` (a *cached* relation). A component that has already touched the
 * relation would hand the resolver a stale address — the resolver would answer
 * `insufficient_address`, write nothing, and every assertion made against a stub
 * would still pass. So the publish and draft paths below run the genuine
 * component and then read the genuine row.
 *
 * The ladder's own behaviour (precedence, change detection, failure posture) is
 * exercised through the real service against the real Eloquent models rather
 * than re-proved from scratch — it is the same service G5 covers, and what is
 * new here is that it is driven by these two roles' rows.
 *
 * ADDRESS FIELDS USED
 * -------------------
 * `address` + `property_state` + `property_zip`. Deliberately not
 * `property_city`: `updatedPropertyCity()` calls `getPlaceSuggestions()`, i.e.
 * Google Places autocomplete, and a test that set it would be making a network
 * call to prove something about a geocoder. A ZIP alone satisfies
 * `hasMinimumForLookup()`, which is exactly why that rule exists.
 */
class HireAgentCoordinateIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const CENSUS = 'geocoding.geo.census.gov/*';

    private const SELLER_COMPONENT   = \App\Http\Livewire\HireSellerAgent\SellerAgentAuction::class;
    private const LANDLORD_COMPONENT = \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction::class;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // The shipped posture. Individual tests opt in where that is the point.
        config()->set('census_geocoder.enabled', false);
        config()->set('google_places.enabled', false);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function owner(string $userType): User
    {
        return User::factory()->create(['user_type' => $userType]);
    }

    private function censusMatch(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'FL', 'zip' => '33602'],
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ]]]];
    }

    /** The Seller wizard's minimum accepted full-submit field set, plus an address. */
    private function sellerComponent()
    {
        return Livewire::test(self::SELLER_COMPONENT)
            ->set('listing_title', 'G6 Seller Hire Agent Listing')
            ->set('property_type', 'Residential')
            ->set('address', '315 E Madison St')
            ->set('property_state', 'FL')
            ->set('property_zip', '33602')
            ->call('selectStateSuggestion', 'Florida')
            ->set('first_name', 'Sam')
            ->set('last_name', 'Seller')
            ->set('phone_number', '8135550100')
            ->set('email', 'sam.seller@example.test')
            ->set('current_status', 'Ready to Sell')
            ->set('compatibility_preferences.seller_specific.communication_style', 'Email')
            ->set('compatibility_preferences.seller_specific.negotiation_style', 'Collaborative')
            ->set('compatibility_preferences.seller_specific.primary_transaction_goal', 'Highest Price')
            ->set('compatibility_preferences.seller_specific.representation_priorities', ['Market Knowledge'])
            ->set('compatibility_preferences.seller_specific.preferred_agent_working_style', 'Proactive');
    }

    /** The Landlord wizard's minimum accepted full-submit field set, plus an address. */
    private function landlordComponent()
    {
        return Livewire::test(self::LANDLORD_COMPONENT)
            ->set('listing_title', 'G6 Landlord Hire Agent Listing')
            ->set('address', '315 E Madison St')
            ->set('property_state', 'FL')
            ->set('property_zip', '33602')
            ->set('first_name', 'Lee')
            ->set('last_name', 'Landlord')
            ->set('phone_number', '8135550200')
            ->set('email', 'lee.landlord@example.test')
            ->set('desired_lease_length', ['12 Months'])
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Phone')
            ->set('compatibility_preferences.landlord_specific.negotiation_style', 'Firm')
            ->set('compatibility_preferences.landlord_specific.primary_leasing_goal', 'Maximum Rent')
            ->set('compatibility_preferences.landlord_specific.representation_priorities', ['Tenant Screening'])
            ->set('compatibility_preferences.landlord_specific.preferred_agent_working_style', 'Hands On');
    }

    /** A saved row of the given role, carrying the address meta the resolver reads. */
    private function row(string $role, array $extraMeta = []): object
    {
        $model = $role === 'seller'
            ? new SellerAgentAuction()
            : new LandlordAgentAuction();

        $model->user_id  = $this->owner($role)->id;
        $model->title    = 'G6 Fixture';
        $model->is_draft = 0;

        if ($role === 'seller') {
            $model->address = 'G6 Fixture';
        }

        $model->save();

        foreach (array_merge([
            'address'        => '315 E Madison St',
            'unit_address'   => '',
            'property_city'  => 'Tampa',
            'property_state' => 'FL',
            'property_zip'   => '33602',
        ], $extraMeta) as $key => $value) {
            $model->saveMeta($key, $value);
        }

        // Read back through a fresh instance: the meta relation caches, and the
        // service reads through info().
        return $role === 'seller'
            ? SellerAgentAuction::find($model->id)
            : LandlordAgentAuction::find($model->id);
    }

    private function service(): PropertyCoordinatePersistenceService
    {
        return new PropertyCoordinatePersistenceService();
    }

    /** @return array<string, array{0: string, 1: string}> role, listing_type */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller', 'seller_agent'],
            'landlord' => ['landlord', 'landlord_agent'],
        ];
    }

    // ── 1. the real publish path resolves AND dispatches ────────────────────

    public function test_seller_publish_resolves_the_coordinate_and_dispatches(): void
    {
        Bus::fake();
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->actingAs($this->owner('seller'));

        $this->sellerComponent()->call('store');

        $auction = SellerAgentAuction::latest('id')->firstOrFail();

        // The coordinate actually landed on the row — this is what a stub cannot
        // prove, because it cannot go stale.
        $this->assertSame('27.948434712759', $auction->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('us_census', $auction->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('interpolated', $auction->info(PropertyCoordinateMeta::PRECISION));

        Bus::assertDispatched(
            ComputeLocationDna::class,
            fn ($job) => $job->listingType === 'seller_agent' && (int) $job->listingId === (int) $auction->id
        );
    }

    public function test_landlord_publish_resolves_the_coordinate_and_dispatches(): void
    {
        Bus::fake();
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->actingAs($this->owner('landlord'));

        $this->landlordComponent()->call('store');

        $auction = LandlordAgentAuction::latest('id')->firstOrFail();

        $this->assertSame('27.948434712759', $auction->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('us_census', $auction->info(PropertyCoordinateMeta::PROVIDER));

        Bus::assertDispatched(
            ComputeLocationDna::class,
            fn ($job) => $job->listingType === 'landlord_agent' && (int) $job->listingId === (int) $auction->id
        );
    }

    // ── 2. drafts resolve but never dispatch ────────────────────────────────

    public function test_seller_draft_resolves_but_does_not_dispatch(): void
    {
        Bus::fake();
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->actingAs($this->owner('seller'));

        $this->sellerComponent()->call('saveDraft');

        $auction = SellerAgentAuction::latest('id')->firstOrFail();

        $this->assertSame(
            '27.948434712759',
            $auction->info(PropertyCoordinateMeta::LAT),
            'A draft still resolves, so the answer is ready before publish'
        );

        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    public function test_landlord_draft_resolves_but_does_not_dispatch(): void
    {
        Bus::fake();
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->actingAs($this->owner('landlord'));

        $this->landlordComponent()->call('saveDraft');

        $auction = LandlordAgentAuction::latest('id')->firstOrFail();

        $this->assertSame('27.948434712759', $auction->info(PropertyCoordinateMeta::LAT));

        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    // ── 3. Census disabled — the shipped posture ────────────────────────────

    /** @dataProvider roles */
    public function test_census_disabled_is_unresolved_and_silent(string $role, string $listingType): void
    {
        Http::fake();

        $listing = $this->row($role);

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);
        $this->assertFalse($listing->info(PropertyCoordinateMeta::LAT));

        Http::assertNothingSent();
    }

    public function test_the_publish_path_still_works_with_census_disabled(): void
    {
        // The listing must save whether or not a coordinate can be found.
        Bus::fake();
        Http::fake();

        $this->actingAs($this->owner('seller'));

        $this->sellerComponent()->call('store');

        $auction = SellerAgentAuction::latest('id')->firstOrFail();

        $this->assertSame(0, (int) $auction->is_draft, 'The listing published normally');
        $this->assertFalse($auction->info(PropertyCoordinateMeta::LAT), 'and no coordinate was invented');

        Http::assertNothingSent();
        Bus::assertDispatched(ComputeLocationDna::class);
    }

    // ── 4. Census enabled — provenance recorded honestly ────────────────────

    /** @dataProvider roles */
    public function test_census_enabled_persists_coordinate_and_provenance(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame('us_census', $outcome['provider']);
        $this->assertSame('interpolated', $outcome['precision']);

        // These models write meta through `$this->meta()` (a query builder) and
        // read it through `$this->meta` (a cached relation), so a held instance
        // does not see its own writes. Reload before reading back — which is
        // what a real save boundary gets for free, each request resolving a
        // freshly-found row.
        $listing->load('meta');

        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.458094358643', $listing->info(PropertyCoordinateMeta::LNG));
        $this->assertSame('interpolated', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertSame('us_census', $listing->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('geocoder', $listing->info(PropertyCoordinateMeta::SOURCE));
        // The address we ASKED about, not the one Census answered with.
        $this->assertSame(
            '315 e madison st tampa fl 33602',
            $listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS)
        );

        Http::assertSentCount(1);
    }

    // ── 5. change detection ─────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_repeated_save_sends_nothing_further(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);

        $this->service()->resolveAndPersist($listing, $listingType);

        for ($i = 0; $i < 4; $i++) {
            // Reload each time: every real save is its own request against a
            // freshly-read row, and the change-detection key lives in meta.
            $listing->load('meta');

            $outcome = $this->service()->resolveAndPersist($listing, $listingType);
            $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $outcome['outcome']);
        }

        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_normalization_only_edit_reuses_the_coordinate(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);
        $this->service()->resolveAndPersist($listing, $listingType);

        $listing->saveMeta('address', '315 East Madison Street');
        $listing->load('meta');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $outcome['outcome']);
        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_unit_only_edit_reuses_the_building_coordinate(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role, ['unit_address' => 'Unit 4A']);
        $this->service()->resolveAndPersist($listing, $listingType);

        $listing->saveMeta('unit_address', 'Unit 4B');
        $listing->load('meta');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(
            PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED,
            $outcome['outcome'],
            'Two units share one building coordinate and one lookup'
        );
        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_meaningful_address_change_re_resolves(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);
        $this->service()->resolveAndPersist($listing, $listingType);

        $listing->saveMeta('address', '987 Elm St');
        $listing->saveMeta('property_zip', '33607');
        $listing->load('meta');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertNotSame(
            PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED,
            $outcome['outcome'],
            'A different property must not inherit the old coordinate'
        );
        $this->assertSame(2, Http::recorded()->count());
    }

    // ── 6. failure posture ──────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_provider_failure_never_destroys_an_existing_coordinate(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response('', 502)]);

        $listing = $this->row($role, [
            PropertyCoordinateMeta::LAT                => '27.9506',
            PropertyCoordinateMeta::LNG                => '-82.4572',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => 'a different old address',
        ]);

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);
        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.4572', $listing->info(PropertyCoordinateMeta::LNG));
    }

    // ── 7. the Existing rung, and no precision inflation ────────────────────

    /** @dataProvider roles */
    public function test_an_existing_coordinate_is_reused_without_calling_census(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true); // even enabled, it must not be reached
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);

        PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listing->id,
            'source_address' => '315 E Madison St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'saved_meta',
            // "Trusted" is now a property of the recorded ladder provenance, not
            // of the `geocode_source` name. Without these the Existing rung
            // declines, and the reuse path would be tested on a coordinate that
            // no longer qualifies for reuse.
            'geocode_provider'  => 'address_point',
            'geocode_precision' => 'rooftop',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);

        $listing->load('meta');

        $this->assertSame('existing', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));

        Http::assertNothingSent();
    }

    /** @dataProvider roles */
    public function test_a_persisted_census_coordinate_is_not_reused_by_the_existing_rung(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);
        $this->service()->resolveAndPersist($listing, $listingType);
        $listing->load('meta');

        // Hand the pipeline exactly what the runner would read from this row.
        app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, (int) $listing->id, [
            'address'    => '315 E Madison St',
            'city'       => 'Tampa',
            'state'      => 'FL',
            'zip'        => '33602',
            'pre_lat'    => $listing->info(PropertyCoordinateMeta::LAT),
            'pre_lng'    => $listing->info(PropertyCoordinateMeta::LNG),
            'provenance' => PropertyCoordinateMeta::readProvenance(
                static fn (string $key) => $listing->info($key)
            ),
        ]);

        // Now read it back through the Existing rung, which sources from the
        // property_location_dna row just written.
        //
        // The coordinate meta has to go first: with it present the service
        // short-circuits as UNCHANGED and the ladder never runs, so the rung
        // under test would not be consulted at all. Dropping it reproduces the
        // case that matters — a row whose only surviving coordinate is the one
        // in property_location_dna, carrying honest Census provenance.
        $listing->deleteMeta(PropertyCoordinateMeta::LAT);
        $listing->deleteMeta(PropertyCoordinateMeta::LNG);
        $listing->deleteMeta(PropertyCoordinateMeta::NORMALIZED_ADDRESS);
        $listing->load('meta');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(
            PropertyCoordinatePersistenceService::OUTCOME_RESOLVED,
            $outcome['outcome']
        );

        $listing->load('meta');
        // The Existing rung must DECLINE a Census-derived point rather than hand
        // it back and stop the ladder — that is what keeps a later authoritative
        // address-point match reachable once a corpus exists.
        $this->assertSame(
            'geocoder',
            $listing->info(PropertyCoordinateMeta::SOURCE),
            'The Existing rung must decline a Census-derived point'
        );

        $this->assertSame(
            'interpolated',
            $outcome['precision'],
            'And it is still never inflated on the way back out'
        );
    }

    // ── 8. the handoff into property_location_dna ───────────────────────────

    /** @dataProvider roles */
    public function test_provenance_survives_into_property_location_dna(string $role, string $listingType): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->row($role);
        $this->service()->resolveAndPersist($listing, $listingType);
        $listing->load('meta');

        $provenance = PropertyCoordinateMeta::readProvenance(
            static fn (string $key) => $listing->info($key)
        );

        $this->assertNotNull($provenance, 'The ladder must record provable provenance');

        $written = app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, (int) $listing->id, [
            'address'    => '315 E Madison St',
            'city'       => 'Tampa',
            'state'      => 'FL',
            'zip'        => '33602',
            'pre_lat'    => $listing->info(PropertyCoordinateMeta::LAT),
            'pre_lng'    => $listing->info(PropertyCoordinateMeta::LNG),
            'provenance' => $provenance,
        ]);

        $this->assertTrue($written['success']);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listing->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(27.948434712759, (float) $row->geocoded_lat, 0.0001);
        $this->assertSame('geocoded', $row->geocode_status);
        $this->assertSame('interpolated', $row->geocode_precision);
        $this->assertSame('us_census', $row->geocode_provider);
        $this->assertSame('315 e madison st tampa fl 33602', $row->normalized_address);
        // Stays 'saved_meta' for ExistingCoordinatesAdapter's allow-list.
        $this->assertSame('saved_meta', $row->geocode_source);
    }

    // ── 9. PR #61 ownership is still enforced ───────────────────────────────

    public function test_a_non_owner_still_cannot_publish_over_another_users_seller_listing(): void
    {
        $victim = $this->row('seller');

        $this->actingAs($this->owner('seller')); // a different user

        $this->withoutExceptionHandling();

        try {
            $this->sellerComponent()->set('listingId', $victim->id)->call('store');
            $this->fail('Expected a 403 — G6 must not have weakened PR #61');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(
            $victim->user_id,
            SellerAgentAuction::find($victim->id)->user_id,
            'Ownership must not transfer'
        );
    }

    public function test_a_non_owner_still_cannot_publish_over_another_users_landlord_listing(): void
    {
        $victim = $this->row('landlord');

        $this->actingAs($this->owner('landlord'));

        $this->withoutExceptionHandling();

        try {
            $this->landlordComponent()->set('listingId', $victim->id)->call('store');
            $this->fail('Expected a 403 — G6 must not have weakened PR #61');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(
            $victim->user_id,
            LandlordAgentAuction::find($victim->id)->user_id,
            'Ownership must not transfer'
        );
    }
}

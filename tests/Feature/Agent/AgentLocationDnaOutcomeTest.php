<?php

namespace Tests\Feature\Agent;

use App\Models\PropertyLocationDna;
use App\Models\User;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * AgentLocationDnaOutcomeTest
 *
 * Covers the response AgentLocationDnaController returns when the queue driver is
 * `sync` — the committed default — and ComputeLocationDna::dispatch() therefore runs
 * the pipeline inline, so its outcome is already final when the response is rendered.
 *
 * These tests deliberately do NOT Bus::fake(). The whole point is to exercise the real
 * path the sibling AgentLocationDnaPanelTest fakes away:
 *
 *   controller → dispatch() → SyncQueue → ComputeLocationDna::handle() → runner
 *
 * Only LocationDnaPipelineRunner is mocked, standing in for the network-bound pipeline.
 * Because the runner is also what *persists* PropertyLocationDna, each mock reproduces
 * the persistence side effects the real services would have left behind for that
 * outcome — that persisted row is the only channel through which the controller can
 * learn what happened (SyncQueue::push() discards handle()'s return value).
 *
 * Outcomes covered: success, failed, skipped, partial, and the stale-generated_at
 * refresh regression.
 */
class AgentLocationDnaOutcomeTest extends TestCase
{
    use DatabaseTransactions;

    private const LIFESTYLE_JSON = [
        'version'              => 'LDNA_LIFESTYLE_V1',
        'coastal_score'        => 85,
        'walkability_score'    => 70,
        'convenience_score'    => 60,
        'commuter_score'       => 50,
        'family_score'         => 45,
        'lifestyle_categories' => ['Beach Lovers'],
        'location_narrative'   => 'A vibrant coastal community.',
    ];

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSellerListing(int $userId): int
    {
        $id = DB::table('seller_agent_auctions')->insertGetId([
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['address' => '123 Main St', 'property_city' => 'Tampa', 'property_state' => 'FL'] as $key => $value) {
            DB::table('seller_agent_auction_metas')->insert([
                'seller_agent_auction_id' => $id,
                'meta_key'                => $key,
                'meta_value'              => $value,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        return $id;
    }

    /**
     * Bind a LocationDnaPipelineRunner whose run() reproduces the persistence side
     * effects of the given outcome and returns the matching status array.
     */
    private function fakePipeline(string $status, ?callable $persist = null): void
    {
        $runner = Mockery::mock(LocationDnaPipelineRunner::class);

        $runner->shouldReceive('run')
            ->once()
            ->andReturnUsing(function (string $listingType, int $listingId) use ($status, $persist) {
                if ($persist !== null) {
                    $persist($listingType, $listingId);
                }

                return ['status' => $status, 'steps' => []];
            });

        $this->instance(LocationDnaPipelineRunner::class, $runner);
    }

    private function upsertDna(string $listingType, int $listingId, array $attributes): void
    {
        PropertyLocationDna::updateOrCreate(
            ['listing_type' => $listingType, 'listing_id' => $listingId],
            $attributes,
        );
    }

    private function postGenerate(User $agent, int $listingId)
    {
        return $this->actingAs($agent)
            ->from(route('dashboard'))
            ->post(route('agent.location-dna.generate', ['seller_agent', $listingId]));
    }

    // =========================================================================
    // §0 — Guard: these tests are only meaningful under an inline queue driver
    // =========================================================================

    public function test_test_suite_runs_under_an_inline_queue_driver(): void
    {
        $this->assertInstanceOf(
            SyncQueue::class,
            app(QueueFactory::class)->connection(),
            'These tests assert inline-execution semantics; they are vacuous under an async driver.',
        );
    }

    // =========================================================================
    // §1 — success: the pipeline completed, so say so
    // =========================================================================

    public function test_successful_inline_run_reports_generated(): void
    {
        $agent     = User::factory()->asAgent()->create();
        $listingId = $this->makeSellerListing($agent->id);

        $this->fakePipeline('success', function ($type, $id) {
            $this->upsertDna($type, $id, [
                'geocode_status' => 'geocoded',
                'geocoded_lat'   => 27.9506,
                'geocoded_lng'   => -82.4572,
                'lifestyle_json' => self::LIFESTYLE_JSON,
                'generated_at'   => now(),
            ]);
        });

        $response = $this->postGenerate($agent, $listingId);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('dna_success', 'Location DNA generated.');
    }

    // =========================================================================
    // §2 — failed: geocode failed, so surface an error, not a green banner
    //
    // This is the case that produced the launch bug: the panel renders a red
    // "Failed" badge from geocode_status while the flash claimed success.
    // =========================================================================

    public function test_failed_inline_run_reports_error_and_not_success(): void
    {
        $agent     = User::factory()->asAgent()->create();
        $listingId = $this->makeSellerListing($agent->id);

        $this->fakePipeline('failed', function ($type, $id) {
            $this->upsertDna($type, $id, [
                'geocode_status' => 'failed',
                'geocode_error'  => 'Geocoding API returned no results. Status: ZERO_RESULTS',
            ]);
        });

        $response = $this->postGenerate($agent, $listingId);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('dna');
        $response->assertSessionMissing('dna_success');
    }

    // =========================================================================
    // §3 — skipped: the geocode step returns before creating any record, so the
    // row is absent. Absence must not be read as "queued and pending".
    // =========================================================================

    public function test_skipped_inline_run_reports_error_and_not_success(): void
    {
        $agent     = User::factory()->asAgent()->create();
        $listingId = $this->makeSellerListing($agent->id);

        // No persistence callback: the real skipped branch writes nothing.
        $this->fakePipeline('skipped');

        $response = $this->postGenerate($agent, $listingId);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('dna');
        $response->assertSessionMissing('dna_success');

        $this->assertDatabaseMissing('property_location_dna', [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listingId,
        ]);
    }

    // =========================================================================
    // §4 — partial: geocode succeeded, a later step did not. Nothing is still
    // running under sync, so "Scores Pending" would be a lie.
    // =========================================================================

    public function test_partial_inline_run_reports_error_and_not_success(): void
    {
        $agent     = User::factory()->asAgent()->create();
        $listingId = $this->makeSellerListing($agent->id);

        $this->fakePipeline('partial', function ($type, $id) {
            // POI step failed: geocoded, but summary never ran, so generated_at
            // and lifestyle_json are never written.
            $this->upsertDna($type, $id, [
                'geocode_status' => 'geocoded',
                'geocoded_lat'   => 27.9506,
                'geocoded_lng'   => -82.4572,
            ]);
        });

        $response = $this->postGenerate($agent, $listingId);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('dna');
        $response->assertSessionMissing('dna_success');
    }

    // =========================================================================
    // §5 — REGRESSION: stale generated_at on a failed refresh
    //
    // The listing already has a successful run from two days ago. The refresh
    // re-geocodes fine but fails at the POI step, leaving the OLD generated_at
    // and lifestyle_json untouched. A controller that merely null-checked
    // generated_at would read that stale success as a fresh one and flash green.
    // The pre-dispatch snapshot comparison is what prevents this.
    // =========================================================================

    public function test_failed_refresh_does_not_report_stale_generated_at_as_success(): void
    {
        $agent     = User::factory()->asAgent()->create();
        $listingId = $this->makeSellerListing($agent->id);

        $staleTimestamp = now()->subDays(2);

        $this->upsertDna('seller_agent', $listingId, [
            'geocode_status' => 'geocoded',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'lifestyle_json' => self::LIFESTYLE_JSON,
            'generated_at'   => $staleTimestamp,
        ]);

        $this->fakePipeline('partial', function ($type, $id) {
            // Re-geocode succeeds; POI step fails. generated_at / lifestyle_json
            // are deliberately NOT touched — exactly what the real services do.
            $this->upsertDna($type, $id, ['geocode_status' => 'geocoded']);
        });

        $response = $this->postGenerate($agent, $listingId);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasErrors('dna');
        $response->assertSessionMissing('dna_success');

        // The stale row is still intact — proving the assertion above is about the
        // controller's reading of it, not about the row having been cleared.
        $record = PropertyLocationDna::where('listing_type', 'seller_agent')
            ->where('listing_id', $listingId)
            ->first();

        $this->assertNotNull($record->generated_at);
        $this->assertSame(
            $staleTimestamp->toDateTimeString(),
            $record->generated_at->toDateTimeString(),
        );
    }
}

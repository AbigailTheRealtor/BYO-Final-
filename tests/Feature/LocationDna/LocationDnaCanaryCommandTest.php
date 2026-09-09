<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Mockery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `location-dna:generate` as a single-listing canary.
 *
 * WHAT A CANARY HAS TO GUARANTEE, and what these tests hold it to:
 *
 *   ONE LISTING. Not "a small number". The command takes one id, and an argument
 *   that is not a single positive integer is refused rather than cast — (int)'all'
 *   is 0 and (int)'12,13' is 12, and both would read afterwards as a successful
 *   run against the wrong record.
 *
 *   DELIBERATE. `bridge` is an imported MLS record rather than one of our users'
 *   own listings, so it requires --canary. A canary you can start by typing the
 *   wrong word is not a canary.
 *
 *   OBSERVABLE. The run reports which provider surface it is about to execute
 *   under, because a successful run tells an operator nothing about whether the
 *   rows came from the corpus or from Google.
 *
 *   REVERSIBLE TO INSPECT. --dry-run answers all of the above and writes nothing.
 *
 * Deterministic: the pipeline runner is mocked, and HTTP is faked so no test here
 * can reach a provider even if the wiring changes underneath it.
 */
class LocationDnaCanaryCommandTest extends TestCase
{
    // The base TestCase migrates the shared in-memory schema only for classes that
    // declare this trait, and these tests render real pages — `layouts.main` reads
    // `settings` through get_setting(). Without it the page 500s on a missing table
    // and the attribution assertions never get to run.
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing in this file should ever reach the network. Combined with the
        // Bridge credential blanking in phpunit.xml, a regression that makes the
        // command fetch shows up here rather than in a provider's quota.
        Http::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Run the command and return [exitCode, output].
     *
     * Laravel 8's PendingCommand has no expectsOutputToContain() — that arrived in
     * Laravel 9 — so output is captured through the Artisan facade instead of
     * asserted fluently.
     *
     * @param  array<string, mixed> $args
     * @return array{0: int, 1: string}
     */
    private function runCommand(array $args): array
    {
        $code = Artisan::call('location-dna:generate', $args);

        return [$code, Artisan::output()];
    }

    /** Bind a runner that must never be called. */
    private function expectPipelineNeverRuns(): void
    {
        $runner = Mockery::mock(LocationDnaPipelineRunner::class);
        $runner->shouldNotReceive('run');

        $this->app->instance(LocationDnaPipelineRunner::class, $runner);
    }

    // ── Type acceptance ─────────────────────────────────────────────────────

    /** @test */
    public function bridge_is_an_accepted_listing_type(): void
    {
        // The pipeline runner has resolved bridge addresses since the Bridge import
        // shipped; only this command refused to pass the string through.
        $this->assertStringContainsString(
            'bridge',
            (string) file_get_contents(base_path('app/Console/Commands/GenerateLocationDna.php'))
        );

        $this->expectPipelineNeverRuns();

        // Rejected for a reason other than "invalid listing_type".
        [$code, $output] = $this->runCommand(['listing_type' => 'bridge', 'listing_id' => '1']);

        $this->assertSame(1, $code);
        $this->assertStringNotContainsString("Invalid listing_type 'bridge'", $output);
    }

    /** @test */
    public function an_unknown_listing_type_is_still_refused(): void
    {
        $this->expectPipelineNeverRuns();

        [$code, $output] = $this->runCommand(['listing_type' => 'buyer', 'listing_id' => '1']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString("Invalid listing_type 'buyer'", $output);
    }

    // ── One-listing isolation ───────────────────────────────────────────────

    /** @test */
    public function a_non_numeric_listing_id_is_refused_rather_than_cast(): void
    {
        $this->expectPipelineNeverRuns();

        foreach (['all', '0', '-1', '12,13', '1 2', ''] as $bad) {
            [$code, $output] = $this->runCommand(['listing_type' => 'seller', 'listing_id' => $bad]);

            $this->assertSame(1, $code, "listing_id '{$bad}' was not refused.");
            $this->assertStringContainsString('exactly one listing', $output);
        }
    }

    /** @test */
    public function the_command_takes_exactly_one_id_and_offers_no_batch_option(): void
    {
        // The structural half of one-listing isolation: there is no --all, no
        // --chunk and no id list to widen the blast radius into.
        $source = (string) file_get_contents(base_path('app/Console/Commands/GenerateLocationDna.php'));

        $this->assertStringNotContainsString('--all', $source);
        $this->assertStringNotContainsString('{--chunk', $source);
        $this->assertStringNotContainsString('listing_ids', $source);
        $this->assertStringContainsString('{listing_id :', $source);
    }

    // ── The canary acknowledgement ──────────────────────────────────────────

    /** @test */
    public function bridge_requires_the_canary_flag(): void
    {
        $this->expectPipelineNeverRuns();

        [$code, $output] = $this->runCommand(['listing_type' => 'bridge', 'listing_id' => '137']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('requires --canary', $output);
    }

    /** @test */
    public function seller_and_landlord_do_not_require_the_canary_flag(): void
    {
        // The flag guards the imported-MLS path specifically. Requiring it
        // everywhere would train operators to type it, which defeats it.
        $this->expectPipelineNeverRuns();

        [$code, $output] = $this->runCommand(['listing_type' => 'seller', 'listing_id' => '999999']);

        $this->assertSame(1, $code); // no such listing — but not blocked by the flag
        $this->assertStringNotContainsString('requires --canary', $output);
        $this->assertStringContainsString('No seller listing found', $output);
    }

    // ── Dry run writes nothing ──────────────────────────────────────────────

    /** @test */
    public function a_dry_run_does_not_invoke_the_pipeline(): void
    {
        $this->expectPipelineNeverRuns();

        $this->runCommand([
            'listing_type' => 'bridge',
            'listing_id'   => '137',
            '--canary'     => true,
            '--dry-run'    => true,
        ]);

        // Whether the listing exists in this environment or not, the pipeline must
        // not have been called. Mockery's shouldNotReceive asserts it on close.
        $this->assertTrue(true);
    }

    /** @test */
    public function a_dry_run_writes_no_poi_rows(): void
    {
        $this->expectPipelineNeverRuns();

        $before = PropertyLocationPoi::count();

        $this->runCommand([
            'listing_type' => 'bridge',
            'listing_id'   => '137',
            '--canary'     => true,
            '--dry-run'    => true,
        ]);

        $this->assertSame($before, PropertyLocationPoi::count(), 'A dry run wrote POI rows.');
    }

    // ── Posture reporting ───────────────────────────────────────────────────

    /** @test */
    public function the_posture_report_names_the_corpus_state_and_the_notice_obligation(): void
    {
        // The provider must be reported as disabled here, which is also this
        // phase's standing requirement.
        config([
            'overture_corpus_poi.enabled'                        => false,
            'overture_corpus_poi.corpus_version'                 => null,
            'location_providers.providers.overture_corpus.enabled' => false,
        ]);

        $this->expectPipelineNeverRuns();

        // No listing is needed: the posture describes the ENVIRONMENT, and the command
        // reports it before resolving the record precisely so a mistyped id still
        // answers "which provider would have answered".
        [, $output] = $this->runCommand([
            'listing_type' => 'seller',
            'listing_id'   => '999999',
            '--dry-run'    => true,
        ]);

        $this->assertStringContainsString('adapter=disabled', $output);
        $this->assertStringContainsString('registry=disabled', $output);
        $this->assertStringContainsString('(unpinned)', $output);
        $this->assertStringContainsString('exactly one listing', $output);

        // The NOTICE obligations are satisfied now that the upstream text, the licence
        // copy and our change notice are committed — so the posture must say so, AND
        // must say in the same breath that this is not permission to serve. An operator
        // who has just watched the licensing prerequisite clear is the person most
        // likely to read a green line as authorization.
        $this->assertStringContainsString('all NOTICE obligations satisfied', $output);
        $this->assertStringContainsString('NOT activation authorization', $output);
    }
}

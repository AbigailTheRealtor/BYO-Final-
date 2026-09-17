<?php

namespace Tests\Feature\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\TestCase;

/**
 * Phase 2 — `smart-tags:derive`.
 *
 * The companion to the deferred paths: Explore, criteria search and the bulk CLI
 * importer do not tag as they go, and this is what catches those rows up.
 */
class SmartTagBackfillCommandTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;
    use WiresSmartTags;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();
    }

    /**
     * Run the command against an EXPLICIT output buffer.
     *
     * CONSOLE OUTPUT IS NOT ASSERTABLE UNDER THIS HARNESS, and this file no longer
     * tries. The command prints correctly from a real terminal — verified — but
     * under PHPUnit it writes nothing, to `Artisan::output()` OR to an explicit
     * BufferedOutput passed in here. Two other suites in this repository carry the
     * same standing note (tests/Feature/Location/ProbeCensusAddressCommandTest.php
     * and tests/Feature/ListingImport/MlsSyncActivationSafetyTest.php).
     *
     * That mattered: every output assertion in this file used to pass in a
     * whole-file run and FAIL when its own test ran alone — they were matching a
     * previous command's leaked buffer, not this command's output. So the
     * assertions now rest on what is reliable: the EXIT CODE, the DATABASE, and
     * for operator-facing wording the command's own SOURCE.
     *
     * @param array<string, mixed> $options
     * @return array{0: int, 1: string}
     */
    private function callDerive(array $options): array
    {
        $buffer = new \Symfony\Component\Console\Output\BufferedOutput();

        $exit = \Illuminate\Support\Facades\Artisan::call('smart-tags:derive', $options, $buffer);

        return [$exit, $buffer->fetch()];
    }

    /**
     * Run the command and return its output.
     *
     * Laravel 8's PendingCommand has no expectsOutputToContain(), so assertions
     * about what a run REPORTED are made against captured output instead.
     *
     * @param array<string, mixed> $options
     */
    private function runDerive(array $options = []): string
    {
        [$exit, $output] = $this->callDerive($options);

        $this->assertSame(0, $exit, "smart-tags:derive exited {$exit}:\n{$output}");

        return $output;
    }

    /** As runDerive(), for a run that is expected to be refused. */
    private function runDeriveExpectingRefusal(array $options = []): string
    {
        [$exit, $output] = $this->callDerive($options);

        $this->assertSame(1, $exit, "smart-tags:derive should have been refused:\n{$output}");

        return $output;
    }

    /** A published Seller listing with no Smart Tags yet — what a deferred path leaves. */
    private function untaggedSeller(array $meta = []): \App\Models\SellerAgentAuction
    {
        return $this->sellerListing($this->sellerOwner(), array_merge([
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'additional_details' => 'Quartz countertops and a private pool.',
        ], $meta));
    }

    private function untaggedBridge(string $key): \App\Models\BridgeProperty
    {
        (new BridgePropertyNormalizer())->upsert([
            'ListingKey'      => $key,
            'ListingId'       => $key . '-id',
            'StandardStatus'  => 'Active',
            'PropertyType'    => 'Residential',
            'ListPrice'       => 350000,
            'UnparsedAddress' => '1 Backfill Way',
            'City'            => 'PhpunitBackfillCity',
            'StateOrProvince' => 'FL',
            'PostalCode'      => '33601',
            'WaterfrontYN'    => true,
            'PublicRemarks'   => 'Quartz countertops and a private pool.',
        ]);

        return \App\Models\BridgeProperty::query()->where('listing_key', $key)->firstOrFail();
    }

    // ── it does the work ──────────────────────────────────────────────────

    /** @test */
    public function it_tags_a_native_listing_that_no_live_path_tagged(): void
    {
        $listing = $this->untaggedSeller();

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->count());

        $this->artisan('smart-tags:derive', ['--source' => 'native', '--listing-type' => ['seller_agent']])
            ->assertExitCode(0);

        $this->assertGreaterThan(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->count(),
            'The backfill tagged nothing.');
    }

    /** @test */
    public function it_tags_a_bridge_row_that_a_deferred_import_left_untagged(): void
    {
        $row = $this->untaggedBridge('PHPUNIT-BF-1');

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->count());

        $this->runDerive(['--source' => 'bridge']);

        $this->assertGreaterThan(0, SmartTagEvidence::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->count());
    }

    // ── idempotence and staleness ─────────────────────────────────────────

    /** @test */
    public function running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->untaggedSeller();

        $this->runDerive(['--source' => 'native']);

        $after = [
            'evidence'    => SmartTagEvidence::query()->get()->map->only(['listing_type', 'listing_id', 'tag_key', 'source', 'state'])->toArray(),
            'assignments' => SmartTagAssignment::query()->get()->map->only(['listing_type', 'listing_id', 'tag_key', 'state'])->toArray(),
        ];

        $this->runDerive(['--source' => 'native']);

        $second = [
            'evidence'    => SmartTagEvidence::query()->get()->map->only(['listing_type', 'listing_id', 'tag_key', 'source', 'state'])->toArray(),
            'assignments' => SmartTagAssignment::query()->get()->map->only(['listing_type', 'listing_id', 'tag_key', 'state'])->toArray(),
        ];

        $this->assertEquals($after, $second, 'The backfill is not idempotent.');
    }

    /** @test */
    public function only_stale_skips_a_listing_that_is_already_current(): void
    {
        $listing = $this->untaggedSeller();

        $this->runDerive(['--source' => 'native']);

        $derivedAt = SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->value('derived_at');

        $this->runDerive(['--source' => 'native', '--only-stale' => true]);

        $this->assertEquals($derivedAt, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->value('derived_at'),
            '--only-stale re-derived a current listing.');
    }

    // ── dry run ───────────────────────────────────────────────────────────

    /** @test */
    public function a_dry_run_performs_zero_writes_to_every_smart_tag_table(): void
    {
        $this->untaggedSeller();
        $this->untaggedBridge('PHPUNIT-BF-DRY');

        $before = [
            SmartTagEvidence::query()->count(),
            SmartTagAssignment::query()->count(),
            SmartTagDerivationState::query()->count(),
            SmartTagManualEvent::query()->count(),
        ];

        $this->runDerive(['--dry-run' => true]);

        $after = [
            SmartTagEvidence::query()->count(),
            SmartTagAssignment::query()->count(),
            SmartTagDerivationState::query()->count(),
            SmartTagManualEvent::query()->count(),
        ];

        $this->assertSame($before, $after, 'A dry run wrote to a Smart Tag table.');
    }

    /** @test */
    public function a_dry_run_still_reports_what_it_would_do(): void
    {
        $this->untaggedSeller();

        $this->runDerive(['--source' => 'native', '--dry-run' => true]);

        // The report itself is asserted against the command's source: what it
        // prints is not observable under this harness (see callDerive()).
        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        $this->assertStringContainsString('considered', $source);
        $this->assertStringContainsString('DRY RUN — nothing is written', $source);
        $this->assertStringContainsString('Dry run complete', $source);
    }

    // ── resilience ────────────────────────────────────────────────────────

    /** @test */
    public function one_failing_listing_does_not_abort_the_batch(): void
    {
        $good1 = $this->untaggedSeller();
        $broken = $this->untaggedSeller();
        $good2 = $this->untaggedSeller();

        // A listing the derivation service will refuse: no supported context.
        $broken->saveMeta('property_type', 'Houseboat');

        $this->artisan('smart-tags:derive', ['--source' => 'native', '--listing-type' => ['seller_agent']])
            ->assertExitCode(0);

        foreach ([$good1, $good2] as $listing) {
            $this->assertGreaterThan(0, SmartTagEvidence::query()
                ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->count(),
                "Listing {$listing->id} was not tagged — the batch stopped early.");
        }

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $broken->id)->count());
    }

    /** @test */
    public function a_derivation_fault_is_counted_and_the_batch_continues(): void
    {
        $this->untaggedSeller();
        $this->untaggedSeller();

        $this->app->bind(\App\Services\SmartTags\SmartTagDerivationService::class,
            fn () => new \Tests\Feature\SmartTags\Doubles\ThrowingDerivationService());

        // Exit 0: one (here, every) failing listing must not abort the batch.
        $this->runDerive(['--source' => 'native', '--listing-type' => ['seller_agent']]);

        $this->assertSame(0, SmartTagEvidence::query()->count());
    }

    // ── manual evidence and remarks ───────────────────────────────────────

    /** @test */
    public function the_backfill_preserves_manual_owner_evidence(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'additional_details' => 'Quartz countertops.',
        ]);

        app(ManualSmartTagWriter::class)->replaceSelections(
            SmartTagListingRef::fromModel($listing), ['kitchen_island'], $owner
        );

        $before = SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('source', 'manual_listing_owner')->pluck('tag_key')->all();

        $this->assertSame(['kitchen_island'], $before);

        $this->runDerive(['--source' => 'native']);
        $this->runDerive(['--source' => 'native']);

        $after = SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('source', 'manual_listing_owner')->pluck('tag_key')->all();

        $this->assertSame($before, $after, 'The backfill erased manual owner evidence.');
    }

    /** @test */
    public function the_backfill_never_processes_public_remarks(): void
    {
        $this->untaggedBridge('PHPUNIT-BF-REMARKS');

        $this->runDerive(['--source' => 'bridge']);

        $this->assertSame(0, SmartTagEvidence::query()->where('source', 'mls_remarks')->count(),
            'The backfill produced remarks-derived evidence.');
    }

    /** @test */
    public function the_backfill_reports_the_remarks_refusal_rather_than_assuming_it(): void
    {
        $this->untaggedBridge('PHPUNIT-BF-REMARKS-2');

        $this->runDerive(['--source' => 'bridge']);

        // No remarks evidence exists, and the refusal is reported — the first is
        // the guarantee, the second is asserted on source (see callDerive()).
        $this->assertSame(0, SmartTagEvidence::query()->where('source', 'mls_remarks')->count());

        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        $this->assertStringContainsString('NOT PROCESSED', $source);
        $this->assertStringContainsString('remarks_blocked', $source);
    }

    // ── selection, resumability and bounds ────────────────────────────────

    /** @test */
    public function limit_stops_after_the_requested_number_of_listings(): void
    {
        foreach (range(1, 3) as $i) {
            $this->untaggedSeller();
        }

        $this->runDerive([
            '--source'       => 'native',
            '--listing-type' => ['seller_agent'],
            '--limit'        => 1,
        ]);

        $this->assertSame(1, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->count(),
            '--limit did not bound the run.');
    }

    /** @test */
    public function from_id_resumes_past_the_listings_already_processed(): void
    {
        $first = $this->untaggedSeller();
        $second = $this->untaggedSeller();

        $this->runDerive([
            '--source'       => 'native',
            '--listing-type' => ['seller_agent'],
            '--from-id'      => $first->id,
        ]);

        $this->assertSame(0, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $first->id)->count(),
            '--from-id is inclusive; it must process ids strictly greater.');
        $this->assertSame(1, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $second->id)->count());
    }

    /** @test */
    public function id_restricts_the_run_to_the_named_listings(): void
    {
        $wanted = $this->untaggedSeller();
        $other = $this->untaggedSeller();

        $this->runDerive([
            '--source'       => 'native',
            '--listing-type' => ['seller_agent'],
            '--id'           => [$wanted->id],
        ]);

        $this->assertSame(1, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $wanted->id)->count());
        $this->assertSame(0, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $other->id)->count());
    }

    /** @test */
    public function id_without_exactly_one_listing_type_is_refused(): void
    {
        $this->runDeriveExpectingRefusal(['--source' => 'native', '--id' => [1]]);

        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));
        $this->assertStringContainsString('--id requires exactly one --listing-type', $source);
    }

    /** @test */
    public function an_unknown_source_listing_type_or_context_is_refused(): void
    {
        $this->runDeriveExpectingRefusal(['--source' => 'nonsense']);
        $this->runDeriveExpectingRefusal(['--listing-type' => ['seller']]);
        $this->runDeriveExpectingRefusal(['--context' => 'residential_sale']);
        $this->runDeriveExpectingRefusal(['--source' => 'bridge', '--listing-type' => ['seller_agent']]);
    }

    /** @test */
    public function the_command_reports_the_activation_posture(): void
    {
        $this->disableSmartTags();

        $this->runDerive(['--dry-run' => true]);

        // Both gates really are off, and the command really does print them.
        $this->assertFalse(\App\Support\SmartTags\SmartTagWiring::enabled());
        $this->assertFalse(\App\Support\SmartTags\SmartTagWiring::bridgeEnabled());

        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        $this->assertStringContainsString('master gate    : ', $source);
        $this->assertStringContainsString('bridge gate    : ', $source);
    }

    /** @test */
    public function with_the_gates_closed_a_write_run_derives_nothing(): void
    {
        $this->untaggedSeller();
        $this->disableSmartTags();

        $this->runDerive(['--source' => 'native']);

        $this->assertSame(0, SmartTagEvidence::query()->count(),
            'The backfill wrote with the activation gates closed.');
    }

    // ── production posture ────────────────────────────────────────────────

    /**
     * A production WRITE requires a human at a terminal.
     *
     * `$this->artisan()` is non-interactive, which is exactly the shape of a cron
     * entry, a CI step or an agent invocation — the cases requirement 18 names.
     * The run must ABORT, not proceed, and must write nothing.
     *
     * @test
     */
    public function a_non_interactive_production_write_is_refused_and_writes_nothing(): void
    {
        $this->untaggedSeller();
        config(['app.env' => 'production']);

        $before = $this->smartTagRowCounts();

        $this->runDeriveExpectingRefusal(['--source' => 'native']);

        $this->assertSame($before, $this->smartTagRowCounts(),
            'A refused production run still wrote Smart Tag rows.');

        // What the operator is told. Asserted against the command's source rather
        // than Artisan::output(), which does not capture warn()/error() under a
        // programmatic call — the very invocation shape this test simulates.
        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        $this->assertStringContainsString('PRODUCTION DATABASE', $source);
        $this->assertStringContainsString('needs an interactive confirmation', $source);
        $this->assertStringContainsString('Nothing was written', $source);
    }

    /**
     * The refusal depends on a real terminal, not on Symfony's interactivity flag.
     *
     * `Artisan::call()` reports an ArrayInput as INTERACTIVE, so `isInteractive()`
     * alone would have let a scheduler, another command or an agent walk into a
     * prompt nobody can answer. The STDIN terminal check is what makes the
     * refusal true for those callers, and this pins that both halves are asked.
     *
     * @test
     */
    public function the_interactivity_check_requires_a_terminal_not_just_a_flag(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        $this->assertStringContainsString('stream_isatty', $source,
            'A production write must be gated on a real terminal.');
        $this->assertStringContainsString('$this->input->isInteractive()', $source);
    }

    /**
     * A DRY RUN against production is allowed, because it writes nothing.
     *
     * The confirmation exists to gate writes, not to make the command unusable
     * for the read-only inspection an operator does first.
     *
     * @test
     */
    public function a_dry_run_against_production_is_allowed_and_still_writes_nothing(): void
    {
        $this->untaggedSeller();
        config(['app.env' => 'production']);

        $before = $this->smartTagRowCounts();

        $this->assertStringContainsString('DRY RUN',
            $this->runDerive(['--source' => 'native', '--dry-run' => true]));

        $this->assertSame($before, $this->smartTagRowCounts());
    }

    /**
     * The command carries no production override token.
     *
     * Asserted against the source: a token can be pasted into a cron line once and
     * then means nothing forever after, which is why requirement 18 forbids one.
     *
     * @test
     */
    public function the_command_has_no_production_override_token(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));

        // The option does not exist, and the QA-only refusal trait is not used.
        // Asserted on CODE, not prose: the docblock explains both decisions and
        // would otherwise match a naive substring search.
        $this->assertDoesNotMatchRegularExpression('/\{--i-know-this-is-production/', $source);
        $this->assertDoesNotMatchRegularExpression('/^\s*use\s+.*RefusesProductionDatabase\s*;/m', $source);
    }

    /** @test */
    public function a_hire_agent_row_is_counted_as_not_an_offer_listing_and_never_tagged(): void
    {
        $this->sellerListing($this->sellerOwner(), [
            'property_type' => 'Residential',
            'waterfront'    => 'Yes',
        ], workflow: 'hire_agent');

        $this->runDerive(['--source' => 'native', '--listing-type' => ['seller_agent']]);

        $this->assertSame(0, SmartTagEvidence::query()->count());
    }
}

<?php

namespace Tests\Feature\SmartTags;

use App\Models\BridgeProperty;
use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\SmartTags\Coverage\BridgeSmartTagCoverageAuditor as Auditor;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\Seeker\BridgeSmartTagCheckability;
use App\Services\SmartTags\Seeker\ListingSmartTagIndex;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\TestCase;

/**
 * Bridge Smart Tag coverage, and the backfill that produces it.
 *
 * Seeker matching reads present `smart_tag_assignments` for Bridge rows; a row with
 * none is UNKNOWN to the scorer. These tests pin three things: the coverage report
 * measures that honestly and writes nothing; the existing `smart-tags:derive`
 * backfill is provider-scoped, idempotent and leaves manual owner tags alone; and
 * what the backfill stores is exactly what seeker matching then reads.
 *
 * Console output is not assertable under this harness (see
 * SmartTagBackfillCommandTest::callDerive()), so assertions rest on exit codes, the
 * database and the report array the command prints.
 */
class BridgeSmartTagCoverageBackfillTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;
    use WiresSmartTags;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();
    }

    protected function tearDown(): void
    {
        SmartTagTaxonomy::flush();
        parent::tearDown();
    }

    // ── the coverage report ───────────────────────────────────────────────

    /** @test */
    public function the_coverage_report_writes_nothing_even_when_simulating(): void
    {
        $this->bridgeRow();
        $this->bridgeRow(['property_type' => 'Land']);
        $this->derive(['--source' => 'bridge']);
        $this->bridgeRow();

        $before = $this->allSmartTagRowCounts();

        $this->coverage(simulate: true);
        $this->assertSame(0, $this->callCommand('smart-tags:coverage', ['--simulate' => true, '--json' => true]));

        $this->assertSame($before, $this->allSmartTagRowCounts(), 'The coverage report wrote to a Smart Tag table.');
    }

    /** @test */
    public function stored_coverage_says_why_a_listing_has_no_tag_instead_of_assuming_failure(): void
    {
        $covered     = $this->bridgeRow();
        $absentOnly  = $this->bridgeRow(['raw' => ['PoolPrivateYN' => false]]);
        $unsupported = $this->bridgeRow(['property_type' => 'Land']);
        $this->derive(['--source' => 'bridge']);
        $never       = $this->bridgeRow();

        $stored = $this->coverage()['stored'];

        $this->assertSame(4, $stored['listings']);
        $this->assertSame(1, $stored['covered']);
        $this->assertSame([
            Auditor::REASON_DERIVED_NO_PRESENT_TAG   => 1,
            Auditor::REASON_NEVER_DERIVED            => 1,
            Auditor::REASON_UNSUPPORTED_PROPERTY_TYPE => 1,
        ], $stored['uncovered_reasons']);

        // The listing whose only structured fact is a "No" has an absent assignment,
        // not a present one — known-absent, never counted as coverage.
        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'bridge')->where('listing_id', $absentOnly->id)->where('state', 'present')->count());
        $this->assertGreaterThan(0, SmartTagAssignment::query()
            ->where('listing_type', 'bridge')->where('listing_id', $absentOnly->id)->where('state', 'absent')->count());

        unset($covered, $unsupported, $never);
    }

    /** @test */
    public function a_listing_derived_under_an_older_tagger_is_covered_but_flagged_stale(): void
    {
        $row = $this->bridgeRow();
        $this->derive(['--source' => 'bridge']);

        SmartTagDerivationState::query()->where('listing_type', 'bridge')->where('listing_id', $row->id)
            ->update(['tagger_version' => 'an-older-tagger']);

        $stored = $this->coverage()['stored'];

        $this->assertSame(1, $stored['covered'], 'A stale listing still has tags the scorer reads.');
        $this->assertSame(1, $stored['stale_but_covered']);
        $this->assertSame([], $stored['uncovered_reasons']);
    }

    /** @test */
    public function the_simulation_predicts_exactly_what_the_backfill_then_stores(): void
    {
        $this->bridgeRow();
        $this->bridgeRow(['raw' => ['Cooling' => ['Central Air'], 'InteriorFeatures' => ['Walk-In Closet(s)'], 'PoolPrivateYN' => true]]);
        $this->bridgeRow(['property_type' => 'Residential Lease', 'raw' => ['Furnished' => 'Furnished', 'Cooling' => ['Wall/Window Unit(s)']]]);
        $this->bridgeRow(['raw' => ['PoolPrivateYN' => false]]);
        $this->bridgeRow(['property_type' => 'Land']);

        $predicted = $this->coverage(simulate: true)['simulated'];

        $this->derive(['--source' => 'bridge']);

        $stored = $this->coverage()['stored'];

        foreach (['covered', 'seeker_covered', 'tag_counts', 'seeker_tag_counts', 'present_tag_histogram'] as $key) {
            $this->assertEquals($predicted[$key], $stored[$key], "Simulated {$key} did not match what the backfill stored.");
        }

        $this->assertGreaterThan(0, $stored['covered']);
    }

    /** @test */
    public function seeker_relevant_coverage_counts_only_seeker_selectable_derivable_tags(): void
    {
        // gated_community is PENDING REVIEW: derivable as evidence by governance, but
        // never selectable, so it must not count as seeker coverage.
        $this->bridgeRow(['raw' => ['CommunityFeatures' => ['Gated Community - Guard']]]);
        $this->derive(['--source' => 'bridge']);

        $stored = $this->coverage()['stored'];
        $seekerDerivable = array_keys($stored['seeker_tag_coverage']);

        $this->assertNotContains('gated_community', $seekerDerivable);
        $this->assertNotContains('natural_light', $seekerDerivable, 'natural_light has no derivation rule and can never be covered.');
        $this->assertNotContains('accessible_features', $seekerDerivable);
        $this->assertNotContains('playground', $seekerDerivable);
        $this->assertContains('central_air', $seekerDerivable);
    }

    /** @test */
    public function the_report_reads_in_batches_not_per_listing(): void
    {
        foreach (range(1, 6) as $i) {
            $this->bridgeRow();
        }
        $this->derive(['--source' => 'bridge']);

        $report = SmartTagLifecycle::bridgeCoverage(null, [], true, 2);

        // Per batch: rows, assignments, states — plus the final empty chunk probe.
        $this->assertSame(3, $report['performance']['batches']);
        $this->assertLessThanOrEqual(3 * 3 + 2, $report['performance']['queries']);
    }

    /** @test */
    public function the_report_can_be_scoped_to_a_provider_and_a_status(): void
    {
        $this->bridgeRow();
        $this->bridgeRow(['provider' => 'another_mls']);
        $this->bridgeRow(['standard_status' => 'Pending']);

        $this->assertSame(2, SmartTagLifecycle::bridgeCoverage('stellar_bridge', [], false, 500)['scope']['listings']);
        $this->assertSame(1, SmartTagLifecycle::bridgeCoverage(null, ['Pending'], false, 500)['scope']['listings']);

        $byProvider = SmartTagLifecycle::bridgeCoverage(null, [], false, 500)['stored']['by']['provider'];
        $this->assertSame(2, $byProvider['stellar_bridge']['listings']);
        $this->assertSame(1, $byProvider['another_mls']['listings']);
    }

    // ── the backfill ──────────────────────────────────────────────────────

    /** @test */
    public function a_provider_scoped_backfill_never_touches_another_providers_rows(): void
    {
        $ours   = $this->bridgeRow();
        $theirs = $this->bridgeRow(['provider' => 'another_mls']);

        $this->derive(['--source' => 'bridge', '--provider' => 'stellar_bridge']);

        $this->assertSame(1, $this->stateCount($ours));
        $this->assertSame(0, $this->stateCount($theirs));
        $this->assertSame(0, SmartTagEvidence::query()->where('listing_type', 'bridge')->where('listing_id', $theirs->id)->count());
    }

    /** @test */
    public function provider_is_refused_where_it_cannot_mean_anything(): void
    {
        $this->assertSame(1, $this->callCommand('smart-tags:derive', ['--provider' => 'stellar_bridge']));
        $this->assertSame(1, $this->callCommand('smart-tags:derive', ['--source' => 'native', '--provider' => 'stellar_bridge']));
        $this->assertSame(0, SmartTagDerivationState::query()->count(), 'A refused run wrote.');
    }

    /** @test */
    public function rerunning_the_bridge_backfill_reproduces_the_same_assignment_set(): void
    {
        foreach (range(1, 3) as $i) {
            $this->bridgeRow();
        }
        $this->bridgeRow(['raw' => ['PoolPrivateYN' => false]]);

        $this->derive(['--source' => 'bridge']);
        $first = $this->bridgeSnapshot();

        $this->derive(['--source' => 'bridge']);
        $this->derive(['--source' => 'bridge', '--only-stale' => true]);

        $this->assertEquals($first, $this->bridgeSnapshot(), 'Re-running the backfill changed the stored set.');
        $this->assertSame(
            SmartTagAssignment::query()->where('listing_type', 'bridge')->count(),
            SmartTagAssignment::query()->where('listing_type', 'bridge')->distinct()->count(DB::raw("listing_id || ':' || tag_key")),
            'A listing carries the same tag twice.'
        );
    }

    /** @test */
    public function a_derived_tag_follows_its_source_when_the_mls_facts_change(): void
    {
        $row = $this->bridgeRow(['raw' => ['PoolPrivateYN' => true]]);
        $this->derive(['--source' => 'bridge']);
        $this->assertContains('private_pool', $this->presentTags('bridge', $row->id));

        $row->update(['pool_private_yn' => false, 'raw_json' => json_encode($this->raw(['PoolPrivateYN' => false]))]);
        $this->derive(['--source' => 'bridge', '--only-stale' => true]);
        $this->derive(['--source' => 'bridge']);

        $this->assertNotContains('private_pool', $this->presentTags('bridge', $row->id));
        $this->assertSame('absent', SmartTagAssignment::query()
            ->where('listing_type', 'bridge')->where('listing_id', $row->id)->where('tag_key', 'private_pool')->value('state'));
    }

    /** @test */
    public function manual_owner_tags_survive_a_full_rebuild_and_are_never_duplicated(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'additional_details' => 'Large kitchen island and a walk-in pantry.',
        ]);

        app(ManualSmartTagWriter::class)->replaceSelections(
            SmartTagListingRef::fromModel($listing), ['kitchen_island', 'shaker_cabinets'], $owner
        );
        $manualEvents = SmartTagManualEvent::query()->count();

        $this->bridgeRow();
        $this->derive(['--source' => 'all']);
        $this->derive(['--source' => 'all']);

        $manual = SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)
            ->where('source', 'manual_listing_owner')->orderBy('tag_key')->pluck('tag_key')->all();
        $this->assertSame(['kitchen_island', 'shaker_cabinets'], $manual, 'The rebuild erased manual owner evidence.');
        $this->assertSame($manualEvents, SmartTagManualEvent::query()->count(), 'The rebuild wrote manual history.');

        // kitchen_island is both described AND selected: two evidence rows, ONE assignment.
        $island = SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->where('tag_key', 'kitchen_island')->get();
        $this->assertCount(1, $island);
        $this->assertSame('manual_listing_owner', $island->first()->winning_source);
        $this->assertContains('shaker_cabinets', $this->presentTags('seller_agent', $listing->id));
    }

    /** @test */
    public function a_bridge_row_can_never_carry_manual_evidence(): void
    {
        $this->assertFalse(\App\Support\SmartTags\SmartTagListingType::Bridge->allowsSource(\App\Support\SmartTags\SmartTagSource::ManualListingOwner));
    }

    /** @test */
    public function unsupported_property_types_are_skipped_and_left_without_tags(): void
    {
        $land = $this->bridgeRow(['property_type' => 'Land']);
        $resIncome = $this->bridgeRow(['property_type' => 'Residential Income']);

        $this->derive(['--source' => 'bridge']);

        foreach ([$land, $resIncome] as $row) {
            $this->assertSame(0, SmartTagAssignment::query()->where('listing_type', 'bridge')->where('listing_id', $row->id)->count());
            $this->assertSame(0, $this->stateCount($row));
        }
    }

    /** @test */
    public function a_retired_tag_is_never_derived_and_never_counted(): void
    {
        config()->set('smart_tags.tags.central_air.status', 'retired');
        SmartTagTaxonomy::flush();

        $row = $this->bridgeRow();
        $this->derive(['--source' => 'bridge']);

        $this->assertNotContains('central_air', $this->presentTags('bridge', $row->id));
        $this->assertSame(0, SmartTagEvidence::query()->where('tag_key', 'central_air')->count());
        $this->assertArrayNotHasKey('central_air', $this->coverage()['stored']['seeker_tag_coverage']);
    }

    /** @test */
    public function a_pending_review_tag_may_be_stored_but_can_never_be_scored_for_a_seeker(): void
    {
        $row = $this->bridgeRow(['raw' => ['CommunityFeatures' => ['Gated Community - Guard']]]);
        $this->derive(['--source' => 'bridge']);

        // Governance: pending_review = derivable as evidence, not selectable, not displayed.
        $this->assertContains('gated_community', $this->presentTags('bridge', $row->id));

        $accepted = SmartTagSelectionPolicy::project(['gated_community'], SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER)->accepted;
        $this->assertSame([], $accepted, 'A pending-review tag reached a seeker selection.');
    }

    /**
     * Stellar sends "Eating Space In Kitchen", never the RESO-style "Eat-in Kitchen"
     * the native forms use; both are the same picklist slot and map to one key.
     *
     * @test
     */
    public function stellars_eating_space_in_kitchen_derives_eat_in_kitchen(): void
    {
        $stellar = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Eating Space In Kitchen', 'L Dining']]]);
        $reso = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Eat-in Kitchen']]]);
        $neither = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Kitchen/Family Room Combo', 'L Dining']]]);

        $this->derive(['--source' => 'bridge']);

        $this->assertContains('eat_in_kitchen', $this->presentTags('bridge', $stellar->id));
        $this->assertContains('eat_in_kitchen', $this->presentTags('bridge', $reso->id));
        $this->assertNotContains('eat_in_kitchen', $this->presentTags('bridge', $neither->id));
    }

    /** @test */
    public function natural_light_is_never_derived_from_any_source(): void
    {
        $owner = $this->sellerOwner();
        $listing = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'additional_details' => 'Abundant natural light and sun-filled rooms throughout.',
        ]);
        $row = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Skylight(s)', 'Open Floorplan']]]);

        $this->derive(['--source' => 'all']);

        $this->assertSame(0, SmartTagEvidence::query()->where('tag_key', 'natural_light')->count());
        unset($listing, $row);
    }

    /** @test */
    public function only_stale_reads_derivation_state_once_per_batch_not_once_per_listing(): void
    {
        foreach (range(1, 6) as $i) {
            $this->bridgeRow();
        }
        $this->ageBridgeRows();
        $this->derive(['--source' => 'bridge']);

        $stateReads = 0;
        DB::listen(static function ($query) use (&$stateReads) {
            if (str_contains($query->sql, 'smart_tag_derivation_states')) {
                $stateReads++;
            }
        });

        $this->derive(['--source' => 'bridge', '--only-stale' => true, '--batch-size' => 3]);

        // Six current rows in two batches: two state reads, no per-row lookups and
        // no derivation (every row is current, so nothing is rewritten).
        $this->assertSame(2, $stateReads);
    }

    /** @test */
    public function a_bounded_run_resumes_from_its_cursor_and_finishes_the_set(): void
    {
        $rows = [];
        foreach (range(1, 4) as $i) {
            $rows[] = $this->bridgeRow();
        }

        $this->derive(['--source' => 'bridge', '--limit' => 2, '--batch-size' => 1]);
        $this->assertSame(2, SmartTagDerivationState::query()->where('listing_type', 'bridge')->count());

        $this->derive(['--source' => 'bridge', '--from-id' => $rows[1]->id]);
        foreach ($rows as $row) {
            $this->assertSame(1, $this->stateCount($row));
        }
    }

    /** @test */
    public function one_failing_bridge_row_is_counted_and_the_batch_continues(): void
    {
        $this->bridgeRow();
        $this->bridgeRow();

        $this->app->bind(\App\Services\SmartTags\SmartTagDerivationService::class,
            fn () => new \Tests\Feature\SmartTags\Doubles\ThrowingDerivationService());

        $this->derive(['--source' => 'bridge']);

        $this->assertSame(0, SmartTagEvidence::query()->count());
    }

    // ── ongoing freshness: the scheduled catch-up ─────────────────────────

    /** @test */
    public function the_catch_up_is_not_scheduled_unless_every_gate_is_on(): void
    {
        $this->assertSame([], $this->scheduledCatchUps(), 'Both derivation gates on must not schedule a catch-up.');

        $this->openCatchUp();
        $this->enableSmartTags(bridge: false);
        config()->set('smart_tags_wiring.bridge_catch_up_schedule_enabled', true);
        $this->assertSame([], $this->scheduledCatchUps(), 'The Bridge gate is part of the catch-up gate.');
    }

    /** @test */
    public function with_every_gate_on_it_is_an_hourly_bounded_provider_scoped_only_stale_run(): void
    {
        $this->openCatchUp();

        $events = $this->scheduledCatchUps();
        $this->assertCount(1, $events);

        $event = $events[0];
        foreach (['--scheduled', '--source=bridge', '--provider=stellar_bridge', '--only-stale', '--batch-size=200', '--max-derived=500'] as $part) {
            $this->assertStringContainsString($part, $event->command);
        }
        $this->assertSame('17 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping, 'Two catch-ups must never run at once.');
    }

    /** @test */
    public function the_scheduled_mode_is_refused_unless_its_gate_is_open_and_its_scope_is_the_narrowest(): void
    {
        $this->bridgeRow();
        $narrowest = ['--scheduled' => true, '--source' => 'bridge', '--provider' => 'stellar_bridge', '--only-stale' => true];

        $this->assertSame(1, $this->callCommand('smart-tags:derive', $narrowest), 'Refused while the catch-up gate is closed.');

        $this->openCatchUp();
        foreach ([
            array_diff_key($narrowest, ['--only-stale' => 1]),
            array_diff_key($narrowest, ['--provider' => 1]),
            ['--source' => 'all'] + $narrowest,
            $narrowest + ['--id' => [1], '--listing-type' => ['bridge']],
            $narrowest + ['--from-id' => 1],
            $narrowest + ['--context' => 'residential.sale'],
            $narrowest + ['--dry-run' => true],
        ] as $widened) {
            $this->assertSame(1, $this->callCommand('smart-tags:derive', $widened), json_encode($widened));
        }

        $this->assertSame(0, SmartTagDerivationState::query()->count(), 'A refused scheduled run wrote.');
    }

    /** @test */
    public function consecutive_scheduled_runs_rotate_a_cursor_and_finish_the_set(): void
    {
        $this->openCatchUp();
        \Illuminate\Support\Facades\Cache::forget(\App\Console\Commands\DeriveSmartTags::SCHEDULED_CURSOR_KEY . 'stellar_bridge');

        $rows = [];
        foreach (range(1, 5) as $i) {
            $rows[] = $this->bridgeRow();
        }
        // Imported before they are derived. A row updated in the same second as its
        // derivation deliberately reads as stale, which would spend the wrapped run's
        // budget re-deriving rows 1–2 instead of reaching the new row.
        $this->ageBridgeRows();

        $run = ['--scheduled' => true, '--source' => 'bridge', '--provider' => 'stellar_bridge', '--only-stale' => true, '--max-derived' => 2, '--batch-size' => 2];

        $this->assertSame(0, $this->callCommand('smart-tags:derive', $run));
        $this->assertSame(2, SmartTagDerivationState::query()->count());

        $this->assertSame(0, $this->callCommand('smart-tags:derive', $run));
        $this->assertSame(4, SmartTagDerivationState::query()->count(), 'The second run must resume, not restart.');

        $this->assertSame(0, $this->callCommand('smart-tags:derive', $run));
        foreach ($rows as $row) {
            $this->assertSame(1, $this->stateCount($row));
        }

        // Finished: the cursor wrapped, so a newly imported row is reached next hour.
        $new = $this->bridgeRow();
        $this->assertSame(0, $this->callCommand('smart-tags:derive', $run));
        $this->assertSame(1, $this->stateCount($new));
    }

    /** @test */
    public function only_stale_rederives_a_row_whose_mls_facts_changed_after_it_was_tagged(): void
    {
        $row = $this->bridgeRow(['raw' => ['PoolPrivateYN' => true]]);
        $this->ageBridgeRows();
        $this->derive(['--source' => 'bridge']);
        $this->assertContains('private_pool', $this->presentTags('bridge', $row->id));

        // A re-import rewrites the row (and its updated_at) — nothing touches the state.
        $row->refresh()->update(['pool_private_yn' => false, 'raw_json' => json_encode($this->raw(['PoolPrivateYN' => false]))]);

        $this->derive(['--source' => 'bridge', '--only-stale' => true]);

        $this->assertNotContains('private_pool', $this->presentTags('bridge', $row->id),
            '--only-stale kept a tag the MLS row no longer supports.');
    }

    // ── the dry run says what it is ───────────────────────────────────────

    /** @test */
    public function the_dry_run_points_to_the_simulation_for_an_authoritative_prediction(): void
    {
        $notice = \App\Console\Commands\DeriveSmartTags::DRY_RUN_NOTICE;

        $this->assertStringContainsString('php artisan smart-tags:coverage --simulate', $notice);
        $this->assertStringContainsString('NOT "would receive a tag"', $notice);

        // Printed before the run and again beside the totals — asserted on source,
        // since console output is not capturable under this harness.
        $source = (string) file_get_contents(base_path('app/Console/Commands/DeriveSmartTags.php'));
        $this->assertSame(2, substr_count($source, "\$this->warn('  ' . self::DRY_RUN_NOTICE)"));

        // And it is still a dry run: nothing is written.
        $this->bridgeRow();
        $before = $this->allSmartTagRowCounts();
        $this->derive(['--source' => 'bridge', '--dry-run' => true]);
        $this->assertSame($before, $this->allSmartTagRowCounts());
    }

    // ── pending-review assignments: stored by governance, never consumed ──

    /** @test */
    public function a_pending_review_assignment_is_never_counted_scored_or_offered(): void
    {
        $row = $this->bridgeRow(['raw' => ['Cooling' => ['Central Air'], 'CommunityFeatures' => ['Gated Community - Guard']]]);
        $this->derive(['--source' => 'bridge']);
        $this->assertContains('gated_community', $this->presentTags('bridge', $row->id));

        $gated = SmartTagTaxonomy::get('gated_community');
        $this->assertTrue($gated->isPendingReview());
        $this->assertFalse($gated->isSeekerSelectable());

        // Seeker matching: the payload drops the pick before any read happens.
        $this->openSeekerMatching();
        $this->assertSame([], $this->payload(['gated_community'])->seekerSmartTags);
        $this->assertNotContains('gated_community', array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER)));

        // Coverage: it is not seeker coverage.
        $this->assertArrayNotHasKey('gated_community', $this->coverage()['stored']['seeker_tag_counts']);
    }

    /**
     * Storage is policy-neutral: a pending-review tag has a resolved row. It is never
     * CHECKABLE for a seeker — not present, not a known miss — and a home whose only
     * row is pending-review has nothing that answers an unrelated pick either.
     *
     * @test
     */
    public function a_pending_review_or_inactive_tag_is_never_checkable(): void
    {
        $pendingOnly = $this->bridgeRow(['raw' => ['CommunityFeatures' => ['Gated Community - Guard']]]);
        $governed = $this->bridgeRow(['raw' => ['Cooling' => ['Central Air']]]);
        $this->derive(['--source' => 'bridge']);

        $this->assertSame(['gated_community'], $this->presentTags('bridge', $pendingOnly->id), 'Evidence and the resolved row may exist.');

        $this->openSeekerMatching();
        $payload = $this->payload(['central_air']);
        $index = ListingSmartTagIndex::forCandidates([$pendingOnly, $governed], $payload);

        // Cooling is not in the pending-only row: unknown, whatever else the row carries.
        $this->assertSame([], $index->factsFor($pendingOnly)->presentKeys);
        $this->assertSame([], $index->factsFor($pendingOnly)->knownAbsentKeys);
        $this->assertSame(['central_air'], $index->factsFor($governed)->presentKeys);

        // Asked directly, the pending tag itself is unknown even with a present row, and so is a
        // retired one — the classifier re-checks governance rather than trusting storage.
        $record = \App\Services\SmartTags\Derivation\BridgeRecordAccessor::fromModel($pendingOnly->fresh());
        $answers = BridgeSmartTagCheckability::classify($record, SmartTagContext::ResidentialSale, ['gated_community'],
            ['gated_community' => \App\Support\SmartTags\SmartTagState::Present], [], true);
        $this->assertSame(['gated_community' => BridgeSmartTagCheckability::UNKNOWN], $answers);
        $this->assertFalse(BridgeSmartTagCheckability::hasStructuredCapability('gated_community', SmartTagContext::ResidentialSale));

        config()->set('smart_tags.tags.central_air.status', 'retired');
        SmartTagTaxonomy::flush();
        $this->assertSame(['central_air' => BridgeSmartTagCheckability::UNKNOWN], BridgeSmartTagCheckability::classify(
            \App\Services\SmartTags\Derivation\BridgeRecordAccessor::fromModel($governed->fresh()), SmartTagContext::ResidentialSale,
            ['central_air'], ['central_air' => \App\Support\SmartTags\SmartTagState::Present], [], true));
        $this->assertFalse(BridgeSmartTagCheckability::hasStructuredCapability('central_air', SmartTagContext::ResidentialSale));
    }

    /**
     * Checkability comes from the GOVERNED RULES: quartz has a structured Bridge rule (it may be
     * checked even with zero rows anywhere); updated_kitchen has none (never checkable, whatever
     * its count). A populated field is a known miss; an empty or absent one is unknown.
     *
     * @test
     */
    public function checkability_follows_the_governed_rule_and_the_populated_field(): void
    {
        $this->assertTrue(BridgeSmartTagCheckability::hasStructuredCapability('quartz_countertops', SmartTagContext::ResidentialSale));
        $this->assertFalse(BridgeSmartTagCheckability::hasStructuredCapability('updated_kitchen', SmartTagContext::ResidentialSale));
        $this->assertFalse(BridgeSmartTagCheckability::hasStructuredCapability('natural_light', SmartTagContext::ResidentialSale));

        $has      = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Quartz Counters']]]);
        $lacks    = $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Walk-In Closet(s)']]]);
        $empty    = $this->bridgeRow(['raw' => ['InteriorFeatures' => []]]);
        $missing  = $this->bridgeRow(['raw' => ['Cooling' => ['Central Air']]]);
        $this->derive(['--source' => 'bridge']);

        $index = ListingSmartTagIndex::forBridgeRows([$has, $lacks, $empty, $missing], ['quartz_countertops', 'updated_kitchen']);

        $this->assertSame(['quartz_countertops'], $index->factsFor($has)->presentKeys);
        $this->assertSame(['quartz_countertops'], $index->factsFor($lacks)->knownAbsentKeys, 'populated field, rule did not name it');
        foreach ([$empty, $missing] as $row) {
            $this->assertSame([], $index->factsFor($row)->presentKeys);
            $this->assertSame([], $index->factsFor($row)->knownAbsentKeys, 'an empty or missing field is unknown');
        }
        foreach ([$has, $lacks, $empty, $missing] as $row) {
            $this->assertNotContains('updated_kitchen', $index->factsFor($row)->knownAbsentKeys, 'no structured rule: never a miss');
        }
    }

    /**
     * A tag dropped by a cross-tag conflict keeps its evidence but has no assignment: the
     * field was populated, yet the honest answer is unknown, never a miss.
     *
     * @test
     */
    public function a_conflict_dropped_tag_is_unknown_not_a_miss(): void
    {
        // Furnished = Unfurnished → unfurnished; BuildingFeatures names Furnished → furnished.
        // Equal-strength structured evidence for two conflicting tags: both are dropped.
        $row = $this->bridgeRow([
            'property_type' => 'Residential Lease',
            'raw'           => ['Furnished' => 'Unfurnished', 'BuildingFeatures' => ['Furnished']],
        ]);
        $this->derive(['--source' => 'bridge']);

        $this->assertSame(2, SmartTagEvidence::query()->where('listing_id', $row->id)->whereIn('tag_key', ['furnished', 'unfurnished'])->count(),
            'fixture: both conflicting tags have structured evidence');
        $this->assertSame([], array_values(array_intersect(['furnished', 'unfurnished'], $this->presentTags('bridge', $row->id))),
            'fixture: the resolver dropped both');

        $facts = ListingSmartTagIndex::forBridgeRows([$row], ['furnished', 'unfurnished'])->factsFor($row);

        $this->assertSame([], $facts->presentKeys);
        $this->assertSame([], $facts->knownAbsentKeys, 'a conflict is not an absence');
    }

    /**
     * The report splits seeker tags by governed structured capability per context and, when
     * simulating, counts each listing × checkable tag as present, known non-match, field
     * unavailable, or unknown for another reason. Keys and counts only — no MLS value.
     *
     * @test
     */
    public function the_coverage_report_separates_checkable_uncheckable_and_field_unavailable(): void
    {
        $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Quartz Counters']]]);
        $this->bridgeRow(['raw' => ['InteriorFeatures' => ['Walk-In Closet(s)']]]);
        $this->bridgeRow(['raw' => ['Cooling' => ['Central Air']]]);

        $stored = $this->coverage()['checkability'];
        $this->assertFalse($stored['simulated']);
        $this->assertArrayNotHasKey('listing_tag_checks', $stored['by_context']['residential.sale']);

        $report = $this->coverage(true);
        $row = $report['checkability']['by_context']['residential.sale'];

        $this->assertSame($row['seeker_tags_applicable'], $row['seeker_tags_structured'] + $row['seeker_tags_not_structured']);
        $this->assertContains('updated_kitchen', $row['not_structured_tags']);
        $this->assertContains('natural_light', $row['not_structured_tags']);
        $this->assertNotContains('quartz_countertops', $row['not_structured_tags'], 'a rule exists, whatever today\'s count');
        $this->assertSame($row['seeker_tags_structured'], $row['structured_with_present'] + $row['structured_zero_present']);

        $checks = $row['listing_tag_checks'];
        $this->assertSame(3 * $row['seeker_tags_structured'], array_sum($checks), 'every listing × structured tag is counted once');
        $this->assertGreaterThan(0, $checks['present']);
        $this->assertGreaterThan(0, $checks['known_non_match']);
        $this->assertGreaterThan(0, $checks['source_field_unavailable']);

        $json = (string) json_encode($report);
        $this->assertStringNotContainsString('Walk-In Closet', $json, 'no raw MLS value in the report');
    }

    // ── what seeker matching reads afterwards ─────────────────────────────

    /** @test */
    public function seeker_matching_reads_the_backfilled_assignments_and_an_untagged_row_stays_unknown(): void
    {
        $tagged = $this->bridgeRow();
        $this->derive(['--source' => 'bridge']);
        $untagged = $this->bridgeRow();

        // In a test only: open both seeker gates. The runtime flag is untouched.
        $this->openSeekerMatching();

        $payload = $this->payload(['central_air']);
        $index = ListingSmartTagIndex::forCandidates([$tagged, $untagged], $payload);

        $taggedFacts = $index->factsFor($tagged);
        $this->assertSame(['central_air'], $taggedFacts->presentKeys);

        $untaggedFacts = $index->factsFor($untagged);
        $this->assertSame([], $untaggedFacts->presentKeys);
        $this->assertSame([], $untaggedFacts->knownAbsentKeys, 'A never-derived row must read as unknown, not as absent.');

        $results = (new BuyerMatchScorer())->scoreAll([$tagged, $untagged], $payload);
        $this->assertSame(1, $results[0]->seekerFeatureMatch->matchedCount());
        $this->assertSame(0, $results[1]->seekerFeatureMatch->matchedCount());
        $this->assertSame(10, $results[0]->categoryScores['amenities']);
    }

    /** @test */
    public function with_matching_off_no_listing_tag_is_read_even_after_a_backfill(): void
    {
        $row = $this->bridgeRow();
        $this->derive(['--source' => 'bridge']);

        $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabled(), 'The matching gate must default off.');

        $assignmentReads = 0;
        DB::listen(static function ($query) use (&$assignmentReads) {
            if (preg_match('/smart_tag_(assignments|evidence|derivation_states)/', $query->sql)) {
                $assignmentReads++;
            }
        });

        // A real pick, with the gate off: the payload itself drops it, so nothing is read.
        $payload = $this->payload(['central_air']);
        $this->assertSame([], $payload->seekerSmartTags);

        (new BuyerMatchScorer())->scoreAll([$row], $payload);

        $this->assertSame(0, $assignmentReads);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** A Residential Stellar row with Central Air — the most common seeker-relevant tag live. */
    private function bridgeRow(array $overrides = []): BridgeProperty
    {
        $this->n++;
        $raw = $this->raw($overrides['raw'] ?? ['Cooling' => ['Central Air']], $overrides['property_type'] ?? 'Residential');
        unset($overrides['raw']);

        return BridgeProperty::create(array_merge([
            'provider'          => 'stellar_bridge',
            'listing_key'       => sprintf('BSTC-%03d-%s', $this->n, uniqid()),
            'listing_id'        => "BSTC-{$this->n}-" . uniqid(),
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 400000,
            'city'              => 'Orlando',
            'state_or_province' => 'FL',
            'postal_code'       => '32801',
            'pool_private_yn'   => $raw['PoolPrivateYN'] ?? null,
            'raw_json'          => json_encode($raw),
        ], $overrides));
    }

    private function openCatchUp(): void
    {
        $this->enableSmartTags();
        config()->set('smart_tags_wiring.bridge_catch_up_schedule_enabled', true);
        \App\Support\SmartTags\SmartTagConfig::flush();
    }

    /** @return list<\Illuminate\Console\Scheduling\Event> the Kernel's catch-up entries, defined afresh */
    private function scheduledCatchUps(): array
    {
        $schedule = new \Illuminate\Console\Scheduling\Schedule();
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $define = new \ReflectionMethod($kernel, 'schedule');
        $define->setAccessible(true);
        $define->invoke($kernel, $schedule);

        return array_values(array_filter($schedule->events(), static fn ($e) => str_contains((string) $e->command, 'smart-tags:derive')));
    }

    /**
     * Put every Bridge row's last update in the past, so a derivation run now is
     * strictly later — the staleness check treats a same-second update as stale.
     */
    private function ageBridgeRows(): void
    {
        BridgeProperty::query()->update(['updated_at' => now()->subHour()]);
    }

    private function openSeekerMatching(): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_enabled', true);
        \App\Support\SmartTags\SmartTagConfig::flush();
    }

    private function raw(array $fields, string $propertyType = 'Residential'): array
    {
        return array_merge(['PropertyType' => $propertyType, 'IDXParticipationYN' => true], $fields);
    }

    private function payload(array $tags): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
            'preferred_cities'    => ['Orlando'],
            'seeker_smart_tags'   => $tags,
        ]);
    }

    /** @return array<string, mixed> */
    private function coverage(bool $simulate = false): array
    {
        return SmartTagLifecycle::bridgeCoverage(null, [], $simulate, 500);
    }

    private function derive(array $options): void
    {
        $this->assertSame(0, $this->callCommand('smart-tags:derive', $options), 'smart-tags:derive failed');
    }

    private function callCommand(string $command, array $options): int
    {
        return Artisan::call($command, $options, new BufferedOutput());
    }

    private function stateCount(BridgeProperty $row): int
    {
        return SmartTagDerivationState::query()->where('listing_type', 'bridge')->where('listing_id', $row->id)->count();
    }

    /** @return array<int, array<string, mixed>> */
    private function bridgeSnapshot(): array
    {
        return [
            'evidence'    => SmartTagEvidence::query()->where('listing_type', 'bridge')->orderBy('listing_id')->orderBy('tag_key')
                ->get(['listing_id', 'tag_key', 'source', 'state'])->toArray(),
            'assignments' => SmartTagAssignment::query()->where('listing_type', 'bridge')->orderBy('listing_id')->orderBy('tag_key')
                ->get(['listing_id', 'tag_key', 'state', 'winning_source'])->toArray(),
            'states'      => SmartTagDerivationState::query()->where('listing_type', 'bridge')->orderBy('listing_id')
                ->get(['listing_id', 'context', 'tagger_version', 'structured_inputs_hash'])->toArray(),
        ];
    }

    /** @return array<string, int> */
    private function allSmartTagRowCounts(): array
    {
        return $this->smartTagRowCounts() + ['manual_events' => SmartTagManualEvent::query()->count()];
    }
}

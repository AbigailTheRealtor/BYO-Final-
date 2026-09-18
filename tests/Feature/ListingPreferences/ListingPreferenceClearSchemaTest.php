<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\ListingPreferenceEvent;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The Phase 2 schema correction and, more importantly, WHAT NULL MEANS.
 *
 * Phase 1 could record the three transitions INTO a state and not the one out
 * of all of them. These tests pin the meaning the migration introduced, so a
 * later change cannot quietly repurpose the null.
 */
class ListingPreferenceClearSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function the_forward_migration_left_to_state_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('listing_preference_events', 'to_state'));

        $event = ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Save->value,
            'to_state'   => null,
        ]));

        $this->assertNull($event->fresh()->to_state);
    }

    /**
     * The three shapes, and nothing else.
     *
     * @test
     */
    public function the_three_transition_shapes_are_all_recordable(): void
    {
        $first = ListingPreferenceEvent::create($this->row([
            'from_state' => null,
            'to_state'   => ListingPreferenceState::Save->value,
            'subject_key' => 'mls:stellar_bridge:CLEAR-SHAPES',
        ]));

        $changed = ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Save->value,
            'to_state'   => ListingPreferenceState::Pass->value,
            'subject_key' => 'mls:stellar_bridge:CLEAR-SHAPES',
        ]));

        $cleared = ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Pass->value,
            'to_state'   => null,
            'subject_key' => 'mls:stellar_bridge:CLEAR-SHAPES',
        ]));

        $this->assertNull($first->fresh()->from_state, 'first preference has no prior state');
        $this->assertSame('save', $first->fresh()->to_state);

        $this->assertSame('pass', $changed->fresh()->to_state);

        $this->assertNull($cleared->fresh()->to_state, 'a clear has no resulting state');
        $this->assertSame('pass', $cleared->fresh()->from_state, 'and remembers what was cleared');

        $this->assertSame(3, ListingPreferenceEvent::where('subject_key', 'mls:stellar_bridge:CLEAR-SHAPES')->count());
    }

    /**
     * NULL means "no current preference" and nothing else — it is not a fourth
     * state, so it must never appear in the state vocabulary.
     *
     * @test
     */
    public function null_is_not_a_fourth_state(): void
    {
        $this->assertSame(['save', 'maybe', 'pass'], ListingPreferenceState::values());
        $this->assertCount(3, ListingPreferenceState::cases());
        $this->assertNull(ListingPreferenceState::tryFrom(''));
        $this->assertNull(ListingPreferenceState::tryFrom('cleared'));
        $this->assertNull(ListingPreferenceState::tryFrom('none'));
    }

    /** @test */
    public function the_model_reads_a_cleared_event_without_coercing_the_null(): void
    {
        $cleared = ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Maybe->value,
            'to_state'   => null,
        ]));

        $kept = ListingPreferenceEvent::create($this->row([
            'from_state' => null,
            'to_state'   => ListingPreferenceState::Maybe->value,
        ]));

        $this->assertTrue($cleared->fresh()->wasCleared());
        $this->assertFalse($kept->fresh()->wasCleared());

        // A cast to string would turn the null into '' and destroy the
        // distinction. Serialization must preserve it too.
        $this->assertNull($cleared->fresh()->toArray()['to_state']);
        $this->assertNull(json_decode((string) json_encode($cleared->fresh()), true)['to_state']);
    }

    /** @test */
    public function a_cleared_event_is_still_append_only(): void
    {
        $event = ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Save->value,
            'to_state'   => null,
        ]));

        $this->expectException(\LogicException::class);
        $event->delete();
    }

    /**
     * ROLLBACK SAFETY, the half that protects history.
     *
     * down() must refuse while clear events exist rather than invent a
     * preference for them or delete append-only rows.
     *
     * @test
     */
    public function rollback_refuses_while_clear_events_exist(): void
    {
        ListingPreferenceEvent::create($this->row([
            'from_state' => ListingPreferenceState::Save->value,
            'to_state'   => null,
        ]));

        try {
            $this->migration()->down();
            $this->fail('down() destroyed or ignored clear-event history instead of refusing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing to restore NOT NULL', $e->getMessage());
            $this->assertStringContainsString('clear event', $e->getMessage());
        }

        // The refusal changed nothing: the column is still nullable and the
        // history is intact.
        $this->assertSame(1, ListingPreferenceEvent::whereNull('to_state')->count());
        $this->assertNull(ListingPreferenceEvent::whereNull('to_state')->first()->to_state);
    }

    /**
     * ROLLBACK SAFETY, the half that must still work.
     *
     * The realistic rollback — a deploy reverted before anyone used the feature
     * — restores NOT NULL normally.
     *
     * @test
     */
    public function rollback_succeeds_when_no_clear_events_exist(): void
    {
        DB::table('listing_preference_events')->whereNull('to_state')->delete();
        $this->assertSame(0, ListingPreferenceEvent::whereNull('to_state')->count());

        $this->migration()->down();

        // NOT NULL is back: a clear can no longer be recorded.
        $refused = false;
        try {
            DB::table('listing_preference_events')->insert($this->row([
                'from_state' => 'save',
                'to_state'   => null,
            ]) + ['created_at' => now()]);
        } catch (QueryException) {
            $refused = true;
        }

        // Restore the column before other tests in this process see it, whatever
        // the assertion outcome.
        $this->migration()->up();

        $this->assertTrue($refused, 'down() did not actually restore the NOT NULL constraint.');
    }

    /**
     * A fresh instance of the Phase 2 migration.
     *
     * Laravel 8 migrations DECLARE a class rather than returning an instance, so
     * `require`-ing the file twice in one process is a fatal redeclaration.
     */
    private function migration(): object
    {
        $class = 'AllowNullToStateOnListingPreferenceEvents';

        if (! class_exists($class, false)) {
            require_once database_path(
                'migrations/2026_09_17_000001_allow_null_to_state_on_listing_preference_events.php'
            );
        }

        return new $class();
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'user_id'      => 77001,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 5501,
            'subject_key'  => 'mls:stellar_bridge:CLEAR-TEST',
            'from_state'   => null,
            'to_state'     => ListingPreferenceState::Save->value,
            'surface'      => ListingPreferenceEvent::SURFACE_DETAIL,
            'created_at'   => now(),
        ], $overrides);
    }
}

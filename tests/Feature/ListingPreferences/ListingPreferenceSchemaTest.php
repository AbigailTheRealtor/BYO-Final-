<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\ListingPreferenceReason;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * The listing preference schema, under the project's SQLite test database.
 */
class ListingPreferenceSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function the_three_phase_one_tables_exist_with_their_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('listing_preferences', [
            'user_id', 'seeker_role', 'listing_type', 'listing_id', 'subject_key',
            'state', 'state_set_at', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('listing_preference_reasons', [
            'listing_preference_id', 'reason_key', 'dimension', 'smart_tag_key', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('listing_preference_events', [
            'user_id', 'seeker_role', 'listing_type', 'listing_id', 'subject_key',
            'from_state', 'to_state', 'reasons_json', 'surface', 'created_at',
        ]));

        // Append-only: no updated_at, the same shape as smart_tag_manual_events.
        $this->assertFalse(Schema::hasColumn('listing_preference_events', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('listing_preference_reasons', 'updated_at'));
    }

    /**
     * The constraint that makes Save → Maybe → Pass an update rather than a pile
     * of contradictions.
     *
     * @test
     */
    public function one_customer_cannot_hold_two_current_states_for_one_subject(): void
    {
        $row = [
            'user_id'      => 4242,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99001,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-1',
            'state'        => ListingPreferenceState::Save->value,
            'state_set_at' => now(),
        ];

        ListingPreference::create($row);

        $this->expectException(QueryException::class);
        ListingPreference::create(array_merge($row, ['state' => ListingPreferenceState::Pass->value]));
    }

    /**
     * Buying and renting are different intents about different inventory: a Pass
     * on a rental must not suppress a purchase.
     *
     * @test
     */
    public function buyer_and_tenant_preferences_on_one_subject_coexist(): void
    {
        $base = [
            'listing_type' => 'bridge',
            'listing_id'   => 99002,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-2',
            'state_set_at' => now(),
        ];

        $buyer = ListingPreference::create($base + [
            'user_id'     => 4243,
            'seeker_role' => SeekerRole::Buyer->value,
            'state'       => ListingPreferenceState::Save->value,
        ]);
        $tenant = ListingPreference::create($base + [
            'user_id'     => 4243,
            'seeker_role' => SeekerRole::Tenant->value,
            'state'       => ListingPreferenceState::Pass->value,
        ]);

        $this->assertNotSame($buyer->id, $tenant->id);
        $this->assertSame(2, ListingPreference::where('subject_key', 'mls:stellar_bridge:SCHEMA-TEST-2')->count());
    }

    /**
     * The same MLS property expressed through two different listings is ONE
     * subject — the duplicated-feedback collision, refused at the database.
     *
     * @test
     */
    public function one_subject_reached_through_two_listings_is_still_one_preference(): void
    {
        ListingPreference::create([
            'user_id'      => 4244,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99003,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-3',
            'state'        => ListingPreferenceState::Pass->value,
            'state_set_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Same customer, same property, reached as the imported BYO listing.
        ListingPreference::create([
            'user_id'      => 4244,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'seller_agent',
            'listing_id'   => 555,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-3',
            'state'        => ListingPreferenceState::Save->value,
            'state_set_at' => now(),
        ]);
    }

    /** @test */
    public function a_reason_cannot_be_recorded_twice_against_one_preference(): void
    {
        $preference = ListingPreference::create([
            'user_id'      => 4245,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99004,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-4',
            'state'        => ListingPreferenceState::Save->value,
            'state_set_at' => now(),
        ]);

        $reason = [
            'listing_preference_id' => $preference->id,
            'reason_key'            => 'natural_light',
            'dimension'             => 'smart_tag',
            'smart_tag_key'         => 'natural_light',
            'created_at'            => now(),
        ];

        ListingPreferenceReason::create($reason);

        $this->expectException(QueryException::class);
        ListingPreferenceReason::create($reason);
    }

    /** @test */
    public function reasons_are_reachable_from_their_preference(): void
    {
        $preference = ListingPreference::create([
            'user_id'      => 4246,
            'seeker_role'  => SeekerRole::Tenant->value,
            'listing_type' => 'landlord_agent',
            'listing_id'   => 99005,
            'subject_key'  => 'byo:landlord_agent:99005',
            'state'        => ListingPreferenceState::Maybe->value,
            'state_set_at' => now(),
        ]);

        ListingPreferenceReason::create([
            'listing_preference_id' => $preference->id,
            'reason_key'            => 'price',
            'dimension'             => 'criteria',
            'smart_tag_key'         => null,
            'created_at'            => now(),
        ]);

        $this->assertSame(['price'], $preference->fresh()->reasons->pluck('reason_key')->all());
    }

    /**
     * The history is what recency weighting, decay, undo and Fair Housing
     * auditability all read. A mutable history answers none of them honestly.
     *
     * @test
     */
    public function preference_events_are_append_only(): void
    {
        $event = ListingPreferenceEvent::create([
            'user_id'      => 4247,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99006,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-6',
            'from_state'   => ListingPreferenceState::Save->value,
            'to_state'     => ListingPreferenceState::Pass->value,
            'reasons_json' => ['too_expensive'],
            'surface'      => ListingPreferenceEvent::SURFACE_RESULTS,
            'created_at'   => now(),
        ]);

        $this->assertSame(['too_expensive'], $event->fresh()->reasons_json);

        try {
            $event->to_state = ListingPreferenceState::Maybe->value;
            $event->save();
            $this->fail('A preference event was updated.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $event->delete();
            $this->fail('A preference event was deleted.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Contradictory CURRENT states cannot stack, but the transitions that
     * produced them all remain.
     *
     * @test
     */
    public function many_events_may_describe_one_subject_over_time(): void
    {
        $base = [
            'user_id'      => 4248,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99007,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-7',
            'surface'      => ListingPreferenceEvent::SURFACE_DETAIL,
        ];

        ListingPreferenceEvent::create($base + ['from_state' => null, 'to_state' => 'save', 'created_at' => now()->subDays(2)]);
        ListingPreferenceEvent::create($base + ['from_state' => 'save', 'to_state' => 'maybe', 'created_at' => now()->subDay()]);
        ListingPreferenceEvent::create($base + ['from_state' => 'maybe', 'to_state' => 'pass', 'created_at' => now()]);

        $this->assertSame(3, ListingPreferenceEvent::where('subject_key', 'mls:stellar_bridge:SCHEMA-TEST-7')->count());
    }

    /**
     * Deleting a current state must never erase the record that it existed —
     * the events table deliberately does not cascade.
     *
     * @test
     */
    public function deleting_a_preference_removes_its_reasons_but_never_its_history(): void
    {
        $preference = ListingPreference::create([
            'user_id'      => 4249,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99008,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-8',
            'state'        => ListingPreferenceState::Pass->value,
            'state_set_at' => now(),
        ]);

        ListingPreferenceReason::create([
            'listing_preference_id' => $preference->id,
            'reason_key'            => 'too_expensive',
            'dimension'             => 'criteria',
            'created_at'            => now(),
        ]);

        ListingPreferenceEvent::create([
            'user_id'      => 4249,
            'seeker_role'  => SeekerRole::Buyer->value,
            'listing_type' => 'bridge',
            'listing_id'   => 99008,
            'subject_key'  => 'mls:stellar_bridge:SCHEMA-TEST-8',
            'from_state'   => null,
            'to_state'     => ListingPreferenceState::Pass->value,
            'created_at'   => now(),
        ]);

        $preferenceId = $preference->id;
        $preference->delete();

        $this->assertSame(0, ListingPreferenceReason::where('listing_preference_id', $preferenceId)->count());
        $this->assertSame(1, ListingPreferenceEvent::where('subject_key', 'mls:stellar_bridge:SCHEMA-TEST-8')->count());
    }
}

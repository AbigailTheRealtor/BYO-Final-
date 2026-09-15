<?php

namespace Tests\Feature\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * The shared Smart Tag schema, under the project's SQLite test database.
 */
class SmartTagSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function the_four_phase_one_tables_exist_and_preferences_is_not_created_yet(): void
    {
        $this->assertTrue(Schema::hasColumns('smart_tag_evidence', [
            'listing_type', 'listing_id', 'tag_key', 'context', 'source', 'state', 'confidence',
            'source_field', 'rule_id', 'set_by_user_id', 'tagger_version',
        ]));
        $this->assertTrue(Schema::hasColumns('smart_tag_assignments', [
            'listing_type', 'listing_id', 'tag_key', 'context', 'state', 'winning_source', 'has_conflict', 'conflict_tags', 'resolved_at',
        ]));
        $this->assertTrue(Schema::hasColumns('smart_tag_derivation_states', [
            'listing_type', 'listing_id', 'context', 'structured_inputs_hash', 'mls_remarks_hash', 'native_description_hash', 'tagger_version', 'derived_at',
        ]));
        $this->assertTrue(Schema::hasColumns('smart_tag_manual_events', [
            'listing_type', 'listing_id', 'tag_key', 'action', 'actor_user_id', 'actor_role', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('smart_tag_manual_events', 'updated_at'));

        $this->assertFalse(Schema::hasTable('smart_tag_preferences'), 'Preferences are reserved for the later Buyer/Tenant phase');
    }

    /** @test */
    public function one_source_cannot_hold_two_evidence_rows_for_the_same_listing_and_tag(): void
    {
        $row = [
            'listing_type' => 'seller_agent', 'listing_id' => 7, 'tag_key' => 'garage', 'context' => 'residential.sale',
            'source' => 'structured_native_listing', 'state' => 'present', 'confidence' => 95,
        ];
        SmartTagEvidence::query()->create($row);

        // A different source for the same tag is legitimate.
        SmartTagEvidence::query()->create(array_merge($row, ['source' => 'manual_listing_owner']));

        $this->expectException(QueryException::class);
        SmartTagEvidence::query()->create($row);
    }

    /** @test */
    public function duplicate_canonical_assignments_cannot_be_created(): void
    {
        $row = [
            'listing_type' => 'bridge', 'listing_id' => 9, 'tag_key' => 'private_pool', 'context' => 'residential.sale',
            'state' => 'present', 'winning_source' => 'structured_mls',
        ];
        SmartTagAssignment::query()->create($row);

        $this->expectException(QueryException::class);
        SmartTagAssignment::query()->create(array_merge($row, ['winning_source' => 'manual_listing_owner']));
    }

    /** @test */
    public function the_same_tag_on_different_listing_types_with_the_same_id_does_not_collide(): void
    {
        foreach (['bridge', 'seller_agent', 'landlord_agent'] as $type) {
            SmartTagAssignment::query()->create([
                'listing_type' => $type, 'listing_id' => 42, 'tag_key' => 'waterfront',
                'context' => $type === 'landlord_agent' ? 'residential.lease' : 'residential.sale',
                'state' => 'present', 'winning_source' => 'structured_mls',
            ]);
        }

        $this->assertSame(3, SmartTagAssignment::query()->where('listing_id', 42)->count());
    }

    /** @test */
    public function manual_events_are_append_only(): void
    {
        $event = SmartTagManualEvent::query()->create([
            'listing_type' => 'seller_agent', 'listing_id' => 1, 'tag_key' => 'garage',
            'action' => 'selected', 'actor_user_id' => 1, 'actor_role' => 'listing_owner', 'created_at' => now(),
        ]);

        try {
            $event->update(['action' => 'deselected']);
            $this->fail('An event row was updated');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }
}

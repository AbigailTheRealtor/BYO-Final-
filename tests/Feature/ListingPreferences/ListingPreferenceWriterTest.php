<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\ListingPreferenceReason;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceMissing;
use App\Services\ListingPreferences\ListingPreferenceSubjectUnresolvable;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The shared write service: state, reasons, clear — and the invariants that
 * make it safe to call from every surface.
 */
class ListingPreferenceWriterTest extends TestCase
{
    use DatabaseTransactions;

    private ListingPreferenceWriter $writer;

    private const USER = 88001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = app(ListingPreferenceWriter::class);
    }

    // ── state ───────────────────────────────────────────────────────────────

    /** @test */
    public function each_state_creates_exactly_one_current_preference(): void
    {
        foreach ([ListingPreferenceState::Save, ListingPreferenceState::Maybe, ListingPreferenceState::Pass] as $i => $state) {
            $listing = $this->sellerListing();
            $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

            $outcome = $this->writer->setState(self::USER + $i, SeekerRole::Buyer, $ref, $state);

            $this->assertSame($state, $outcome->state);
            $this->assertSame(1, ListingPreference::where('user_id', self::USER + $i)->count());
            $this->assertSame($state->value, ListingPreference::where('user_id', self::USER + $i)->first()->state);
        }
    }

    /** @test */
    public function save_then_maybe_then_pass_updates_the_same_current_row(): void
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save);
        $firstId = ListingPreference::where('user_id', self::USER)->first()->id;

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Maybe);
        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Pass);

        $rows = ListingPreference::where('user_id', self::USER)->get();

        $this->assertCount(1, $rows, 'contradictory current states must never stack');
        $this->assertSame($firstId, $rows->first()->id, 'the same row is updated, not replaced');
        $this->assertSame('pass', $rows->first()->state);

        // …and every transition is still on the timeline.
        $events = ListingPreferenceEvent::where('user_id', self::USER)->orderBy('id')->get();
        $this->assertCount(3, $events);
        $this->assertSame([null, 'save', 'maybe'], $events->pluck('from_state')->all());
        $this->assertSame(['save', 'maybe', 'pass'], $events->pluck('to_state')->all());
    }

    /** @test */
    public function a_duplicate_request_does_not_create_a_duplicate_current_row(): void
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save);
        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save);

        $this->assertSame(1, ListingPreference::where('user_id', self::USER)->count());
    }

    /** @test */
    public function buyer_and_tenant_preferences_on_one_subject_are_independent(): void
    {
        $bridge = $this->bridgeListing('WRITER-ROLES');
        $ref    = new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save);
        $this->writer->setState(self::USER, SeekerRole::Tenant, $ref, ListingPreferenceState::Pass);

        $rows = ListingPreference::where('user_id', self::USER)->get()->keyBy('seeker_role');

        $this->assertCount(2, $rows);
        $this->assertSame('save', $rows['buyer']->state);
        $this->assertSame('pass', $rows['tenant']->state);
    }

    /** @test */
    public function one_users_preference_is_invisible_to_another(): void
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save);
        $this->writer->setState(self::USER + 1, SeekerRole::Buyer, $ref, ListingPreferenceState::Pass);

        $this->assertSame('save', ListingPreference::where('user_id', self::USER)->first()->state);
        $this->assertSame('pass', ListingPreference::where('user_id', self::USER + 1)->first()->state);
    }

    // ── durable identity ────────────────────────────────────────────────────

    /**
     * THE collision the subject key exists to prevent, end to end.
     *
     * @test
     */
    public function the_bridge_row_and_its_canonical_byo_listing_are_one_preference(): void
    {
        $bridge  = $this->bridgeListing('WRITER-SHARED');
        $listing = $this->sellerListing();
        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $listing->id,
            'meta_key'                => MlsQuickImportDraftWriter::META_LISTING_KEY,
            'meta_value'              => 'WRITER-SHARED',
        ]);

        $bridgeRef = new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id);
        $byoRef    = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $a = $this->writer->setState(self::USER, SeekerRole::Buyer, $bridgeRef, ListingPreferenceState::Pass);
        $b = $this->writer->setState(self::USER, SeekerRole::Buyer, $byoRef, ListingPreferenceState::Save);

        $this->assertSame($a->subject->subjectKey, $b->subject->subjectKey);
        $this->assertSame('mls:WRITER-SHARED', $b->subject->subjectKey);

        $rows = ListingPreference::where('user_id', self::USER)->get();
        $this->assertCount(1, $rows, 'one property must not yield two current preferences');
        $this->assertSame('save', $rows->first()->state);

        // The acted-on reference follows the listing actually used.
        $this->assertSame('seller_agent', $rows->first()->listing_type);
        $this->assertSame($listing->id, (int) $rows->first()->listing_id);
    }

    /** @test */
    public function a_listing_with_no_durable_identity_is_refused(): void
    {
        $bridge = BridgeProperty::create([
            'listing_id' => 'NOKEY', 'standard_status' => 'Active', 'property_type' => 'Residential',
        ]);

        $this->expectException(ListingPreferenceSubjectUnresolvable::class);

        $this->writer->setState(
            self::USER,
            SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, $bridge->id),
            ListingPreferenceState::Save,
        );
    }

    // ── reasons ─────────────────────────────────────────────────────────────

    /** @test */
    public function multiple_valid_reasons_are_stored_with_their_dimension_and_tag(): void
    {
        $ref = $this->savedSellerListing();

        $outcome = $this->writer->updateReasons(
            self::USER, SeekerRole::Buyer, $ref,
            ['updated_kitchen', 'natural_light', 'price'],
        );

        $this->assertSame(['updated_kitchen', 'natural_light', 'price'], $outcome->reasons);

        $stored = ListingPreferenceReason::query()
            ->whereIn('reason_key', ['updated_kitchen', 'natural_light', 'price'])
            ->get()->keyBy('reason_key');

        $this->assertSame('smart_tag', $stored['updated_kitchen']->dimension);
        $this->assertSame('updated_kitchen', $stored['updated_kitchen']->smart_tag_key);
        $this->assertSame('criteria', $stored['price']->dimension);
        $this->assertNull($stored['price']->smart_tag_key, 'a criteria reason is never a Smart Tag');
    }

    /** @test */
    public function reasons_replace_atomically_rather_than_accumulating(): void
    {
        $ref = $this->savedSellerListing();

        $this->writer->updateReasons(self::USER, SeekerRole::Buyer, $ref, ['updated_kitchen', 'natural_light']);
        $this->writer->updateReasons(self::USER, SeekerRole::Buyer, $ref, ['waterfront']);

        $preference = ListingPreference::where('user_id', self::USER)->first();

        $this->assertSame(
            ['waterfront'],
            ListingPreferenceReason::where('listing_preference_id', $preference->id)->pluck('reason_key')->all()
        );
    }

    /** @test */
    public function changing_state_does_not_carry_the_previous_states_reasons(): void
    {
        $ref = $this->savedSellerListing();
        $this->writer->updateReasons(self::USER, SeekerRole::Buyer, $ref, ['updated_kitchen', 'natural_light']);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Pass);

        $preference = ListingPreference::where('user_id', self::USER)->first();

        $this->assertSame(
            [],
            ListingPreferenceReason::where('listing_preference_id', $preference->id)->pluck('reason_key')->all(),
            '"Updated kitchen" is not an answer to "Why isn\'t this one for you?"'
        );
    }

    /** @test */
    public function a_reason_incompatible_with_the_new_state_is_refused(): void
    {
        $ref = $this->savedSellerListing();

        // too_expensive is offered for maybe/pass, never for save.
        $outcome = $this->writer->setState(
            self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save,
            ['too_expensive', 'updated_kitchen'],
        );

        $this->assertSame(['updated_kitchen'], $outcome->reasons);
        $this->assertArrayHasKey('too_expensive', $outcome->rejected);
    }

    /** @test */
    public function invalid_and_non_seeker_selectable_reasons_are_refused(): void
    {
        $ref = $this->savedSellerListing();

        $outcome = $this->writer->updateReasons(
            self::USER, SeekerRole::Buyer, $ref,
            ['not_a_reason', 'accessible_features', 'playground', 'No families with children', 'updated_kitchen'],
        );

        $this->assertSame(['updated_kitchen'], $outcome->reasons);
        foreach (['not_a_reason', 'accessible_features', 'playground', 'No families with children'] as $refused) {
            $this->assertArrayHasKey($refused, $outcome->rejected, "{$refused} must be refused");
        }

        $this->assertSame(
            0,
            ListingPreferenceReason::whereIn('reason_key', ['accessible_features', 'playground'])->count()
        );
    }

    /** @test */
    public function reasons_without_a_current_preference_are_refused(): void
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $this->expectException(ListingPreferenceMissing::class);

        $this->writer->updateReasons(self::USER, SeekerRole::Buyer, $ref, ['updated_kitchen']);
    }

    // ── context ─────────────────────────────────────────────────────────────

    /**
     * With a resolvable context the strict path runs: a residential tag is
     * accepted on a residential listing and refused on land.
     *
     * @test
     */
    public function real_listing_context_is_used_when_it_can_be_resolved(): void
    {
        $residential = $this->sellerListing('Residential');
        $land        = $this->sellerListing('Vacant Land');

        $onResidential = $this->writer->setState(
            self::USER, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $residential->id),
            ListingPreferenceState::Save, ['updated_kitchen'],
        );

        $onLand = $this->writer->setState(
            self::USER, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $land->id),
            ListingPreferenceState::Save, ['updated_kitchen'],
        );

        $this->assertTrue($onResidential->hadContext());
        $this->assertSame(['updated_kitchen'], $onResidential->reasons);

        $this->assertTrue($onLand->hadContext());
        $this->assertSame([], $onLand->reasons, 'a kitchen tag does not apply to vacant land');
        $this->assertArrayHasKey('updated_kitchen', $onLand->rejected);
    }

    /**
     * The fallback is reached only when the listing genuinely cannot answer —
     * here, a listing with no property type at all.
     *
     * @test
     */
    public function the_null_context_fallback_applies_only_when_context_is_unresolvable(): void
    {
        $listing = $this->sellerListing(propertyType: null);
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $outcome = $this->writer->setState(
            self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save,
            ['updated_kitchen', 'accessible_features'],
        );

        $this->assertFalse($outcome->hadContext(), 'this listing has no resolvable context');

        // Applicability relaxed…
        $this->assertSame(['updated_kitchen'], $outcome->reasons);
        // …but the Fair Housing gate is NOT.
        $this->assertArrayHasKey('accessible_features', $outcome->rejected);
    }

    // ── clear ───────────────────────────────────────────────────────────────

    /** @test */
    public function clear_removes_the_current_preference_and_its_reasons_but_keeps_history(): void
    {
        $ref = $this->savedSellerListing();
        $this->writer->updateReasons(self::USER, SeekerRole::Buyer, $ref, ['updated_kitchen']);

        $preferenceId = ListingPreference::where('user_id', self::USER)->first()->id;

        $outcome = $this->writer->clear(self::USER, SeekerRole::Buyer, $ref);

        $this->assertTrue($outcome->wasCleared());
        $this->assertNull($outcome->state);
        $this->assertSame(0, ListingPreference::where('user_id', self::USER)->count());
        $this->assertSame(0, ListingPreferenceReason::where('listing_preference_id', $preferenceId)->count());

        // History survives, and the clear is on it.
        $events = ListingPreferenceEvent::where('user_id', self::USER)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $events->count());

        $last = $events->last();
        $this->assertTrue($last->wasCleared());
        $this->assertNull($last->to_state);
        $this->assertSame('save', $last->from_state, 'the clear remembers what was cleared');
    }

    /** @test */
    public function clearing_nothing_writes_no_event(): void
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $outcome = $this->writer->clear(self::USER, SeekerRole::Buyer, $ref);

        $this->assertTrue($outcome->wasCleared());
        $this->assertSame(0, ListingPreferenceEvent::where('user_id', self::USER)->count());
    }

    /** @test */
    public function a_preference_can_be_set_again_after_being_cleared(): void
    {
        $ref = $this->savedSellerListing();
        $this->writer->clear(self::USER, SeekerRole::Buyer, $ref);
        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Maybe);

        $this->assertSame(1, ListingPreference::where('user_id', self::USER)->count());
        $this->assertSame('maybe', ListingPreference::where('user_id', self::USER)->first()->state);

        $events = ListingPreferenceEvent::where('user_id', self::USER)->orderBy('id')->get();
        $this->assertNull($events[1]->to_state, 'the clear');
        $this->assertNull($events[2]->from_state, 'and the new preference starts from nothing again');
        $this->assertSame('maybe', $events[2]->to_state);
    }

    // ── history shape ───────────────────────────────────────────────────────

    /** @test */
    public function every_event_records_the_acted_on_listing_the_subject_and_the_surface(): void
    {
        $ref = $this->savedSellerListing('detail');

        $event = ListingPreferenceEvent::where('user_id', self::USER)->first();

        $this->assertSame('seller_agent', $event->listing_type);
        $this->assertSame($ref->id, (int) $event->listing_id);
        $this->assertSame("byo:seller_agent:{$ref->id}", $event->subject_key);
        $this->assertSame('detail', $event->surface);
        $this->assertSame('buyer', $event->seeker_role);
    }

    /** @test */
    public function one_reason_update_produces_exactly_one_event(): void
    {
        $ref = $this->savedSellerListing();
        $before = ListingPreferenceEvent::where('user_id', self::USER)->count();

        $this->writer->updateReasons(
            self::USER, SeekerRole::Buyer, $ref,
            ['updated_kitchen', 'natural_light', 'open_floor_plan', 'move_in_ready'],
        );

        $this->assertSame(
            $before + 1,
            ListingPreferenceEvent::where('user_id', self::USER)->count(),
            'four chips must not mean four history events'
        );

        $last = ListingPreferenceEvent::where('user_id', self::USER)->orderByDesc('id')->first();
        $this->assertSame(
            ['updated_kitchen', 'natural_light', 'open_floor_plan', 'move_in_ready'],
            $last->reasons_json,
            'the event carries the whole snapshot'
        );
        $this->assertSame('save', $last->from_state);
        $this->assertSame('save', $last->to_state, 'a reason revision does not move the state');
    }

    /** @test */
    public function the_writer_never_modifies_the_listing(): void
    {
        $listing = $this->sellerListing();

        // The stored ROW, not the hydrated model: toArray() includes a dynamic
        // accessor object whose identity differs between instances and would
        // make this assertion fail for a reason that is not about the data.
        $row  = fn () => (array) \DB::table('seller_agent_auctions')->where('id', $listing->id)->first();
        $meta = fn () => \DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', $listing->id)
            ->orderBy('meta_key')->get(['meta_key', 'meta_value'])->toArray();

        $beforeRow  = $row();
        $beforeMeta = $meta();

        $ref = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);
        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Pass, ['too_expensive']);
        $this->writer->clear(self::USER, SeekerRole::Buyer, $ref);

        $this->assertEquals($beforeRow, $row(), 'Pass is preference data, never a listing change');
        $this->assertEquals($beforeMeta, $meta(), 'and it never writes listing meta');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function sellerListing(?string $propertyType = 'Residential'): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900100,
            'address' => '9 Writer Way, St. Petersburg, FL 33701',
        ]);

        if ($propertyType !== null) {
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => 'property_type',
                'meta_value'              => $propertyType,
            ]);
        }

        return $auction;
    }

    private function bridgeListing(string $listingKey): BridgeProperty
    {
        return BridgeProperty::create([
            'listing_key'     => $listingKey,
            'listing_id'      => $listingKey,
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
        ]);
    }

    /** A seller listing with a Save already recorded, returned as its ref. */
    private function savedSellerListing(?string $surface = null): SmartTagListingRef
    {
        $listing = $this->sellerListing();
        $ref     = new SmartTagListingRef(SmartTagListingType::SellerAgent, $listing->id);

        $this->writer->setState(self::USER, SeekerRole::Buyer, $ref, ListingPreferenceState::Save, [], $surface);

        return $ref;
    }
}

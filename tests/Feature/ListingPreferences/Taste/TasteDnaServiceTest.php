<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\SmartTagAssignment;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\Taste\TasteDnaService;
use App\Services\ListingPreferences\Taste\TasteEvidenceReader;
use App\Services\ListingPreferences\Taste\TasteListingFactsReader;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteConfidence;
use App\Support\ListingPreferences\Taste\TasteDimension;
use App\Support\ListingPreferences\Taste\TasteDirection;
use App\Support\ListingPreferences\Taste\TasteObservationPresenter;
use App\Support\ListingPreferences\Taste\TasteSource;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Taste DNA end to end against the real tables: every choice here is written
 * through ListingPreferenceWriter, so the history the learner reads is exactly
 * what real clicks produce.
 */
class TasteDnaServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.taste_dna_enabled', true);
    }

    /** @test */
    public function saves_with_a_stated_reason_become_a_positive_pattern(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        $signal = $this->profile($user)->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Positive, $signal->direction);
        $this->assertSame(TasteConfidence::Established, $signal->confidence);
        $this->assertSame(3, $signal->saveCount);
    }

    /** @test */
    public function passes_become_a_negative_pattern_and_structured_criteria_reasons_count(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Pass, ['needs_complete_update', 'too_expensive']);
        }

        $profile = $this->profile($user);

        $this->assertSame(TasteDirection::Negative, $profile->signal(TasteDimension::SmartTag, 'needs_complete_update')->direction);
        $this->assertSame(TasteDirection::Negative, $profile->signal(TasteDimension::Reason, 'too_expensive')->direction);
    }

    /** @test */
    public function clearing_a_choice_keeps_it_in_the_evidence_without_letting_it_stand(): void
    {
        $user     = $this->buyer();
        $listings = $this->listings(3);

        foreach ($listings as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }
        foreach ($listings as $listing) {
            app(ListingPreferenceWriter::class)->clear((int) $user->id, SeekerRole::Buyer, $this->ref($listing));
        }

        $this->assertSame(0, ListingPreference::where('user_id', $user->id)->count(), 'the current rows are gone');

        $signal = $this->profile($user)->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(3, $signal->saveCount, 'the history still says they were saved');
        $this->assertSame(TasteConfidence::Insufficient, $signal->confidence);
    }

    /** @test */
    public function buyer_and_tenant_histories_never_mix(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }
        foreach ($this->listings(3) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Pass, ['garage'], SeekerRole::Tenant);
        }

        $buyer  = $this->profile($user, SeekerRole::Buyer);
        $tenant = $this->profile($user, SeekerRole::Tenant);

        $this->assertNotNull($buyer->signal(TasteDimension::SmartTag, 'natural_light'));
        $this->assertNull($buyer->signal(TasteDimension::SmartTag, 'garage'));
        $this->assertNotNull($tenant->signal(TasteDimension::SmartTag, 'garage'));
        $this->assertNull($tenant->signal(TasteDimension::SmartTag, 'natural_light'));
    }

    /** @test */
    public function one_customers_choices_never_reach_another_customers_profile(): void
    {
        $alice = $this->buyer();
        $bob   = $this->buyer();

        foreach ($this->listings(4) as $listing) {
            $this->choose($alice, $listing, ListingPreferenceState::Save, ['natural_light']);
            $this->choose($bob, $listing, ListingPreferenceState::Pass, ['natural_light']);
        }

        $this->assertSame(TasteDirection::Positive, $this->profile($alice)->signal(TasteDimension::SmartTag, 'natural_light')->direction);
        $this->assertSame(TasteDirection::Negative, $this->profile($bob)->signal(TasteDimension::SmartTag, 'natural_light')->direction);
        $this->assertSame(4, $this->profile($alice)->signal(TasteDimension::SmartTag, 'natural_light')->supportCount);
    }

    /** @test */
    public function rebuilding_from_the_same_history_yields_identical_output_and_writes_nothing(): void
    {
        $user = $this->buyer();
        [$a, $b, $c, $d] = $this->listings(4);

        $this->choose($user, $a, ListingPreferenceState::Save, ['natural_light', 'garage']);
        $this->choose($user, $b, ListingPreferenceState::Save, ['natural_light']);
        $this->choose($user, $c, ListingPreferenceState::Maybe, ['private_pool']);
        $this->choose($user, $d, ListingPreferenceState::Pass, ['too_expensive']);
        $this->choose($user, $c, ListingPreferenceState::Pass, ['needs_complete_update']);

        $before = $this->tableCounts();

        $first  = $this->profile($user)->toArray();
        $second = app(TasteDnaService::class)->profileFor((int) $user->id, SeekerRole::Buyer)->toArray();

        $this->assertSame($first, $second);
        $this->assertSame($before, $this->tableCounts(), 'deriving is read-only');
    }

    /** @test */
    public function resolved_listing_tags_are_learned_but_excluded_tags_never_are(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(4) as $listing) {
            foreach (['private_pool', 'accessible_features', 'playground'] as $tag) {
                SmartTagAssignment::create([
                    'listing_type' => 'seller_agent', 'listing_id' => $listing->id, 'tag_key' => $tag,
                    'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_native_listing',
                ]);
            }
            // An ABSENT row is not evidence of anything.
            SmartTagAssignment::create([
                'listing_type' => 'seller_agent', 'listing_id' => $listing->id, 'tag_key' => 'garage',
                'context' => 'residential.sale', 'state' => 'absent', 'winning_source' => 'structured_native_listing',
            ]);

            $this->choose($user, $listing, ListingPreferenceState::Save, []);
        }

        $profile = $this->profile($user);
        $pool    = $profile->signal(TasteDimension::SmartTag, 'private_pool');

        $this->assertSame([TasteSource::ListingCharacteristic], $pool->sources);
        $this->assertSame(TasteDirection::Positive, $pool->direction);
        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'accessible_features'));
        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'playground'));
        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'garage'));
    }

    /** @test */
    public function structured_facts_are_read_from_the_listing_and_other_free_text_is_not(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $i => $listing) {
            foreach ([
                'bedrooms'              => (string) (3 + $i % 2),
                'bathrooms'             => '2',
                'minimum_heated_square' => '1,850',
                'total_acreage'         => '0.25',
                'property_items'        => json_encode(['Single Family Residence', 'Other']),
                'other_property_items'  => 'Great family neighborhood',
            ] as $key => $value) {
                SellerAgentAuctionMeta::create(['seller_agent_auction_id' => $listing->id, 'meta_key' => $key, 'meta_value' => $value]);
            }

            $this->choose($user, $listing, ListingPreferenceState::Save, []);
        }

        $profile = $this->profile($user);

        $this->assertSame([3.0, 4.0], [
            $profile->signal(TasteDimension::Bedrooms, 'bedrooms')->saveBand->low,
            $profile->signal(TasteDimension::Bedrooms, 'bedrooms')->saveBand->high,
        ]);
        $this->assertSame(1900.0, $profile->signal(TasteDimension::LivingArea, 'living_area')->saveBand->low);
        $this->assertSame(0.25, $profile->signal(TasteDimension::LotSize, 'lot_size')->saveBand->low);

        $subtypes = array_values(array_map(
            static fn ($s) => $s->key,
            array_filter($profile->signals, static fn ($s) => $s->dimension === TasteDimension::PropertySubtype),
        ));
        $this->assertSame(['single family residence'], $subtypes, '"Other" and its free text are never learned');
    }

    /** @test */
    public function a_listing_the_platform_no_longer_publishes_contributes_no_facts(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            SmartTagAssignment::create([
                'listing_type' => 'seller_agent', 'listing_id' => $listing->id, 'tag_key' => 'private_pool',
                'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_native_listing',
            ]);
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
            $listing->forceFill(['is_archived' => 1])->save();
        }

        foreach ([1, 2, 3] as $i) {
            $bridge = BridgeProperty::create([
                'provider'       => 'stellar_bridge',
                'listing_key'    => "TASTE-IDX-OFF-{$i}-" . uniqid(),
                'property_type'  => 'Residential',
                'bedrooms_total' => 5,
                'raw_json'       => json_encode(['IDXParticipationYN' => false]),
            ]);
            app(ListingPreferenceWriter::class)->setState(
                (int) $user->id, SeekerRole::Buyer,
                new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id),
                ListingPreferenceState::Save, [],
            );
        }

        $profile = $this->profile($user);

        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'private_pool'), 'archived: no facts');
        $this->assertNull($profile->signal(TasteDimension::Bedrooms, 'bedrooms'), 'IDX-refused: no facts');
        $this->assertTrue($profile->signal(TasteDimension::SmartTag, 'natural_light')->isDisplayable(), 'their own words still count');
    }

    // ------------------------------------------------ truncated history

    /** @test */
    public function a_truncated_history_derives_nothing_customer_facing(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(4) as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        $cut = $this->serviceWithCeiling(3)->profileFor((int) $user->id, SeekerRole::Buyer);

        $this->assertFalse($cut->complete, 'the reader saw more history than it may read');
        $this->assertSame([], $cut->signals, 'nothing is derived from part of a history');
        $this->assertSame([], $cut->displayable());
        $this->assertSame([], TasteObservationPresenter::present($cut));

        // At exactly the ceiling the history is whole, and the pattern is there.
        $whole = $this->serviceWithCeiling(4)->profileFor((int) $user->id, SeekerRole::Buyer);

        $this->assertTrue($whole->complete);
        $this->assertSame(TasteConfidence::Established, $whole->signal(TasteDimension::SmartTag, 'natural_light')->confidence);
    }

    /** @test */
    public function a_ceiling_that_would_cut_one_homes_sequence_refuses_instead_of_flipping_it(): void
    {
        $user = $this->buyer();
        [$a, $b, $c] = $this->listings(3);

        // Three Saves, then the customer changes their mind on all three.
        foreach ([$a, $b, $c] as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }
        foreach ([$a, $b, $c] as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Pass, ['natural_light']);
        }

        $whole = $this->profile($user)->signal(TasteDimension::SmartTag, 'natural_light');
        $this->assertSame(TasteDirection::Negative, $whole->direction, 'the full history says Pass');

        // Any window that keeps only one side of those sequences would claim a
        // direction the full history does not support — so none is derived.
        foreach ([1, 3, 5] as $ceiling) {
            $cut = $this->serviceWithCeiling($ceiling)->profileFor((int) $user->id, SeekerRole::Buyer);
            $this->assertFalse($cut->complete, "ceiling {$ceiling}");
            $this->assertSame([], $cut->signals, "ceiling {$ceiling}");
        }
    }

    // --------------------------------- historical reasons, current facts

    /** @test */
    public function explicit_reasons_survive_a_listing_that_no_longer_exists_and_no_fact_is_invented(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->facts($listing, ['bedrooms' => '4', 'property_items' => json_encode(['Townhouse'])]);
            SmartTagAssignment::create([
                'listing_type' => 'seller_agent', 'listing_id' => $listing->id, 'tag_key' => 'private_pool',
                'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_native_listing',
            ]);
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);

            // Gone entirely — not archived, deleted.
            DB::table('seller_agent_auction_metas')->where('seller_agent_auction_id', $listing->id)->delete();
            DB::table('seller_agent_auctions')->where('id', $listing->id)->delete();
        }

        $profile = $this->profile($user);

        $this->assertTrue($profile->signal(TasteDimension::SmartTag, 'natural_light')->isDisplayable(), 'their own words still count');

        foreach ($profile->signals as $signal) {
            $this->assertSame([TasteSource::StatedReason], $signal->sources, "{$signal->id()} must not come from an unavailable listing");
        }
        $this->assertNull($profile->signal(TasteDimension::Bedrooms, 'bedrooms'));
        $this->assertNull($profile->signal(TasteDimension::PropertySubtype, 'townhouse'));
        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'private_pool'), 'an orphaned tag row is not a published fact');
    }

    /** @test */
    public function a_blank_or_unreadable_fact_is_omitted_not_guessed(): void
    {
        $user = $this->buyer();

        foreach ($this->listings(3) as $listing) {
            $this->facts($listing, ['bedrooms' => '', 'bathrooms' => 'two-ish', 'minimum_heated_square' => 'call agent']);
            $this->choose($user, $listing, ListingPreferenceState::Save, []);
        }

        $profile = $this->profile($user);

        $this->assertNull($profile->signal(TasteDimension::Bedrooms, 'bedrooms'));
        $this->assertNull($profile->signal(TasteDimension::Bathrooms, 'bathrooms'));
        $this->assertNull($profile->signal(TasteDimension::LivingArea, 'living_area'));
    }

    /** @test */
    public function a_changed_listing_fact_is_correlated_as_it_is_now_and_history_is_never_rewritten(): void
    {
        $user     = $this->buyer();
        $listings = $this->listings(3);

        foreach ($listings as $listing) {
            $this->facts($listing, ['bedrooms' => '3']);
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        $before  = $this->profile($user);
        $history = ListingPreferenceEvent::where('user_id', $user->id)->orderBy('id')->get()->toArray();

        $this->assertSame([3.0, 3.0], [$before->signal(TasteDimension::Bedrooms, 'bedrooms')->saveBand->low, $before->signal(TasteDimension::Bedrooms, 'bedrooms')->saveBand->high]);

        // The sellers correct their listings after the customer chose.
        foreach ($listings as $listing) {
            SellerAgentAuctionMeta::where('seller_agent_auction_id', $listing->id)->where('meta_key', 'bedrooms')->update(['meta_value' => '5']);
        }

        $after = $this->profile($user);
        $beds  = $after->signal(TasteDimension::Bedrooms, 'bedrooms');

        $this->assertSame([5.0, 5.0], [$beds->saveBand->low, $beds->saveBand->high], 'observed facts are the CURRENT canonical ones');
        $this->assertSame($after->toArray(), $this->profile($user)->toArray(), 'and deterministically so');
        $this->assertSame(
            $before->signal(TasteDimension::SmartTag, 'natural_light')->toArray(),
            $after->signal(TasteDimension::SmartTag, 'natural_light')->toArray(),
            'a stated reason is historical and does not move with the listing',
        );
        $this->assertSame($history, ListingPreferenceEvent::where('user_id', $user->id)->orderBy('id')->get()->toArray(), 'history untouched');
    }

    // ---------------------------------------------- canonical subjects

    /** @test */
    public function one_mls_home_seen_through_bridge_and_through_its_byo_listing_is_one_home(): void
    {
        $user   = $this->buyer();
        $key    = 'TASTE-DEDUP-' . uniqid();
        $bridge = $this->bridgeRow($key);
        [$seller] = $this->listings(1);
        $seller->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, $key);

        $writer = app(ListingPreferenceWriter::class);
        $writer->setState((int) $user->id, SeekerRole::Buyer, new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id), ListingPreferenceState::Save, ['natural_light']);
        $writer->setState((int) $user->id, SeekerRole::Buyer, $this->ref($seller), ListingPreferenceState::Pass, ['natural_light']);

        $subjects = ListingPreferenceEvent::where('user_id', $user->id)->pluck('subject_key')->unique()->values()->all();
        $this->assertCount(1, $subjects, 'both surfaces resolved to one durable subject');
        $this->assertStringStartsWith('mls:', $subjects[0]);

        $profile = $this->profile($user);
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(1, $profile->homeCount, 'one home, not two');
        $this->assertSame(1, $signal->supportCount);
        $this->assertSame([1, 1], [$signal->saveCount, $signal->passCount]);
        $this->assertSame(
            [0.25, 1.0],
            [$signal->positiveWeight, $signal->negativeWeight],
            'the Bridge Save is superseded by the BYO Pass on the same home',
        );
    }

    /** @test */
    public function an_ambiguous_provider_key_or_a_shared_address_never_merges_homes(): void
    {
        $user = $this->buyer();
        $key  = 'TASTE-AMBIG-' . uniqid();

        // Two providers hold this key: the BYO listing cannot say which it came
        // from, so it keeps its own byo: subject (PR #186) and stays a second home.
        $bridge = $this->bridgeRow($key);
        $this->bridgeRow($key, 'other_mls_for_taste_test');
        [$seller, $twinA, $twinB] = $this->listings(3);
        $seller->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, $key);

        // Same street address, no MLS link: never grouped by address.
        foreach ([$twinA, $twinB] as $twin) {
            $twin->forceFill(['address' => '1 Same Street, St. Petersburg, FL 33701'])->save();
        }

        $writer = app(ListingPreferenceWriter::class);
        $writer->setState((int) $user->id, SeekerRole::Buyer, new SmartTagListingRef(SmartTagListingType::Bridge, (int) $bridge->id), ListingPreferenceState::Save, ['natural_light']);
        foreach ([$seller, $twinA, $twinB] as $listing) {
            $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
        }

        $this->assertSame(4, $this->profile($user)->homeCount);
        $this->assertSame(4, $this->profile($user)->signal(TasteDimension::SmartTag, 'natural_light')->supportCount);
    }

    /** @test */
    public function the_query_count_does_not_grow_with_the_number_of_homes(): void
    {
        $measure = function (int $homes): int {
            $user = $this->buyer();
            foreach ($this->listings($homes) as $listing) {
                $this->choose($user, $listing, ListingPreferenceState::Save, ['natural_light']);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->profile($user);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $measure(2);
        $large = $measure(12);

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(4, $large, 'events + listings + meta + tags');
    }

    // ---------------------------------------------------------------- helpers

    private function serviceWithCeiling(int $ceiling): TasteDnaService
    {
        return new TasteDnaService(new TasteEvidenceReader($ceiling), app(TasteListingFactsReader::class));
    }

    /** @param array<string, string> $meta */
    private function facts(SellerAgentAuction $listing, array $meta): void
    {
        foreach ($meta as $key => $value) {
            SellerAgentAuctionMeta::create(['seller_agent_auction_id' => $listing->id, 'meta_key' => $key, 'meta_value' => $value]);
        }
    }

    private function bridgeRow(string $listingKey, string $provider = 'stellar_bridge'): BridgeProperty
    {
        return BridgeProperty::create([
            'provider'        => $provider,
            'listing_key'     => $listingKey,
            'listing_id'      => 'MLS-' . $listingKey,
            'standard_status' => 'Active',
            'property_type'   => 'Residential',
            'raw_json'        => json_encode(['ListingKey' => $listingKey]),
        ]);
    }

    private function profile(User $user, SeekerRole $role = SeekerRole::Buyer)
    {
        return app(TasteDnaService::class)->profileFor((int) $user->id, $role);
    }

    private function choose(User $user, SellerAgentAuction $listing, ListingPreferenceState $state, array $reasons, SeekerRole $role = SeekerRole::Buyer): void
    {
        app(ListingPreferenceWriter::class)->setState((int) $user->id, $role, $this->ref($listing), $state, $reasons);
    }

    private function ref(SellerAgentAuction $listing): SmartTagListingRef
    {
        return new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $listing->id);
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    /** @return list<SellerAgentAuction> */
    private function listings(int $count): array
    {
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $this->n++;
            $auction = SellerAgentAuction::create([
                'user_id'     => 903000,
                'address'     => "{$this->n} Taste Terrace, St. Petersburg, FL 33701",
                'is_approved' => 1,
                'is_draft'    => false,
                'is_archived' => 0,
            ]);
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => 'property_type',
                'meta_value'              => 'Residential',
            ]);
            $out[] = $auction;
        }

        return $out;
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        return [
            'preferences' => ListingPreference::count(),
            'events'      => ListingPreferenceEvent::count(),
            'reasons'     => DB::table('listing_preference_reasons')->count(),
            'tags'        => SmartTagAssignment::count(),
            'dna_scores'  => DB::table('dna_scores')->count(),
        ];
    }
}

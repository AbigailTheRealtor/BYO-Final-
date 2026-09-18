<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceSubjectResolver;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-3 — an MLS preference subject names the provider that issued the listing.
 *
 * THE COLLISION THIS CLOSES
 * -------------------------
 * `listing_preferences` is unique on `(user_id, seeker_role, subject_key)`. Keyed
 * on the bare `ListingKey`, one customer's Pass on Stellar's 12345 and their Save
 * on another provider's 12345 were ONE row: one silently overwrote the other, and
 * the append-only history recorded a transition that never happened.
 *
 * The second provider is written straight to the `provider` column rather than
 * added to `MlsProvider`, for the reason P0-2 gives: a speculative enum case
 * would claim the platform supports a provider that no adapter or credential
 * backs. **No Provider B configuration is created anywhere.**
 */
class ListingPreferenceProviderNamespacedIdentityTest extends TestCase
{
    use DatabaseTransactions;

    private const OTHER_PROVIDER = 'other_mls_for_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Identity is what is under test; the feature gate is asserted OFF by
        // default in its own test below and is never enabled for the others.
        config(['listing_preferences.enabled' => true]);
    }

    private function resolver(): ListingPreferenceSubjectResolver
    {
        return app(ListingPreferenceSubjectResolver::class);
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

    private function seller(?string $linkedKey = null): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'address'  => '123 Main Street',
            'is_draft' => false,
        ]);

        if ($linkedKey !== null) {
            $listing->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, $linkedKey);
        }

        return $listing;
    }

    private function landlord(?string $linkedKey = null): LandlordAgentAuction
    {
        // landlord_agent_auctions is EAV-dominant and has no `address` column —
        // the deliberate schema asymmetry, not an omission.
        $listing = LandlordAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'is_draft' => false,
        ]);

        if ($linkedKey !== null) {
            $listing->saveMeta(MlsQuickImportDraftWriter::META_LISTING_KEY, $linkedKey);
        }

        return $listing;
    }

    private function refFor(BridgeProperty $row): SmartTagListingRef
    {
        return new SmartTagListingRef(SmartTagListingType::Bridge, $row->id);
    }

    // ─── 1 & 2: provider namespacing ─────────────────────────────────────────

    /** @test */
    public function a_stellar_listing_produces_a_provider_namespaced_subject(): void
    {
        $subject = $this->resolver()->resolve($this->refFor($this->bridgeRow('12345')));

        $this->assertNotNull($subject);
        $this->assertSame('mls:stellar_bridge:12345', $subject->subjectKey);
        $this->assertSame(MlsProvider::StellarBridge, $subject->mlsProvider());
        $this->assertSame('12345', $subject->mlsListingKey());
    }

    /** @test */
    public function another_provider_with_the_same_listing_key_produces_a_different_subject(): void
    {
        $stellar = $this->resolver()->resolve($this->refFor($this->bridgeRow('12345')));
        $other   = $this->bridgeRow('12345', self::OTHER_PROVIDER);

        $this->assertNotNull($stellar);
        $this->assertSame('mls:stellar_bridge:12345', $stellar->subjectKey);

        // The foreign provider is not in MlsProvider, so it resolves to no MLS
        // subject at all — fail closed, never silently Stellar's.
        $this->assertNull($this->resolver()->resolve($this->refFor($other)));
    }

    /** @test */
    public function the_same_provider_and_key_always_resolve_to_the_same_subject(): void
    {
        $a = $this->resolver()->resolve($this->refFor($this->bridgeRow('SAME-1')));
        $b = $this->resolver()->resolve($this->refFor(BridgeProperty::where('listing_key', 'SAME-1')->first()));

        $this->assertSame($a->subjectKey, $b->subjectKey);
    }

    // ─── 4 & 5: raw MLS and MLS-linked BYO are one subject ───────────────────

    /** @test */
    public function a_raw_stellar_row_and_an_mls_linked_seller_listing_share_one_subject(): void
    {
        $bridge = $this->bridgeRow('SHARED-S');
        $seller = $this->seller('SHARED-S');

        $fromBridge = $this->resolver()->resolve($this->refFor($bridge));
        $fromSeller = $this->resolver()->resolve(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id)
        );

        $this->assertSame('mls:stellar_bridge:SHARED-S', $fromBridge->subjectKey);
        $this->assertSame($fromBridge->subjectKey, $fromSeller->subjectKey);
        $this->assertTrue($fromBridge->sameSubjectAs($fromSeller));
    }

    /** @test */
    public function a_raw_stellar_row_and_an_mls_linked_landlord_listing_share_one_subject(): void
    {
        $bridge   = $this->bridgeRow('SHARED-L');
        $landlord = $this->landlord('SHARED-L');

        $fromBridge   = $this->resolver()->resolve($this->refFor($bridge));
        $fromLandlord = $this->resolver()->resolve(
            new SmartTagListingRef(SmartTagListingType::LandlordAgent, $landlord->id)
        );

        $this->assertSame('mls:stellar_bridge:SHARED-L', $fromBridge->subjectKey);
        $this->assertSame($fromBridge->subjectKey, $fromLandlord->subjectKey);
    }

    /** @test */
    public function the_batch_resolver_agrees_with_the_single_resolver(): void
    {
        $bridge = $this->bridgeRow('BATCH-1');
        $seller = $this->seller('BATCH-1');
        $plain  = $this->seller();

        $refs = [
            $this->refFor($bridge),
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id),
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $plain->id),
        ];

        $many = $this->resolver()->resolveMany($refs);

        $this->assertSame('mls:stellar_bridge:BATCH-1', $many["bridge:{$bridge->id}"]->subjectKey);
        $this->assertSame('mls:stellar_bridge:BATCH-1', $many["seller_agent:{$seller->id}"]->subjectKey);
        $this->assertSame("byo:seller_agent:{$plain->id}", $many["seller_agent:{$plain->id}"]->subjectKey);

        foreach ($refs as $ref) {
            $this->assertSame(
                $this->resolver()->resolve($ref)?->subjectKey,
                $many["{$ref->type->value}:{$ref->id}"]->subjectKey ?? null
            );
        }
    }

    /**
     * A BYO listing stores only the key. When two providers hold that key the
     * provider is genuinely unknowable from the meta alone, so the listing keeps
     * its own `byo:` subject rather than being attached to a house the customer
     * may never have seen.
     *
     * @test
     */
    public function an_ambiguous_linked_key_falls_back_to_the_native_subject(): void
    {
        $this->bridgeRow('AMBIG-1');
        $this->bridgeRow('AMBIG-1', self::OTHER_PROVIDER);

        $seller = $this->seller('AMBIG-1');

        $subject = $this->resolver()->resolve(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id)
        );

        $this->assertSame("byo:seller_agent:{$seller->id}", $subject->subjectKey);
        $this->assertFalse($subject->isMlsSubject());
    }

    /** @test */
    public function a_linked_key_with_no_bridge_row_falls_back_to_the_native_subject(): void
    {
        $seller = $this->seller('NO-SUCH-KEY');

        $subject = $this->resolver()->resolve(
            new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id)
        );

        $this->assertSame("byo:seller_agent:{$seller->id}", $subject->subjectKey);
    }

    // ─── 11 & 12: native subjects are untouched ──────────────────────────────

    /** @test */
    public function a_listing_with_no_mls_link_keeps_its_existing_byo_subject_format(): void
    {
        $seller   = $this->seller();
        $landlord = $this->landlord();

        $this->assertSame(
            "byo:seller_agent:{$seller->id}",
            $this->resolver()->resolve(new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id))->subjectKey
        );
        $this->assertSame(
            "byo:landlord_agent:{$landlord->id}",
            $this->resolver()->resolve(new SmartTagListingRef(SmartTagListingType::LandlordAgent, $landlord->id))->subjectKey
        );
    }

    // ─── 6, 7, 9: the write path ─────────────────────────────────────────────

    /** @test */
    public function two_providers_with_the_same_key_do_not_share_preference_state(): void
    {
        $user   = User::factory()->create();
        $writer = app(ListingPreferenceWriter::class);

        $stellar = $this->bridgeRow('CLASH-1');
        $writer->setState($user->id, SeekerRole::Buyer, $this->refFor($stellar), ListingPreferenceState::Pass, []);

        // The other provider's row resolves to no subject today, so it cannot
        // reach — let alone overwrite — Stellar's row. That is the guarantee.
        $this->assertSame(
            1,
            ListingPreference::query()->where('user_id', $user->id)->count()
        );
        $this->assertSame(
            'mls:stellar_bridge:CLASH-1',
            ListingPreference::query()->where('user_id', $user->id)->value('subject_key')
        );
    }

    /** @test */
    public function the_writer_never_persists_a_legacy_un_namespaced_key(): void
    {
        $user   = User::factory()->create();
        $writer = app(ListingPreferenceWriter::class);

        $writer->setState(
            $user->id,
            SeekerRole::Buyer,
            $this->refFor($this->bridgeRow('NOLEGACY-1')),
            ListingPreferenceState::Save,
            []
        );

        foreach (['listing_preferences', 'listing_preference_events'] as $table) {
            $legacy = DB::table($table)
                ->where('subject_key', 'LIKE', 'mls:%')
                ->where('subject_key', 'NOT LIKE', 'mls:%:%')
                ->count();

            $this->assertSame(0, $legacy, "{$table} holds a legacy mls:<listing_key> row");
        }
    }

    /**
     * The current row and its history row must name the SAME subject. They are
     * written from one resolved object, and this pins that they cannot drift.
     *
     * @test
     */
    public function the_event_row_carries_the_same_namespaced_subject_as_the_current_row(): void
    {
        $user   = User::factory()->create();
        $writer = app(ListingPreferenceWriter::class);
        $ref    = $this->refFor($this->bridgeRow('EVENT-1'));

        $writer->setState($user->id, SeekerRole::Buyer, $ref, ListingPreferenceState::Maybe, []);
        $writer->setState($user->id, SeekerRole::Buyer, $ref, ListingPreferenceState::Pass, []);

        $current = ListingPreference::query()->where('user_id', $user->id)->firstOrFail();
        $events  = ListingPreferenceEvent::query()->where('user_id', $user->id)->get();

        $this->assertSame('mls:stellar_bridge:EVENT-1', $current->subject_key);
        $this->assertGreaterThanOrEqual(2, $events->count());

        foreach ($events as $event) {
            $this->assertSame($current->subject_key, $event->subject_key);
        }
    }

    /** @test */
    public function the_reader_finds_a_preference_stored_under_the_namespaced_key(): void
    {
        $user   = User::factory()->create();
        $writer = app(ListingPreferenceWriter::class);
        $reader = app(\App\Services\ListingPreferences\ListingPreferenceReader::class);
        $ref    = $this->refFor($this->bridgeRow('READ-1'));

        $writer->setState($user->id, SeekerRole::Buyer, $ref, ListingPreferenceState::Save, []);

        $current = $reader->current($user->id, SeekerRole::Buyer, $ref);

        $this->assertSame('save', $current['state']);
    }

    // ─── 10 & 13: flag and inference ─────────────────────────────────────────

    /** @test */
    public function the_feature_flag_is_still_off_by_default(): void
    {
        $shipped = require base_path('config/listing_preferences.php');

        $this->assertFalse($shipped['enabled'], 'LISTING_PREFERENCES_ENABLED must still ship false.');
    }

    /**
     * The provider comes from the row's own column, never from the listing type.
     * A native listing type carries no provider meaning at all — proven by the
     * fact that an MLS-linked seller row and a bridge row of a DIFFERENT type
     * produce the identical key.
     *
     * @test
     */
    public function the_provider_is_never_inferred_from_the_listing_type(): void
    {
        $bridge = $this->bridgeRow('NOINFER-1');
        $seller = $this->seller('NOINFER-1');

        $a = $this->resolver()->resolve($this->refFor($bridge));
        $b = $this->resolver()->resolve(new SmartTagListingRef(SmartTagListingType::SellerAgent, $seller->id));

        $this->assertSame($a->subjectKey, $b->subjectKey);
        $this->assertNotSame($a->listingType(), $b->listingType());

        // And with the column changed to something unrecognised, the SAME
        // listing type now yields no MLS subject — so the type was never what
        // decided it.
        DB::table('bridge_properties')->where('id', $bridge->id)->update(['provider' => self::OTHER_PROVIDER]);

        $this->assertNull($this->resolver()->resolve($this->refFor($bridge->fresh())));
    }
}

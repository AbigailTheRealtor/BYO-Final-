<?php

namespace Tests\Feature\VirtualDrive;

use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\VirtualDrive\VirtualDriveListingActions;
use App\Support\VirtualDrive\VirtualDrivePreferenceControl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\TestCase;

/**
 * Save | Maybe | Pass inside the Virtual Drive.
 *
 * NO GOOGLE IS INVOLVED IN ANY OF THIS. Nothing here loads a provider, requests
 * the Maps API, or takes a launch claim — the preference control lives in the
 * shopper card, which exists before and independently of any panorama.
 *
 * THE POINT OF THE PHASE IS DELEGATION, so most of these assert what the
 * Virtual Drive does NOT contain: no state, no endpoints, no reasons, no flag.
 */
class VirtualDriveListingPreferenceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    // ------------------------------------------------- the obsolete finding

    /**
     * The action used to report that no Save feature existed anywhere in this
     * application. One was built; the finding is retired.
     *
     * @test
     */
    public function the_save_action_no_longer_claims_the_feature_does_not_exist(): void
    {
        $source = (string) file_get_contents(base_path('app/Support/VirtualDrive/VirtualDriveListingActions.php'));

        $this->assertStringNotContainsString(
            "'No Save / Favorite feature exists anywhere in this application.'",
            $source,
            'the obsolete finding must be gone from the action list'
        );
    }

    /** With a trusted id and nothing refusing it, Save is offered. @test */
    public function save_is_available_when_the_shared_system_offers_it(): void
    {
        $actions = collect(VirtualDriveListingActions::for($this->projection(), 4242, null))
            ->keyBy('key');

        $this->assertTrue($actions['save']['available']);
        $this->assertNull($actions['save']['reason']);
        // The control is the interaction; the action carries no destination.
        $this->assertNull($actions['save']['url']);
    }

    /** Without a trusted Bridge row id, Save is refused and says why. @test */
    public function save_is_refused_without_a_trusted_bridge_row_id(): void
    {
        $actions = collect(VirtualDriveListingActions::for($this->projection(), null, null))
            ->keyBy('key');

        $this->assertFalse($actions['save']['available']);
        $this->assertNotNull($actions['save']['reason']);
    }

    /** A reason from the shared system is what the action reports. @test */
    public function a_refusal_from_the_shared_system_becomes_the_actions_reason(): void
    {
        $actions = collect(VirtualDriveListingActions::for($this->projection(), 4242, 'Saving properties is not switched on in this environment.'))
            ->keyBy('key');

        $this->assertFalse($actions['save']['available']);
        $this->assertSame('Saving properties is not switched on in this environment.', $actions['save']['reason']);
    }

    /** The action shape the Virtual Drive depends on is unchanged. @test */
    public function every_action_keeps_the_proofs_shape(): void
    {
        foreach (VirtualDriveListingActions::for($this->projection(), 1, null) as $action) {
            $this->assertSame(
                ['key', 'label', 'available', 'url', 'reason'],
                array_keys($action),
                'the Virtual Drive action contract must not change'
            );
        }
    }

    // --------------------------------------------------------- the delegation

    /** The seam resolves through the shared availability, not its own rules. @test */
    public function the_seam_offers_a_control_for_an_eligible_buyer(): void
    {
        $this->actingAs($this->buyer());

        $decision = app(VirtualDrivePreferenceControl::class)->forListing((int) $this->saleListing()->id);

        $this->assertNull($decision['reason']);
        $this->assertStringContainsString('data-lp-control', $decision['html']);
        $this->assertStringContainsString('data-lp-listing-type="bridge"', $decision['html']);
    }

    /**
     * Buyer = sale, Tenant = lease — enforced by the shared policy, which is
     * why the Virtual Drive does not restate it.
     *
     * @test
     */
    public function a_tenant_is_offered_nothing_on_a_sale_listing(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'tenant']));

        $decision = app(VirtualDrivePreferenceControl::class)->forListing((int) $this->saleListing()->id);

        $this->assertNotNull($decision['reason']);
        $this->assertSame('', $decision['html']);
    }

    /** A guest gets a control that routes through the existing login flow. @test */
    public function a_guest_gets_a_control_that_leads_to_login_and_stores_nothing(): void
    {
        $decision = app(VirtualDrivePreferenceControl::class)->forListing((int) $this->saleListing()->id);

        $this->assertNull($decision['reason'], 'a guest is not refused — signing in changes the answer');
        $this->assertStringContainsString('data-lp-guest="1"', $decision['html']);
        $this->assertStringContainsString(route('login'), $decision['html']);
        $this->assertDatabaseCount('listing_preferences', 0);
    }

    /** Feature off: nothing is offered, and the reason says so. @test */
    public function the_feature_flag_off_leaves_a_safe_unavailable_state(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());

        $decision = app(VirtualDrivePreferenceControl::class)->forListing((int) $this->saleListing()->id);

        $this->assertSame('', $decision['html']);
        $this->assertNotNull($decision['reason']);

        $actions = collect(VirtualDriveListingActions::for($this->projection(), 1, $decision['reason']))->keyBy('key');
        $this->assertFalse($actions['save']['available']);
    }

    // ------------------------------------------------------------- the pool

    /** The page's pool carries one control per listing, keyed for the shell. @test */
    public function the_pool_renders_one_control_per_listing(): void
    {
        $this->actingAs($this->buyer());

        $a = $this->saleListing('VD-POOL-A');
        $b = $this->saleListing('VD-POOL-B');

        $pool = app(VirtualDrivePreferenceControl::class)->pool(['VD-POOL-A', 'VD-POOL-B', 'VD-POOL-MISSING']);

        // Key => the trusted row id; a missing key gets no entry at all.
        $this->assertSame(['VD-POOL-A' => (int) $a->id, 'VD-POOL-B' => (int) $b->id], $pool);

        $slots = $this->slotsOnPage(['VD-POOL-A', 'VD-POOL-B', 'VD-POOL-MISSING']);
        $this->assertSame(['VD-POOL-A', 'VD-POOL-B'], array_keys($slots));
        $this->assertStringContainsString('data-lp-listing-id="' . $a->id . '"', $slots['VD-POOL-A']);
        $this->assertStringContainsString('data-lp-listing-id="' . $b->id . '"', $slots['VD-POOL-B']);
    }

    /**
     * A LISTING KEY IS UNIQUE ONLY WITHIN ITS PROVIDER.
     *
     * Another MLS may mint the same key. The pool resolves each key within the
     * current provider, so the control carries that provider's row and never
     * the other MLS's property.
     *
     * @test
     */
    public function the_pool_resolves_keys_within_the_current_provider_only(): void
    {
        $this->actingAs($this->buyer());

        BridgeProperty::create([
            'provider'      => 'another_mls',
            'listing_key'   => 'VD-SHARED-KEY',
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);
        $ours = $this->saleListing('VD-SHARED-KEY');

        BridgeProperty::create([
            'provider'      => 'another_mls',
            'listing_key'   => 'VD-FOREIGN-ONLY',
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);

        $pool = app(VirtualDrivePreferenceControl::class)->pool(['VD-SHARED-KEY', 'VD-FOREIGN-ONLY']);

        $this->assertSame(['VD-SHARED-KEY' => (int) $ours->id], $pool);
    }

    /**
     * THE TRUSTED ID IS THE ROW ID, NOT THE MLS KEY.
     *
     * The pool is KEYED by listing key because that is the selector the shell
     * has. What the control carries is the Bridge row id, resolved server-side.
     *
     * @test
     */
    public function the_control_carries_the_row_id_and_never_the_listing_key(): void
    {
        $this->actingAs($this->buyer());

        $listing = $this->saleListing('VD-TRUSTED-KEY');

        // The control INSIDE the slot — the key is only the slot's selector.
        $html = $this->slotsOnPage(['VD-TRUSTED-KEY'])['VD-TRUSTED-KEY'];

        $this->assertStringContainsString('data-lp-listing-id="' . $listing->id . '"', $html);
        $this->assertStringNotContainsString('VD-TRUSTED-KEY', $html, 'the MLS key must not be the submitted identity');
    }

    /**
     * THE BEHAVIOUR IS ON THE PAGE EXACTLY ONCE, however many controls the pool
     * renders. Each pooled control is rendered on its own, and a copy of the
     * delegated script per control means one click is handled once per copy:
     * one Done sent several reason writes, and a late one closed the tray the
     * shopper had just reopened.
     *
     * @test
     */
    public function the_proof_page_carries_one_copy_of_the_shared_behaviour(): void
    {
        config([
            'virtual_drive.proof_enabled'      => true,
            'virtual_drive.test_listing_keys'  => ['VD-ONCE-A', 'VD-ONCE-B', 'VD-ONCE-C'],
        ]);
        $this->actingAs($this->buyer());

        foreach (['VD-ONCE-A', 'VD-ONCE-B', 'VD-ONCE-C'] as $key) {
            $this->saleListing($key);
        }

        $html = $this->get('/dev/virtual-drive/apple')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-lp-listing-type="bridge"'), 'one control per listing');
        $this->assertSame(1, substr_count($html, 'One delegated listener for every control on the page'),
            'the delegated behaviour must be emitted once per document');
        $this->assertSame(1, substr_count($html, '.lp-control {'), 'the stylesheet must be emitted once');
    }

    /** The pool is empty while the feature is off. @test */
    public function the_pool_is_empty_when_the_feature_is_off(): void
    {
        config()->set('listing_preferences.enabled', false);
        $this->actingAs($this->buyer());
        $this->saleListing('VD-OFF');

        $this->assertSame([], app(VirtualDrivePreferenceControl::class)->pool(['VD-OFF']));
    }

    // ----------------------------------------------- writes and current state

    /** A Virtual Drive write goes through the shared endpoints. @test */
    public function a_write_from_the_virtual_drive_uses_the_shared_service(): void
    {
        $user    = $this->buyer();
        $listing = $this->saleListing('VD-WRITE');

        $this->actingAs($user);

        $this->postJson(route('listing-preferences.virtual-drive.store'), [
            'listing_type' => 'bridge',
            'listing_id'   => $listing->id,
            'state'        => 'save',
        ])->assertOk()->assertJson(['success' => true, 'state' => 'save']);

        $this->assertDatabaseHas('listing_preferences', [
            'user_id'      => $user->id,
            'listing_type' => 'bridge',
            'listing_id'   => $listing->id,
            'state'        => 'save',
        ]);
    }

    /** …and the event records the Virtual Drive, from the ROUTE. @test */
    public function the_event_records_the_virtual_drive_surface(): void
    {
        $user    = $this->buyer();
        $listing = $this->saleListing('VD-SURFACE');

        $this->actingAs($user);
        $this->postJson(route('listing-preferences.virtual-drive.store'), [
            'listing_type' => 'bridge',
            'listing_id'   => $listing->id,
            'state'        => 'pass',
        ])->assertOk();

        $this->assertDatabaseHas('listing_preference_events', [
            'user_id' => $user->id,
            'surface' => 'virtual_drive',
        ]);
    }

    /** Reasons and clear work from this surface too. @test */
    public function reasons_and_clear_work_from_the_virtual_drive(): void
    {
        $user    = $this->buyer();
        $listing = $this->saleListing('VD-REASONS');

        $this->actingAs($user);

        $this->postJson(route('listing-preferences.virtual-drive.store'), [
            'listing_type' => 'bridge', 'listing_id' => $listing->id, 'state' => 'save',
        ])->assertOk();

        $this->postJson(route('listing-preferences.virtual-drive.reasons'), [
            'listing_type' => 'bridge', 'listing_id' => $listing->id, 'reasons' => ['updated_kitchen'],
        ])->assertOk()->assertJson(['success' => true]);

        $this->deleteJson(route('listing-preferences.virtual-drive.destroy'), [
            'listing_type' => 'bridge', 'listing_id' => $listing->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('listing_preferences', [
            'user_id' => $user->id, 'listing_id' => $listing->id,
        ]);
    }

    /** Reopening the same property shows the stored state. @test */
    public function the_control_shows_the_current_state_for_the_selected_listing(): void
    {
        $user    = $this->buyer();
        $listing = $this->saleListing('VD-CURRENT');

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $listing->id),
            ListingPreferenceState::Maybe, [],
        );

        $this->actingAs($user);
        $html = app(VirtualDrivePreferenceControl::class)->forListing((int) $listing->id)['html'];

        $this->assertStringContainsString('"state":"maybe"', $html);
    }

    /** Different listings carry different state — the pool is per property. @test */
    public function changing_the_selected_property_changes_the_state_shown(): void
    {
        $user   = $this->buyer();
        $saved  = $this->saleListing('VD-A');
        $silent = $this->saleListing('VD-B');

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $saved->id),
            ListingPreferenceState::Save, [],
        );

        $this->actingAs($user);
        $slots = $this->slotsOnPage(['VD-A', 'VD-B']);

        $this->assertStringContainsString('"state":"save"', $slots['VD-A']);
        $this->assertStringContainsString('"state":null', $slots['VD-B']);
    }

    // ----------------------------------------------- the structural guarantees

    /**
     * THE VIRTUAL DRIVE'S JAVASCRIPT CONTAINS NO PREFERENCE LOGIC.
     *
     * Not "little"; none. No state names, no endpoints, no reason vocabulary,
     * no feature flag, and no markup built from a server string.
     *
     * @test
     */
    public function the_virtual_drive_javascript_holds_no_preference_logic(): void
    {
        foreach (glob(base_path('public/js/virtual-drive/*.js')) ?: [] as $path) {
            $source = (string) file_get_contents($path);
            $name   = basename($path);

            foreach ([
                'listing-preferences',   // an endpoint
                'data-lp-state',         // a state control
                'data-lp-chip',          // a reason
                'ListingPreference',     // a class
                'LISTING_PREFERENCES',   // the flag
                'innerHTML',             // listing data as markup
                "'save'",                // a state name
                "'maybe'",
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$name} must contain no preference logic — found {$forbidden}"
                );
            }
        }
    }

    /** The action class delegates; it stores and reads nothing. @test */
    public function the_action_class_reads_and_writes_no_preference(): void
    {
        $source = (string) file_get_contents(base_path('app/Support/VirtualDrive/VirtualDriveListingActions.php'));

        // Its only mention is the docblock explaining the delegation.
        foreach (['ListingPreferenceWriter', 'ListingPreferenceReader', 'ListingPreference::', 'listing_preferences'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    /**
     * The Explore projection is NOT widened to carry a database id.
     *
     * The controller already holds the model; publishing our primary key to
     * every Explore consumer to save it a lookup would be the wrong trade.
     *
     * @test
     */
    public function the_explore_projection_still_publishes_no_row_id(): void
    {
        $source = (string) file_get_contents(base_path('app/Services/Explore/ExploreListingProjection.php'));

        $this->assertStringNotContainsString('bridge_property_id', $source);
        $this->assertStringNotContainsString('bridgeRowId', $source);
    }

    /**
     * NO N+1 IN THE LISTINGS API, and no HTML rendered only to be discarded.
     * The nearby query returns up to 150 homes and re-runs as the shopper
     * drives; deciding Save's availability for three times as many homes must
     * cost the same number of queries.
     *
     * @test
     */
    public function the_listings_api_decides_save_in_batch(): void
    {
        config(['virtual_drive.proof_enabled' => true]);
        config()->set('listing_preferences.enabled', true);
        $this->actingAs($this->buyer());

        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $measure = function () use (&$sql): int {
            app(\App\Support\ListingPreferences\ListingPreferencePrefetch::class)->forget();
            $sql = [];
            $this->getJson('/dev/virtual-drive/api/listings?lat=' . self::LAT . '&lng=' . self::LNG . '&radius=500')
                ->assertOk();

            return count($sql);
        };

        foreach (range(1, 3) as $i) { $this->makeListing(); }
        $small = $measure();
        foreach (range(1, 6) as $i) { $this->makeListing(); }
        $large = $measure();

        $this->assertSame($small, $large, "{$small} queries for 3 homes, {$large} for 9");
    }

    /** …and the page's pool of controls is batched the same way. @test */
    public function the_page_pool_is_rendered_in_batch(): void
    {
        config()->set('listing_preferences.enabled', true);
        $this->actingAs($this->buyer());

        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });

        $pool = function (array $keys) use (&$sql): int {
            app(\App\Support\ListingPreferences\ListingPreferencePrefetch::class)->forget();
            $sql = [];
            $this->assertCount(count($keys), app(VirtualDrivePreferenceControl::class)->pool($keys));

            return count($sql);
        };

        $keys = [];
        foreach (range(1, 6) as $i) { $keys[] = (string) $this->makeListing()->listing_key; }

        $small = $pool(array_slice($keys, 0, 2));
        $large = $pool($keys);

        $this->assertSame($small, $large, "{$small} queries for 2 controls, {$large} for 6");
    }

    /** Nothing in this phase touches the Google launch machinery. @test */
    public function the_google_guards_are_untouched(): void
    {
        $shell = (string) file_get_contents(base_path('public/js/virtual-drive/virtual-drive-shell.js'));

        // The launch path still claims the allowance before loading a provider.
        $this->assertStringContainsString('claimDailyAllowance()', $shell);
        $this->assertStringContainsString('.load(cfg, hooks)', $shell);
        $this->assertLessThan(
            strpos($shell, '.load(cfg, hooks)'),
            strpos($shell, 'claimDailyAllowance()'),
        );
    }

    // --------------------------------------------------------------- helpers

    /**
     * The real proof page's hidden preference slots, keyed by listing key, each
     * holding the markup INSIDE it (the rendered control) — not the key.
     *
     * @param  list<string> $keys
     * @return array<string, string>
     */
    private function slotsOnPage(array $keys): array
    {
        config([
            'virtual_drive.proof_enabled'     => true,
            'virtual_drive.test_listing_keys' => $keys,
        ]);

        $html = $this->get('/dev/virtual-drive/apple')->assertOk()->getContent();

        preg_match_all(
            '/<div data-vd-preference-for="([^"]+)" hidden>(.*?)(?=<div data-vd-preference-for=|<script src="\/js\/virtual-drive\/)/s',
            $html,
            $m,
            PREG_SET_ORDER,
        );

        $slots = [];
        foreach ($m as [, $key, $inner]) {
            $slots[html_entity_decode($key)] = $inner;
        }

        return $slots;
    }

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function saleListing(string $key = 'VD-DEFAULT'): BridgeProperty
    {
        return BridgeProperty::create([
            'provider'      => 'stellar_bridge',
            'listing_key'   => $key,
            'property_type' => 'Residential',
            'raw_json'      => json_encode(['IDXParticipationYN' => true]),
        ]);
    }

    /** @return array<string,mixed> a minimal ExploreListingProjection::toArray() shape */
    private function projection(): array
    {
        return [
            'id'                => 'VD-PROJECTION',
            'has_photos'        => false,
            'photo_urls'        => [],
            'has_video'         => false,
            'has_virtual_tour'  => false,
            'canonical_url'     => null,
            'detail_url'        => null,
            'showing_available' => false,
        ];
    }
}

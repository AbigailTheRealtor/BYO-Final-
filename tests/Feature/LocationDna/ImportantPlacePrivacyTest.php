<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * An Important Place is private to the listing's owner.
 *
 * It is where the client works, their child's school, a relative's home — and all six Buyer/Tenant
 * detail pages that show it are public. Everyone other than the owner sees the type of place and the
 * distance ("Work · Within 3 miles"); nobody else gets the address, the street number, the coordinate,
 * a pin, a ring centred on it, or a tooltip/popup naming it. The owner — the only account that can
 * edit these listings — still sees all of it, and the stored row is never altered by a page view.
 *
 * Whole-page assertions, not summary ones: the leak this closes was in the map's JSON payload, which
 * a reader never sees and a summary-only check would never have caught.
 */
class ImportantPlacePrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private const ADDRESS = '116 8th St E, Tierra Verde, FL 33715';

    private const WORK = [
        'type' => 'Work', 'type_other' => '', 'address' => self::ADDRESS,
        'lat' => 27.6917, 'lng' => -82.7215, 'distance_pref' => 'miles', 'distance_value' => 3, 'travel_mode' => 'driving',
    ];

    /** Every trace of where the place is. Nothing else on these pages uses these values. */
    private const LOCATING = [self::ADDRESS, '116 8th St', 'Tierra Verde', '27.6917', '-82.7215'];

    /** A radius, so every surface renders its drawn map — the tier a place pin would sit on. */
    private const RADIUS = ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5];

    private User $owner;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create();
        $this->stranger = User::factory()->create(['user_type' => 'agent']);

        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => ['seller', 'buyer', 'landlord', 'tenant'],
        ]);
    }

    private function renderer(bool $maplibre): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => $maplibre,
            'spatial_basemap.maplibre_renderer_surfaces' => ['display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
            // A placeholder so the Google branch renders its script; nothing is requested.
            'services.google.places_key'                 => $maplibre ? '' : 'test-placeholder-key',
        ]);
    }

    public static function surfacesAndRenderers(): array
    {
        $cases = [];
        foreach (['buyer offer listing', 'tenant offer listing', 'buyer criteria', 'tenant criteria', 'buyer hire', 'tenant hire'] as $surface) {
            $cases["{$surface}, MapLibre"] = [$surface, true];
            $cases["{$surface}, Google"]   = [$surface, false];
        }

        return $cases;
    }

    // ── Public ────────────────────────────────────────────────────────────────

    /** @dataProvider surfacesAndRenderers */
    public function test_a_non_owner_sees_the_type_and_miles_and_nothing_that_locates_the_place(string $surface, bool $maplibre): void
    {
        $this->renderer($maplibre);
        [$url, $listing] = $this->listing($surface);

        $html = $this->actingAs($this->stranger)->get($url)->assertOk()->getContent();

        $this->assertPublicView($html, $surface);
        $this->assertStoredRowUntouched($listing);
    }

    public static function offerListingSurfaces(): array
    {
        return ['buyer offer listing' => ['buyer offer listing'], 'tenant offer listing' => ['tenant offer listing']];
    }

    /** @dataProvider offerListingSurfaces */
    public function test_a_guest_gets_the_same_public_view(string $surface): void
    {
        $this->renderer(true);
        [$url] = $this->listing($surface);

        $this->assertPublicView($this->get($url)->assertOk()->getContent(), $surface);
    }

    // ── Owner ─────────────────────────────────────────────────────────────────

    /** @dataProvider surfacesAndRenderers */
    public function test_the_owner_sees_the_exact_address_and_the_pin(string $surface, bool $maplibre): void
    {
        $this->renderer($maplibre);
        [$url, $listing] = $this->listing($surface);

        $html    = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
        $summary = $this->summary($html);

        $this->assertStringContainsString('Work', $summary);
        $this->assertStringContainsString(self::ADDRESS, $summary);
        $this->assertStringContainsString('Within 3 miles', $summary);
        $this->assertStringNotContainsString('data-ldna-criteria-places-private', $summary);

        // The owner's map is handed the located row — the pin and its ring.
        $located = array_filter($this->mapPlaces($html, $maplibre), fn ($row) => ($row['lat'] ?? null) == 27.6917);
        $this->assertNotEmpty($located, "{$surface}: the owner's map must receive the place's coordinate");

        $this->assertStoredRowUntouched($listing);
    }

    public function test_the_owner_edits_the_exact_address_on_both_criteria_edit_pages(): void
    {
        // Criteria listings are created by agents, and both Edit routes sit in the agent route
        // groups — as CriteriaLocationDnaTest's owner does, this one must be an agent to reach them.
        $this->owner = User::factory()->create(['user_type' => 'agent']);

        [, $buyer]  = $this->listing('buyer criteria');
        [, $tenant] = $this->listing('tenant criteria');

        foreach ([
            route('buyer_agent.auction.edit', $buyer->id),
            route('agent.tenant.criteria.auction.edit', $tenant->id),
        ] as $url) {
            $this->assertStringContainsString(self::ADDRESS, $this->actingAs($this->owner)->get($url)->assertOk()->getContent());
        }
    }

    // ── Terminology ───────────────────────────────────────────────────────────

    /** @dataProvider surfacesAndRenderers */
    public function test_the_section_is_named_for_the_clients_preferences(string $surface, bool $maplibre): void
    {
        $this->renderer($maplibre);
        [$url] = $this->listing($surface);

        $html = $this->actingAs($this->stranger)->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('Search Areas &amp; Location Preferences', $html);
        $this->assertStringNotContainsString('Searching within a defined radius from a preferred location', $html);
        $this->assertStringNotContainsString('within commuting distance', $html);
    }

    // ── Assertions ────────────────────────────────────────────────────────────

    private function assertPublicView(string $html, string $surface): void
    {
        $summary = $this->summary($html);

        // What the public may know: the kind of place, and how far from it the search reaches.
        $this->assertStringContainsString('Work', $summary, "{$surface}: the place type is public");
        $this->assertStringContainsString('Within 3 miles', $summary, "{$surface}: the requested miles are public");
        $this->assertStringContainsString('data-ldna-criteria-places-private', $summary);

        // What it may not — anywhere on the page: summary, tooltip, popup, pin, ring or JSON payload.
        foreach (self::LOCATING as $needle) {
            $this->assertStringNotContainsString($needle, $html, "{$surface}: a non-owner must not receive [{$needle}]");
        }

        // The map still hears about the place — without anything to put a pin or a ring on.
        foreach ($this->mapPlaces($html, null) as $row) {
            foreach (['address', 'lat', 'lng'] as $key) {
                $this->assertArrayNotHasKey($key, $row, "{$surface}: a public map row must not carry [{$key}]");
            }
        }
    }

    /** The stored row is exact before and after — privacy shapes the page, never the data. */
    private function assertStoredRowUntouched(Model $listing): void
    {
        $this->assertSame([self::WORK], json_decode($listing->fresh()->info('important_places_json'), true));
    }

    private function summary(string $html): string
    {
        $start = strpos($html, 'data-ldna-criteria-summary');
        $this->assertNotFalse($start, 'The written summary must render');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    /**
     * Every Important Place row a map on this page was handed, from either renderer: the MapLibre
     * hydration payload and the Google script's `places` array. $maplibre = null reads both.
     */
    private function mapPlaces(string $html, ?bool $maplibre): array
    {
        $rows = [];

        if ($maplibre !== false && preg_match_all('/data-ldna-state="([^"]*)"/', $html, $m)) {
            foreach ($m[1] as $encoded) {
                $state = json_decode(html_entity_decode($encoded, ENT_QUOTES), true) ?? [];
                $rows  = array_merge($rows, $state['important_places'] ?? []);
            }
        }

        if ($maplibre !== true && preg_match_all('/var places\s*=\s*(\[[^;]*\]);/', $html, $m)) {
            foreach ($m[1] as $json) {
                $rows = array_merge($rows, json_decode($json, true) ?? []);
            }
        }

        return $rows;
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function meta(): array
    {
        return [
            'location_dna_preferences' => [
                'radius_searches'   => [self::RADIUS],
                'polygons'          => [],
                'flexible_location' => false,
                'location_notes'    => '',
            ],
            'important_places_json' => [self::WORK],
            'property_type'         => 'Residential Property',
        ];
    }

    /** @return array{0: string, 1: Model} the detail URL and the listing */
    private function listing(string $surface): array
    {
        switch ($surface) {
            case 'buyer offer listing':
                return $this->offerListing(BuyerAgentAuction::class, BuyerAgentAuctionMeta::class, 'buyer_agent_auction_id', 'offer.listing.buyer.view');
            case 'tenant offer listing':
                return $this->offerListing(TenantAgentAuction::class, TenantAgentAuctionMeta::class, 'tenant_agent_auction_id', 'offer.listing.tenant.view');
            case 'buyer hire':
                return $this->hireListing(BuyerAgentAuction::class, 'buyer.view-auction', true);
            case 'tenant hire':
                return $this->hireListing(TenantAgentAuction::class, 'tenant.agent.auction.view', false);
            case 'buyer criteria':
                $criteria = $this->buyerCriteria();

                return [route('buyer.criteria.view', $criteria->id), $criteria];
            case 'tenant criteria':
                $criteria = $this->tenantCriteria();

                return [route('tenant.criteria.auction.view', $criteria->id), $criteria];
        }

        $this->fail("Unknown surface {$surface}");
    }

    private function offerListing(string $model, string $metaModel, string $fk, string $route): array
    {
        $auction = $model::forceCreate([
            'user_id'     => $this->owner->id,
            'title'       => 'Offer listing privacy fixture',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        foreach (['workflow_type' => 'offer_listing'] + $this->meta() as $key => $value) {
            $metaModel::create([
                $fk          => $auction->id,
                'meta_key'   => $key,
                'meta_value' => is_array($value) ? json_encode($value) : $value,
            ]);
        }

        return [route($route, $auction->id), $auction];
    }

    private function hireListing(string $model, string $route, bool $hasAddressColumn): array
    {
        $attributes = [
            'user_id'     => $this->owner->id,
            'title'       => 'Hire listing privacy fixture',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if ($hasAddressColumn) {
            $attributes['address'] = 'Hire listing privacy fixture';
        }

        $listing = $model::forceCreate($attributes);
        foreach (['workflow_type' => 'hire_agent', 'listing_title' => 'Hire listing'] + $this->meta() as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return [route($route, $listing->id), $listing->fresh()];
    }

    private function skipWithoutTables(array $tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function buyerCriteria(): BuyerCriteriaAuction
    {
        $this->skipWithoutTables(['buyer_criteria_auctions', 'buyer_criteria_auction_metas']);

        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $this->owner->id,
            'buyer_id'    => $this->owner->id,
            'max_price'   => 500000,
            'title'       => 'Buyer criteria privacy fixture',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = BuyerCriteriaAuction::findOrFail($id);
        foreach ($this->meta() as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return $record->fresh();
    }

    private function tenantCriteria(): TenantCriteriaAuction
    {
        $this->skipWithoutTables(['tenant_criteria_auctions', 'tenant_criteria_auction_metas']);

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $this->owner->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = TenantCriteriaAuction::findOrFail($id);
        // What every real tenant criteria listing carries (see CriteriaLocationDnaTest): the view
        // and edit pages read these list metas unguarded.
        $meta = array_merge([
            'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => ['FL'],
            'water_view' => [], 'waterFrontage' => [], 'water_extras' => [], 'water_access' => [],
            'viewReference' => [], 'negotiable' => [], 'property_items' => [], 'prop_condition' => [],
            'leaseLength' => [], 'Furnishings' => [], 'dock' => [],
        ], $this->meta());
        foreach ($meta as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return $record->fresh();
    }
}

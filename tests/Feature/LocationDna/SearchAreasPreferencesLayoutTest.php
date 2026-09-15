<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use App\Services\LocationDna\LocationIntelligenceComposer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "Search Areas & Location Preferences" — one layout for Buyer and Tenant.
 *
 * Presentation only. Each Important Place is a card: its type on one line, the requested distance
 * on the next and, for the owner alone, a smaller "Exact location … (owner only)" line — never run
 * together as "Work · address · Within 3 miles". A non-owner gets the type and the distance and
 * nothing that locates the place (PR #146, unchanged). Blank Tenant rows are omitted, and the
 * Location Intelligence card shows calculated lines only — never the client's criteria echoed back.
 */
class SearchAreasPreferencesLayoutTest extends TestCase
{
    use DatabaseTransactions;

    private const PLACES = [
        ['type' => 'Work', 'type_other' => '', 'address' => 'QA test point — downtown Orlando (not a real address)',
         'lat' => 28.5383, 'lng' => -81.3792, 'distance_pref' => 'miles', 'distance_value' => 3, 'travel_mode' => 'driving'],
        ['type' => 'School', 'type_other' => '', 'address' => 'QA test point — south of downtown Orlando (not a real address)',
         'lat' => 28.516, 'lng' => -81.37, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving'],
        ['type' => 'Other', 'type_other' => 'Publix', 'address' => 'QA test point — west of downtown Orlando (not a real address)',
         'lat' => 28.547, 'lng' => -81.4, 'distance_pref' => 'miles', 'distance_value' => 2, 'travel_mode' => 'driving'],
    ];

    /** Title / distance pairs every viewer sees, in order. */
    private const ROWS = [['Work', 'Within 3 miles'], ['School', 'Within 5 miles'], ['Publix', 'Within 2 miles']];

    private User $owner;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['buyer_criteria_auctions', 'buyer_criteria_auction_metas', 'tenant_criteria_auctions', 'tenant_criteria_auction_metas'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }

        $this->owner    = User::factory()->create();
        $this->stranger = User::factory()->create(['user_type' => 'agent']);

        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => ['display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function blob(): string
    {
        return json_encode(['cities' => ['ORLANDO'], 'zip_codes' => [], 'radius_searches' => [], 'polygons' => [], 'flexible_location' => false, 'location_notes' => '']);
    }

    private function buyerCriteriaUrl(): string
    {
        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id' => $this->owner->id, 'buyer_id' => $this->owner->id, 'max_price' => 500000, 'title' => 'Layout fixture',
            'is_approved' => true, 'is_sold' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = BuyerCriteriaAuction::findOrFail($id);
        $record->saveMeta('property_types', json_encode(['Residential']));
        $record->saveMeta('location_dna_preferences', $this->blob());
        $record->saveMeta('important_places_json', json_encode(self::PLACES));

        return route('buyer.criteria.view', $id);
    }

    private function tenantCriteriaUrl(array $meta = []): string
    {
        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id' => $this->owner->id, 'is_approved' => true, 'is_sold' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = TenantCriteriaAuction::findOrFail($id);
        foreach (array_merge([
            'cities' => ['ORLANDO'], 'counties' => [], 'state' => [],
            'water_view' => [], 'waterFrontage' => [], 'water_extras' => [], 'water_access' => [],
            'viewReference' => [], 'negotiable' => [], 'property_items' => [], 'prop_condition' => [],
            'leaseLength' => [], 'Furnishings' => [], 'dock' => [],
            'location_dna_preferences' => $this->blob(), 'important_places_json' => self::PLACES,
        ], $meta) as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return route('tenant.criteria.auction.view', $id);
    }

    private function offerListingUrl(string $model, string $metaModel, string $fk, string $route): string
    {
        $auction = $model::forceCreate([
            'user_id' => $this->owner->id, 'title' => 'Layout fixture', 'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        foreach (['workflow_type' => 'offer_listing', 'location_dna_preferences' => $this->blob(), 'important_places_json' => json_encode(self::PLACES)] as $key => $value) {
            $metaModel::create([$fk => $auction->id, 'meta_key' => $key, 'meta_value' => $value]);
        }

        return route($route, $auction->id);
    }

    private function criteriaUrl(string $role): string
    {
        return $role === 'buyer' ? $this->buyerCriteriaUrl() : $this->tenantCriteriaUrl();
    }

    private function page(string $url, ?User $viewer): string
    {
        $request = $viewer ? $this->actingAs($viewer) : $this;

        return $request->get($url)->assertOk()->getContent();
    }

    private function summary(string $html): string
    {
        $start = strpos($html, 'data-ldna-criteria-summary');
        $this->assertNotFalse($start, 'The Search Areas & Location Preferences summary must render');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    /** The markup skeleton of the Important Places group: its classes and data markers, in order. */
    private function placesSkeleton(string $summary): array
    {
        $group = substr($summary, strpos($summary, 'Important Places'));
        preg_match_all('/class="(ldna-crit-[a-z-]+)|(data-ldna-criteria-[a-z-]+)/', $group, $m);

        return array_map(fn ($class, $data) => $class !== '' ? $class : $data, $m[1], $m[2]);
    }

    private function assertRowsAreTitleThenDistance(string $summary, string $who): void
    {
        foreach (self::ROWS as [$title, $distance]) {
            $this->assertMatchesRegularExpression(
                '#<div class="ldna-crit-title">' . preg_quote($title, '#') . '</div>\s*<div class="ldna-crit-meta">' . preg_quote($distance, '#') . '</div>#',
                $summary,
                "{$who}: [{$title}] and [{$distance}] must be separate lines of one card"
            );
        }
        $this->assertStringNotContainsString('&middot;', $summary, "{$who}: no values run together with separators");
    }

    public static function roles(): array
    {
        return ['buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    public static function rolesAndViewers(): array
    {
        return [
            'buyer, signed-in non-owner'  => ['buyer', true],
            'buyer, signed out'           => ['buyer', false],
            'tenant, signed-in non-owner' => ['tenant', true],
            'tenant, signed out'          => ['tenant', false],
        ];
    }

    // ── Owner layout ────────────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_the_owner_sees_each_place_as_a_card_with_its_exact_location_on_its_own_line(string $role): void
    {
        $summary = $this->summary($this->page($this->criteriaUrl($role), $this->owner));

        $this->assertStringContainsString('Important Places', $summary);
        $this->assertRowsAreTitleThenDistance($summary, "{$role} owner");

        $this->assertSame(3, substr_count($summary, 'data-ldna-criteria-place-exact'));
        foreach (self::PLACES as $place) {
            $this->assertMatchesRegularExpression(
                '#<div class="ldna-crit-exact" data-ldna-criteria-place-exact>Exact location: ' . preg_quote($place['address'], '#') . ' <span class="text-nowrap">\(owner only\)</span></div>#',
                $summary
            );
        }
        $this->assertStringNotContainsString('data-ldna-criteria-places-private', $summary, 'The owner is not shown the privacy notice');
    }

    // ── Non-owner / public layout ───────────────────────────────────────────

    /** @dataProvider rolesAndViewers */
    public function test_anyone_else_sees_the_type_and_distance_and_nothing_that_locates_a_place(string $role, bool $signedIn): void
    {
        $html    = $this->page($this->criteriaUrl($role), $signedIn ? $this->stranger : null);
        $summary = $this->summary($html);

        $this->assertRowsAreTitleThenDistance($summary, "{$role} non-owner");
        $this->assertStringContainsString('data-ldna-criteria-places-private', $summary);
        $this->assertStringContainsString('Exact locations are private — only the type of place and the distance are shown.', $summary);

        $this->assertStringNotContainsString('data-ldna-criteria-place-exact', $summary);
        $this->assertStringNotContainsString('Exact location:', $summary);
        foreach (['QA test point', '28.5383', '-81.3792', '28.547', '(owner only)'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "{$role}: a non-owner page must not carry [{$needle}]");
        }
    }

    // ── Buyer / Tenant parity ───────────────────────────────────────────────

    public function test_buyer_and_tenant_render_the_same_visual_contract(): void
    {
        foreach ([$this->owner, $this->stranger] as $viewer) {
            $buyer  = $this->placesSkeleton($this->summary($this->page($this->buyerCriteriaUrl(), $viewer)));
            $tenant = $this->placesSkeleton($this->summary($this->page($this->tenantCriteriaUrl(), $viewer)));

            $this->assertNotEmpty($buyer);
            $this->assertSame($buyer, $tenant, 'Buyer and Tenant must render the same Important Places markup');
        }
    }

    /** @dataProvider roles */
    public function test_rows_are_shielded_from_page_list_decoration(string $role): void
    {
        $html = $this->page($this->criteriaUrl($role), $this->owner);

        // The Buyer page decorates every `ul li::marker` with a FontAwesome angle quote; the rows
        // are flex items, which generate no marker, and the reset is scoped to the summary.
        $this->assertStringContainsString('.ldna-criteria-summary ul.ldna-crit-list > li::marker', $html);
        $this->assertStringContainsString('<ul class="ldna-crit-list">', $this->summary($html));
    }

    // ── Empty Tenant rows ───────────────────────────────────────────────────

    public function test_blank_tenant_state_and_county_rows_do_not_render(): void
    {
        foreach ([['counties' => [], 'state' => []], ['counties' => [''], 'state' => ['  ']]] as $blank) {
            $html = $this->page($this->tenantCriteriaUrl($blank), $this->owner);

            $this->assertStringContainsString('<strong>Cities:</strong>', $html);
            $this->assertStringNotContainsString('<strong>Counties:</strong>', $html);
            $this->assertStringNotContainsString('<strong>State:</strong>', $html);
        }

        $filled = $this->page($this->tenantCriteriaUrl(['counties' => ['Orange'], 'state' => ['FL']]), $this->owner);
        $this->assertStringContainsString('<strong>Counties:</strong>', $filled);
        $this->assertStringContainsString('<strong>State:</strong>', $filled);
    }

    // ── Location Intelligence: calculated lines only ────────────────────────

    public static function intelligenceSurfaces(): array
    {
        return [
            'buyer criteria'       => ['buyer criteria'],
            'tenant criteria'      => ['tenant criteria'],
            'buyer offer listing'  => ['buyer offer listing'],
            'tenant offer listing' => ['tenant offer listing'],
        ];
    }

    private function surfaceUrl(string $surface): string
    {
        return match ($surface) {
            'buyer criteria'       => $this->buyerCriteriaUrl(),
            'tenant criteria'      => $this->tenantCriteriaUrl(),
            'buyer offer listing'  => $this->offerListingUrl(BuyerAgentAuction::class, BuyerAgentAuctionMeta::class, 'buyer_agent_auction_id', 'offer.listing.buyer.view'),
            'tenant offer listing' => $this->offerListingUrl(TenantAgentAuction::class, TenantAgentAuctionMeta::class, 'tenant_agent_auction_id', 'offer.listing.tenant.view'),
        };
    }

    private function composerReturns(array $restatements, array $calculated): void
    {
        $this->mock(LocationIntelligenceComposer::class, function ($mock) use ($restatements, $calculated) {
            $mock->shouldReceive('compose')->andReturn(['summary' => [
                'summary_lines'    => array_merge($restatements, $calculated),
                'calculated_lines' => $calculated,
            ]]);
        });
    }

    /** @dataProvider intelligenceSurfaces */
    public function test_location_intelligence_shows_calculated_lines_and_never_restates_the_criteria(string $surface): void
    {
        $this->composerReturns(['Highly targeted location preferences.', 'Preferences defined by city or municipality.'], ['Flood Zone: AE']);

        $html = $this->page($this->surfaceUrl($surface), $this->owner);

        $this->assertStringContainsString('Flood Zone: AE', $html);
        $this->assertStringNotContainsString('Highly targeted location preferences.', $html);
        $this->assertStringNotContainsString('Preferences defined by city or municipality.', $html);
    }

    /** @dataProvider intelligenceSurfaces */
    public function test_a_card_that_would_only_restate_the_criteria_is_not_rendered(string $surface): void
    {
        $this->composerReturns(['Highly targeted location preferences.'], []);

        $html = $this->page($this->surfaceUrl($surface), $this->owner);

        $this->assertStringNotContainsString('Highly targeted location preferences.', $html);
        $this->assertStringNotContainsString('<i class="fa-solid fa-location-dot me-1"></i> Location Intelligence', $html);
    }
}

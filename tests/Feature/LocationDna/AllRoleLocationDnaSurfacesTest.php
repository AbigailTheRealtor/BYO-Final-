<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Location DNA across all four listing roles, residential and commercial.
 *
 * Buyer and Tenant SEARCH: their detail map draws radius circles, custom areas and
 * Important Place pins and rings, and the page must say in words what each one is —
 * address, distance, place type — without a coordinate or a JSON fragment in sight.
 *
 * Seller and Landlord OFFER one property: their map is a single pin, it follows the same
 * address-display permission as the address line, and no search criteria appear.
 *
 * Every Buyer/Tenant case runs under BOTH renderers, because the Google path is still the
 * default and it is the one that used to drop every flat radius row.
 */
class AllRoleLocationDnaSurfacesTest extends TestCase
{
    use DatabaseTransactions;

    private const RADIUS_A = ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5];
    private const RADIUS_B = ['address' => '11687 Oxford St N, Seminole, FL 33772', 'lat' => 27.8403, 'lng' => -82.7876, 'radius_miles' => 5];
    private const WORK     = [
        'type' => 'Work', 'type_other' => '', 'address' => '116 8th St E, Tierra Verde, FL 33715',
        'lat' => 27.6917, 'lng' => -82.7215, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving',
    ];

    private const ROLES = [
        'buyer'    => [BuyerAgentAuction::class, BuyerAgentAuctionMeta::class, 'buyer_agent_auction_id', 'offer.listing.buyer.view'],
        'tenant'   => [TenantAgentAuction::class, TenantAgentAuctionMeta::class, 'tenant_agent_auction_id', 'offer.listing.tenant.view'],
        'seller'   => [SellerAgentAuction::class, SellerAgentAuctionMeta::class, 'seller_agent_auction_id', 'offer.listing.seller.view'],
        'landlord' => [LandlordAgentAuction::class, LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id', 'offer.listing.landlord.view'],
    ];

    private const COMMERCIAL = [
        'buyer' => 'Commercial', 'tenant' => 'Commercial Property', 'seller' => 'Commercial', 'landlord' => 'Commercial Property',
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    private function renderer(bool $maplibre): void
    {
        config([
            'spatial_basemap.maplibre_renderer_enabled'  => $maplibre,
            'spatial_basemap.maplibre_renderer_surfaces' => ['hire_buyer', 'hire_tenant', 'create_buyer', 'create_tenant', 'buyer_criteria', 'tenant_criteria', 'display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
            // A placeholder so the Google branch renders its script; nothing is requested.
            // The BROWSER credential — the map components never read GOOGLE_PLACES_API_KEY.
            'google_maps_browser.enabled'                => true,
            'google_maps_browser.key'                    => $maplibre ? '' : 'test-placeholder-key',
        ]);
    }

    /** Create an approved, published listing for $role with the given metas. Returns its id. */
    private function listing(string $role, array $meta, array $attributes = []): int
    {
        [$model, $metaModel, $fk] = self::ROLES[$role];

        $auction = $model::forceCreate(array_merge([
            'user_id'     => $this->owner->id,
            'title'       => ucfirst($role) . ' Location DNA listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ], $attributes));

        foreach (['workflow_type' => 'offer_listing'] + $meta as $key => $value) {
            $metaModel::create([
                $fk          => $auction->id,
                'meta_key'   => $key,
                'meta_value' => is_array($value) ? json_encode($value) : $value,
            ]);
        }

        return $auction->id;
    }

    private function renderDetail(string $role, int $id, ?User $viewer = null): string
    {
        return $this->actingAs($viewer ?? $this->owner)
            ->get(route(self::ROLES[$role][3], $id))
            ->assertOk()
            ->getContent();
    }

    /** The written criteria summary — the part of the page a reader reads. */
    private function summary(string $html): string
    {
        $start = strpos($html, 'data-ldna-criteria-summary');
        $this->assertNotFalse($start, 'The Location DNA criteria summary must render');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    private function searchListing(string $role, array $prefs = [], array $places = [self::WORK], array $extra = []): int
    {
        return $this->listing($role, array_merge([
            'location_dna_preferences' => array_merge([
                'radius_searches'   => [self::RADIUS_A, self::RADIUS_B],
                'polygons'          => [],
                'cities'            => [],
                'zip_codes'         => [],
                'flexible_location' => false,
                'location_notes'    => '',
            ], $prefs),
            'important_places_json' => $places,
            'property_type'         => self::COMMERCIAL[$role],
        ], $extra));
    }

    // ── Buyer + Tenant: the map is explained ────────────────────────────────

    public static function searchRolesAndRenderers(): array
    {
        return [
            'buyer, MapLibre'  => ['buyer', true],
            'buyer, Google'    => ['buyer', false],
            'tenant, MapLibre' => ['tenant', true],
            'tenant, Google'   => ['tenant', false],
        ];
    }

    /** @dataProvider searchRolesAndRenderers */
    public function test_the_detail_page_names_every_radius_and_important_place_it_draws(string $role, bool $maplibre): void
    {
        $this->renderer($maplibre);

        // Commercial on purpose: Location DNA must not disappear with the property type.
        $summary = $this->summary($this->renderDetail($role, $this->searchListing($role)));

        $this->assertStringContainsString('Radius Searches', $summary);
        $this->assertStringContainsString('315 E Madison St, Tampa, FL 33602', $summary);
        $this->assertStringContainsString('11687 Oxford St N, Seminole, FL 33772', $summary);

        $this->assertStringContainsString('Important Place', $summary);
        $this->assertStringContainsString('Work', $summary);
        $this->assertStringContainsString('116 8th St E, Tierra Verde, FL 33715', $summary);

        // Two radii and the place, each within 5 miles.
        $this->assertSame(3, substr_count($summary, 'Within 5 miles'));

        foreach (['27.9506', '-82.4572', '27.8403', '-82.7876', '27.6917', '-82.7215', '"lat"', '"lng"', 'radius_miles', '{', 'google', 'census'] as $raw) {
            $this->assertStringNotContainsStringIgnoringCase($raw, $summary, "The summary must not expose [{$raw}]");
        }
    }

    /** @dataProvider searchRolesAndRenderers */
    public function test_the_google_map_is_handed_every_flat_radius_row(string $role, bool $maplibre): void
    {
        if ($maplibre) {
            $this->markTestSkipped('MapLibre reads both radius shapes natively; this pins the Google path.');
        }

        $this->renderer(false);
        $html = $this->renderDetail($role, $this->searchListing($role));

        // The Google script used to require `r.center`, and every row the widget saves is flat,
        // so it drew nothing. It now receives rows normalised through RadiusSearchRow.
        $this->assertStringContainsString('"center":{"lat":27.9506,"lng":-82.4572}', $html);
        $this->assertStringContainsString('"center":{"lat":27.8403,"lng":-82.7876}', $html);
        $this->assertStringContainsString('window.ldnaDisplayDrawGooglePlaces', $html);
        $this->assertSame(1, substr_count($html, 'window.ldnaDisplayDrawGooglePlaces = function'),
            'The places helper is defined once per page');
    }

    /** @dataProvider searchRolesAndRenderers */
    public function test_custom_areas_flexibility_and_notes_are_shown(string $role, bool $maplibre): void
    {
        $this->renderer($maplibre);

        $html = $this->renderDetail($role, $this->searchListing($role, [
            'polygons' => [['label' => 'Area 1', 'path' => [
                ['lat' => 27.70, 'lng' => -82.70], ['lat' => 27.80, 'lng' => -82.70], ['lat' => 27.80, 'lng' => -82.60],
            ]]],
            'flexible_location' => true,
            'location_notes'    => 'Walkable to the waterfront, please.',
        ]));

        $summary = $this->summary($html);
        $this->assertStringContainsString('Custom Search Area', $summary);
        $this->assertStringContainsString('shown on the map', $summary);
        $this->assertStringContainsString('Location Flexible', $html);
        $this->assertStringContainsString('Walkable to the waterfront, please.', $html);
    }

    /** @dataProvider searchRolesAndRenderers */
    public function test_a_historical_minutes_place_is_shown_as_minutes_and_never_as_miles(string $role, bool $maplibre): void
    {
        $this->renderer($maplibre);

        $summary = $this->summary($this->renderDetail($role, $this->searchListing($role, ['radius_searches' => []], [
            ['type' => 'School', 'type_other' => '', 'address' => '1 School Rd, Tampa, FL 33602', 'lat' => 27.95, 'lng' => -82.46,
             'distance_pref' => 'minutes', 'distance_value' => 25, 'travel_mode' => 'transit'],
        ])));

        $this->assertStringContainsString('1 School Rd, Tampa, FL 33602', $summary);
        $this->assertStringContainsString('Within 25 minutes (travel time)', $summary);
        $this->assertStringContainsString('no longer offered', $summary);
        $this->assertStringNotContainsString('25 miles', $summary);
    }

    public function test_the_tenant_hero_badge_reads_the_flexible_answer_not_the_city_list(): void
    {
        $this->renderer(true);

        $rigid = $this->renderDetail('tenant', $this->searchListing('tenant', ['cities' => ['Tampa'], 'flexible_location' => false], [], ['cities' => ['Tampa']]));
        $this->assertStringNotContainsString('Location Flexible', $rigid, 'Naming a city is not asking for flexibility');

        $flexible = $this->renderDetail('tenant', $this->searchListing('tenant', ['cities' => ['Tampa'], 'flexible_location' => true], [], ['cities' => ['Tampa']]));
        $this->assertStringContainsString('Location Flexible', $flexible);
    }

    // ── Seller + Landlord: one property pin, gated like the address ─────────

    public static function propertyRoles(): array
    {
        return ['seller' => ['seller'], 'landlord' => ['landlord']];
    }

    private function propertyListing(string $role, array $meta = []): int
    {
        return $this->listing($role, array_merge([
            'address'       => '200 Central Ave',
            'property_city' => 'St. Petersburg',
            'property_lat'  => '27.7712',
            'property_lng'  => '-82.6390',
            'property_type' => self::COMMERCIAL[$role],
        ], $meta));
    }

    private function hasPin(string $html): bool
    {
        return (bool) preg_match('/id="ldna-display-[a-z0-9]+-pin"/', $html);
    }

    /** @dataProvider propertyRoles */
    public function test_a_commercial_property_pin_renders_with_no_search_criteria(string $role): void
    {
        $this->renderer(false);

        $html = $this->renderDetail($role, $this->propertyListing($role), User::factory()->create());

        $this->assertTrue($this->hasPin($html), 'The property pin must render for a commercial listing');
        $this->assertStringNotContainsString('data-ldna-criteria-summary', $html, 'A property listing carries no search criteria');
        $this->assertStringNotContainsString('Radius Search', $html);
    }

    /** @dataProvider propertyRoles */
    public function test_the_pin_is_withheld_wherever_the_address_is(string $role): void
    {
        $this->renderer(false);

        $id = $this->propertyListing($role, [
            'mls_listing_key'         => 'LK-PIN-PRIVACY',
            'mls_display_permissions' => ['address_display' => false],
        ]);

        $this->assertFalse($this->hasPin($this->renderDetail($role, $id, User::factory()->create())),
            'A visitor must not get the rooftop point of an address the feed withholds');
        $this->assertTrue($this->hasPin($this->renderDetail($role, $id)),
            'The owner always sees their own property');
    }

    /** @dataProvider propertyRoles */
    public function test_the_pin_label_is_text_never_markup(string $role): void
    {
        $this->renderer(false);

        $html = $this->renderDetail($role, $this->propertyListing($role, ['address' => '<img src=x onerror=alert(1)>']));

        $this->assertStringContainsString('iwContent.textContent = String(pinData.label)', $html);
        $this->assertStringNotContainsString("+ pinData.label + '</span>'", $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    // ── Create / Edit forms ──────────────────────────────────────────────────

    public function test_buyer_and_tenant_create_forms_offer_location_dna_without_commute_or_minutes(): void
    {
        $this->renderer(true);

        foreach (['/offer-listing/buyer', '/offer-listing/tenant'] as $url) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="ldna-ip-section"', $html, "{$url} must offer Important Places");
            $this->assertStringContainsString('ldnaAddRadiusSearch()', $html, "{$url} must offer Radius Search");
            $this->assertStringContainsString('function ldnaLookupAddress(address)', $html, "{$url} must use the server-side lookup");

            // What a user SEES or TYPES INTO. The property names themselves still appear in
            // Livewire's serialized component state — the component keeps them on purpose, so a
            // listing that already holds commute values writes them back unchanged.
            foreach (['Commute Preferences', 'Work or School ZIP Code', 'Max Commute Time', 'How Will You Commute',
                      'wire:model.defer="commute_destination_zip"', 'wire:model="commute_destination_zip"',
                      'wire:model.defer="max_commute_minutes"', 'wire:model="max_commute_minutes"',
                      'wire:model="commute_mode"', 'Within minutes', 'Travel Mode'] as $gone) {
                $this->assertStringNotContainsString($gone, $html, "{$url} must not ask about [{$gone}]");
            }
        }
    }

    public function test_seller_and_landlord_create_forms_carry_no_search_criteria(): void
    {
        foreach (['/offer-listing/seller', '/offer-listing/landlord'] as $url) {
            $html = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('id="ldna-ip-section"', $html, "{$url} must not offer Important Places");
            $this->assertStringNotContainsString('ldnaAddRadiusSearch()', $html, "{$url} must not offer Radius Search");
        }
    }
}

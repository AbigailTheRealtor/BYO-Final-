<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Hire Agent detail pages show the Location DNA their listings store.
 *
 * A Hire Agent listing shares its model and meta with the Offer Listing of the same role and
 * stores the same Location DNA — a Buyer/Tenant Hire listing saves search areas, radius searches
 * and Important Places; a Seller/Landlord Hire listing has a property coordinate and a Location
 * DNA row — and none of the four detail pages read any of it. They now render it through the same
 * shared component, summary and renderer as the Offer Listing pages.
 *
 * Every case runs in both layouts (the redesigned cards and the legacy page), because the section
 * is part of what the listing IS, not a rollout detail.
 */
class HireAgentLocationDnaSectionTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTES = [
        'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail'],
        'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction'],
        'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view'],
        'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view'],
    ];

    private const RADIUS_A = ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5];
    private const RADIUS_B = ['address' => '11687 Oxford St N, Seminole, FL 33772', 'lat' => 27.8403, 'lng' => -82.7876, 'radius_miles' => 5];
    private const WORK     = [
        'type' => 'Work', 'type_other' => '', 'address' => '116 8th St E, Tierra Verde, FL 33715',
        'lat' => 27.6917, 'lng' => -82.7215, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving',
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_type' => 'seller']);

        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => ['display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);
    }

    private function layout(bool $redesign): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => $redesign,
            'hire_agent_detail.redesign_roles'   => $redesign ? ['seller', 'buyer', 'landlord', 'tenant'] : [],
        ]);
    }

    private function listing(string $role, array $meta): Model
    {
        [$model] = self::ROUTES[$role];

        $attributes = [
            'user_id'     => $this->owner->id,
            'title'       => ucfirst($role) . ' hire listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = ucfirst($role) . ' hire listing';
        }

        $listing = $model::forceCreate($attributes);

        foreach (['workflow_type' => 'hire_agent', 'listing_title' => 'Hire listing'] + $meta as $key => $value) {
            $listing->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return $listing->fresh();
    }

    private function render(string $role, Model $listing, ?User $viewer = null): string
    {
        return $this->actingAs($viewer ?? $this->owner)
            ->get(route(self::ROUTES[$role][1], $listing->id))
            ->assertOk()
            ->getContent();
    }

    /**
     * The section is present. The redesigned card carries the anchor id; the legacy branch of the
     * section component deliberately emits NO id (HireAgentSectionCardDomEquivalenceTest pins that),
     * so there the section is identified by its heading.
     */
    private function assertSection(string $html, bool $redesign, string $title): void
    {
        $this->assertStringContainsString($redesign ? 'id="hla-section-location-dna"' : $title, $html);
    }

    /** No Location DNA rendered at all — the shared component's own markers, in either layout. */
    private function assertNoLocationDna(string $html): void
    {
        foreach (['id="hla-section-location-dna"', 'class="ldna-hero', 'ldna-fallback-box', 'data-ldna-criteria-summary', 'data-ldna-property-pin'] as $marker) {
            $this->assertStringNotContainsString($marker, $html, "Nothing to show must render nothing — found [{$marker}]");
        }
    }

    private function summary(string $html): string
    {
        $start = strpos($html, 'data-ldna-criteria-summary');
        $this->assertNotFalse($start, 'The written Location DNA summary must render');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public static function searchRolesAndLayouts(): array
    {
        return [
            'buyer, redesigned'  => ['buyer', true],
            'buyer, legacy'      => ['buyer', false],
            'tenant, redesigned' => ['tenant', true],
            'tenant, legacy'     => ['tenant', false],
        ];
    }

    /** @dataProvider searchRolesAndLayouts */
    public function test_a_search_listing_shows_its_radius_searches_and_important_places(string $role, bool $redesign): void
    {
        $this->layout($redesign);

        $html = $this->render($role, $this->listing($role, [
            'location_dna_preferences' => ['radius_searches' => [self::RADIUS_A, self::RADIUS_B], 'polygons' => [], 'flexible_location' => false],
            'important_places_json'    => [self::WORK],
            // Commercial on purpose: Location DNA must not disappear with the property type.
            'property_type'            => $role === 'tenant' ? 'Commercial Property' : 'Commercial',
        ]));

        $this->assertSection($html, $redesign, 'Location DNA');

        $summary = $this->summary($html);
        $this->assertStringContainsString('315 E Madison St, Tampa, FL 33602', $summary);
        $this->assertStringContainsString('11687 Oxford St N, Seminole, FL 33772', $summary);
        $this->assertStringContainsString('Work', $summary);
        $this->assertStringContainsString('116 8th St E, Tierra Verde, FL 33715', $summary);
        $this->assertSame(3, substr_count($summary, 'Within 5 miles'));

        foreach (['27.9506', '-82.4572', '27.6917', '"lat"', 'radius_miles', '{'] as $raw) {
            $this->assertStringNotContainsString($raw, $summary, "The summary must not expose [{$raw}]");
        }
    }

    /** @dataProvider searchRolesAndLayouts */
    public function test_a_search_listing_with_no_location_dna_gets_no_section(string $role, bool $redesign): void
    {
        $this->layout($redesign);

        $this->assertNoLocationDna($this->render($role, $this->listing($role, ['property_type' => 'Residential Property'])));
    }

    public static function layouts(): array
    {
        return ['redesigned' => [true], 'legacy' => [false]];
    }

    /** @dataProvider layouts */
    public function test_the_seller_pin_is_shown_to_the_owner_and_withheld_from_everyone_else(bool $redesign): void
    {
        $this->layout($redesign);

        $listing = $this->listing('seller', [
            'address'       => '200 Central Ave',
            'property_city' => 'St. Petersburg',
            'property_lat'  => '27.7712',
            'property_lng'  => '-82.6390',
            'property_type' => 'Commercial',
        ]);

        $owner = $this->render('seller', $listing);
        $this->assertSection($owner, $redesign, 'Property Location');
        $this->assertStringContainsString('data-ldna-property-pin', $owner);
        $this->assertStringNotContainsString('data-ldna-criteria-summary', $owner, 'A property listing carries no search criteria');

        // This page never publishes the seller's street address, so the exact point is withheld.
        $visitor = $this->render('seller', $listing, User::factory()->create(['user_type' => 'agent']));
        $this->assertNoLocationDna($visitor);
        $this->assertStringNotContainsString('27.7712', $visitor);
    }

    /** @dataProvider layouts */
    public function test_the_landlord_pin_follows_whether_the_page_publishes_the_address(bool $redesign): void
    {
        $this->layout($redesign);
        $visitor = User::factory()->create(['user_type' => 'agent']);

        $published = $this->listing('landlord', [
            'address'       => '200 Central Ave',
            'property_lat'  => '27.7712',
            'property_lng'  => '-82.6390',
            'property_type' => 'Commercial Property',
        ]);
        $this->assertStringContainsString('data-ldna-property-pin', $this->render('landlord', $published, $visitor),
            'The landlord hero already publishes this address; the pin reveals nothing more');

        $unpublished = $this->listing('landlord', ['property_lat' => '27.7712', 'property_lng' => '-82.6390']);
        $this->assertStringNotContainsString('data-ldna-property-pin', $this->render('landlord', $unpublished, $visitor));
        $this->assertStringContainsString('data-ldna-property-pin', $this->render('landlord', $unpublished));
    }

    public function test_a_property_listing_with_no_coordinate_gets_no_section(): void
    {
        $this->layout(true);

        foreach (['seller', 'landlord'] as $role) {
            $this->assertNoLocationDna($this->render($role, $this->listing($role, ['address' => '200 Central Ave'])));
        }
    }
}

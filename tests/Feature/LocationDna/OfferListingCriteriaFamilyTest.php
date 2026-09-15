<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Buyer and Tenant Offer Listing detail pages belong to the Criteria page family.
 *
 * Same frame as the Buyer/Tenant Criteria pages: `container listingDescription`, an 8/4 row, the
 * approved Search Areas component and one description card on the left, the title and every action
 * on the right. The legacy hero/snapshot, Quick Actions grid, scroll-spy tabs, detached sticky
 * sidebar, mobile bar and the page-level Google loader (and its "not configured" strip) are gone.
 * Every action still reaches the same route or modal, owner tools stay owner-only, Important Places
 * stay private, and a section whose rows are all empty renders no heading.
 */
class OfferListingCriteriaFamilyTest extends TestCase
{
    use DatabaseTransactions;

    private const PLACES = [
        ['type' => 'Work', 'type_other' => '', 'address' => 'QA test point — downtown Orlando (not a real address)',
         'lat' => 28.5383, 'lng' => -81.3792, 'distance_pref' => 'miles', 'distance_value' => 3, 'travel_mode' => 'driving'],
    ];

    private const ROLES = [
        'buyer'  => ['model' => BuyerAgentAuction::class,  'meta' => BuyerAgentAuctionMeta::class,  'fk' => 'buyer_agent_auction_id',  'p' => 'bol', 'type' => 'Residential',          'price' => 'maximum_budget'],
        'tenant' => ['model' => TenantAgentAuction::class, 'meta' => TenantAgentAuctionMeta::class, 'fk' => 'tenant_agent_auction_id', 'p' => 'tcl', 'type' => 'Residential Property', 'price' => 'budget'],
    ];

    private User $owner;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create();
        $this->stranger = User::factory()->create(['user_type' => 'agent']);

        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => ['display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);
    }

    public static function roles(): array
    {
        return ['buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    private function listing(string $role, array $meta = []): int
    {
        $r = self::ROLES[$role];
        $auction = $r['model']::forceCreate([
            'user_id' => $this->owner->id, 'title' => 'Family fixture', 'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $defaults = [
            'workflow_type'            => 'offer_listing',
            'property_type'            => $r['type'],
            $r['price']                => '500000',
            'location_dna_preferences' => json_encode(['cities' => ['ORLANDO'], 'zip_codes' => [], 'radius_searches' => [], 'polygons' => [], 'flexible_location' => false, 'location_notes' => '']),
            'important_places_json'    => json_encode(self::PLACES),
        ];
        foreach (array_merge($defaults, $meta) as $key => $value) {
            $r['meta']::create([$r['fk'] => $auction->id, 'meta_key' => $key, 'meta_value' => $value]);
        }

        return $auction->id;
    }

    private function page(string $role, int $id, ?User $viewer): string
    {
        $this->app['auth']->forgetGuards();
        $request = $viewer ? $this->actingAs($viewer) : $this;

        return $request->get(route("offer.listing.{$role}.view", $id))->assertOk()->getContent();
    }

    /** The markup between two needles, both of which must exist. */
    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "Missing {$from}");
        $end = strpos($html, $to, $start);
        $this->assertNotFalse($end, "Missing {$to} after {$from}");

        return substr($html, $start, $end - $start);
    }

    /** @dataProvider roles */
    public function test_the_page_uses_the_criteria_frame(string $role): void
    {
        $p    = self::ROLES[$role]['p'];
        $html = $this->page($role, $this->listing($role), null);

        $this->assertStringContainsString('assets/css/listingDescription.css', $html);
        $this->assertStringContainsString("<div class=\"container listingDescription {$p}-view-page\">", $html);

        $left = $this->between($html, 'col-sm-12 col-md-8 col-lg-8 leftCol', 'col-sm-12 col-md-4 col-lg-4 rightCol');
        $this->assertStringContainsString('data-ldna-criteria-summary', $left, 'Search Areas sits in the left column, as on the Criteria pages.');
        $this->assertStringContainsString("card description {$p}-description", $left);
        $this->assertStringContainsString("<h4 class=\"{$p}-crit-title\">Listing Overview:</h4>", $left);

        // Rows are the Criteria row: check icon, label, value — not the old label/value grid.
        $this->assertStringContainsString("<div class=\"{$p}-field\"><i class=\"fa-regular fa-square-check\" aria-hidden=\"true\"></i> <strong>Property Type:</strong>", $left);
        $this->assertStringNotContainsString('col-md-5 text-muted fw-semibold', $html);
    }

    /** @dataProvider roles */
    public function test_the_legacy_presentation_is_gone(string $role): void
    {
        $p    = self::ROLES[$role]['p'];
        $html = $this->page($role, $this->listing($role), null);

        foreach ([
            "class=\"{$p}-hero",            // hero + snapshot table
            'Criteria Snapshot',
            "id=\"{$p}-interaction-hub\"",   // Quick Actions grid
            'Quick Actions &amp; Listing Info',
            "id=\"{$p}NavTabs\"",            // scroll-spy tabs
            "class=\"{$p}-sticky-card\"",    // detached sidebar
            "class=\"{$p}-mobile-bar",       // fixed mobile bar
            'MapsReady',                     // page-level Google loader …
            'Google Maps is not configured', // … and the strip it painted
            'QR Code — coming soon',         // placeholder QR / Embed pills (a real QR replaces them)
        ] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html, "Legacy presentation still rendered: {$legacy}");
        }
    }

    /** @dataProvider roles */
    public function test_every_action_is_still_reachable_from_the_right_column(string $role): void
    {
        $p     = self::ROLES[$role]['p'];
        $html  = $this->page($role, $this->listing($role), $this->stranger);
        $right = $this->between($html, 'col-sm-12 col-md-4 col-lg-4 rightCol', "id=\"{$p}QuestionModal\"");

        $this->assertStringContainsString('<h1>Family fixture</h1>', $right);
        $this->assertStringContainsString('action="' . route('offers.store') . '"', $right);
        $this->assertStringContainsString("name=\"role\" value=\"{$role}\"", $right);
        $this->assertStringContainsString("name=\"listing_type\" value=\"{$role}_criteria\"", $right);
        $this->assertSame(1, substr_count($html, 'action="' . route('offers.store') . '"'), 'One Respond form, not four copies.');

        foreach (['QuestionModal', 'ShowingModal', 'AiModal', 'HireAgentModal'] as $modal) {
            $this->assertStringContainsString("data-bs-target=\"#{$p}{$modal}\"", $right, "{$modal} is reachable from the right column.");
            $this->assertStringContainsString("id=\"{$p}{$modal}\"", $html, "{$modal} still renders.");
        }
        $this->assertStringContainsString(route("offer.listing.{$role}.searchListing"), $right);
        $this->assertStringContainsString("id=\"{$p}HubCopyBtn\"", $right);
        $this->assertStringContainsString('<svg', $right, 'The share card carries a QR code, as on the Criteria pages.');
    }

    /** @dataProvider roles */
    public function test_edit_listing_is_owner_only(string $role): void
    {
        $id   = $this->listing($role);
        $edit = route("offer.listing.{$role}.edit", ['auctionId' => $id]);

        $this->assertStringContainsString($edit, $this->page($role, $id, $this->owner));
        $this->assertStringNotContainsString($edit, $this->page($role, $id, $this->stranger));
        $this->assertStringNotContainsString($edit, $this->page($role, $id, null));
    }

    /** @dataProvider roles */
    public function test_the_approved_search_areas_component_keeps_important_places_private(string $role): void
    {
        $id = $this->listing($role);

        foreach ([null, $this->stranger] as $viewer) {
            $html = $this->page($role, $id, $viewer);
            $this->assertStringContainsString('data-ldna-criteria-places-private', $html);
            $this->assertStringNotContainsString('downtown Orlando', $html);
            $this->assertStringNotContainsString('28.5383', $html);
        }

        $owner = $this->page($role, $id, $this->owner);
        $this->assertStringContainsString('data-ldna-criteria-place-exact', $owner);
        $this->assertStringContainsString('downtown Orlando', $owner);
    }

    public function test_buyer_sections_without_content_render_no_heading(): void
    {
        $html = $this->page('buyer', $this->listing('buyer'), null);

        $this->assertStringContainsString('<h4 class="bol-crit-title">Purchase Criteria:</h4>', $html);
        foreach (['Financing Details:', 'Desired Property Features:', 'Additional Purchase Terms:', 'Contact Information:'] as $empty) {
            $this->assertStringNotContainsString("<h4 class=\"bol-crit-title\">{$empty}</h4>", $html);
        }
    }

    public function test_a_tenant_section_whose_only_value_is_hidden_renders_no_heading(): void
    {
        // `number_occupant` opens the Pets & Occupancy gate but is deliberately never shown, so the
        // section used to render as a heading over nothing.
        $html = $this->page('tenant', $this->listing('tenant', ['number_occupant' => '3']), null);

        $this->assertStringNotContainsString('Pets &amp; Occupancy:', $html);
        $this->assertStringNotContainsString('id="section-pets"', $html);
    }

    public function test_tenant_screening_badges_are_owner_only(): void
    {
        $id = $this->listing('tenant', ['prior_eviction' => 'No', 'prior_felony' => 'No']);

        foreach ([null, $this->stranger] as $viewer) {
            $html = $this->page('tenant', $id, $viewer);
            $this->assertStringNotContainsString('No Evictions', $html);
            $this->assertStringNotContainsString('No Prior Felony', $html);
        }

        $owner = $this->page('tenant', $id, $this->owner);
        $this->assertStringContainsString('No Evictions', $owner);
        $this->assertStringContainsString('No Prior Felony', $owner);
    }
}

<?php

namespace Tests\Unit\Spatial;

use Tests\TestCase;

/**
 * Phase 2B — STRUCTURAL characterisation of the Search Areas widget and its hosts.
 *
 * ⚠️  READ THIS BEFORE TRUSTING A GREEN RUN  ⚠️
 * ------------------------------------------------------------------------
 * These tests assert on SOURCE TEXT. They do not execute one line of
 * JavaScript, render one Blade template, or open one browser.
 *
 * This project has no JavaScript test runner. The Search Areas blob is
 * serialised in the browser by `window.ldnaSerialize`, and that function is
 * therefore beyond what any test in this repository can execute. Structural
 * assertions catch a key being renamed or a binding being deleted. They CANNOT
 * catch a serialisation bug, a precision loss, an event that stops firing, or a
 * geometry shape that changes at runtime.
 *
 * A green run here means "the source still says what it said". It does NOT mean
 * "the widget works". Reporting it as JavaScript coverage would be false.
 *
 * This is the same technique, and the same limitation, as the Phase 1 payload-
 * guard assertions in tests/Feature/Location/SharedAddressAutocompleteAdoptionTest.
 *
 * FINDINGS
 * --------
 * Several tests below are named `test_finding_*`. They record divergence that
 * exists at this commit. Phase 2B is characterisation-only: divergence is
 * RECORDED, never normalised. Each finding is written up in
 * docs/spatial/phase-2b-geometry-contract.md.
 */
class SearchAreasWidgetContractTest extends TestCase
{
    /** The nine keys of the `location_dna_preferences` blob. */
    private const BLOB_KEYS = [
        'cities', 'zip_codes', 'neighborhoods', 'counties', 'state',
        'polygons', 'radius_searches', 'flexible_location', 'location_notes',
    ];

    /** Every Blade file that includes the map widget. */
    private const MAP_INPUT_INCLUDE_SITES = [
        // Livewire hosts — Hire flows (use the shared bridge partial)
        'livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php',
        'livewire/tenant-agent-auction-tabs/commission-based/property-details.blade.php',
        // Livewire hosts — Offer flows (carry their own inline bridge; see FINDING 2B-4)
        'livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php',
        'livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php',
        // Legacy non-Livewire criteria pages (plain form POST; see FINDING 2B-5)
        'buyer_criteria/add.blade.php',
        'buyer_criteria/edit.blade.php',
        'tenant_criteria/add.blade.php',
        'tenant_criteria/edit.blade.php',
    ];

    private function viewSource(string $relative): string
    {
        $path = base_path('resources/views/' . $relative);
        $this->assertFileExists($path, "Expected view is missing: {$relative}");

        return file_get_contents($path);
    }

    private function mapInput(): string
    {
        return $this->viewSource('partials/location-dna/map-input.blade.php');
    }

    private function bridge(): string
    {
        return $this->viewSource('partials/location-dna/search-areas-bridge.blade.php');
    }

    private function source(string $relative): string
    {
        return file_get_contents(base_path($relative));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // The widget's own contract
    // ─────────────────────────────────────────────────────────────────────────

    /** All nine blob keys are still referenced by the widget. */
    public function test_widget_references_every_blob_key(): void
    {
        $source = $this->mapInput();

        foreach (self::BLOB_KEYS as $key) {
            $this->assertStringContainsString(
                $key,
                $source,
                "Blob key '{$key}' is no longer referenced by the map widget."
            );
        }
    }

    /**
     * `ldnaSerialize` is the single serialisation entry point and writes the
     * blob into `#ldna-json-field`. Every transport below reads that one field,
     * so its id is load-bearing across all eight include sites.
     */
    public function test_serialiser_writes_the_blob_into_the_json_field(): void
    {
        $source = $this->mapInput();

        $this->assertStringContainsString('window.ldnaSerialize = function', $source);
        $this->assertStringContainsString("id=\"ldna-json-field\"", $source);
        $this->assertStringContainsString("getElementById('ldna-json-field')", $source);
        $this->assertStringContainsString('JSON.stringify(ldnaState)', $source);
    }

    /**
     * The geometry shapes `ldnaSerialize` emits, asserted so a silent change of
     * shape is caught. These are the exact structures the PHP characterisation
     * fixtures mirror — if this test and those fixtures ever disagree, the
     * fixtures are lying about what the widget produces.
     *
     * polygons        → { label, path: [{lat, lng}] }
     * radius_searches → { lat, lng, radius_miles, address | label }
     */
    public function test_serialiser_emits_the_documented_geometry_shapes(): void
    {
        $source = $this->mapInput();

        $this->assertStringContainsString('ldnaState.polygons.push({ label: item.label, path: path });', $source);
        $this->assertStringContainsString('radius_miles: rm', $source);
        $this->assertStringContainsString("entry.address = item.data.address;", $source);
        $this->assertStringContainsString('entry.label   = item.label;', $source);

        // Radius is stored in MILES, converted from the overlay's metres. The
        // divisor is the contract: a renderer swap that reports metres would
        // silently multiply every saved radius by ~1609.
        $this->assertStringContainsString('1609.34', $source);
    }

    /** Both list-valued geometry collections are reset before rebuild, never appended to. */
    public function test_serialiser_rebuilds_geometry_collections_from_scratch(): void
    {
        $source = $this->mapInput();

        $this->assertStringContainsString('ldnaState.polygons        = [];', $source);
        $this->assertStringContainsString('ldnaState.radius_searches = [];', $source);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transport
    // ─────────────────────────────────────────────────────────────────────────

    /** The shared bridge binds the blob to the host's Livewire property. */
    public function test_shared_bridge_binds_the_blob_to_the_livewire_property(): void
    {
        $source = $this->bridge();

        $this->assertStringContainsString(
            'wire:model.defer="location_dna_preferences_json"',
            $source
        );
        $this->assertStringContainsString("id=\"ldna-livewire-bridge\"", $source);
        $this->assertStringContainsString("getElementById('ldna-json-field')", $source);
    }

    /**
     * The bridge injects the blob into Livewire's updateQueue as a `syncInput`
     * on `message.sent`, rather than mutating `component.data`.
     *
     * Characterised because the partial documents this as the ONLY reliable
     * path, and because mutating serverMemo.data instead would fail an HMAC
     * check — a 403 or silent rejection rather than an obvious error. Any 2C
     * transport rewrite has to preserve this property or reproduce that bug.
     */
    public function test_bridge_injects_via_sync_input_not_server_memo(): void
    {
        $source = $this->bridge();

        $this->assertStringContainsString("Livewire.hook('message.sent'", $source);
        $this->assertStringContainsString("type: 'syncInput'", $source);
        $this->assertStringContainsString("name: 'location_dna_preferences_json'", $source);
    }

    /** Every enumerated include site actually includes the widget. */
    public function test_every_enumerated_site_includes_the_widget(): void
    {
        foreach (self::MAP_INPUT_INCLUDE_SITES as $site) {
            $this->assertStringContainsString(
                'partials.location-dna.map-input',
                $this->viewSource($site),
                "{$site} no longer includes the map widget."
            );
        }
    }

    /**
     * The enumeration above is COMPLETE — no site includes the widget without
     * being listed. Without this, the list could silently go stale and 2C would
     * rewrite a renderer against an incomplete map of its consumers.
     */
    public function test_the_include_site_enumeration_is_complete(): void
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(base_path('resources/views') . '/', '', $file->getPathname());

            // The bridge partial names the widget in a comment, not an @include.
            if ($relative === 'partials/location-dna/search-areas-bridge.blade.php') {
                continue;
            }

            if (str_contains(file_get_contents($file->getPathname()), "@include('partials.location-dna.map-input'")) {
                $found[] = $relative;
            }
        }

        sort($found);
        $expected = self::MAP_INPUT_INCLUDE_SITES;
        sort($expected);

        $this->assertSame(
            $expected,
            $found,
            'The map-input include sites have changed. Update MAP_INPUT_INCLUDE_SITES and re-verify 2C scope.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINDINGS — recorded, deliberately NOT corrected
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * FINDING 2B-2 · the discrete-mirror contract has FIVE implementations.
     *
     * `HasSearchAreas` is used by the four Hire components only. The four Offer
     * components each carry their own inline copy of
     * `hydrateDiscreteLocationFromBlob()`. The four copies are byte-identical to
     * each other and differ from the trait only by the trait's `property_exists`
     * guards, which the copies omit because their hosts always declare the props.
     *
     * This is the same 5→1 shape Phase 1 resolved for `fillFromGooglePlaces()`,
     * still open for Search Areas. Recorded, not consolidated: consolidation is
     * a refactor, and 2B changes no production file.
     */
    public function test_finding_2b2_five_implementations_of_the_discrete_mirror(): void
    {
        $inline = [
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php',
            'app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php',
            'app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php',
        ];

        foreach ($inline as $file) {
            $source = $this->source($file);

            $this->assertStringContainsString(
                'protected function hydrateDiscreteLocationFromBlob(): void',
                $source,
                "{$file} was expected to carry its own inline copy."
            );
            $this->assertStringNotContainsString(
                'use HasSearchAreas',
                $source,
                "{$file} now uses the trait — FINDING 2B-2 has been resolved; update the docs."
            );
        }

        // And the trait remains the fifth implementation, guards intact.
        $trait = $this->source('app/Http/Livewire/Concerns/HasSearchAreas.php');
        $this->assertStringContainsString("property_exists(\$this, 'state')", $trait);
    }

    /**
     * FINDING 2B-3 · the two Tenant Offer components never write the `cities` mirror.
     *
     * `saveMeta('cities', …)` is present in the trait and in both Buyer Offer
     * components, and absent from both Tenant Offer components. For a Tenant
     * offer listing the discrete `cities` meta therefore goes stale relative to
     * the blob — and `cities` is read by Ask AI, the match engine, filtering and
     * public display.
     *
     * This is the highest-consequence finding in 2B, and it is a PRE-EXISTING
     * defect, not something 2B introduced. It is recorded rather than fixed
     * because fixing it is a behaviour change; it is a candidate for its own
     * scoped task.
     */
    public function test_finding_2b3_tenant_offer_components_omit_the_cities_mirror(): void
    {
        $writes = "saveMeta('cities'";

        $this->assertStringContainsString($writes, $this->source('app/Http/Livewire/Concerns/HasSearchAreas.php'));
        $this->assertStringContainsString($writes, $this->source('app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php'));
        $this->assertStringContainsString($writes, $this->source('app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php'));

        $this->assertStringNotContainsString(
            $writes,
            $this->source('app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php'),
            'FINDING 2B-3 appears to be resolved — update docs/spatial/phase-2b-geometry-contract.md.'
        );
        $this->assertStringNotContainsString(
            $writes,
            $this->source('app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php'),
            'FINDING 2B-3 appears to be resolved — update docs/spatial/phase-2b-geometry-contract.md.'
        );
    }

    /**
     * FINDING 2B-4 · the Livewire bridge is duplicated three ways.
     *
     * The shared `search-areas-bridge` partial is included by the two Hire tabs
     * only. Both Offer tabs carry an inline copy of the same script, each with
     * its own re-entry guard flag. Three flags means three independently
     * evolvable copies of the transport that carries every saved geometry.
     */
    public function test_finding_2b4_three_bridge_implementations_with_distinct_guards(): void
    {
        $this->assertStringContainsString('window._ldnaSearchAreasBridgeReady', $this->bridge());

        $buyerTab  = $this->viewSource('livewire/offer-listing/offer-buyer-tabs/commission-based/property-preferences.blade.php');
        $tenantTab = $this->viewSource('livewire/offer-listing/offer-tenant-tabs/commission-based/property-details.blade.php');

        $this->assertStringContainsString('window._ldnaBuyerBridgeReady', $buyerTab);
        $this->assertStringContainsString('window._ldnaTenantBridgeReady', $tenantTab);

        // Neither Offer tab includes the shared partial.
        $this->assertStringNotContainsString('partials.location-dna.search-areas-bridge', $buyerTab);
        $this->assertStringNotContainsString('partials.location-dna.search-areas-bridge', $tenantTab);
    }

    /**
     * FINDING 2B-5 · the four legacy criteria pages use a different transport entirely.
     *
     * They carry no Livewire bridge at all. The widget's `<textarea
     * name="location_dna_preferences">` is submitted by ordinary form POST.
     * So the blob reaches the server two structurally different ways depending
     * on the page, and 2C must preserve both or it breaks four pages silently.
     */
    public function test_finding_2b5_legacy_criteria_pages_use_plain_form_post(): void
    {
        // The widget exposes the blob as a named form field, not only as a JS target.
        $this->assertStringContainsString(
            'name="location_dna_preferences"',
            $this->mapInput()
        );

        foreach ([
            'buyer_criteria/add.blade.php',
            'buyer_criteria/edit.blade.php',
            'tenant_criteria/add.blade.php',
            'tenant_criteria/edit.blade.php',
        ] as $page) {
            $source = $this->viewSource($page);

            $this->assertStringNotContainsString('partials.location-dna.search-areas-bridge', $source);
            $this->assertStringNotContainsString('ldna-livewire-bridge', $source);
        }
    }

    /**
     * FINDING 2B-6 · the widget's renderer is Google Maps, and that is the
     * concrete form of the Phase 2C licence-ordering blocker.
     *
     * `ldnaSerialize` reads geometry straight off Google overlay objects
     * (`getPath()`, `getCenter()`, `getRadius()`) and geometry edits are driven
     * by `google.maps.event` listeners. Swapping the basemap beneath this
     * displays Google Maps Content over a non-Google basemap.
     *
     * Asserted as a standing reminder rather than as an aspiration: when this
     * test starts failing, the Google renderer is on its way out and D1's
     * licence ordering must already have been satisfied.
     */
    public function test_finding_2b6_widget_geometry_is_read_from_google_overlays(): void
    {
        $source = $this->mapInput();

        $this->assertStringContainsString('google.maps.event', $source);
        $this->assertStringContainsString('item.overlay.getPath()', $source);
        $this->assertStringContainsString('item.overlay.getCenter()', $source);
        $this->assertStringContainsString('item.overlay.getRadius()', $source);
    }

    /**
     * The proof-of-render remains isolated from the Search Areas widget.
     *
     * Phase 2A must not have leaked into a consumer surface, and 2B must not
     * have wired it in. Asserted from the widget's side so the isolation is
     * checked where a breach would actually land.
     */
    public function test_maplibre_proof_is_not_wired_into_the_widget(): void
    {
        $source = $this->mapInput();

        $this->assertStringNotContainsString('maplibre', strtolower($source));
        $this->assertStringNotContainsString('pmtiles', strtolower($source));
    }
}

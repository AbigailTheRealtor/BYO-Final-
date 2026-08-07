<?php

namespace Tests\Feature\Location;

use Tests\TestCase;

/**
 * Phase 1 — the Seller/Landlord listing flows share ONE address autocomplete
 * implementation, and Buyer/Tenant are untouched by it.
 *
 * WHAT THIS GUARDS
 * ----------------
 * The four Seller/Landlord listing blades each carried their own copy of the
 * Google Places listener, bound by DOM id to a street input rendered elsewhere —
 * in a property-preferences partial that is included by SIX listing blades across
 * all four roles. That split is why this migration is script-only: the inputs stay
 * exactly where they are, so the Buyer/Tenant pages that include the same partials
 * render byte-identically to before, while the listener collapses 4 copies -> 1.
 *
 * THE GATE IS THE SAFETY PROPERTY
 * -------------------------------
 * BuyerOfferListing, BuyerOfferListingEdit, TenantOfferListing and
 * TenantOfferListingEdit do NOT implement fillFromResolvedAddress() — not their own,
 * not via HandlesResolvedPropertyAddress. If the shared component were emitted on
 * their pages, selecting a Google suggestion would call a Livewire method that
 * does not exist. The partial therefore gates on an explicit class allowlist AND
 * on method_exists(). Both halves are asserted here.
 *
 * ON THE JAVASCRIPT ASSERTIONS
 * ----------------------------
 * This project has no JS test runner (no jest/vitest, no *.test.js), and adding
 * one is outside this phase. The two payload corrections below are therefore
 * asserted structurally, against the text of the shared script. That is weaker
 * than executing the handler, but it is not vacuous: the offer-listing tree now
 * has zero listeners of its own (asserted) and the component defines exactly one
 * (asserted), so these pin the behaviour all four Seller/Landlord flows get, and a
 * regression reintroducing the old guards would fail them.
 *
 * Scope caveat, stated plainly: roughly 40 Blade files ELSEWHERE in resources/views
 * — legacy criteria pages, standalone hire/bid forms, the Location DNA map partial —
 * still build their own Autocomplete. Phase 1 does not touch them and this file does
 * not assert anything about them. "One listener" is true of the offer-listing flows,
 * not of the codebase.
 *
 * @see resources/views/components/byo-address-autocomplete.blade.php
 */
class SharedAddressAutocompleteAdoptionTest extends TestCase
{
    private const COMPONENT = 'resources/views/components/byo-address-autocomplete.blade.php';

    private const SELLER_PARTIAL = 'resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php';

    private const LANDLORD_PARTIAL = 'resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php';

    /** The four listing blades that used to carry a copy of the listener. */
    private const MIGRATED_BLADES = [
        'resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php',
        'resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php',
        'resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php',
    ];

    private function source(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    // ─── The component's new capability ───────────────────────────────────────

    /**
     * Default behaviour is unchanged. Every pre-Phase-1 consumer — the four Hire
     * Agent flows and the two Tenant auction flows — renders the component with no
     * render-markup attribute, so this is the contract they rely on.
     */
    public function test_the_component_renders_full_markup_by_default(): void
    {
        $html = (string) $this->withViewErrors([])->blade(
            '<x-byo-address-autocomplete street-id="default-street" callback="defaultCb" />'
        );

        $this->assertStringContainsString('id="default-street"', $html);
        $this->assertStringContainsString('wire:model="address"', $html);
        $this->assertStringContainsString('wire:model="unit_address"', $html);
        $this->assertStringContainsString('Street Address:', $html);
    }

    /**
     * Script-only mode renders no inputs. The listener and loader still go out —
     * they live outside the guarded block — which is the whole point of the mode.
     */
    public function test_script_only_mode_renders_no_input_markup(): void
    {
        $html = (string) $this->withViewErrors([])->blade(
            '<x-byo-address-autocomplete street-id="seller-offer-street-address" callback="cb" :render-markup="false" />'
        );

        $this->assertStringNotContainsString('<input', $html);
        $this->assertStringNotContainsString('Street Address:', $html);
        $this->assertStringNotContainsString('Unit / Apt / Suite:', $html);
    }

    /**
     * show-unit already existed and must keep working independently of the new prop.
     */
    public function test_show_unit_still_suppresses_only_the_unit_field(): void
    {
        $html = (string) $this->withViewErrors([])->blade(
            '<x-byo-address-autocomplete street-id="s" callback="c" :show-unit="false" />'
        );

        $this->assertStringContainsString('id="s"', $html);
        $this->assertStringNotContainsString('Unit / Apt / Suite:', $html);
    }

    // ─── Adoption by the two partials ─────────────────────────────────────────

    /**
     * @return array<string,array{string,string,string,array<int,string>}>
     */
    public function partials(): array
    {
        return [
            'Seller' => [
                self::SELLER_PARTIAL,
                'seller-offer-street-address',
                'byoInitSellerOfferPlaces',
                [
                    \App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class,
                    \App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit::class,
                ],
            ],
            'Landlord' => [
                self::LANDLORD_PARTIAL,
                'landlord-offer-street-address',
                'byoInitLandlordOfferPlaces',
                [
                    \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class,
                    \App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit::class,
                ],
            ],
        ];
    }

    /**
     * Each partial invokes the shared component in script-only mode, with the same
     * DOM id and callback name its blades used before the migration.
     *
     * @dataProvider partials
     */
    public function test_each_partial_invokes_the_shared_component_script_only(
        string $partial,
        string $streetId,
        string $callback
    ): void {
        $source = $this->source($partial);

        $this->assertStringContainsString('<x-byo-address-autocomplete', $source);
        $this->assertStringContainsString('street-id="'.$streetId.'"', $source);
        $this->assertStringContainsString('callback="'.$callback.'"', $source);
        $this->assertStringContainsString(':render-markup="false"', $source);
    }

    /**
     * The street and unit inputs stay in the partial, unchanged — that is what keeps
     * the Buyer/Tenant pages byte-identical.
     *
     * @dataProvider partials
     */
    public function test_the_street_and_unit_field_contracts_are_preserved(
        string $partial,
        string $streetId
    ): void {
        $source = $this->source($partial);

        $this->assertStringContainsString('id="'.$streetId.'"', $source);
        $this->assertStringContainsString('wire:model="address"', $source);
        $this->assertStringContainsString('wire:model="unit_address"', $source);
        $this->assertStringContainsString('<x-address-assist-notice', $source);
        $this->assertStringContainsString("@error('address')", $source);
    }

    // ─── The gate ─────────────────────────────────────────────────────────────

    /**
     * Every class the gate admits must actually implement the method the listener
     * calls. This is the invariant that makes an unimplemented Livewire call
     * structurally impossible rather than merely unlikely.
     *
     * @dataProvider partials
     */
    public function test_every_gated_host_implements_the_fill_method(
        string $partial,
        string $streetId,
        string $callback,
        array $expectedHosts
    ): void {
        $source = $this->source($partial);

        foreach ($expectedHosts as $host) {
            $this->assertStringContainsString(
                '\\'.$host.'::class',
                $source,
                "{$host} must be listed in the gate"
            );
            $this->assertTrue(
                method_exists($host, 'fillFromResolvedAddress'),
                "{$host} is gated in but does not implement fillFromResolvedAddress()"
            );
        }

        $this->assertStringContainsString(
            "method_exists(\$this, 'fillFromResolvedAddress')",
            $source,
            'the gate must also assert the method exists at render time'
        );
    }

    /**
     * @return array<string,array{class-string}>
     */
    public function outOfScopeHosts(): array
    {
        return [
            'BuyerOfferListing'      => [\App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class],
            'BuyerOfferListingEdit'  => [\App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit::class],
            'TenantOfferListing'     => [\App\Http\Livewire\OfferListing\Tenant\TenantOfferListing::class],
            'TenantOfferListingEdit' => [\App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit::class],
        ];
    }

    /**
     * Buyer and Tenant hosts include these same partials (when $user_type is
     * 'seller' or 'landlord'). They must not appear in either gate, and they must
     * still not implement the fill method — if a future change gives them one, that
     * is a Phase 1 scope expansion and should be a deliberate decision, not a
     * side effect.
     *
     * @dataProvider outOfScopeHosts
     */
    public function test_buyer_and_tenant_hosts_are_excluded_from_both_gates(string $host): void
    {
        foreach ([self::SELLER_PARTIAL, self::LANDLORD_PARTIAL] as $partial) {
            $this->assertStringNotContainsString(
                '\\'.$host.'::class',
                $this->source($partial),
                "{$host} must not be gated into the shared autocomplete"
            );
        }

        $this->assertFalse(
            method_exists($host, 'fillFromResolvedAddress'),
            "{$host} does not implement fillFromResolvedAddress(); the gate depends on that staying true"
        );
    }

    /**
     * The gate, actually evaluated — not merely grepped.
     *
     * Parses the class allowlist out of each partial, rebuilds the exact predicate
     * the Blade @php block uses, and runs it against a real instance of all eight
     * candidate hosts. This is the check that answers "can the conditional path
     * execute on a Buyer or Tenant page?" with an evaluation rather than a string
     * search: for those four, BOTH clauses must be false.
     *
     * @dataProvider partials
     */
    public function test_the_gate_predicate_admits_only_the_in_scope_hosts(
        string $partial,
        string $streetId,
        string $callback,
        array $expectedHosts
    ): void {
        preg_match(
            '/in_array\(get_class\(\$this\), \[(.*?)\], true\)/s',
            $this->source($partial),
            $m
        );
        $this->assertNotEmpty($m, 'could not locate the gate allowlist in '.$partial);

        preg_match_all('/\\\\([A-Za-z0-9_\\\\]+)::class/', $m[1], $found);
        $allowlist = $found[1];

        $this->assertEqualsCanonicalizing(
            $expectedHosts,
            $allowlist,
            'the gate allowlist must contain exactly the in-scope hosts'
        );

        // The literal predicate from the partial, applied to a real instance.
        $gate = fn (object $host): bool => in_array(get_class($host), $allowlist, true)
            && method_exists($host, 'fillFromResolvedAddress');

        foreach ($expectedHosts as $host) {
            $this->assertTrue($gate(new $host()), "{$host} must be admitted by the gate");
        }

        foreach ($this->outOfScopeHosts() as [$host]) {
            $instance = new $host();

            $this->assertFalse(
                $gate($instance),
                "{$host} must NOT be admitted — the shared component would call a method it lacks"
            );
            $this->assertNotContains(
                $host,
                $allowlist,
                "{$host} must fail the allowlist clause"
            );
            $this->assertFalse(
                method_exists($instance, 'fillFromResolvedAddress'),
                "{$host} must fail the method_exists clause"
            );
        }
    }

    // ─── The duplication is actually gone ─────────────────────────────────────

    /**
     * No listener survives in the four migrated blades.
     */
    public function test_no_inline_autocomplete_javascript_remains(): void
    {
        foreach (self::MIGRATED_BLADES as $blade) {
            $source = $this->source($blade);

            foreach (['places.Autocomplete', 'address_components', 'byoInitSellerOfferPlaces', 'byoInitLandlordOfferPlaces'] as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $source,
                    "{$blade} still carries inline autocomplete JS ({$marker})"
                );
            }
        }
    }

    /**
     * Zero autocomplete listeners remain anywhere under the offer-listing view tree —
     * the Phase 1 surface. If this ever exceeds zero, the fork has come back.
     *
     * SCOPE NOTE, deliberately narrow. Roughly 40 other Blade files elsewhere in
     * resources/views (legacy criteria pages, the standalone hire/bid forms, the
     * Location DNA map partial) still construct their own Autocomplete. They are
     * untouched by Phase 1 and are NOT asserted here — claiming "one listener in
     * the codebase" would be false. Consolidating those is separate work.
     */
    public function test_no_autocomplete_listener_remains_under_the_offer_listing_tree(): void
    {
        $matches  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views/livewire/offer-listing'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                if (str_contains(file_get_contents($file->getPathname()), 'new google.maps.places.Autocomplete(')) {
                    $matches[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $matches,
            'offer-listing views must delegate to the shared component, not build their own listener'
        );
    }

    /**
     * ...and the shared component is where the one listener those flows use lives.
     */
    public function test_the_shared_component_is_the_single_listener_for_these_flows(): void
    {
        $this->assertSame(
            1,
            substr_count($this->source(self::COMPONENT), 'new google.maps.places.Autocomplete('),
            'the shared component must define exactly one listener'
        );
    }

    // ─── The two approved payload corrections ─────────────────────────────────

    /**
     * CORRECTION 1 — address_components present, geometry absent.
     *
     * The four inline copies opened with:
     *     if (!place || !place.geometry || !place.geometry.location) { return; }
     * so a place Google returned without geometry was dropped entirely and the user
     * saw their selection do nothing. The shared component instead fills the textual
     * fields and leaves lat/lng blank.
     *
     * Approved by the owner as a deliberate correctness fix, 2026-07-26.
     */
    public function test_a_place_without_geometry_still_fills_the_textual_fields(): void
    {
        $script = $this->source(self::COMPONENT);

        $this->assertStringNotContainsString(
            '!place.geometry ||',
            $script,
            'the shared listener must not reject a place merely for lacking geometry'
        );

        $this->assertStringContainsString(
            "var lat = (place.geometry && place.geometry.location) ? String(place.geometry.location.lat()) : '';",
            $script,
            'lat must degrade to an empty string rather than throwing or bailing'
        );
        $this->assertStringContainsString(
            "var lng = (place.geometry && place.geometry.location) ? String(place.geometry.location.lng()) : '';",
            $script,
            'lng must degrade to an empty string rather than throwing or bailing'
        );
    }

    /**
     * CORRECTION 2 — geometry present, address_components absent.
     *
     * The four inline copies proceeded past their geometry guard, found no
     * address_components, left every part as '' and then called the fill method
     * anyway — silently wiping the street, city, county, state and ZIP the user had
     * already entered. The shared component returns before calling anything.
     *
     * Approved by the owner as a deliberate correctness fix, 2026-07-26.
     */
    public function test_a_place_without_address_components_does_not_wipe_the_address(): void
    {
        $script = $this->source(self::COMPONENT);

        $this->assertStringContainsString(
            'if (!place || !place.address_components) { return; }',
            $script,
            'the shared listener must return before filling when there are no address components'
        );

        // The guard must precede the fill call — a guard after it would not prevent the wipe.
        $this->assertLessThan(
            strpos($script, 'comp.call('),
            strpos($script, 'if (!place || !place.address_components) { return; }'),
            'the address_components guard must run before the fill call'
        );
    }
}

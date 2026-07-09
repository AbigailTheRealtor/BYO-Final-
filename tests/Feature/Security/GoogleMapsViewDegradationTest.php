<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Phase 0 item 1, view half — the Maps SDK loaders degrade, and never emit broken JS.
 *
 * Two failure modes are under test, and they are different:
 *
 *   1. **No credential.** The SDK must never be injected, a labelled panel must appear,
 *      and any function the page will still call — notably `loadGoogleMapsScript()` —
 *      must exist as a no-op. The four legacy loaders omitted that no-op, so the
 *      deferred-init list threw `ReferenceError: loadGoogleMapsScript is not defined`.
 *
 *   2. **Credential present but rejected** (dead key, blocked referrer). The server
 *      renders the SDK tag, Google refuses it, and `google` is never defined. A
 *      server-side `@if($mapsKey)` cannot help here — only a runtime guard can. That is
 *      why `GoogleMapsBladeGuardTest` asserts the guard inside every entry function.
 *
 * @see erratum E-43 — Phase 0's address degrade is free-text only; no pin confirmation.
 */
class GoogleMapsViewDegradationTest extends TestCase
{
    private const SDK_URL = 'maps.googleapis.com/maps/api/js';

    /** @test */
    public function the_canonical_loader_emits_the_sdk_and_the_auth_telemetry_when_a_key_is_present(): void
    {
        config(['services.google.places_key' => 'AIza-test-key']);

        $html = Blade::render('<x-google-maps-script :callback="\'initialize\'" />');

        $this->assertStringContainsString(self::SDK_URL, $html);
        $this->assertStringContainsString('AIza-test-key', $html);
        $this->assertStringContainsString('callback=initialize', $html);
        $this->assertStringContainsString('gm_authFailure', $html);
    }

    /** @test */
    public function the_auth_telemetry_is_defined_before_the_sdk_script_tag(): void
    {
        // Ordering is load-bearing: Google looks for window.gm_authFailure the moment the
        // SDK evaluates. Defined afterwards, the callback would never fire and the browser
        // key's state would stay unobservable (SIA-D32).
        config(['services.google.places_key' => 'AIza-test-key']);

        $html = Blade::render('<x-google-maps-script />');

        $this->assertLessThan(
            strpos($html, self::SDK_URL),
            strpos($html, 'gm_authFailure'),
            'gm_authFailure must be defined before the Maps SDK script tag.',
        );
    }

    /** @test */
    public function the_canonical_loader_degrades_without_a_key(): void
    {
        config(['services.google.places_key' => null]);

        $html = Blade::render('<x-google-maps-script />');

        $this->assertStringNotContainsString(self::SDK_URL, $html);
        $this->assertStringContainsString('Google Maps is not configured', $html);
        $this->assertStringContainsString('type the address manually', $html);
    }

    /** @test */
    public function the_deferred_loader_defines_the_injector_when_a_key_is_present(): void
    {
        config(['services.google.places_key' => 'AIza-test-key']);

        $html = Blade::render('<x-google-maps-deferred-loader callback="initializeMap" />');

        $this->assertStringContainsString('function loadGoogleMapsScript()', $html);
        $this->assertStringContainsString(self::SDK_URL, $html);
        $this->assertStringContainsString('callback=initializeMap', $html);
        $this->assertStringContainsString('gm_authFailure', $html, 'The deferred loader must carry auth telemetry too.');
    }

    /** @test */
    public function the_deferred_loader_defines_a_no_op_injector_without_a_key(): void
    {
        // THE regression this batch closes. The deferred-init list calls
        // loadGoogleMapsScript() unconditionally; the old @else branch defined nothing.
        config(['services.google.places_key' => null]);

        $html = Blade::render('<x-google-maps-deferred-loader />');

        $this->assertStringContainsString(
            'function loadGoogleMapsScript()',
            $html,
            'Without a no-op, calling loadGoogleMapsScript() throws ReferenceError and kills the handler.',
        );
        $this->assertStringNotContainsString(self::SDK_URL, $html);
        $this->assertStringContainsString('Google Maps is not configured', $html);
    }

    /** @test */
    public function no_loader_leaks_an_empty_key_into_an_sdk_url(): void
    {
        // `src=...key=&libraries=places` is what a naive @if-less loader emits: a request
        // to Google with no credential, answered with an error, on every page view.
        config(['services.google.places_key' => '']);

        foreach (['<x-google-maps-script />', '<x-google-maps-deferred-loader />'] as $tag) {
            $this->assertStringNotContainsString('key=&', Blade::render($tag));
        }
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 9A — B1.1 (rename "Location Preferences Map" → "Search Areas" + helper text)
 * and B1.6 (Radius Search field labels + placeholder) on the shared location partial
 * `resources/views/partials/location-dna/map-input.blade.php`, which is @included by
 * Buyer/Tenant create and the buyer_criteria/tenant_criteria forms.
 *
 * The partial is self-contained (plain HTML + vanilla JS + Blade vars that self-default
 * from $existingLocationDna), so it can be rendered directly without a Livewire context.
 */
class SearchAreasPartialTest extends TestCase
{
    private function render(): string
    {
        return view('partials.location-dna.map-input', ['existingLocationDna' => []])->render();
    }

    public function test_partial_uses_search_areas_heading(): void
    {
        $html = $this->render();

        // B1.1 — renamed section + new helper text
        $this->assertStringContainsString('Search Areas', $html);
        $this->assertStringContainsString('Choose where the property search should focus', $html);
        // Old visible heading is gone (the {{-- --}} doc comment is stripped by Blade)
        $this->assertStringNotContainsString('Location Preferences Map', $html);
    }

    public function test_partial_has_radius_search_labels(): void
    {
        $html = $this->render();

        // B1.6 — labels + address placeholder + button text
        $this->assertStringContainsString('Radius Search Address', $html);
        $this->assertStringContainsString('Radius Miles', $html);
        $this->assertStringContainsString('Enter an address or place for radius search', $html);
        $this->assertStringContainsString('Add Radius Search', $html);
    }

    public function test_partial_core_structure_preserved(): void
    {
        $html = $this->render();

        // Regression — B1.2 controls + ids + JSON sink + handlers intact (no breakage)
        $this->assertStringContainsString('id="ldna-cities-input"', $html);
        $this->assertStringContainsString('id="ldna-zips-input"', $html);
        $this->assertStringContainsString('id="ldna-counties-input"', $html);
        $this->assertStringContainsString('id="ldna-radius-address"', $html);
        $this->assertStringContainsString('id="ldna-radius-miles"', $html);
        $this->assertStringContainsString('name="location_dna_preferences"', $html);
        $this->assertStringContainsString('ldnaAddRadiusSearch()', $html);
    }

    public function test_partial_has_preferred_state_control(): void
    {
        $html = $this->render();

        // B1.2 pos 4 — single Preferred State field with a type-ahead US-states datalist
        $this->assertStringContainsString('Preferred State', $html);
        $this->assertStringContainsString('id="ldna-state-input"', $html);
        // Datalist id is suffixed with the panel id; the states populate it (type-ahead)
        $this->assertStringContainsString('list="ldna-us-states-', $html);
        $this->assertStringContainsString('<datalist id="ldna-us-states-', $html);
        $this->assertStringContainsString('<option value="Florida">', $html);
        $this->assertStringContainsString('<option value="California">', $html);
        // Serialized shape carries the new `state` key, and the serializer reads it back
        $this->assertStringContainsString('state:', $html);
        $this->assertStringContainsString("getElementById('ldna-state-input')", $html);
    }

    public function test_preferred_state_prefills_from_existing_blob(): void
    {
        // A saved blob value round-trips into the input + the ldnaState initializer
        $html = view('partials.location-dna.map-input', [
            'existingLocationDna' => ['state' => 'Texas'],
        ])->render();

        $this->assertStringContainsString('value="Texas"', $html);
        // The ldnaState JS initializer carries the prefilled value (whitespace-tolerant)
        $this->assertMatchesRegularExpression('/state:\s*"Texas"/', $html);
    }
}

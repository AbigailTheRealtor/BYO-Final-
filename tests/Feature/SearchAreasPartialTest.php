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

    private function renderWith(array $data): string
    {
        return view('partials.location-dna.map-input', array_merge(['existingLocationDna' => []], $data))->render();
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

    // ── Phase 9C — Important Places ──────────────────────────────────────────

    public function test_important_places_hidden_unless_host_opts_in(): void
    {
        // Default (legacy criteria pages): no opt-in → no Important Places UI at all.
        $html = $this->render();

        $this->assertStringNotContainsString('id="ldna-ip-section"', $html);
        $this->assertStringNotContainsString('Important Places', $html);
        $this->assertStringNotContainsString('ldnaIpAddRow()', $html);
    }

    public function test_important_places_renders_when_enabled(): void
    {
        $html = $this->renderWith(['enableImportantPlaces' => true]);

        // Section + repeatable-row scaffolding
        $this->assertStringContainsString('id="ldna-ip-section"', $html);
        $this->assertStringContainsString('Important Places', $html);
        $this->assertStringContainsString('id="ldna-ip-rows"', $html);
        $this->assertStringContainsString('id="ldna-ip-row-template"', $html);
        $this->assertStringContainsString('ldnaIpAddRow()', $html);
        $this->assertStringContainsString('ldnaIpRemoveRow(this)', $html);

        // Field controls: type selector (+ "Other"), address, distance pref + value, travel mode
        $this->assertStringContainsString('ldna-ip-type', $html);
        $this->assertStringContainsString('ldna-ip-type-other', $html);
        $this->assertStringContainsString('ldna-ip-address', $html);
        $this->assertStringContainsString('ldna-ip-distpref', $html);
        $this->assertStringContainsString('ldna-ip-distval', $html);
        $this->assertStringContainsString('ldna-ip-mode', $html);
        $this->assertStringContainsString('<option value="Work">', $html);
        $this->assertStringContainsString('<option value="Other">', $html);
        $this->assertStringContainsString('<option value="driving">', $html);

        // Distance preference offers both miles (circle) and minutes (travel time)
        $this->assertStringContainsString('Within miles', $html);
        $this->assertStringContainsString('Within minutes', $html);

        // Serialization sink + serializer, and the "no fake travel-time circles" contract
        $this->assertStringContainsString('id="ldna-important-places-field"', $html);
        $this->assertStringContainsString('window.ldnaIpSerialize', $html);
        $this->assertStringContainsString('never for travel-time minutes', $html);
    }

    public function test_important_places_prefills_existing_rows(): void
    {
        $rows = [[
            'type'           => 'School',
            'type_other'     => '',
            'address'        => '1 School Rd, Tampa, FL',
            'lat'            => 27.9,
            'lng'            => -82.4,
            'distance_pref'  => 'minutes',
            'distance_value' => 15,
            'travel_mode'    => 'transit',
        ]];

        $html = $this->renderWith([
            'enableImportantPlaces'   => true,
            'existingImportantPlaces' => $rows,
        ]);

        // Server-embedded JSON seeds both the hidden field and the JS row builder.
        $this->assertStringContainsString('1 School Rd, Tampa, FL', $html);
        $this->assertStringContainsString('"distance_pref":"minutes"', $html);
        $this->assertStringContainsString('"travel_mode":"transit"', $html);
    }
}

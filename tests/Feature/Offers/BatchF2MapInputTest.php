<?php

namespace Tests\Feature\Offers;

use Tests\TestCase;

/**
 * Batch F-2 — Location-DNA map partial cosmetics (issues #28, #29).
 *
 * Shared partial (SC5): resources/views/partials/location-dna/map-input.blade.php,
 * consumed by Create Buyer/Tenant + Hire Buyer/Tenant. Cosmetic-only; no 9D logic,
 * ldnaIpSerialize(), or important_places_json behavior is touched.
 *
 *   #28 — remove the user-facing "County bias / Seminole" helper sentence from the
 *         Preferred Cities hint. The remaining "Seminole" occurrences are a code
 *         comment and a JS-config example and MUST stay intact.
 *   #29 — VERIFY-ONLY: the Important Places Type / Distance Preference / Travel Mode
 *         controls already render at parity with Exact Address / Miles. The layout
 *         loads Bootstrap 5.2.2, where .form-select-sm and .form-control-sm share the
 *         same height tokens; every Important Places control already carries the "-sm"
 *         class and the small columns are all col-md-3. This test PINS that parity so a
 *         future edit cannot silently regress it (no markup change was required).
 *
 * Both items remain "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until a browser pass.
 */
class BatchF2MapInputTest extends TestCase
{
    private const MAP_INPUT = 'resources/views/partials/location-dna/map-input.blade.php';

    private function source(): string
    {
        $full = base_path(self::MAP_INPUT);
        $this->assertFileExists($full, 'Expected file missing: ' . self::MAP_INPUT);

        return (string) file_get_contents($full);
    }

    /** #28 — the user-facing county-bias helper sentence is gone. */
    public function test_issue_28_county_bias_helper_text_removed(): void
    {
        $src = $this->source();

        $this->assertStringNotContainsString(
            'County bias is used so "Seminole, FL" maps to Pinellas, not Seminole County.',
            $src,
            'The user-facing county-bias helper sentence should have been removed (#28).'
        );

        // The sibling hint line stays.
        $this->assertStringContainsString('Selecting a city draws its boundary on the map.', $src);
    }

    /** #28 — the non-user-facing "Seminole" occurrences (comment + JS config example) are preserved. */
    public function test_issue_28_non_user_facing_seminole_references_preserved(): void
    {
        $src = $this->source();

        // JS-config example array and the explanatory code comment must remain.
        $this->assertStringContainsString('"cities":           ["Seminole, FL"]', $src);
        $this->assertStringContainsString('resolves to Pinellas County', $src);
    }

    /**
     * #29 — parity guard (verify-only). Every Important Places control already matches
     * the Exact Address / Miles sizing: selects use form-select-sm, text/number inputs
     * use form-control-sm (identical height under Bootstrap 5.2.2). No markup change.
     */
    public function test_issue_29_important_places_controls_are_size_matched(): void
    {
        $src = $this->source();

        // The three flagged controls — Type / Distance Preference / Travel Mode — are -sm selects.
        $this->assertStringContainsString('class="form-select form-select-sm ldna-ip-type"', $src);
        $this->assertStringContainsString('class="form-select form-select-sm ldna-ip-distpref"', $src);
        $this->assertStringContainsString('class="form-select form-select-sm ldna-ip-mode"', $src);

        // The reference controls — Exact Address / Miles (distance value) — are -sm inputs.
        $this->assertStringContainsString('class="form-control form-control-sm ldna-ip-address"', $src);
        $this->assertStringContainsString('class="form-control form-control-sm ldna-ip-distval"', $src);

        // No Important Places control regressed to a non-"-sm" size.
        $this->assertStringNotContainsString('class="form-select ldna-ip-type"', $src);
        $this->assertStringNotContainsString('class="form-select ldna-ip-distpref"', $src);
        $this->assertStringNotContainsString('class="form-select ldna-ip-mode"', $src);
    }
}

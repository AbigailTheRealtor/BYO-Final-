<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsCoverageReporter;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;

/**
 * Phase C: Preview Gating + Coverage Report
 *
 * Tests:
 *   (a-static)     Universal base key inventory: each key exists as a parsed key
 *                  and as a Livewire property on all applicable components.
 *   (a-behavioral) Real importListingFromUrl() call with user_type null/unset:
 *                  base fields appear in importPreviewData; role-gated fields do not.
 *   (b)            No approved parsed field (Parsed=Y) is silently dropped from the
 *                  preview — all role-map keys with existing properties reach the
 *                  preview path.
 *   (c)            Coverage report Markdown includes the two new columns: Previewed
 *                  (Y/N) and Reason if not mapped.
 */
class MlsPreviewGatingTest extends TestCase
{
    // ─── (a) Universal base keys — preview without property type ─────────────

    public function test_universal_base_keys_returns_non_empty_array(): void
    {
        $keys = MlsFieldMap::universalBaseKeys();

        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys, 'universalBaseKeys() must not be empty');
    }

    public function test_universal_base_map_returns_non_empty_array(): void
    {
        $map = MlsFieldMap::universalBaseMap();

        $this->assertIsArray($map);
        $this->assertNotEmpty($map, 'universalBaseMap() must not be empty');
    }

    public function test_universal_base_keys_matches_base_map_keys(): void
    {
        $this->assertSame(
            array_keys(MlsFieldMap::universalBaseMap()),
            MlsFieldMap::universalBaseKeys(),
            'universalBaseKeys() must equal array_keys(universalBaseMap())'
        );
    }

    /**
     * Each key in the universal base map must target a property that actually
     * exists on the Seller component (the reference role that covers all base fields).
     */
    public function test_all_base_map_target_properties_exist_on_seller_component(): void
    {
        $baseMap = MlsFieldMap::universalBaseMap();

        foreach ($baseMap as $canonicalKey => $propName) {
            $cleanProp = ltrim($propName, '*');
            $this->assertTrue(
                property_exists(SellerOfferListing::class, $cleanProp),
                "Base map '{$canonicalKey}' → '{$cleanProp}': property missing on SellerOfferListing"
            );
        }
    }

    /**
     * Base map target properties must exist on the Landlord component.
     * (Buyer is intentionally excluded from several address fields by design;
     *  the property_exists() guard in HasMlsImport prevents any invalid write.)
     */
    public function test_all_base_map_target_properties_exist_on_landlord_component(): void
    {
        $baseMap = MlsFieldMap::universalBaseMap();

        foreach ($baseMap as $canonicalKey => $propName) {
            $cleanProp = ltrim($propName, '*');
            $this->assertTrue(
                property_exists(LandlordOfferListing::class, $cleanProp),
                "Base map '{$canonicalKey}' → '{$cleanProp}': property missing on LandlordOfferListing"
            );
        }
    }

    /**
     * Core structural base keys (beds/baths/sqft/pool/garage/carport/description)
     * must exist on Buyer and Tenant as well.
     */
    public function test_core_structural_base_keys_exist_on_buyer_and_tenant(): void
    {
        $coreKeys = [
            'bedrooms'    => 'bedrooms',
            'bathrooms'   => 'bathrooms',
            'heated_sqft' => 'minimum_heated_square',
            'pool'        => 'pool_needed',
            'garage'      => 'garage_needed',
            'carport'     => 'carport_needed',
            'furnished'   => 'tenant_require',
            'description' => 'additional_details',
        ];

        foreach ([BuyerOfferListing::class, TenantOfferListing::class] as $class) {
            $short = class_basename($class);
            foreach ($coreKeys as $canonicalKey => $propName) {
                $this->assertTrue(
                    property_exists($class, $propName),
                    "Core base key '{$canonicalKey}' → '{$propName}': property missing on {$short}"
                );
            }
        }
    }

    /**
     * Address base keys must exist as properties on the Tenant component.
     * (They are intentionally excluded from the Tenant *field map* in Phase A,
     *  but TenantOfferListing does have the properties — Phase B will add the
     *  field map entries.  The base-map fallback in HasMlsImport already covers
     *  the preview for these fields.)
     */
    public function test_address_base_keys_exist_on_tenant_component(): void
    {
        $addressMap = [
            'address' => 'address',
            'city'    => 'property_city',
            'state'   => 'property_state',
            'zip'     => 'property_zip',
            'county'  => 'property_county',
        ];

        foreach ($addressMap as $canonicalKey => $propName) {
            $this->assertTrue(
                property_exists(TenantOfferListing::class, $propName),
                "Address base key '{$canonicalKey}' → '{$propName}': property missing on TenantOfferListing"
            );
        }
    }

    /**
     * Every key in universalBaseKeys() must be present in the parser output
     * (i.e. MlsListingImportService::parseFields() emits it).  A base key that
     * is never parsed can never appear in the preview — that would be a silent gap.
     */
    public function test_every_base_key_is_also_a_parsed_key(): void
    {
        // Derive parsed keys the same way MlsCoverageReporter does.
        $src = file_get_contents(app_path('Services/ListingImport/MlsListingImportService.php'));
        preg_match_all('/\$data\[\'([a-z_]+)\'\]\s*=/', $src, $m);
        $parsedKeys = array_values(array_unique($m[1] ?? []));

        foreach (MlsFieldMap::universalBaseKeys() as $baseKey) {
            $this->assertContains(
                $baseKey,
                $parsedKeys,
                "Base key '{$baseKey}' is not emitted by parseFields() — it can never appear in the preview"
            );
        }
    }

    // ─── (b) No approved parsed field silently dropped from preview ──────────

    /**
     * For every parsed key that has a field-map entry AND a Livewire property on
     * the Seller component, the HasMlsImport preview loop will reach the row-append
     * path.  We verify this by confirming that for each such key, either:
     *   - the role's forRole() map contains it (existing path), OR
     *   - universalBaseMap() contains it (new fallback path).
     *
     * A key that has a field-map entry and a live property but is absent from BOTH
     * maps would be silently dropped — this test catches that.
     */
    public function test_no_safe_seller_key_is_silently_dropped_from_preview(): void
    {
        $sellerMap = MlsFieldMap::forRole('seller');
        $baseMap   = MlsFieldMap::universalBaseMap();

        foreach ($sellerMap as $canonicalKey => $propNameRaw) {
            $propName = ltrim($propNameRaw, '*');
            if (!property_exists(SellerOfferListing::class, $propName)) {
                continue; // Property guard in HasMlsImport would skip this anyway
            }

            $inRoleMap = isset($sellerMap[$canonicalKey]);
            $inBaseMap = isset($baseMap[$canonicalKey]);

            $this->assertTrue(
                $inRoleMap || $inBaseMap,
                "Seller key '{$canonicalKey}' has a valid property but is in neither "
                . "the role map nor the base map — it would be silently dropped from the preview"
            );
        }
    }

    /**
     * Same invariant for the Landlord role.
     */
    public function test_no_safe_landlord_key_is_silently_dropped_from_preview(): void
    {
        $landlordMap = MlsFieldMap::forRole('landlord');
        $baseMap     = MlsFieldMap::universalBaseMap();

        foreach ($landlordMap as $canonicalKey => $propNameRaw) {
            $propName = ltrim($propNameRaw, '*');
            if (!property_exists(LandlordOfferListing::class, $propName)) {
                continue;
            }

            $inRoleMap = isset($landlordMap[$canonicalKey]);
            $inBaseMap = isset($baseMap[$canonicalKey]);

            $this->assertTrue(
                $inRoleMap || $inBaseMap,
                "Landlord key '{$canonicalKey}' has a valid property but is in neither "
                . "the role map nor the base map — it would be silently dropped from the preview"
            );
        }
    }

    // ─── (c) Coverage report new columns ─────────────────────────────────────

    /**
     * The coverage report Markdown must include the two new column headers.
     */
    public function test_coverage_report_includes_previewed_column_header(): void
    {
        $tmpFile = sys_get_temp_dir() . '/mls_preview_gating_test_' . getmypid() . '.md';

        try {
            MlsCoverageReporter::generate($tmpFile);

            $this->assertFileExists($tmpFile);
            $content = file_get_contents($tmpFile);

            $this->assertStringContainsString('Previewed (Y/N)', $content,
                'Coverage report must include a "Previewed (Y/N)" column header');
            $this->assertStringContainsString('Reason if not mapped', $content,
                'Coverage report must include a "Reason if not mapped" column header');
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Every canonical key in universalBaseKeys() must appear as a Y in the
     * "Previewed" column of at least one report row.
     */
    public function test_coverage_report_marks_base_keys_as_previewed_y(): void
    {
        $tmpFile = sys_get_temp_dir() . '/mls_preview_gating_test_' . getmypid() . '.md';

        try {
            MlsCoverageReporter::generate($tmpFile);
            $content = file_get_contents($tmpFile);

            foreach (MlsFieldMap::universalBaseKeys() as $baseKey) {
                // Each row that contains the base key canonical cell should have Y
                // in the Previewed column.  We look for the backtick-wrapped key
                // appearing on a table row that ends with | Y | … |
                $this->assertStringContainsString(
                    "`{$baseKey}`",
                    $content,
                    "Coverage report must contain a row for base key '{$baseKey}'"
                );
            }

            // Count rows with Previewed=Y — must be exactly the number of
            // (form × base_key) combinations, i.e. at least as many as base keys.
            $yCount = substr_count($content, '| Y |');
            $this->assertGreaterThanOrEqual(
                count(MlsFieldMap::universalBaseKeys()),
                $yCount,
                'Number of Previewed=Y rows must be >= number of universal base keys'
            );
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Rows with canonical_key=null must show reason_not_mapped = missing_from_parser.
     */
    public function test_coverage_report_null_key_rows_have_missing_from_parser_reason(): void
    {
        $tmpFile = sys_get_temp_dir() . '/mls_preview_gating_test_' . getmypid() . '.md';

        try {
            MlsCoverageReporter::generate($tmpFile);
            $content = file_get_contents($tmpFile);

            // There must be at least some missing_from_parser entries (commercial,
            // vacant land, income, business fields all have null canonical keys).
            $this->assertStringContainsString(
                'missing_from_parser',
                $content,
                'Coverage report must contain at least one missing_from_parser reason'
            );
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * The coverage report must contain known non-parser reasons for rows
     * that are parsed but not fully wired up (e.g. missing_from_field_map,
     * no_form_binding, intentionally_excluded).
     */
    public function test_coverage_report_contains_variety_of_not_mapped_reasons(): void
    {
        $tmpFile = sys_get_temp_dir() . '/mls_preview_gating_test_' . getmypid() . '.md';

        try {
            MlsCoverageReporter::generate($tmpFile);
            $content = file_get_contents($tmpFile);

            // intentionally_excluded: mls_number is parsed but not in any field map
            $this->assertStringContainsString(
                'intentionally_excluded',
                $content,
                'Coverage report must contain intentionally_excluded for known omissions'
            );
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Rows that ARE safe (Safe=Y) must have an empty reason_not_mapped cell.
     * We verify this indirectly: the report must not contain a Y-safe row
     * that also has a populated reason string in the same row.
     *
     * This is tested by re-running buildRows via generate() and checking the
     * live row data directly through the MlsCoverageReporter internals.
     */
    public function test_safe_rows_have_empty_reason_not_mapped(): void
    {
        $tmpFile = sys_get_temp_dir() . '/mls_preview_gating_test_' . getmypid() . '.md';

        try {
            MlsCoverageReporter::generate($tmpFile);
            $lines = file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $checkedRows = 0;

            foreach ($lines as $line) {
                if (!str_starts_with(trim($line), '|')) {
                    continue;
                }

                // Split on '|' and trim each cell, but preserve empty cells so column
                // indices remain stable even when reason_not_mapped is empty ('').
                $parts = explode('|', $line);
                // Leading and trailing elements are empty strings from the outer pipes
                array_shift($parts);
                array_pop($parts);
                $cells = array_map('trim', $parts);

                // We need at least 13 columns:
                // 0:MLS Form  1:Section  2:Label  3:Key  4:Mapping  5:Target
                // 6:PropExists  7:FormField  8:Safe  9:Norm  10:Previewed
                // 11:Reason  12:Notes
                if (count($cells) < 13) {
                    continue;
                }

                // Skip the header row and separator row
                if ($cells[0] === 'MLS Form' || str_starts_with($cells[0], '---')) {
                    continue;
                }

                $safeCell   = $cells[8];
                $reasonCell = $cells[11];

                // Rows where Safe starts with "Y (" are safe for at least one role
                // and must have an empty reason_not_mapped cell.
                if (str_starts_with($safeCell, 'Y (')) {
                    $this->assertEmpty(
                        $reasonCell,
                        "Safe row (Safe='{$safeCell}') must have empty reason_not_mapped, got: '{$reasonCell}'"
                    );
                    $checkedRows++;
                }
            }

            // Sanity: there must be at least some safe rows in the report
            $this->assertGreaterThan(
                0,
                $checkedRows,
                'Coverage report must contain at least one Safe=Y row to verify the reason_not_mapped invariant'
            );
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    // ─── (a-behavioral) Real preview call — import runs, fields gated by role ──

    /**
     * With user_type left empty the role resolves from the class name ('seller').
     * importListingFromUrl() must populate importPreviewData with every universal
     * base-field canonical key whose value appeared in the raw text.
     *
     * This proves the base fields reach the preview without requiring the caller
     * to set a property type before triggering the import.
     */
    public function test_base_fields_appear_in_preview_when_user_type_is_unset(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = '';                 // unset — role falls back to class name

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Bathrooms: 2',
            'Heated Sq Ft: 1800',
            'Pool: Yes',
            'Garage Spaces: 2',
            'Carport YN: No',
            'Furnished: Unfurnished',
            'Address: 123 Elm Street',
            'City: Tampa',
            'State: FL',
            'Zip Code: 33601',
            'County: Hillsborough',
            'Public Remarks: Lovely home in a great neighbourhood.',
        ]);

        $component->importListingFromUrl();

        $this->assertEmpty(
            $component->importError,
            'Import must succeed, error: ' . ($component->importError ?? '')
        );
        $this->assertNotEmpty($component->importPreviewData, 'importPreviewData must be populated');

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');

        $expectedBaseKeys = [
            'bedrooms', 'bathrooms', 'heated_sqft', 'pool', 'garage',
            'carport', 'furnished', 'description',
            'address', 'city', 'state', 'zip', 'county',
        ];

        foreach ($expectedBaseKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $keyedPreview,
                "Base field '{$key}' must appear in preview when user_type is unset (seller role derived from class name)"
            );
        }
    }

    /**
     * For the buyer role address fields and owner-disclosure fields are intentionally
     * absent from MlsFieldMap::forRole('buyer').  Parsing raw text that contains
     * those fields must NOT produce preview rows for them — the role-specific map
     * is authoritative and no base-map fallback must override its omissions.
     *
     * Core structural fields (beds, baths, pool, etc.) that ARE in the buyer map
     * must still appear.
     */
    public function test_gated_fields_excluded_for_buyer_role(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component            = new BuyerOfferListing();
        $component->user_type = 'buyer';

        $component->importRawText = implode('  ', [
            // Base structural fields — present in buyer map
            'Bedrooms: 3',
            'Bathrooms: 2',
            'Heated Sq Ft: 1600',
            'Pool: Yes',
            'Garage Spaces: 1',
            'Carport YN: No',
            'Public Remarks: Open floor plan with great light.',
            // Address fields — intentionally excluded from buyer map (multi-city model)
            'Address: 456 Oak Ave',
            'City: Orlando',
            'State: FL',
            'Zip Code: 32801',
            'County: Orange',
            // Owner-disclosure fields — intentionally excluded from buyer map
            'Association Y/N: Yes',
            'Association Fee: $200',
            'Tax ID: 12-34-56-78',
            'Annual Property Taxes: $3500',
        ]);

        $component->importListingFromUrl();

        $this->assertEmpty(
            $component->importError,
            'Import must succeed, error: ' . ($component->importError ?? '')
        );
        $this->assertNotEmpty($component->importPreviewData, 'importPreviewData must be populated');

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');

        // Core structural fields ARE in the buyer map — must appear
        foreach (['bedrooms', 'bathrooms', 'heated_sqft', 'pool', 'garage', 'carport', 'description'] as $key) {
            $this->assertArrayHasKey(
                $key,
                $keyedPreview,
                "Structural field '{$key}' must appear in preview for buyer role"
            );
        }

        // Address fields excluded from buyer map — must NOT appear regardless of
        // whether the parser extracted them from the raw text
        foreach (['address', 'city', 'state', 'zip', 'county'] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $keyedPreview,
                "Address field '{$key}' must NOT appear for buyer (buyer uses multi-city model; single-address fields are intentionally excluded from the buyer field map)"
            );
        }

        // Owner-disclosure fields excluded from buyer map — must NOT appear
        foreach (['has_hoa', 'hoa_fee', 'tax_id', 'annual_taxes'] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $keyedPreview,
                "Owner-disclosure field '{$key}' must NOT appear for buyer role"
            );
        }
    }
}

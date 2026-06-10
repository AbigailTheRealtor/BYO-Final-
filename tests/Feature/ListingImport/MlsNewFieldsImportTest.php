<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\User;
use App\Services\ListingImport\MlsListingImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Full MLS import pipeline tests for the five new fields:
 *   waterfront, water_access, water_view, interior_features, flood_zone_date
 *
 * Covers three stages of the pipeline:
 *   (1) Parser output  — parseFields() emits the correct canonical keys
 *   (2) Preview modal  — importListingFromUrl() populates importPreviewData
 *   (3) Apply Selected — applyImportedFields() hydrates component properties
 *                        (scalar and array fields)
 *
 * Values persist after save/reload is verified by SellerMlsFieldRoundTripTest
 * and LandlordMlsFieldRoundTripTest.
 */
class MlsNewFieldsImportTest extends TestCase
{
    use DatabaseTransactions;

    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MlsListingImportService::class);
    }

    // =========================================================================
    // (1) Parser output
    // =========================================================================

    public function test_parser_emits_water_access_key(): void
    {
        $rawText = 'Water Access: Lake, Canal - Freshwater  Bedrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('water_access', $result['data'],
            'Parser must emit the water_access canonical key');
        $this->assertStringContainsStringIgnoringCase('Lake', $result['data']['water_access']);
    }

    public function test_parser_emits_water_view_key(): void
    {
        $rawText = 'Water View: Gulf/Ocean - Full  Pool: Yes';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('water_view', $result['data'],
            'Parser must emit the water_view canonical key');
        $this->assertStringContainsStringIgnoringCase('Gulf', $result['data']['water_view']);
    }

    public function test_parser_emits_interior_features_key(): void
    {
        $rawText = 'Interior Features: Crown Molding, Walk-In Closet(s), High Ceilings  Bedrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('interior_features', $result['data'],
            'Parser must emit the interior_features canonical key');
        $this->assertStringContainsStringIgnoringCase('Crown Molding', $result['data']['interior_features']);
        $this->assertStringContainsStringIgnoringCase('Walk-In Closet', $result['data']['interior_features']);
    }

    public function test_parser_emits_waterfront_key_normalized_to_yes(): void
    {
        $rawText = 'Waterfront: Yes  Bedrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waterfront', $result['data'],
            'Parser must emit the waterfront canonical key');
        $this->assertEquals('yes', $result['data']['waterfront'],
            'Waterfront Yes must be normalized to lowercase yes');
    }

    public function test_parser_emits_waterfront_key_normalized_to_no(): void
    {
        $rawText = 'Waterfront: No  Bedrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waterfront', $result['data']);
        $this->assertEquals('no', $result['data']['waterfront']);
    }

    public function test_parser_emits_flood_zone_date_key(): void
    {
        $rawText = 'Flood Zone Date: 04/17/2009  Flood Zone Code: X';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flood_zone_date', $result['data'],
            'Parser must emit the flood_zone_date canonical key');
        $this->assertStringContainsString('2009', $result['data']['flood_zone_date']);
    }

    public function test_parser_emits_flood_zone_date_iso_format(): void
    {
        $rawText = 'Flood Zone Date: 2021-06-15  Flood Zone Code: AE';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flood_zone_date', $result['data']);
        $this->assertEquals('2021-06-15', $result['data']['flood_zone_date']);
    }

    public function test_water_access_does_not_bleed_into_water_view(): void
    {
        $rawText = 'Water Access: Lake  Water View: Gulf/Ocean - Full  Pool: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('water_access', $data);
        $this->assertArrayHasKey('water_view', $data);
        $this->assertStringNotContainsStringIgnoringCase('Gulf', $data['water_access'],
            'water_access must not bleed into the Water View label');
        $this->assertStringNotContainsStringIgnoringCase('Lake', $data['water_view'],
            'water_view must not include the Water Access value');
    }

    public function test_interior_features_does_not_bleed_into_exterior_construction(): void
    {
        $rawText = 'Interior Features: Crown Molding  Exterior Construction: Block';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Exterior', $data['interior_features'],
            'interior_features must stop at the Exterior Construction label');
        $this->assertStringNotContainsStringIgnoringCase('Block', $data['interior_features']);
    }

    // =========================================================================
    // (2) Preview modal — importListingFromUrl() populates importPreviewData
    // =========================================================================

    public function test_preview_contains_all_five_new_fields_for_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Bathrooms: 2',
            'Waterfront: Yes',
            'Water Access: Lake, Canal - Freshwater',
            'Water View: Gulf/Ocean - Full',
            'Interior Features: Crown Molding, Walk-In Closet(s)',
            'Flood Zone Date: 04/17/2009',
        ]);

        $component->importListingFromUrl();

        $this->assertEmpty($component->importError,
            'Import must succeed, error: ' . $component->importError);
        $this->assertNotEmpty($component->importPreviewData);

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');

        foreach (['waterfront', 'water_access', 'water_view', 'interior_features', 'flood_zone_date'] as $key) {
            $this->assertArrayHasKey($key, $keyedPreview,
                "Field '{$key}' must appear in importPreviewData for seller role");
        }
    }

    public function test_preview_contains_all_five_new_fields_for_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new LandlordOfferListing();
        $component->user_type = 'landlord';

        $component->importRawText = implode('  ', [
            'Bedrooms: 2',
            'Bathrooms: 1',
            'Waterfront: No',
            'Water Access: Bay/Harbor',
            'Water View: Canal',
            'Interior Features: Granite Counters, Vaulted Ceiling(s)',
            'Flood Zone Date: 2022-01-10',
        ]);

        $component->importListingFromUrl();

        $this->assertEmpty($component->importError,
            'Import must succeed, error: ' . $component->importError);
        $this->assertNotEmpty($component->importPreviewData);

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');

        foreach (['waterfront', 'water_access', 'water_view', 'interior_features', 'flood_zone_date'] as $key) {
            $this->assertArrayHasKey($key, $keyedPreview,
                "Field '{$key}' must appear in importPreviewData for landlord role");
        }
    }

    public function test_preview_marks_array_fields_as_is_array_prop(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Water Access: Lake',
            'Water View: Gulf/Ocean - Full',
            'Interior Features: Crown Molding',
        ]);

        $component->importListingFromUrl();

        $keyedPreview = array_column($component->importPreviewData, null, 'canonical_key');

        foreach (['water_access', 'water_view', 'interior_features'] as $key) {
            $this->assertArrayHasKey($key, $keyedPreview, "'{$key}' must appear in preview");
            $this->assertTrue(
                $keyedPreview[$key]['is_array_prop'],
                "'{$key}' must be flagged as is_array_prop=true in the preview row"
            );
        }

        // Scalar fields must NOT be flagged as arrays
        foreach (['waterfront', 'flood_zone_date'] as $key) {
            if (isset($keyedPreview[$key])) {
                $this->assertFalse(
                    $keyedPreview[$key]['is_array_prop'],
                    "'{$key}' must NOT be flagged as is_array_prop in the preview row"
                );
            }
        }
    }

    // =========================================================================
    // (3) Apply Selected — applyImportedFields() hydrates component properties
    // =========================================================================

    public function test_apply_hydrates_scalar_waterfront_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Waterfront: Yes',
        ]);
        $component->importListingFromUrl();

        $this->assertNotEmpty($component->importPreviewData);

        $component->applyImportedFields(['waterfront']);

        $this->assertEquals('yes', $component->waterfront,
            'applyImportedFields must set waterfront to the normalized yes/no string');
    }

    public function test_apply_hydrates_scalar_flood_zone_date_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Flood Zone Date: 2021-03-15',
        ]);
        $component->importListingFromUrl();

        $component->applyImportedFields(['flood_zone_date']);

        $this->assertEquals('2021-03-15', $component->flood_zone_date,
            'applyImportedFields must set flood_zone_date to the parsed date string');
    }

    public function test_apply_splits_water_access_into_array_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Water Access: Lake, Canal - Freshwater',
        ]);
        $component->importListingFromUrl();

        $this->assertNotEmpty($component->importPreviewData);

        $component->applyImportedFields(['water_access']);

        $this->assertIsArray($component->water_access,
            'water_access must be an array after applyImportedFields');
        $this->assertContains('Lake', $component->water_access);
        $this->assertContains('Canal - Freshwater', $component->water_access);
    }

    public function test_apply_splits_water_view_into_array_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Water View: Gulf/Ocean - Full',
        ]);
        $component->importListingFromUrl();

        $component->applyImportedFields(['water_view']);

        $this->assertIsArray($component->water_view);
        $this->assertContains('Gulf/Ocean - Full', $component->water_view);
    }

    public function test_apply_splits_interior_features_into_array_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Interior Features: Crown Molding, Walk-In Closet(s), High Ceilings',
        ]);
        $component->importListingFromUrl();

        $component->applyImportedFields(['interior_features']);

        $this->assertIsArray($component->interior_features);
        $this->assertContains('Crown Molding', $component->interior_features);
        $this->assertContains('Walk-In Closet(s)', $component->interior_features);
        $this->assertContains('High Ceilings', $component->interior_features);
    }

    public function test_apply_all_five_fields_together_on_seller(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new SellerOfferListing();
        $component->user_type = 'seller';

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Waterfront: Yes',
            'Water Access: Lake, River',
            'Water View: Lake',
            'Interior Features: Granite Counters, Crown Molding',
            'Flood Zone Date: 2020-11-01',
        ]);
        $component->importListingFromUrl();

        $this->assertNotEmpty($component->importPreviewData);

        $component->applyImportedFields(
            ['waterfront', 'water_access', 'water_view', 'interior_features', 'flood_zone_date']
        );

        $this->assertEquals('yes',              $component->waterfront);
        $this->assertEquals(['Lake', 'River'],   $component->water_access);
        $this->assertEquals(['Lake'],            $component->water_view);
        $this->assertEquals(['Granite Counters', 'Crown Molding'], $component->interior_features);
        $this->assertEquals('2020-11-01',        $component->flood_zone_date);
    }

    public function test_apply_all_five_fields_together_on_landlord(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component            = new LandlordOfferListing();
        $component->user_type = 'landlord';

        $component->importRawText = implode('  ', [
            'Bedrooms: 2',
            'Waterfront: No',
            'Water Access: Bay/Harbor',
            'Water View: Canal',
            'Interior Features: Ceiling Fans(s)',
            'Flood Zone Date: 2019-08-22',
        ]);
        $component->importListingFromUrl();

        $this->assertNotEmpty($component->importPreviewData);

        $component->applyImportedFields(
            ['waterfront', 'water_access', 'water_view', 'interior_features', 'flood_zone_date']
        );

        $this->assertEquals('no',            $component->waterfront);
        $this->assertEquals(['Bay/Harbor'],   $component->water_access);
        $this->assertEquals(['Canal'],        $component->water_view);
        $this->assertEquals(['Ceiling Fans(s)'], $component->interior_features);
        $this->assertEquals('2019-08-22',    $component->flood_zone_date);
    }

    public function test_apply_respects_no_override_when_field_already_filled(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component               = new SellerOfferListing();
        $component->user_type    = 'seller';
        $component->waterfront   = 'no';    // pre-existing value

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Waterfront: Yes',
        ]);
        $component->importListingFromUrl();

        // Apply without override — existing 'no' must be preserved
        $component->applyImportedFields(['waterfront'], []);

        $this->assertEquals('no', $component->waterfront,
            'applyImportedFields must NOT overwrite an existing value unless the key is in overrideKeys');
    }

    public function test_apply_overrides_when_key_in_override_list(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $component               = new SellerOfferListing();
        $component->user_type    = 'seller';
        $component->waterfront   = 'no';    // pre-existing value

        $component->importRawText = implode('  ', [
            'Bedrooms: 3',
            'Waterfront: Yes',
        ]);
        $component->importListingFromUrl();

        // Apply WITH override — existing 'no' must be replaced with 'yes'
        $component->applyImportedFields(['waterfront'], ['waterfront']);

        $this->assertEquals('yes', $component->waterfront,
            'applyImportedFields must overwrite an existing value when the key is in overrideKeys');
    }
}

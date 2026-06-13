<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end MLS import workflow tests.
 *
 * These tests verify the full pipeline from raw MLS text paste through to
 * Livewire component property population — the complete "Apply Selected" flow:
 *
 *   raw text → importListingFromUrl() → importPreviewData populated
 *            → applyImportedFields()  → component props correctly set
 *
 * They also verify that the preview data shown to the user never contains
 * contaminating text from adjacent fields (parser bleed reaching the UI).
 *
 * Coverage:
 *   - Seller full pipeline (raw text → Livewire props)
 *   - Landlord full pipeline (raw text → Livewire props)
 *   - Seller fixture preview-modal cleanliness (no bleed in preview values)
 *   - Landlord fixture preview-modal cleanliness
 *   - Preview: waterfront value is "yes" or "no" only
 *   - Preview: city value does not contain County/School District
 *   - Preview: water_view value does not contain Tax/Assessment
 *   - Preview: appliances value does not contain section headers
 *   - Preview: rent_includes value does not contain waterfront data
 */
class MlsImportWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private User $sellerUser;
    private User $landlordUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sellerUser   = User::factory()->create(['user_type' => 'seller']);
        $this->landlordUser = User::factory()->create(['user_type' => 'seller']);
    }

    // ─── Seller full pipeline ─────────────────────────────────────────────────

    /**
     * Seller: raw MLS text → importListingFromUrl → importPreviewData populated
     * → applyImportedFields → correct Livewire props set.
     *
     * This is the exact sequence a user triggers by pasting text and clicking
     * "Import" then "Apply Selected" in the modal.
     */
    public function test_seller_full_pipeline_raw_text_to_livewire_props(): void
    {
        $raw = implode("\n", [
            'City: Tampa',
            'County: Hillsborough',
            'State: FL',
            'Zip: 33610',
            'Bedrooms: 4',
            'Bathrooms: 2.5',
            'Heated Sq. Ft.: 2,184',
            'Year Built: 1998',
            'Pool: Yes',
            'Garage: Yes',
            'Carport: No',
            'Air Conditioning: Central Air',
            'Heating and Fuel: Central,Electric',
            'Interior Features: Ceiling Fans(s),Crown Molding,Walk-In Closet(s)',
            'Appliances: Dishwasher,Disposal,Microwave',
            'Exterior Construction: Block',
            'Roof Type: Shingle',
            'Foundation: Slab',
            'Lot Dimensions: 80x120',
            'Waterfront: No',
            'Water Access: Lake',
            'Water View: Lake',
            'Flood Zone Code: X',
            'Tax Year: 2023',
            'Tax ID: 19-30-17-45612-000-1410',
        ]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        // ── Preview data must be populated ────────────────────────────────────
        $component->assertSet('importError', '');
        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview, 'importPreviewData must be populated after importListingFromUrl');

        // Build lookup: canonical_key → value
        $previewByKey = [];
        foreach ($preview as $row) {
            $previewByKey[$row['canonical_key']] = $row['value'];
        }

        // ── Preview values must be clean (no bleed) ───────────────────────────
        $this->assertArrayHasKey('city', $previewByKey);
        $this->assertStringNotContainsStringIgnoringCase('County', $previewByKey['city']);
        $this->assertStringNotContainsStringIgnoringCase('Hillsborough', $previewByKey['city']);

        $this->assertArrayHasKey('waterfront', $previewByKey);
        $this->assertContains($previewByKey['waterfront'], ['yes', 'no'],
            'Preview waterfront must be normalized yes/no, got: ' . $previewByKey['waterfront']);

        if (isset($previewByKey['water_view'])) {
            $this->assertStringNotContainsStringIgnoringCase('Tax', $previewByKey['water_view']);
            $this->assertStringNotContainsStringIgnoringCase('Assessment', $previewByKey['water_view']);
        }

        if (isset($previewByKey['appliances'])) {
            $this->assertStringNotContainsStringIgnoringCase('Roof', $previewByKey['appliances']);
            $this->assertStringNotContainsStringIgnoringCase('Exterior', $previewByKey['appliances']);
        }

        if (isset($previewByKey['interior_features'])) {
            $this->assertStringNotContainsStringIgnoringCase('Appliances', $previewByKey['interior_features']);
            $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $previewByKey['interior_features']);
        }

        // ── Apply all selected and verify Livewire props ──────────────────────
        $selectedKeys = array_column($preview, 'canonical_key');
        $component->call('applyImportedFields', $selectedKeys, []);

        // City
        $component->assertSet('property_city', 'Tampa');

        // Waterfront clean
        $component->assertSet('waterfront', 'no');

        // Structural fields
        $this->assertNotEmpty($component->get('interior_features'));
        $this->assertContains('Ceiling Fans(s)', $component->get('interior_features'));
        $this->assertNotEmpty($component->get('appliances'));
        $this->assertContains('Dishwasher', $component->get('appliances'));
        $this->assertContains('Shingle', $component->get('roof_type'));
        $this->assertContains('Block', $component->get('exterior_construction'));
        $this->assertContains('Slab', $component->get('foundation'));

        // Lot dimensions
        $component->assertSet('lot_dimensions', '80x120');

        // Modal must be closed after apply
        $component->assertSet('showImportModal', false);
        $component->assertSet('importSuccess', true);
    }

    // ─── Landlord full pipeline ───────────────────────────────────────────────

    /**
     * Landlord: raw MLS text → importListingFromUrl → importPreviewData populated
     * → applyImportedFields → correct Livewire props set.
     */
    public function test_landlord_full_pipeline_raw_text_to_livewire_props(): void
    {
        $raw =
            'City: St. Petersburg County: Pinellas State: FL Zip: 33705 ' .
            'Monthly Rent: $2,800 Bedrooms: 3 Bathrooms: 2 ' .
            'Heated Sq. Ft.: 1,650 Year Built: 2002 ' .
            'Carport: No Rental Rate Type: Monthly ' .
            'Air Conditioning: Central Air Heating and Fuel: Central,Gas ' .
            'Interior Features: Ceiling Fans(s),Stone Counters Appliances: Dishwasher,Range ' .
            'Rent Includes: Lawn Care,Trash Collection ' .
            'Water Frontage: 50 Waterfront: No Water View: Bay/Harbor - Partial ' .
            'Special Assessment Y/N: No Tax Year: 2023 Tax ID: 25-31-17-67890-001-0220 ' .
            'Lot Dimensions: 65x110 Roof Type: Shingle Foundation: Slab';

        $component = Livewire::actingAs($this->landlordUser)
            ->test(LandlordOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        // ── Preview data must be populated ────────────────────────────────────
        $component->assertSet('importError', '');
        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview, 'importPreviewData must be populated after importListingFromUrl');

        $previewByKey = [];
        foreach ($preview as $row) {
            $previewByKey[$row['canonical_key']] = $row['value'];
        }

        // ── Preview cleanliness ───────────────────────────────────────────────
        // Carport must not contain Rental Rate Type bleed
        if (isset($previewByKey['carport'])) {
            $this->assertStringNotContainsStringIgnoringCase('Rental', $previewByKey['carport']);
        }

        // Rent Includes must not contain waterfront bleed
        if (isset($previewByKey['rent_includes'])) {
            $this->assertStringNotContainsStringIgnoringCase('Waterfront', $previewByKey['rent_includes']);
            $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $previewByKey['rent_includes']);
        }

        // Water View must not contain Assessment/Tax bleed
        if (isset($previewByKey['water_view'])) {
            $this->assertStringNotContainsStringIgnoringCase('Assessment', $previewByKey['water_view']);
            $this->assertStringNotContainsStringIgnoringCase('Tax', $previewByKey['water_view']);
        }

        // Waterfront must be normalized yes/no
        if (isset($previewByKey['waterfront'])) {
            $this->assertContains($previewByKey['waterfront'], ['yes', 'no'],
                'Preview waterfront must be "yes" or "no", got: ' . $previewByKey['waterfront']);
        }

        // ── Apply all and verify Livewire props ───────────────────────────────
        $selectedKeys = array_column($preview, 'canonical_key');
        $component->call('applyImportedFields', $selectedKeys, []);

        // Waterfront correct
        $component->assertSet('waterfront', 'no');

        // Rent Includes correct
        $rentIncludes = $component->get('rent_includes');
        $this->assertNotEmpty($rentIncludes);
        $this->assertContains('Lawn Care', $rentIncludes);

        // Water View clean
        $waterView = $component->get('water_view');
        if (!empty($waterView)) {
            $this->assertStringNotContainsStringIgnoringCase('Assessment', implode(',', $waterView));
            $this->assertStringNotContainsStringIgnoringCase('Tax', implode(',', $waterView));
        }

        // Structural
        $this->assertContains('Shingle', $component->get('roof_type'));
        $this->assertContains('Slab', $component->get('foundation'));

        // Lot dimensions
        $component->assertSet('lot_dimensions', '65x110');

        // Modal closed after apply
        $component->assertSet('showImportModal', false);
        $component->assertSet('importSuccess', true);
    }

    // ─── Fixture-based preview cleanliness ───────────────────────────────────

    /**
     * Seller residential fixture: every preview value shown to the user
     * must be free of cross-field contamination.
     */
    public function test_seller_fixture_preview_modal_values_are_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        $component->assertSet('importError', '');
        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview);

        $byKey = [];
        foreach ($preview as $row) {
            $byKey[$row['canonical_key']] = $row['value'];
        }

        // City
        $this->assertArrayHasKey('city', $byKey);
        $this->assertStringNotContainsStringIgnoringCase('County', $byKey['city']);
        $this->assertStringNotContainsStringIgnoringCase('Hillsborough', $byKey['city']);

        // Waterfront: normalized
        $this->assertArrayHasKey('waterfront', $byKey);
        $this->assertContains($byKey['waterfront'], ['yes', 'no']);

        // Water View: no Assessment/Tax bleed
        if (isset($byKey['water_view'])) {
            $this->assertStringNotContainsStringIgnoringCase('Assessment', $byKey['water_view']);
            $this->assertStringNotContainsStringIgnoringCase('Tax', $byKey['water_view']);
            $this->assertStringNotContainsStringIgnoringCase('Flood', $byKey['water_view']);
        }

        // Appliances: no Rooms/Exterior bleed
        if (isset($byKey['appliances'])) {
            $this->assertStringNotContainsStringIgnoringCase('Rooms', $byKey['appliances']);
            $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $byKey['appliances']);
            $this->assertStringNotContainsStringIgnoringCase('Interior Information', $byKey['appliances']);
        }

        // Interior Features: no Appliances bleed
        if (isset($byKey['interior_features'])) {
            $this->assertStringNotContainsStringIgnoringCase('Appliances', $byKey['interior_features']);
            $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $byKey['interior_features']);
        }

        // Carport: no Tax/Legal bleed
        if (isset($byKey['carport'])) {
            $this->assertStringNotContainsStringIgnoringCase('Tax', $byKey['carport']);
            $this->assertStringNotContainsStringIgnoringCase('Legal', $byKey['carport']);
        }
    }

    /**
     * Landlord rental fixture: every preview value shown to the user
     * must be free of cross-field contamination.
     */
    public function test_landlord_fixture_preview_modal_values_are_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/rental.txt'));

        $component = Livewire::actingAs($this->landlordUser)
            ->test(LandlordOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        $component->assertSet('importError', '');
        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview);

        $byKey = [];
        foreach ($preview as $row) {
            $byKey[$row['canonical_key']] = $row['value'];
        }

        // City clean
        $this->assertArrayHasKey('city', $byKey);
        $this->assertStringNotContainsStringIgnoringCase('County', $byKey['city']);

        // Waterfront: normalized
        $this->assertArrayHasKey('waterfront', $byKey);
        $this->assertContains($byKey['waterfront'], ['yes', 'no']);

        // Rent Includes: no Waterfront bleed
        if (isset($byKey['rent_includes'])) {
            $this->assertStringNotContainsStringIgnoringCase('Waterfront', $byKey['rent_includes']);
            $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $byKey['rent_includes']);
            $this->assertStringNotContainsStringIgnoringCase('Flood', $byKey['rent_includes']);
        }

        // Water View: no Assessment/Tax bleed
        if (isset($byKey['water_view'])) {
            $this->assertStringNotContainsStringIgnoringCase('Assessment', $byKey['water_view']);
            $this->assertStringNotContainsStringIgnoringCase('Tax', $byKey['water_view']);
        }

        // Carport: no Rental Rate Type bleed
        if (isset($byKey['carport'])) {
            $this->assertStringNotContainsStringIgnoringCase('Rental Rate', $byKey['carport']);
            $this->assertStringNotContainsStringIgnoringCase('Monthly', $byKey['carport']);
        }

        // Appliances: no section header bleed
        if (isset($byKey['appliances'])) {
            $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $byKey['appliances']);
            $this->assertStringNotContainsStringIgnoringCase('Interior Information', $byKey['appliances']);
            $this->assertStringNotContainsStringIgnoringCase('Rooms', $byKey['appliances']);
        }
    }

    // ─── Apply Selected: empty form, all fields fill correctly ───────────────

    /**
     * Seller: verify every field in a parsed result maps to the correct
     * Livewire prop after applyImportedFields.
     */
    public function test_seller_apply_selected_maps_all_fields_to_correct_props(): void
    {
        $raw =
            'City: Tampa County: Hillsborough State: FL Zip: 33610 ' .
            'Bedrooms: 3 Bathrooms: 2 Heated Sq. Ft.: 1,800 Year Built: 2005 ' .
            'Pool: No Garage: Yes Carport: No ' .
            'Lot Dimensions: 70x110 ' .
            'Interior Features: Crown Molding,High Ceilings ' .
            'Appliances: Dishwasher,Refrigerator ' .
            'Roof Type: Shingle Exterior Construction: Block Foundation: Slab ' .
            'Waterfront: No Water View: Lake Flood Zone Code: X';

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview);

        $selectedKeys  = array_column($preview, 'canonical_key');
        $component->call('applyImportedFields', $selectedKeys, []);

        // Scalar fields → correct props
        $component->assertSet('property_city', 'Tampa');
        $component->assertSet('waterfront', 'no');
        $component->assertSet('flood_zone_code', 'X');
        $component->assertSet('lot_dimensions', '70x110');
        $component->assertSet('year_built', '2005');

        // Array fields → correct props
        $this->assertContains('Crown Molding', $component->get('interior_features'));
        $this->assertContains('High Ceilings', $component->get('interior_features'));
        $this->assertContains('Dishwasher', $component->get('appliances'));
        $this->assertContains('Refrigerator', $component->get('appliances'));
        $this->assertContains('Shingle', $component->get('roof_type'));
        $this->assertContains('Block', $component->get('exterior_construction'));
        $this->assertContains('Slab', $component->get('foundation'));
        $this->assertContains('Lake', $component->get('water_view'));
    }

    /**
     * Landlord: verify every field maps to correct prop after applyImportedFields.
     */
    public function test_landlord_apply_selected_maps_all_fields_to_correct_props(): void
    {
        $raw =
            'City: St. Petersburg County: Pinellas State: FL Zip: 33705 ' .
            'Monthly Rent: $1,950 Bedrooms: 2 Bathrooms: 1 ' .
            'Heated Sq. Ft.: 1,100 Year Built: 1998 ' .
            'Carport: No Waterfront: No ' .
            'Interior Features: Ceiling Fans(s),Open Floorplan ' .
            'Appliances: Dishwasher,Range Rent Includes: Water,Trash ' .
            'Roof Type: Tile Foundation: Slab ' .
            'Water View: Bay/Harbor - Partial ' .
            'Lot Dimensions: 60x90 Flood Zone Code: AE';

        $component = Livewire::actingAs($this->landlordUser)
            ->test(LandlordOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        $preview = $component->get('importPreviewData');
        $this->assertNotEmpty($preview);

        $selectedKeys = array_column($preview, 'canonical_key');
        $component->call('applyImportedFields', $selectedKeys, []);

        // Scalar
        $component->assertSet('property_city', 'St. Petersburg');
        $component->assertSet('waterfront', 'no');
        $component->assertSet('flood_zone_code', 'AE');
        $component->assertSet('lot_dimensions', '60x90');
        $component->assertSet('year_built', '1998');

        // Array
        $this->assertContains('Ceiling Fans(s)', $component->get('interior_features'));
        $this->assertContains('Dishwasher', $component->get('appliances'));
        $this->assertContains('Water', $component->get('rent_includes'));
        $this->assertContains('Tile', $component->get('roof_type'));
        $this->assertContains('Slab', $component->get('foundation'));

        // Water View clean and correct
        $waterView = $component->get('water_view');
        $this->assertNotEmpty($waterView);
        $waterViewStr = implode(',', $waterView);
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $waterViewStr);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $waterViewStr);
    }
}

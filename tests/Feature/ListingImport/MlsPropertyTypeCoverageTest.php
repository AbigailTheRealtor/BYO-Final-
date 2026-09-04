<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\Mls\MlsListingDetailsReader;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ALL SEVEN PROPERTY TYPES, END TO END.
 *
 * The 2026-09-04 audit's sharpest per-type finding was that the import was built
 * and tested against residential records while dropping essentially the entire
 * commercial, lease, income and land vocabularies. A residential-only test suite
 * is what let that persist, so this one runs the real flow — lookup, build,
 * persist, read back — once per Stellar category, and asserts each type's own
 * facts survive it.
 *
 * The per-type assertions are deliberately about DIFFERENT fields for each type.
 * That is the product requirement restated as a test: a residential listing and
 * a commercial one should not show the same 90 rows, they should each show the
 * facts their own record actually carries.
 */
class MlsPropertyTypeCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
        ]);
    }

    private function fixture(string $slug): array
    {
        return json_decode(
            (string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")),
            true
        );
    }

    private function seedRecord(string $slug): array
    {
        $raw = $this->fixture($slug);

        BridgeProperty::create([
            'listing_key'             => $raw['ListingKey'],
            'listing_id'              => $raw['ListingId'],
            'standard_status'         => $raw['StandardStatus'] ?? 'Active',
            'property_type'           => $raw['PropertyType'] ?? null,
            'property_sub_type'       => $raw['PropertySubType'] ?? null,
            'list_price'              => $raw['ListPrice'] ?? null,
            'unparsed_address'        => $raw['UnparsedAddress'] ?? null,
            'city'                    => $raw['City'] ?? null,
            'state_or_province'       => $raw['StateOrProvince'] ?? null,
            'postal_code'             => $raw['PostalCode'] ?? null,
            'bedrooms_total'          => $raw['BedroomsTotal'] ?? null,
            'bathrooms_total_integer' => $raw['BathroomsTotalInteger'] ?? null,
            'living_area'             => $raw['LivingArea'] ?? null,
            'year_built'              => $raw['YearBuilt'] ?? null,
            'raw_json'                => json_encode($raw),
            'imported_at'             => now(),
        ]);

        return $raw;
    }

    /** Run the real read half of the flow and return the persisted payload. */
    private function importAndRead(string $slug, string $role = 'seller'): MlsSupplementalDetails
    {
        $raw  = $this->seedRecord($slug);
        $user = User::factory()->create();

        $result = app(MlsQuickImportService::class)->lookup($raw['ListingId'], $role);

        $this->assertTrue($result->isFound(), "Lookup failed for {$slug}: {$result->status}");

        $listing = app(MlsQuickImportDraftWriter::class)->materialise($role, $user->id, $result);

        $this->assertNotNull($listing, "Draft was not materialised for {$slug}");

        $meta = [];
        foreach ($listing->meta as $row) {
            $decoded = json_decode($row->meta_value, true);
            $meta[$row->meta_key] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : $row->meta_value;
        }

        return (new MlsListingDetailsReader())->detailsFrom($meta);
    }

    private function flatten(MlsSupplementalDetails $d): string
    {
        $out = '';

        foreach ($d->sections as $section) {
            $out .= $section['title'] . ' | ';
            foreach ($section['rows'] as $row) {
                $out .= $row['label'] . '=' . $row['value'] . ' | ';
            }
        }

        return $out;
    }

    public static function typeProvider(): array
    {
        return [
            // slug, role, the facts this TYPE must carry through the import
            'residential' => ['residential', 'seller', [
                'Subdivision', 'Community Features', 'Full Bathrooms', 'Levels',
                'Total Monthly Fees', 'MLS #', 'Days on Market', 'Brokerage',
            ]],
            // `Available From` and `Rent Frequency` are deliberately absent from
            // this list: both are Tier 1 on landlord and render from the
            // editable Leasing Terms fields, so repeating them here would be the
            // duplicate the supplemental layer exists to avoid.
            'residential lease' => ['residential_lease', 'landlord', [
                'Months Available', 'Minimum Lease', 'Owner Pays', 'Tenant Pays',
                'Application Fee', 'Pets Allowed', 'Currently Occupied By',
                'MLS #', 'Brokerage',
            ]],
            'income' => ['income', 'seller', [
                'Gross Scheduled Income', 'Net Operating Income', 'Cap Rate',
                'Total Units', 'Number of Buildings', 'Separate Electric Meters',
            ]],
            'commercial sale' => ['commercial_sale', 'seller', [
                'Ownership', 'Road Frontage', 'MLS Area', 'MLS #', 'Brokerage',
            ]],
            'commercial lease' => ['commercial_lease', 'landlord', [
                'Leasable Area', 'Office / Retail Space', 'Offices', 'Ceiling Height',
                'Lease Term', 'Minimum Lease', 'Space Type',
            ]],
            'business opportunity' => ['business_opportunity', 'seller', [
                'Business Name', 'Year Established', 'Sold With Real Estate',
            ]],
            // `Number of Lots` is Tier 1 (→ total_parcel_count) and renders from
            // the editable field, so it is not expected here.
            'vacant land' => ['vacant_land', 'seller', [
                'Total Acreage (MLS Range)', 'Future Land Use', 'Vegetation',
                'Road Surface', 'Adjoining Property', 'Lot Features',
            ]],
        ];
    }

    /**
     * @test
     * @dataProvider typeProvider
     */
    public function each_property_type_carries_its_own_facts_through_the_import(
        string $slug,
        string $role,
        array $expectedLabels
    ): void {
        $details  = $this->importAndRead($slug, $role);
        $rendered = $this->flatten($details);

        $this->assertFalse($details->isEmpty(), "{$slug} produced no MLS Details at all");

        $this->assertGreaterThan(
            25,
            $details->rowCount(),
            "{$slug} produced only {$details->rowCount()} MLS Detail rows — the type's vocabulary is being dropped"
        );

        foreach ($expectedLabels as $label) {
            $this->assertStringContainsString(
                $label . '=',
                $rendered,
                "{$slug} lost the '{$label}' fact during import"
            );
        }
    }

    /**
     * @test
     * @dataProvider typeProvider
     *
     * Not one blank row, on any type. This is the requirement that a listing
     * shows the facts it has rather than 553 labels with dashes beside them.
     */
    public function no_property_type_produces_a_blank_row_or_an_empty_section(
        string $slug,
        string $role
    ): void {
        $details = $this->importAndRead($slug, $role);

        foreach ($details->sections as $section) {
            $this->assertNotSame('', trim($section['title']), "{$slug} produced an untitled section");
            $this->assertNotEmpty($section['rows'], "{$slug} produced an empty section: {$section['title']}");

            foreach ($section['rows'] as $row) {
                $this->assertNotSame('', trim($row['label']), "{$slug} produced a row with no label");
                $this->assertNotSame('', trim($row['value']), "{$slug} produced a blank value for {$row['label']}");
                $this->assertStringNotContainsString('STELLAR_', $row['label'],
                    "{$slug} leaked a raw feed column name into a label");
            }
        }
    }

    /**
     * @test
     *
     * The two types differ, and differ in the right direction: a commercial
     * record shows commercial facts a residential one does not, and vice versa.
     * A test that only asserted "some rows exist" would pass on a bug that
     * rendered the same generic set for everything.
     */
    public function types_render_materially_different_fact_sets(): void
    {
        $residential = $this->importAndRead('residential', 'seller');

        $this->refreshApplication();
        $this->setUp();

        $commercial = $this->importAndRead('commercial_sale', 'seller');

        $keysOf = static function (MlsSupplementalDetails $d): array {
            $keys = [];
            foreach ($d->sections as $section) {
                foreach ($section['rows'] as $row) {
                    $keys[] = $row['key'];
                }
            }

            return $keys;
        };

        $onlyResidential = array_diff($keysOf($residential), $keysOf($commercial));
        $onlyCommercial  = array_diff($keysOf($commercial), $keysOf($residential));

        $this->assertNotEmpty($onlyResidential, 'Residential rendered nothing commercial did not');
        $this->assertNotEmpty($onlyCommercial, 'Commercial rendered nothing residential did not');
    }
}

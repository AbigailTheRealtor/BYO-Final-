<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\ListingImport\Mls\MlsSupplementalDetails;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\ListingImport\Sync\MlsFactProjection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quick Import must not silently lose a permitted MLS fact.
 *
 * THE CONTRACT
 * ------------
 * A source fact is written to a native listing field when a compatible one
 * exists and the import actually fills it COMPLETELY. Otherwise it is kept in
 * MLS Property Details. It is left out of MLS Property Details only when the
 * native field really holds the same information — never merely because a field
 * map names a target.
 *
 * The 2026-09-11 field-completeness audit measured the gap on the 1,203 real
 * cached Stellar records: `BuildingAreaTotal` lost on 415 Residential Lease
 * imports, `BusinessType` values after the first lost on 74 Business
 * Opportunity / Commercial Sale imports, `LivingAreaSource = Estimated` lost on
 * 11, and a latent loss of any TRUE pool / garage / carport on a property type
 * whose form has no such control.
 *
 * HOW THESE RUN
 * -------------
 * Each record goes through the real Quick Import read path: the ingestion
 * normalizer's upsert into bridge_properties, the cached row read back through
 * the candidate adapter, MlsQuickImportService::assembleFromCandidate(), the
 * shared MlsFactProjection the draft writer uses, and the persisted MLS Details
 * blob read back through MlsSupplementalDetails::fromStored() — the bytes the
 * Review screen and the finished listing page render. Two tests drive the
 * Livewire flow end to end.
 */
class MlsQuickImportFieldCompletenessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_related_resources.enabled'          => false,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
        ]);
    }

    // ── A. BuildingAreaTotal ─────────────────────────────────────────────────

    /** @test */
    public function residential_lease_building_area_survives_in_mls_property_details(): void
    {
        $raw = $this->fixture('residential_lease');
        $this->assertSame(1228, $raw['BuildingAreaTotal'], 'fixture changed underneath this test');

        $out = $this->import($raw, 'landlord');

        // The landlord map deliberately has no building-size target (pinned by
        // BridgeFactReconciliationTest), so nothing native carries the figure...
        $this->assertArrayNotHasKey('total_square_feet', $out['native']);
        // ...and MLS Property Details therefore must.
        $this->assertRow($out, 'BuildingAreaTotal', 'Property Details', 'Total Building Area', '1228');
    }

    /** @test */
    public function commercial_lease_building_area_survives_in_mls_property_details(): void
    {
        $out = $this->import($this->fixture('commercial_lease'), 'landlord');

        $this->assertArrayNotHasKey('total_square_feet', $out['native']);
        $this->assertRow($out, 'BuildingAreaTotal', 'Property Details', 'Total Building Area', '3536');
    }

    /** @test */
    public function a_seller_building_area_written_natively_is_not_repeated(): void
    {
        $out = $this->import($this->fixture('residential'), 'seller');

        $this->assertSame(775, (int) $out['native']['total_square_feet']);
        $this->assertArrayNotHasKey('BuildingAreaTotal', $out['rows'], 'Total SqFt would be printed twice');
    }

    /** @test */
    public function landlord_quick_import_persists_building_area_end_to_end(): void
    {
        $raw     = $this->fixture('residential_lease');
        $auction = $this->quickImport($raw, 'landlord', LandlordMlsQuickImport::class, LandlordAgentAuction::class);

        $this->assertEmpty($auction->info('total_square_feet'));
        $this->assertSame(
            '1228',
            $this->storedRows($auction)['BuildingAreaTotal']['value'] ?? null,
            'the persisted MLS Details blob must carry the building area the landlord form does not'
        );
    }

    // ── B. BusinessType ─────────────────────────────────────────────────────

    /** @test */
    public function business_opportunity_keeps_every_business_type_value(): void
    {
        $raw = $this->fixture('business_opportunity');
        $this->assertSame(['Agriculture', 'Other', 'Wholesale'], $raw['BusinessType']);

        $out = $this->import($raw, 'seller');

        // The single-select still gets its first recognised value...
        $this->assertSame('Agriculture', $out['native']['business_type']);
        // ...and the values it cannot hold survive, in the feed's own order.
        $this->assertRow($out, 'BusinessType', 'Commercial / Business', 'Business Type', 'Agriculture, Other, Wholesale');
    }

    /** @test */
    public function commercial_sale_keeps_every_business_type_value(): void
    {
        $raw = array_merge($this->fixture('commercial_sale'), [
            'BusinessType' => ['Retail', 'Professional/Office', 'Warehouse'],
        ]);

        $out = $this->import($raw, 'seller');

        $this->assertSame('Retail', $out['native']['business_type']);
        $this->assertRow($out, 'BusinessType', 'Commercial / Business', 'Business Type', 'Retail, Professional/Office, Warehouse');
    }

    /** @test */
    public function a_single_business_type_written_natively_is_not_repeated(): void
    {
        $out = $this->import(
            array_merge($this->fixture('business_opportunity'), ['BusinessType' => ['Agriculture']]),
            'seller',
        );

        $this->assertSame('Agriculture', $out['native']['business_type']);
        $this->assertArrayNotHasKey('BusinessType', $out['rows']);
    }

    /** @test */
    public function a_business_type_the_select_cannot_offer_is_kept_in_mls_details(): void
    {
        $out = $this->import(
            array_merge($this->fixture('business_opportunity'), ['BusinessType' => ['Laundromat Route']]),
            'seller',
        );

        $this->assertArrayNotHasKey('business_type', $out['native']);
        $this->assertRow($out, 'BusinessType', 'Commercial / Business', 'Business Type', 'Laundromat Route');
    }

    /** @test */
    public function seller_quick_import_persists_every_business_type_end_to_end(): void
    {
        $raw     = $this->fixture('business_opportunity');
        $auction = $this->quickImport($raw, 'seller', SellerMlsQuickImport::class, SellerAgentAuction::class);

        $this->assertSame('Agriculture', (string) $auction->info('business_type'));
        $this->assertSame(
            'Agriculture, Other, Wholesale',
            $this->storedRows($auction)['BusinessType']['value'] ?? null,
        );
    }

    // ── C. LivingAreaSource ─────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider estimatedProvider
     *
     * No Create Offer select offers "Estimated" (Appraisal / Builder / Measured /
     * Owner Provided / Public Records only), so it is not invented as a form
     * value — it is kept in MLS Property Details instead.
     */
    public function living_area_source_estimated_survives(string $slug, string $role): void
    {
        $out = $this->import(array_merge($this->fixture($slug), ['LivingAreaSource' => 'Estimated']), $role);

        $this->assertArrayNotHasKey('sqft_heated_source', $out['native']);
        $this->assertRow($out, 'LivingAreaSource', 'Property Details', 'Living Area Source', 'Estimated');
    }

    public function estimatedProvider(): array
    {
        return [
            'commercial sale (seller)'   => ['commercial_sale', 'seller'],
            'residential lease (landlord)' => ['residential_lease', 'landlord'],
        ];
    }

    /** @test */
    public function a_living_area_source_the_form_offers_is_not_repeated(): void
    {
        $out = $this->import($this->fixture('residential_lease'), 'landlord');

        $this->assertSame('Public Records', $out['native']['sqft_heated_source']);
        $this->assertArrayNotHasKey('LivingAreaSource', $out['rows']);
    }

    // ── D. Property-type applicability ──────────────────────────────────────

    /**
     * @test
     * @dataProvider inapplicableFeatureProvider
     *
     * The projection refuses to write a feature into a control this property
     * type's form never renders. That refusal is right — and it must not ALSO
     * hide the fact from MLS Property Details, which is how a true garage on an
     * Income property used to vanish at both layers.
     *
     * @param array<string,mixed>  $overrides
     * @param array<string,string> $expect     Bridge field => native meta key that must stay unwritten
     */
    public function a_fact_the_form_cannot_take_for_this_type_stays_in_mls_details(
        string $slug,
        string $role,
        array $overrides,
        array $expect,
    ): void {
        $out = $this->import(array_merge($this->fixture($slug), $overrides), $role);

        $sections = ['PoolPrivateYN' => 'Pool / Spa', 'GarageYN' => 'Parking / Garage', 'CarportYN' => 'Parking / Garage'];

        foreach ($expect as $field => $metaKey) {
            $this->assertArrayNotHasKey($metaKey, $out['native'], "{$metaKey} was forced into a control {$slug} does not render");
            $this->assertArrayHasKey($field, $out['rows'], "{$field} = true was lost on {$slug}");
            $this->assertSame('Yes', $out['rows'][$field]['value']);
            $this->assertSame($sections[$field], $out['rows'][$field]['section']);
        }
    }

    public function inapplicableFeatureProvider(): array
    {
        $all = ['PoolPrivateYN' => true, 'GarageYN' => true, 'CarportYN' => true];
        $allTargets = ['PoolPrivateYN' => 'pool_needed', 'GarageYN' => 'garage_needed', 'CarportYN' => 'carport_needed'];

        return [
            'income garage + carport (seller)' => [
                'income', 'seller',
                ['GarageYN' => true, 'CarportYN' => true],
                ['GarageYN' => 'garage_needed', 'CarportYN' => 'carport_needed'],
            ],
            'commercial lease (landlord)'   => ['commercial_lease', 'landlord', $all, $allTargets],
            'commercial sale (seller)'      => ['commercial_sale', 'seller', $all, $allTargets],
            'business opportunity (seller)' => ['business_opportunity', 'seller', $all, $allTargets],
            'vacant land (seller)'          => ['vacant_land', 'seller', $all, $allTargets],
        ];
    }

    /** @test */
    public function an_applicable_feature_written_natively_is_not_repeated(): void
    {
        // Residential Lease: garage and pool are real controls on the
        // Residential Property form, and the fixture has both true.
        $lease = $this->import($this->fixture('residential_lease'), 'landlord');

        $this->assertSame('Yes', $lease['native']['garage_needed']);
        $this->assertSame('Yes', $lease['native']['pool_needed']);
        $this->assertArrayNotHasKey('GarageYN', $lease['rows']);
        $this->assertArrayNotHasKey('PoolPrivateYN', $lease['rows']);

        // Income: pool IS rendered for Income, so a true pool is written and
        // not repeated, while the inapplicable garage beside it is kept.
        $income = $this->import(
            array_merge($this->fixture('income'), ['PoolPrivateYN' => true, 'GarageYN' => true]),
            'seller',
        );

        $this->assertSame('Yes', $income['native']['pool_needed']);
        $this->assertArrayNotHasKey('PoolPrivateYN', $income['rows']);
        $this->assertArrayNotHasKey('garage_needed', $income['native']);
        $this->assertArrayHasKey('GarageYN', $income['rows']);
    }

    // ── Regression: everything else is unchanged ────────────────────────────

    /** @test */
    public function residential_seller_import_is_otherwise_unchanged(): void
    {
        $raw = $this->fixture('residential');
        $out = $this->import($raw, 'seller');

        // Canonical facts still land where they always did.
        $this->assertSame('Residential', $out['native']['property_type']);
        $this->assertSame((string) $raw['BedroomsTotal'], (string) $out['native']['bedrooms']);
        $this->assertSame((string) $raw['YearBuilt'], (string) $out['native']['year_built']);
        $this->assertSame((float) $raw['ListPrice'], (float) $out['native']['maximum_budget']);

        // Facts already written natively are still not repeated.
        foreach (['Appliances', 'Roof', 'ConstructionMaterials', 'TaxAnnualAmount'] as $field) {
            $this->assertArrayNotHasKey($field, $out['rows'], "{$field} is now printed twice");
        }

        // Property Sub-Type, contacts, MLS status and media behave as before.
        $this->assertRow($out, 'PropertySubType', 'Property Details', 'Property Sub-Type', (string) $raw['PropertySubType']);
        $this->assertNotEmpty($out['details']->group('contacts'));
        $this->assertSame((string) $raw['StandardStatus'], $out['rows']['StandardStatus']['value'] ?? null);
        $this->assertSame($raw['StandardStatus'], $out['result']->standardStatus);
        $this->assertSame((float) $raw['ListPrice'], $out['result']->listPrice);
        $this->assertGreaterThan(0, $out['result']->photoCount());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function fixture(string $slug): array
    {
        return json_decode(file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")), true);
    }

    /**
     * The Quick Import read path, minus the Livewire shell.
     *
     * @return array{native: array<string,mixed>, rows: array<string,array{section:string,label:string,value:string}>, details: MlsSupplementalDetails, result: \App\Services\ListingImport\QuickImport\MlsQuickImportResult}
     */
    private function import(array $raw, string $role): array
    {
        $model  = app(BridgePropertyNormalizer::class)->upsert($raw)->model->fresh();
        $result = app(MlsQuickImportService::class)->assembleFromCandidate(
            app(BridgePropertyCandidateAdapter::class)->fromModel($model),
            $role,
        );

        $this->assertTrue($result->isFound(), "{$role} import found nothing to import");

        $native = (new MlsFactProjection())->project(
            role:               $role,
            facts:              $result->facts,
            existing:           [],
            mode:               MlsFactProjection::MODE_IMPORT,
            sourcePropertyType: $result->sourcePropertyType,
        );

        $details = MlsSupplementalDetails::fromStored(json_decode(json_encode($result->details->toArray()), true));

        return ['native' => $native, 'rows' => $this->rowsOf($details), 'details' => $details, 'result' => $result];
    }

    /** Drive the real Livewire Quick Import and return the owned draft it wrote. */
    private function quickImport(array $raw, string $role, string $component, string $modelClass): object
    {
        app(BridgePropertyNormalizer::class)->upsert($raw);

        $user = User::factory()->create(['user_type' => $role]);

        $listingId = Livewire::actingAs($user)
            ->test($component)
            ->set('mlsNumber', $raw['ListingId'])
            ->call('findListing')
            ->call('acceptProperty')
            ->get('listingId');

        $this->assertNotNull($listingId, "{$role} quick import did not create a draft");

        return $modelClass::find($listingId);
    }

    private function storedRows(object $auction): array
    {
        return $this->rowsOf(MlsSupplementalDetails::fromStored(
            $auction->info(MlsQuickImportDraftWriter::META_PROPERTY_DETAILS)
        ));
    }

    /** @return array<string,array{section:string,label:string,value:string}> */
    private function rowsOf(MlsSupplementalDetails $details): array
    {
        $rows = [];

        foreach ($details->sections as $section) {
            foreach ($section['rows'] as $row) {
                $rows[$row['key']] = ['section' => $section['title'], 'label' => $row['label'], 'value' => $row['value']];
            }
        }

        return $rows;
    }

    private function assertRow(array $out, string $field, string $section, string $label, string $value): void
    {
        $this->assertArrayHasKey($field, $out['rows'], "{$field} is missing from MLS Property Details — the fact was lost");
        $this->assertSame(
            ['section' => $section, 'label' => $label, 'value' => $value],
            $out['rows'][$field],
        );
    }
}

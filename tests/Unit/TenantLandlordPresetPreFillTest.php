<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AgentDefaultProfile;
use App\Services\AgentBidMapperService;
use App\Services\AgentPresetCatalog;
use App\Helpers\TenantBidMatchScoreHelper;
use App\Helpers\LandlordBidMatchScoreHelper;

/**
 * Task #118 Verification Tests
 *
 * Confirms that the preset-to-bid-form pre-fill pipeline works correctly for
 * the Tenant and Landlord roles across Residential and Commercial property types.
 *
 * Pipeline under test:
 *   1. Agent saves a preset via AgentPresetController
 *      → AgentDefaultProfile::upsertForAgent() stores profile_data (with services)
 *   2. Agent opens a new bid form → AgentBidMapperService::findAndMap() is called
 *      → AgentDefaultProfile::findForAgentWithFallback() returns the saved profile
 *      → AgentBidMapperService::mapFromProfile() transforms profile_data into bid fields
 *   3. Bid form mount() applies the mapped fields:
 *      - Profile fields (bio, credentials, links) assigned directly
 *      - Services passed through filterServicesToCurrentCatalog() → $this->services
 *
 * All tests are pure unit tests — no database required.
 * AgentDefaultProfile instances are created in-memory (not persisted) to exercise
 * the exact data path that findAndMap() uses once a record is retrieved.
 */
class TenantLandlordPresetPreFillTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers shared by multiple tests
    // ──────────────────────────────────────────────────────────────────────────

    private function normalizeServiceLabel(string $s): string
    {
        return mb_strtolower(trim(str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "'"],
            ["'",        "'",        '"',        '"',        "'"],
            $s
        )));
    }

    private function applyFilterToCurrentCatalog(array $services, string $role, string $propertyType): array
    {
        $propTypeMap = [
            'Residential Property' => 'residential',
            'Commercial Property'  => 'commercial',
            'residential'          => 'Residential Property',
            'commercial'           => 'Commercial Property',
        ];

        $catalogKey = $propTypeMap[$propertyType] ?? $propertyType;
        $catalog = ($role === 'tenant')
            ? TenantBidMatchScoreHelper::getCatalog($catalogKey)
            : LandlordBidMatchScoreHelper::getCatalog($catalogKey);

        $normalize = fn(string $s) => $this->normalizeServiceLabel($s);

        return array_values(array_filter($services, fn($svc) => in_array($normalize((string) $svc), $catalog, true)));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AgentBidMapperService::mapFromProfile() — services are now included
    // These tests confirm that services saved to profile_data by the preset editor
    // are present in the mapped output consumed by bid form mount() methods.
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_includes_services_key_in_mapped_output(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([]);

        $this->assertArrayHasKey('services', $mapped,
            'mapFromProfile() must expose a services key so bid form mount() can read it');
        $this->assertIsArray($mapped['services'],
            'services must always be an array in the mapped output');
    }

    /** @test */
    public function mapper_includes_other_services_key_in_mapped_output(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([]);

        $this->assertArrayHasKey('other_services', $mapped,
            'mapFromProfile() must expose other_services so custom services round-trip');
    }

    /** @test */
    public function mapper_preserves_services_from_tenant_residential_preset(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('tenant', 'residential'), 0, 3);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'    => $presetServices,
            'bio'         => 'Residential tenant expert.',
            'first_name'  => 'Sara',
            'license_no'  => 'RE-100',
        ];

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        $this->assertSame($presetServices, $mapped['services'],
            'Services selected in the Tenant/Residential preset editor must survive mapFromProfile()');
        $this->assertSame('Residential tenant expert.', $mapped['bio']);
        $this->assertSame('Sara', $mapped['first_name']);
        $this->assertSame('RE-100', $mapped['license_no']);
    }

    /** @test */
    public function mapper_preserves_services_from_tenant_commercial_preset(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('tenant', 'commercial'), 0, 3);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'   => $presetServices,
            'bio'        => 'Commercial tenant expert.',
            'first_name' => 'Kevin',
        ];

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        $this->assertSame($presetServices, $mapped['services'],
            'Services selected in the Tenant/Commercial preset editor must survive mapFromProfile()');
        $this->assertCount(3, $mapped['services']);
    }

    /** @test */
    public function mapper_preserves_services_from_landlord_residential_preset(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('landlord', 'residential'), 0, 4);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'   => $presetServices,
            'bio'        => 'Residential landlord specialist.',
            'license_no' => 'RE-200',
        ];

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        $this->assertSame($presetServices, $mapped['services'],
            'Services selected in the Landlord/Residential preset editor must survive mapFromProfile()');
        $this->assertCount(4, $mapped['services']);
    }

    /** @test */
    public function mapper_preserves_services_from_landlord_commercial_preset(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('landlord', 'commercial'), 0, 4);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'   => $presetServices,
            'bio'        => 'Commercial landlord specialist.',
            'license_no' => 'RE-300',
        ];

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        $this->assertSame($presetServices, $mapped['services'],
            'Services selected in the Landlord/Commercial preset editor must survive mapFromProfile()');
        $this->assertCount(4, $mapped['services']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Catalog string compatibility — preset services survive filterServicesToCurrentCatalog()
    //
    // The bid form components (TenantAgentAuctionBid, LandlordAgentAuctionBid)
    // pass mapped['services'] through filterServicesToCurrentCatalog() before
    // assigning to $this->services.  If a preset service doesn't pass the filter
    // it would be silently dropped.
    //
    // These tests confirm that EVERY service from AgentPresetCatalog passes the
    // filter for each of the four role/property-type combinations.
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_residential_preset_services_all_survive_catalog_filter(): void
    {
        $presetServices = AgentPresetCatalog::getServices('tenant', 'residential');
        $scorerCatalog  = TenantBidMatchScoreHelper::getCatalog('Residential Property');

        $missing = [];
        foreach ($presetServices as $svc) {
            if (!in_array($this->normalizeServiceLabel($svc), $scorerCatalog, true)) {
                $missing[] = $svc;
            }
        }

        $this->assertEmpty($missing,
            'These Tenant/Residential preset services would be stripped by filterServicesToCurrentCatalog(): '
            . implode('; ', $missing));
    }

    /** @test */
    public function tenant_commercial_preset_services_all_survive_catalog_filter(): void
    {
        $presetServices = AgentPresetCatalog::getServices('tenant', 'commercial');
        $scorerCatalog  = TenantBidMatchScoreHelper::getCatalog('Commercial Property');

        $missing = [];
        foreach ($presetServices as $svc) {
            if (!in_array($this->normalizeServiceLabel($svc), $scorerCatalog, true)) {
                $missing[] = $svc;
            }
        }

        $this->assertEmpty($missing,
            'These Tenant/Commercial preset services would be stripped by filterServicesToCurrentCatalog(): '
            . implode('; ', $missing));
    }

    /** @test */
    public function landlord_residential_preset_services_all_survive_catalog_filter(): void
    {
        $presetServices = AgentPresetCatalog::getServices('landlord', 'residential');
        $scorerCatalog  = LandlordBidMatchScoreHelper::getCatalog('Residential Property');

        $missing = [];
        foreach ($presetServices as $svc) {
            if (!in_array($this->normalizeServiceLabel($svc), $scorerCatalog, true)) {
                $missing[] = $svc;
            }
        }

        $this->assertEmpty($missing,
            'These Landlord/Residential preset services would be stripped by filterServicesToCurrentCatalog(): '
            . implode('; ', $missing));
    }

    /** @test */
    public function landlord_commercial_preset_services_all_survive_catalog_filter(): void
    {
        $presetServices = AgentPresetCatalog::getServices('landlord', 'commercial');
        $scorerCatalog  = LandlordBidMatchScoreHelper::getCatalog('Commercial Property');

        $missing = [];
        foreach ($presetServices as $svc) {
            if (!in_array($this->normalizeServiceLabel($svc), $scorerCatalog, true)) {
                $missing[] = $svc;
            }
        }

        $this->assertEmpty($missing,
            'These Landlord/Commercial preset services would be stripped by filterServicesToCurrentCatalog(): '
            . implode('; ', $missing));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // End-to-end pipeline — preset profile_data → mapFromProfile → filter → bid form
    //
    // These tests simulate the complete pre-fill flow as it runs in
    // TenantAgentAuctionBid::mount() and LandlordAgentAuctionBid::mount():
    //
    //   1. AgentDefaultProfile instance with known profile_data (as retrieved from DB)
    //   2. AgentBidMapperService::mapFromProfile($profile->profile_data)
    //   3. filterServicesToCurrentCatalog() applied inline (same logic as the component)
    //   4. Assert: services and all profile fields are correctly set
    //
    // The DB-lookup step (findForAgentWithFallback) is intentionally omitted here
    // because it is a trivial Eloquent WHERE + LIMIT 1 query; the interesting
    // logic is entirely in mapFromProfile() and the catalog filter.
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_residential_full_pipeline_services_and_profile_pre_fill(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('tenant', 'residential'), 0, 4);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'            => $presetServices,
            'bio'                 => 'Tenant specialist — Residential.',
            'why_hire_you'        => 'Fastest lease finder.',
            'what_sets_you_apart' => 'Bilingual.',
            'marketing_plan'      => 'Digital-first.',
            'year_licensed'       => '2014',
            'additional_details'  => 'SRES designation.',
            'first_name'          => 'Maria',
            'last_name'           => 'Lopez',
            'phone'               => '555-1000',
            'email'               => 'maria@tenant.com',
            'brokerage'           => 'Sunshine Realty',
            'license_no'          => 'RE-111',
            'nar_id'              => 'NAR-11',
            'reviews_links'       => [['text' => 'https://g.co/r1']],
            'website_link'        => ['https://maria.realty'],
            'social_media'        => [['platform' => 'IG', 'text' => '@maria']],
            'promoMaterials'      => [['type' => 'video', 'link' => 'https://yt.io/m']],
        ];

        $mapped = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);

        $filtered = $this->applyFilterToCurrentCatalog($mapped['services'], 'tenant', 'residential');

        $this->assertCount(count($presetServices), $filtered,
            'All Tenant/Residential preset services must survive the catalog filter and be available for bid form pre-fill');
        $this->assertSame($presetServices, $filtered,
            'The order and content of filtered services must exactly match what was saved in the preset');

        $this->assertSame('Tenant specialist — Residential.', $mapped['bio']);
        $this->assertSame('Fastest lease finder.', $mapped['why_hire_you']);
        $this->assertSame('Digital-first.', $mapped['marketing_plan']);
        $this->assertSame('Maria', $mapped['first_name'],
            'Credential first_name must be present for bid form assignment');
        $this->assertSame('RE-111', $mapped['license_no'],
            'Credential license_no must be present for bid form assignment');
        $this->assertSame([['text' => 'https://g.co/r1']], $mapped['reviews_links']);
        $this->assertNotEmpty($mapped['promoMaterials']);
    }

    /** @test */
    public function tenant_commercial_full_pipeline_services_and_profile_pre_fill(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('tenant', 'commercial'), 0, 4);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'    => $presetServices,
            'bio'         => 'Commercial tenant specialist.',
            'first_name'  => 'Kevin',
            'last_name'   => 'Park',
            'license_no'  => 'RE-222',
            'brokerage'   => 'CRE Partners',
        ];

        $mapped   = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);
        $filtered = $this->applyFilterToCurrentCatalog($mapped['services'], 'tenant', 'commercial');

        $this->assertCount(count($presetServices), $filtered,
            'All Tenant/Commercial preset services must survive the catalog filter');
        $this->assertSame('Commercial tenant specialist.', $mapped['bio']);
        $this->assertSame('Kevin', $mapped['first_name']);
        $this->assertSame('RE-222', $mapped['license_no']);
    }

    /** @test */
    public function landlord_residential_full_pipeline_services_and_profile_pre_fill(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('landlord', 'residential'), 0, 5);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'    => $presetServices,
            'bio'         => 'Landlord residential expert.',
            'first_name'  => 'Sandra',
            'last_name'   => 'Kim',
            'license_no'  => 'RE-333',
            'brokerage'   => 'PM Experts',
            'reviews_links' => [['text' => 'https://g.co/r2']],
        ];

        $mapped   = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);
        $filtered = $this->applyFilterToCurrentCatalog($mapped['services'], 'landlord', 'residential');

        $this->assertCount(count($presetServices), $filtered,
            'All Landlord/Residential preset services must survive the catalog filter');
        $this->assertSame('Landlord residential expert.', $mapped['bio']);
        $this->assertSame('Sandra', $mapped['first_name']);
        $this->assertSame('RE-333', $mapped['license_no']);
        $this->assertSame([['text' => 'https://g.co/r2']], $mapped['reviews_links']);
    }

    /** @test */
    public function landlord_commercial_full_pipeline_services_and_profile_pre_fill(): void
    {
        $presetServices = array_slice(AgentPresetCatalog::getServices('landlord', 'commercial'), 0, 5);

        $profile = new AgentDefaultProfile();
        $profile->profile_data = [
            'services'    => $presetServices,
            'bio'         => 'Commercial landlord specialist.',
            'first_name'  => 'Robert',
            'last_name'   => 'Chen',
            'license_no'  => 'RE-444',
            'brokerage'   => 'Chen Commercial',
        ];

        $mapped   = AgentBidMapperService::mapFromProfile($profile->profile_data ?? []);
        $filtered = $this->applyFilterToCurrentCatalog($mapped['services'], 'landlord', 'commercial');

        $this->assertCount(count($presetServices), $filtered,
            'All Landlord/Commercial preset services must survive the catalog filter');
        $this->assertSame('Commercial landlord specialist.', $mapped['bio']);
        $this->assertSame('Robert', $mapped['first_name']);
        $this->assertSame('RE-444', $mapped['license_no']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Profile field completeness — all bid form fields are present in the
    // mapped output regardless of role, confirming no key-not-found errors
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapped_output_contains_all_fields_required_by_tenant_bid_form(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([
            'services' => AgentPresetCatalog::getServices('tenant', 'residential'),
            'bio'      => 'Test',
        ]);

        $required = [
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'year_licensed', 'additional_details',
            'first_name', 'last_name', 'phone', 'email', 'brokerage',
            'license_no', 'nar_id',
            'presentation_link', 'business_card_link', 'business_card_stored_path',
            'reviews_links', 'website_link', 'social_media', 'promoMaterials',
            'services', 'other_services',
        ];

        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $mapped,
                "Field '{$field}' must be present in mapped output for Tenant bid form pre-fill");
        }
    }

    /** @test */
    public function mapped_output_contains_all_fields_required_by_landlord_bid_form(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([
            'services' => AgentPresetCatalog::getServices('landlord', 'residential'),
            'bio'      => 'Test',
        ]);

        $required = [
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'year_licensed', 'additional_details',
            'first_name', 'last_name', 'phone', 'email', 'brokerage',
            'license_no', 'nar_id',
            'presentation_link', 'business_card_link', 'business_card_stored_path',
            'reviews_links', 'website_link', 'social_media', 'promoMaterials',
            'services', 'other_services',
        ];

        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $mapped,
                "Field '{$field}' must be present in mapped output for Landlord bid form pre-fill");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Default values — absent fields produce safe defaults (no null-pointer errors)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_defaults_scalar_fields_to_empty_string(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([]);

        $scalars = [
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'year_licensed', 'additional_details',
            'first_name', 'last_name', 'phone', 'email', 'brokerage',
            'license_no', 'nar_id',
            'presentation_link', 'business_card_link', 'business_card_stored_path',
        ];

        foreach ($scalars as $field) {
            $this->assertSame('', $mapped[$field],
                "Scalar field '{$field}' must default to '' when absent from profile_data");
        }
    }

    /** @test */
    public function mapper_defaults_array_fields_to_empty_array(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([]);

        foreach (['reviews_links', 'website_link', 'social_media', 'promoMaterials', 'services', 'other_services'] as $field) {
            $this->assertIsArray($mapped[$field]);
            $this->assertEmpty($mapped[$field],
                "Array field '{$field}' must default to [] when absent");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Catalog availability — confirms preset UI can offer services for all combinations
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function catalog_has_services_for_all_tenant_landlord_combinations(): void
    {
        $combinations = [
            ['tenant',   'residential'],
            ['tenant',   'commercial'],
            ['landlord', 'residential'],
            ['landlord', 'commercial'],
        ];

        foreach ($combinations as [$role, $propertyType]) {
            $services = AgentPresetCatalog::getServices($role, $propertyType);
            $this->assertNotEmpty($services,
                "AgentPresetCatalog must have services for {$role}/{$propertyType} so agents can build presets");
            $this->assertTrue(
                AgentPresetCatalog::isValidCombination($role, $propertyType),
                "{$role}/{$propertyType} must be a valid combination in the preset editor"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Cross-role contamination guard — services from wrong role are stripped
    //
    // When a role-default fallback profile is used (e.g. a Tenant role-default
    // that was built from a Residential preset is also used for Commercial bids),
    // the catalog filter must strip any services that don't belong to the current
    // property type's catalog.
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function tenant_catalog_filter_strips_landlord_services(): void
    {
        $landlordService = AgentPresetCatalog::getServices('landlord', 'residential')[0];

        $filtered = $this->applyFilterToCurrentCatalog([$landlordService], 'tenant', 'residential');

        $this->assertEmpty($filtered,
            'A service from the Landlord catalog must not survive the Tenant catalog filter');
    }

    /** @test */
    public function landlord_catalog_filter_strips_tenant_services(): void
    {
        $tenantService = AgentPresetCatalog::getServices('tenant', 'residential')[0];

        $filtered = $this->applyFilterToCurrentCatalog([$tenantService], 'landlord', 'residential');

        $this->assertEmpty($filtered,
            'A service from the Tenant catalog must not survive the Landlord catalog filter');
    }
}

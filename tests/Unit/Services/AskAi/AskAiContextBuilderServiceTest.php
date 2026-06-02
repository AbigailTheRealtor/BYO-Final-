<?php

namespace Tests\Unit\Services\AskAi;

use App\Models\AcceptedBidSummary;
use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\Dna\PropertyIntelligenceProfileService;
use PHPUnit\Framework\TestCase;

/**
 * AskAiContextBuilderServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All model stubs are built in memory using property assignment.
 * AskAiContextBuilderService is tested via a partial mock that stubs
 * all protected finder methods, eliminating any Eloquent/DB calls.
 *
 * PropertyIntelligenceProfileService is constructor-injected and mocked so
 * buildPayloadReadOnly() returns controlled data without touching the DB.
 *
 * Test coverage (cases A–M):
 *   A. not_found status when listing is absent
 *   B. Exact top-level output contract shape is always present (including 'error' key)
 *   C. CONTEXT_VERSION constant equals 'ASK_AI_CONTEXT_V1'
 *   D. No write call strings in the service file (static grep on non-comment lines)
 *   E. missing_sources lists expected-but-absent intelligence sections
 *   F. buyer_avatar populated for buyer listings; null for other types
 *   G. tenant_avatar populated for tenant listings; null for other types
 *   H. property_intelligence returns approved fields (strengths, highlights, positioning, etc.)
 *      via buildPayloadReadOnly(); null for buyer/tenant listings
 *   I. location_intelligence includes lifestyle_json and derived sub-fields
 *   J. compatibility populated only when demand+supply pair options are supplied
 *   K. offer_analysis includes all AcceptedBidSummary columns; null when absent
 *   L. source_versions contains available version values including lifestyle_version
 *   M. status is 'partial' when expected sources are missing; 'assembled' when all present
 */
class AskAiContextBuilderServiceTest extends TestCase
{
    private const REQUIRED_TOP_LEVEL_KEYS = [
        'success',
        'listing_type',
        'listing_id',
        'context_version',
        'status',
        'listing',
        'property_intelligence',
        'location_intelligence',
        'buyer_avatar',
        'tenant_avatar',
        'compatibility',
        'offer_analysis',
        'missing_sources',
        'warnings',
        'source_versions',
        'assembled_at',
        'error',
    ];

    private const SOURCE_VERSIONS_KEYS = [
        'ask_ai_context',
        'property_intelligence_version',
        'location_dna_lifestyle_version',
        'buyer_avatar_version',
        'tenant_avatar_version',
        'compatibility_version',
    ];

    /**
     * The approved property intelligence field keys returned by buildPropertyIntelligence().
     */
    private const PROPERTY_INTELLIGENCE_KEYS = [
        'property_strengths',
        'property_highlights',
        'property_positioning',
        'property_target_audiences',
        'property_personality_tags',
        'property_story',
        'location_intelligence_context',
        'property_intelligence_version',
        'source_profile_id',
        'source_profile_version',
        'source_profile_computed_at',
    ];

    /**
     * Absolute path to the service file — derived without base_path() so this
     * works in pure PHPUnit\Framework\TestCase (no Laravel container).
     */
    private function serviceFilePath(): string
    {
        return dirname(__DIR__, 4) . '/app/Services/AskAi/AskAiContextBuilderService.php';
    }

    /**
     * Build a mock PropertyIntelligenceProfileService that allows us to stub
     * buildPayloadReadOnly() per test without touching the DB.
     *
     * @return PropertyIntelligenceProfileService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeIntelligenceServiceMock(): PropertyIntelligenceProfileService
    {
        return $this->getMockBuilder(PropertyIntelligenceProfileService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildPayloadReadOnly'])
            ->getMock();
    }

    /**
     * A successful buildPayloadReadOnly() return value carrying the approved fields.
     */
    private function makeIntelligencePayload(array $overrides = []): array
    {
        return array_merge([
            'success'                       => true,
            'status'                        => 'generated',
            'listing_type'                  => 'seller',
            'listing_id'                    => 1,
            'property_strengths'            => ['Pool', 'Garage'],
            'property_highlights'           => ['Pool', 'Garage', 'Pets Allowed'],
            'property_positioning'          => 'Move-Up Home',
            'property_target_audiences'     => ['Move-Up Families'],
            'property_personality_tags'     => ['Outdoor Living', 'Family-Friendly'],
            'property_story'                => 'This property is a Move-Up Home.',
            'location_intelligence_context' => null,
            'property_intelligence_version' => 'PROPERTY_INTELLIGENCE_V1',
            'error'                         => null,
        ], $overrides);
    }

    /**
     * Build a partial mock of AskAiContextBuilderService that stubs only the
     * protected finder methods. The PropertyIntelligenceProfileService mock is
     * passed in as a constructor argument.
     *
     * @return AskAiContextBuilderService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeService(
        ?PropertyIntelligenceProfileService $intelligenceService = null
    ): AskAiContextBuilderService {
        $intelligenceService ??= $this->makeIntelligenceServiceMock();

        return $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intelligenceService])
            ->onlyMethods([
                'findListing',
                'findPropertyDnaProfile',
                'findPropertyLocationDna',
                'findBuyerTenantDnaProfile',
                'findCompatibilityScore',
                'findAcceptedBidSummary',
            ])
            ->getMock();
    }

    /**
     * Stub a minimal listing object so the service proceeds past not_found.
     */
    private function makeListingStub(): object
    {
        $stub = new \stdClass();
        $stub->id          = 1;
        $stub->is_approved = true;
        $stub->created_at  = '2026-01-01 00:00:00';
        $stub->updated_at  = '2026-01-01 00:00:00';
        return $stub;
    }

    /**
     * Create a PropertyDnaProfile stub in memory (no DB).
     */
    private function makePropertyDnaProfile(array $attrs = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $profile->id                       = $attrs['id'] ?? 10;
        $profile->listing_type             = $attrs['listing_type'] ?? 'seller';
        $profile->listing_id               = $attrs['listing_id'] ?? 1;
        $profile->version                  = $attrs['version'] ?? 'v1';
        $profile->overall_dna_completeness = $attrs['overall_dna_completeness'] ?? 70.0;
        $profile->ai_buyer_archetype_tags  = $attrs['ai_buyer_archetype_tags'] ?? ['amenity:pool'];
        $profile->ai_marketing_hooks       = $attrs['ai_marketing_hooks'] ?? [];
        $profile->location_intelligence_context = $attrs['location_intelligence_context'] ?? null;
        $profile->computed_at              = $attrs['computed_at'] ?? null;
        $profile->archived_at              = null;
        return $profile;
    }

    /**
     * Create a PropertyLocationDna stub in memory (no DB).
     */
    private function makeLocationDna(array $attrs = []): PropertyLocationDna
    {
        $dna = new PropertyLocationDna();
        $dna->listing_type   = $attrs['listing_type'] ?? 'seller';
        $dna->listing_id     = $attrs['listing_id'] ?? 1;
        $dna->geocode_status = $attrs['geocode_status'] ?? 'success';
        $dna->lifestyle_json = $attrs['lifestyle_json'] ?? [
            'scores'     => ['walkability' => 72],
            'categories' => ['walkable'],
            'narrative'  => 'A walkable neighborhood.',
        ];
        $dna->generated_at   = $attrs['generated_at'] ?? null;
        return $dna;
    }

    /**
     * Create a BuyerTenantDnaProfile stub in memory (no DB).
     */
    private function makeAvatarProfile(array $attrs = []): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        $profile->listing_type              = $attrs['listing_type'] ?? 'buyer';
        $profile->listing_id                = $attrs['listing_id'] ?? 1;
        $profile->avatar_type               = $attrs['avatar_type'] ?? 'First-Time Buyer';
        $profile->primary_motivation        = $attrs['primary_motivation'] ?? 'stability';
        $profile->secondary_motivation      = $attrs['secondary_motivation'] ?? 'investment';
        $profile->buyer_narrative           = $attrs['buyer_narrative'] ?? 'Buyer narrative text.';
        $profile->buyer_preference_summary  = $attrs['buyer_preference_summary'] ?? ['min_beds' => 3];
        $profile->buyer_personality_tags    = $attrs['buyer_personality_tags'] ?? ['value-seeker'];
        $profile->buyer_match_preferences   = $attrs['buyer_match_preferences'] ?? [];
        $profile->avatar_confidence_score   = $attrs['avatar_confidence_score'] ?? 80;
        $profile->buyer_readiness_score     = $attrs['buyer_readiness_score'] ?? 75;
        $profile->buyer_avatar_version      = $attrs['buyer_avatar_version'] ?? 'BUYER_AVATAR_V1';
        $profile->tenant_narrative          = $attrs['tenant_narrative'] ?? 'Tenant narrative text.';
        $profile->tenant_preference_summary = $attrs['tenant_preference_summary'] ?? [];
        $profile->tenant_personality_tags   = $attrs['tenant_personality_tags'] ?? [];
        $profile->tenant_match_preferences  = $attrs['tenant_match_preferences'] ?? [];
        $profile->tenant_avatar_version     = $attrs['tenant_avatar_version'] ?? 'TENANT_AVATAR_V1';
        $profile->archived_at               = null;
        return $profile;
    }

    /**
     * Create a ListingCompatibilityScore stub in memory (no DB).
     */
    private function makeCompatibilityScore(array $attrs = []): ListingCompatibilityScore
    {
        $score = new ListingCompatibilityScore();
        $score->overall_score                = $attrs['overall_score'] ?? 82.5;
        $score->physical_match_score         = $attrs['physical_match_score'] ?? 85.0;
        $score->financial_match_score        = $attrs['financial_match_score'] ?? 80.0;
        $score->terms_match_score            = $attrs['terms_match_score'] ?? 78.0;
        $score->location_match_score         = $attrs['location_match_score'] ?? 87.0;
        $score->compatibility_summary_json   = $attrs['compatibility_summary_json'] ?? ['result' => 'strong'];
        $score->compatibility_highlights     = $attrs['compatibility_highlights'] ?? ['Price match'];
        $score->compatibility_warnings       = $attrs['compatibility_warnings'] ?? [];
        $score->compatibility_readiness_score= $attrs['compatibility_readiness_score'] ?? 0.9;
        $score->compatibility_narrative      = $attrs['compatibility_narrative'] ?? 'Strong match overall.';
        $score->score_explanation            = $attrs['score_explanation'] ?? [];
        $score->version                      = $attrs['version'] ?? 'BYA_COMPAT_V1';
        $score->computed_at                  = null;
        return $score;
    }

    /**
     * Create an AcceptedBidSummary stub in memory (no DB).
     */
    private function makeAcceptedBidSummary(array $attrs = []): AcceptedBidSummary
    {
        $summary = new AcceptedBidSummary();
        $summary->id                    = $attrs['id'] ?? 99;
        $summary->listing_type          = $attrs['listing_type'] ?? 'seller';
        $summary->listing_id            = $attrs['listing_id'] ?? 1;
        $summary->accepted_bid_id       = $attrs['accepted_bid_id'] ?? 55;
        $summary->accepted_counter_id   = $attrs['accepted_counter_id'] ?? null;
        $summary->tenant_user_id        = $attrs['tenant_user_id'] ?? 7;
        $summary->agent_user_id         = $attrs['agent_user_id'] ?? 8;
        $summary->summary_html          = $attrs['summary_html'] ?? '<p>Accepted offer summary.</p>';
        $summary->summary_pdf_path      = $attrs['summary_pdf_path'] ?? null;
        $summary->tenant_signature_name = $attrs['tenant_signature_name'] ?? 'Jane Doe';
        $summary->tenant_signed_at      = $attrs['tenant_signed_at'] ?? null;
        $summary->tenant_ip_address     = $attrs['tenant_ip_address'] ?? '127.0.0.1';
        $summary->tenant_timezone       = $attrs['tenant_timezone'] ?? 'America/New_York';
        $summary->tenant_user_agent     = $attrs['tenant_user_agent'] ?? 'Mozilla/5.0';
        $summary->agent_signature_name  = $attrs['agent_signature_name'] ?? 'John Smith';
        $summary->agent_signed_at       = $attrs['agent_signed_at'] ?? null;
        $summary->agent_ip_address      = $attrs['agent_ip_address'] ?? '127.0.0.2';
        $summary->agent_timezone        = $attrs['agent_timezone'] ?? 'America/Chicago';
        $summary->agent_user_agent      = $attrs['agent_user_agent'] ?? 'Mozilla/5.0';
        $summary->created_at            = null;
        $summary->updated_at            = null;
        return $summary;
    }

    // =========================================================================
    // Case A — not_found when listing is absent
    // =========================================================================

    public function test_case_A_returns_not_found_when_listing_is_absent(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('not_found', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertSame(AskAiContextBuilderService::CONTEXT_VERSION, $result['context_version']);
    }

    // =========================================================================
    // Case B — exact top-level contract shape is always present (incl. 'error')
    // =========================================================================

    public function test_case_B_output_contains_all_required_top_level_keys_not_found_path(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result,
                "Top-level key '{$key}' missing in not_found path"
            );
        }

        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertNull($result['error'], "'error' must be null in not_found path");
    }

    public function test_case_B_output_contains_all_required_top_level_keys_assembled_path(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result,
                "Top-level key '{$key}' missing in assembled path"
            );
        }

        $this->assertTrue($result['success']);
        $this->assertSame('buyer', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertNull($result['error'], "'error' must be null in non-failed path");
    }

    public function test_case_B_error_key_is_populated_on_failed_path(): void
    {
        $intellService = $this->makeIntelligenceServiceMock();
        $service = $this->getMockBuilder(AskAiContextBuilderService::class)
            ->setConstructorArgs([$intellService])
            ->onlyMethods(['findListing'])
            ->getMock();

        $service->method('findListing')->willThrowException(new \RuntimeException('DB offline'));

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('failed', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertSame('seller', $result['listing_type']);
        $this->assertSame(1, $result['listing_id']);
        $this->assertSame('DB offline', $result['error']);

        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Top-level key '{$key}' missing in failed path");
        }
    }

    // =========================================================================
    // Case C — CONTEXT_VERSION constant equals 'ASK_AI_CONTEXT_V1'
    // =========================================================================

    public function test_case_C_context_version_constant_is_correct(): void
    {
        $this->assertSame('ASK_AI_CONTEXT_V1', AskAiContextBuilderService::CONTEXT_VERSION);

        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);
        $result = $service->buildForListing('seller', 1);

        $this->assertSame('ASK_AI_CONTEXT_V1', $result['context_version']);
    }

    // =========================================================================
    // Case D — No write call strings in service file (static grep on code lines)
    // =========================================================================

    public function test_case_D_service_file_contains_no_write_or_openai_calls(): void
    {
        $path = $this->serviceFilePath();
        $this->assertFileExists($path, 'Service file does not exist at expected path');

        $content = file_get_contents($path);

        // Strip comment lines so prohibition keywords in governance docs don't false-positive.
        $codeLines = implode("\n", array_filter(
            explode("\n", $content),
            static function (string $line): bool {
                $trimmed = ltrim($line);
                return !str_starts_with($trimmed, '*') && !str_starts_with($trimmed, '//');
            }
        ));

        // Prohibited import/namespace patterns
        $prohibitedImports = [
            'use OpenAI\\',
            'use OpenAi\\',
            'use GuzzleHttp\\',
            'OpenAI::',
            'ChatGPT::',
        ];

        foreach ($prohibitedImports as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not import or call '{$term}'"
            );
        }

        // Prohibited write/HTTP call patterns in non-comment code
        $prohibitedCalls = [
            '->save(',
            '->update(',
            '->create(',
            '->delete(',
            'Http::post',
            'Http::get',
            'curl_exec',
        ];

        foreach ($prohibitedCalls as $term) {
            $this->assertStringNotContainsString(
                $term,
                $codeLines,
                "Service file must not contain write/HTTP call '{$term}'"
            );
        }
    }

    // =========================================================================
    // Case E — missing_sources lists expected-but-absent intelligence sections
    // =========================================================================

    public function test_case_E_missing_sources_lists_absent_intelligence_for_seller(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn(null);

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertContains('location_intelligence', $result['missing_sources']);
        $this->assertSame('partial', $result['status']);
    }

    public function test_case_E_missing_sources_when_buildPayloadReadOnly_returns_insufficient(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn([
            'success' => false,
            'status'  => 'insufficient_data',
            'error'   => 'No data',
        ]);

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertContains('property_intelligence', $result['missing_sources']);
        $this->assertNull($result['property_intelligence']);
    }

    public function test_case_E_missing_sources_lists_absent_buyer_avatar(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn(null);
        $service->method('findBuyerTenantDnaProfile')->willReturn(null);

        $result = $service->buildForListing('buyer', 1);

        $this->assertContains('buyer_avatar', $result['missing_sources']);
        $this->assertContains('location_intelligence', $result['missing_sources']);
    }

    // =========================================================================
    // Case F — buyer_avatar populated for buyer listings; null for non-buyer
    // =========================================================================

    public function test_case_F_buyer_avatar_populated_for_buyer_listing(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['listing_type' => 'buyer']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['buyer_avatar']);
        $this->assertArrayHasKey('avatar_type', $result['buyer_avatar']);
        $this->assertArrayHasKey('buyer_narrative', $result['buyer_avatar']);
        $this->assertArrayHasKey('buyer_avatar_version', $result['buyer_avatar']);
        $this->assertSame('First-Time Buyer', $result['buyer_avatar']['avatar_type']);
    }

    public function test_case_F_buyer_avatar_is_null_for_seller_listing(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertNull($result['buyer_avatar']);
    }

    // =========================================================================
    // Case G — tenant_avatar populated for tenant listings; null for non-tenant
    // =========================================================================

    public function test_case_G_tenant_avatar_populated_for_tenant_listing(): void
    {
        $avatarProfile = $this->makeAvatarProfile([
            'listing_type'          => 'tenant',
            'tenant_avatar_version' => 'TENANT_AVATAR_V1',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('tenant', 1);

        $this->assertNotNull($result['tenant_avatar']);
        $this->assertArrayHasKey('avatar_type', $result['tenant_avatar']);
        $this->assertArrayHasKey('tenant_narrative', $result['tenant_avatar']);
        $this->assertArrayHasKey('tenant_avatar_version', $result['tenant_avatar']);
        $this->assertSame('TENANT_AVATAR_V1', $result['tenant_avatar']['tenant_avatar_version']);
    }

    public function test_case_G_tenant_avatar_is_null_for_buyer_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['tenant_avatar']);
    }

    // =========================================================================
    // Case H — property_intelligence returns approved spec fields via buildPayloadReadOnly()
    //           null for buyer/tenant listings (only seller and landlord)
    // =========================================================================

    public function test_case_H_property_intelligence_contains_all_approved_fields_for_seller(): void
    {
        $intelligencePayload = $this->makeIntelligencePayload([
            'property_strengths'        => ['Pool', 'Garage'],
            'property_highlights'       => ['Pool', 'Garage'],
            'property_positioning'      => 'Move-Up Home',
            'property_target_audiences' => ['Move-Up Families'],
            'property_personality_tags' => ['Outdoor Living'],
            'property_story'            => 'This property is a Move-Up Home. Key features include Pool and Garage.',
        ]);

        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')->willReturn($intelligencePayload);

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertNotNull($result['property_intelligence']);

        foreach (self::PROPERTY_INTELLIGENCE_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result['property_intelligence'],
                "property_intelligence missing approved field '{$key}'"
            );
        }

        $this->assertSame(['Pool', 'Garage'], $result['property_intelligence']['property_strengths']);
        $this->assertSame(['Pool', 'Garage'], $result['property_intelligence']['property_highlights']);
        $this->assertSame('Move-Up Home', $result['property_intelligence']['property_positioning']);
        $this->assertSame(['Move-Up Families'], $result['property_intelligence']['property_target_audiences']);
        $this->assertSame(['Outdoor Living'], $result['property_intelligence']['property_personality_tags']);
        $this->assertStringContainsString('Move-Up Home', $result['property_intelligence']['property_story']);
        $this->assertSame('PROPERTY_INTELLIGENCE_V1', $result['property_intelligence']['property_intelligence_version']);
        $this->assertSame(10, $result['property_intelligence']['source_profile_id']);
    }

    public function test_case_H_property_intelligence_populated_for_landlord(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload(['listing_type' => 'landlord']));

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')
            ->willReturn($this->makePropertyDnaProfile(['listing_type' => 'landlord']));
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('landlord', 1);

        $this->assertNotNull($result['property_intelligence']);
        $this->assertArrayHasKey('property_strengths', $result['property_intelligence']);
        $this->assertArrayHasKey('property_highlights', $result['property_intelligence']);
        $this->assertArrayHasKey('property_positioning', $result['property_intelligence']);
    }

    public function test_case_H_property_intelligence_is_null_for_buyer_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['property_intelligence']);
    }

    public function test_case_H_property_intelligence_is_null_for_tenant_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($this->makeAvatarProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('tenant', 1);

        $this->assertNull($result['property_intelligence']);
    }

    // =========================================================================
    // Case I — location_intelligence includes lifestyle_json and derived sub-fields
    // =========================================================================

    public function test_case_I_location_intelligence_includes_lifestyle_json(): void
    {
        $lifestyleJson = [
            'scores'     => ['walkability' => 88, 'transit' => 60],
            'categories' => ['walkable', 'transit-friendly'],
            'narrative'  => 'A highly walkable urban neighborhood.',
            'version'    => 'LIFESTYLE_V2',
        ];

        $locationDna = $this->makeLocationDna([
            'lifestyle_json' => $lifestyleJson,
            'geocode_status' => 'success',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNotNull($result['location_intelligence']);
        $this->assertSame($lifestyleJson, $result['location_intelligence']['lifestyle_json']);
        $this->assertSame(
            ['walkability' => 88, 'transit' => 60],
            $result['location_intelligence']['lifestyle_scores']
        );
        $this->assertSame(
            ['walkable', 'transit-friendly'],
            $result['location_intelligence']['lifestyle_categories']
        );
        $this->assertSame(
            'A highly walkable urban neighborhood.',
            $result['location_intelligence']['location_narrative']
        );
        $this->assertSame('success', $result['location_intelligence']['geocode_status']);
        $this->assertSame('LIFESTYLE_V2', $result['location_intelligence']['lifestyle_version']);
    }

    // =========================================================================
    // Case J — compatibility populated only when demand+supply pair options supplied
    // =========================================================================

    public function test_case_J_compatibility_is_null_when_no_pair_options_supplied(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['compatibility']);
    }

    public function test_case_J_compatibility_is_null_when_only_demand_options_supplied(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
        ]);

        $this->assertNull($result['compatibility']);
    }

    public function test_case_J_compatibility_populated_when_pair_options_and_score_exists(): void
    {
        $compatScore = $this->makeCompatibilityScore(['overall_score' => 85.0]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findCompatibilityScore')->willReturn($compatScore);

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 5,
        ]);

        $this->assertNotNull($result['compatibility']);
        $this->assertSame(85.0, (float) $result['compatibility']['overall_score']);
        $this->assertArrayHasKey('compatibility_narrative', $result['compatibility']);
        $this->assertArrayHasKey('compatibility_highlights', $result['compatibility']);
    }

    public function test_case_J_compatibility_null_with_warning_when_pair_requested_but_no_score(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findCompatibilityScore')->willReturn(null);

        $result = $service->buildForListing('buyer', 1, [
            'demand_listing_type' => 'buyer',
            'demand_listing_id'   => 1,
            'supply_listing_type' => 'seller',
            'supply_listing_id'   => 5,
        ]);

        $this->assertNull($result['compatibility']);
        $this->assertNotEmpty($result['warnings']);
    }

    // =========================================================================
    // Case K — offer_analysis includes all AcceptedBidSummary columns; null when absent
    // =========================================================================

    public function test_case_K_offer_analysis_null_when_no_accepted_bid_summary(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findAcceptedBidSummary')->willReturn(null);

        $result = $service->buildForListing('buyer', 1);

        $this->assertNull($result['offer_analysis']);
    }

    public function test_case_K_offer_analysis_includes_approved_deal_content_fields(): void
    {
        $summary = $this->makeAcceptedBidSummary([
            'id'               => 42,
            'listing_type'     => 'seller',
            'listing_id'       => 1,
            'accepted_bid_id'  => 77,
            'summary_html'     => '<p>Accepted offer.</p>',
            'summary_pdf_path' => 'summaries/seller/42.pdf',
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());
        $service->method('findAcceptedBidSummary')->willReturn($summary);

        $result = $service->buildForListing('seller', 1);

        $oa = $result['offer_analysis'];
        $this->assertNotNull($oa);

        // Approved fields are present
        $this->assertSame(42, $oa['id']);
        $this->assertSame('seller', $oa['listing_type']);
        $this->assertSame(1, $oa['listing_id']);
        $this->assertSame(77, $oa['accepted_bid_id']);
        $this->assertArrayHasKey('accepted_counter_id', $oa);
        $this->assertSame('<p>Accepted offer.</p>', $oa['summary_html']);
        $this->assertSame('summaries/seller/42.pdf', $oa['summary_pdf_path']);
        $this->assertArrayHasKey('created_at', $oa);
        $this->assertArrayHasKey('updated_at', $oa);

        // PII / identity metadata must NOT appear
        $deniedKeys = [
            'tenant_user_id', 'agent_user_id',
            'tenant_signature_name', 'agent_signature_name',
            'tenant_signed_at', 'agent_signed_at',
            'tenant_ip_address', 'agent_ip_address',
            'tenant_timezone', 'agent_timezone',
            'tenant_user_agent', 'agent_user_agent',
        ];
        foreach ($deniedKeys as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $oa,
                "offer_analysis must not expose PII/identity field '{$key}'"
            );
        }
    }

    // =========================================================================
    // Case L — source_versions contains available version values
    // =========================================================================

    public function test_case_L_source_versions_always_contains_all_required_keys(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);
        $result = $service->buildForListing('seller', 1);

        foreach (self::SOURCE_VERSIONS_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $result['source_versions'],
                "source_versions is missing key '{$key}'"
            );
        }

        $this->assertSame(
            AskAiContextBuilderService::CONTEXT_VERSION,
            $result['source_versions']['ask_ai_context']
        );
    }

    public function test_case_L_source_versions_carries_property_intelligence_version(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame(
            'PROPERTY_INTELLIGENCE_V1',
            $result['source_versions']['property_intelligence_version']
        );
    }

    public function test_case_L_source_versions_carries_buyer_avatar_version(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['buyer_avatar_version' => 'BUYER_AVATAR_V1']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('BUYER_AVATAR_V1', $result['source_versions']['buyer_avatar_version']);
    }

    public function test_case_L_source_versions_carries_location_dna_lifestyle_version(): void
    {
        $locationDna = $this->makeLocationDna([
            'lifestyle_json' => [
                'scores'     => ['walkability' => 80],
                'categories' => ['walkable'],
                'narrative'  => 'Walkable.',
                'version'    => 'LIFESTYLE_DNA_V3',
            ],
        ]);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyLocationDna')->willReturn($locationDna);

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('LIFESTYLE_DNA_V3', $result['source_versions']['location_dna_lifestyle_version']);
    }

    // =========================================================================
    // Case M — status 'partial' when missing sources; 'assembled' when all present
    // =========================================================================

    public function test_case_M_status_is_partial_when_property_intelligence_missing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn(null);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('partial', $result['status']);
        $this->assertContains('property_intelligence', $result['missing_sources']);
    }

    public function test_case_M_status_is_assembled_when_all_expected_sources_present_for_buyer(): void
    {
        $avatarProfile = $this->makeAvatarProfile(['listing_type' => 'buyer']);

        $service = $this->makeService();
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findBuyerTenantDnaProfile')->willReturn($avatarProfile);
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('buyer', 1);

        $this->assertSame('assembled', $result['status']);
        $this->assertEmpty($result['missing_sources']);
    }

    public function test_case_M_status_is_assembled_when_all_expected_sources_present_for_seller(): void
    {
        $intelligenceService = $this->makeIntelligenceServiceMock();
        $intelligenceService->method('buildPayloadReadOnly')
            ->willReturn($this->makeIntelligencePayload());

        $service = $this->makeService($intelligenceService);
        $service->method('findListing')->willReturn($this->makeListingStub());
        $service->method('findPropertyDnaProfile')->willReturn($this->makePropertyDnaProfile());
        $service->method('findPropertyLocationDna')->willReturn($this->makeLocationDna());

        $result = $service->buildForListing('seller', 1);

        $this->assertSame('assembled', $result['status']);
        $this->assertEmpty($result['missing_sources']);
    }

    public function test_case_M_status_is_not_found_for_absent_listing(): void
    {
        $service = $this->makeService();
        $service->method('findListing')->willReturn(null);

        $result = $service->buildForListing('seller', 999);

        $this->assertSame('not_found', $result['status']);
    }
}

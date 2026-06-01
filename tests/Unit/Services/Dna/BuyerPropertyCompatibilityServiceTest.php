<?php

namespace Tests\Unit\Services\Dna;

use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\BuyerPropertyCompatibilityService;
use PHPUnit\Framework\TestCase;

/**
 * BuyerPropertyCompatibilityServiceTest
 *
 * Pure unit tests — no database, no Laravel TestCase, no DB traits.
 * All profile stubs are built in memory using property assignment.
 *
 * Test coverage:
 *   Guard conditions:
 *     (1)  Wrong buyer listing_type → insufficient_data
 *     (2)  Wrong property listing_type → insufficient_data
 *     (3)  Buyer has no lifestyle_tags and no deal_breaker_flags → insufficient_data
 *     (4)  Property has no archetype tags, no marketing hooks, no personality context → insufficient_data
 *   Output contract:
 *     (5)  All required keys present in every output path
 *     (6)  compatibility_type is always 'buyer_property'
 *     (7)  buyer_listing_id and property_listing_id are cast to int
 *     (8)  Context pass-throughs (buyerAvatar, propertyPersonality, locationContext) forwarded
 *   Property type dimension:
 *     (9)  Both present, same value → aligned
 *     (10) Both present, different values → conflicting
 *     (11) Buyer absent, property present → unresolved (missing_side=buyer)
 *     (12) Buyer present, property absent → unresolved (missing_side=property)
 *     (13) Both absent → unresolved (missing_side=both)
 *   Financing alignment:
 *     (14) Buyer open-to:seller-financing + property financing:seller-financed → aligned
 *     (15) Buyer open-to:seller-financing, property lacks tag → unresolved
 *     (16) Buyer open-to:assumable-loan + property financing:assumable → aligned
 *     (17) Buyer open-to:lease-option + property structure:lease-option → aligned
 *     (18) Buyer open-to:lease-purchase + property structure:lease-purchase → aligned
 *     (19) Buyer has no financing interest → no financing signals emitted
 *   Amenity alignment:
 *     (20) pool_required + amenity:pool present → aligned
 *     (21) pool_required + amenity:pool absent → unresolved
 *     (22) garage_required + amenity:garage present → aligned
 *     (23) garage_required + parking:garage present → aligned
 *     (24) No amenity requirement → no amenity signals emitted
 *   Waterfront dimension:
 *     (25) waterfront_required flag + feature:waterfront tag → aligned
 *     (26) prefers-type:Waterfront + amenity:waterfront → aligned
 *     (27) waterfront signal + personality=Waterfront Property → aligned
 *     (28) waterfront signal + location coastal context → aligned
 *     (29) waterfront signal, no property waterfront signal → unresolved
 *     (30) No waterfront signal → no waterfront entry emitted
 *   Commercial dimension:
 *     (31) commercial_interest flag + use:commercial tag → aligned
 *     (32) prefers-type:Commercial + commercial_score present → aligned
 *     (33) commercial signal + commercial personality → aligned
 *     (34) commercial signal, property lacks all commercial signals → unresolved
 *     (35) No commercial signal → no commercial entry emitted
 *   Budget dimension:
 *     (36) No property price signal → always unresolved
 *   Avatar/personality pairing dimension:
 *     (37) Waterfront Buyer ↔ Waterfront Property → aligned
 *     (38) Vacation Buyer ↔ Coastal Lifestyle Property → aligned
 *     (39) Commercial Buyer ↔ Commercial Flexibility Property → aligned
 *     (40) Investor Buyer ↔ Investment-Oriented Property → aligned
 *     (41) No known pairing → unresolved
 *     (42) Both absent → unresolved (missing_side=both)
 *   Governance tests:
 *     (43) Unresolved signals never become conflict from missing data alone
 *     (44) No recommendation or decision language in any output key or reason string
 *     (45) No AI or DB imports in service source
 *     (46) Deterministic output for identical input
 */
class BuyerPropertyCompatibilityServiceTest extends TestCase
{
    private BuyerPropertyCompatibilityService $service;

    private const CONTRACT_KEYS = [
        'success',
        'status',
        'compatibility_type',
        'buyer_listing_id',
        'property_listing_id',
        'buyer_avatar_context',
        'property_personality_context',
        'location_context',
        'aligned_signals',
        'conflicting_signals',
        'unresolved_signals',
        'missing_inputs',
        'error',
    ];

    private const FORBIDDEN_TERMS = [
        'should',
        'ideal',
        'best',
        'suitable',
        'recommended',
        'do not',
        'avoid',
        'perfect match',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BuyerPropertyCompatibilityService();
    }

    // -------------------------------------------------------------------------
    // Helpers — build in-memory profile stubs
    // -------------------------------------------------------------------------

    private function makeBuyerProfile(array $attributes = []): BuyerTenantDnaProfile
    {
        $profile = new BuyerTenantDnaProfile();
        $profile->listing_type       = 'buyer';
        $profile->listing_id         = 10;
        $profile->lifestyle_tags     = ['prefers-type:SingleFamily'];
        $profile->deal_breaker_flags = [];

        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }

        return $profile;
    }

    private function makePropertyProfile(array $attributes = []): PropertyDnaProfile
    {
        $profile = new PropertyDnaProfile();
        $profile->listing_type          = 'seller';
        $profile->listing_id            = 20;
        $profile->ai_buyer_archetype_tags = ['type:SingleFamily'];
        $profile->ai_marketing_hooks    = [];
        $profile->commercial_score      = null;

        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }

        return $profile;
    }

    private function assertContractShape(array $result): void
    {
        foreach (self::CONTRACT_KEYS as $key) {
            $this->assertArrayHasKey($key, $result, "Output contract key '{$key}' is missing");
        }
    }

    private function findSignalByDimension(array $signals, string $dimension): ?array
    {
        foreach ($signals as $signal) {
            if (($signal['dimension'] ?? '') === $dimension) {
                return $signal;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // (1) Guard — wrong buyer listing_type
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_buyer_listing_type_is_not_buyer(): void
    {
        $buyer    = $this->makeBuyerProfile(['listing_type' => 'tenant']);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (2) Guard — wrong property listing_type
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_property_listing_type_is_not_seller(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile(['listing_type' => 'landlord']);

        $result = $this->service->generate($buyer, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
        $this->assertNotEmpty($result['missing_inputs']);
    }

    // -------------------------------------------------------------------------
    // (3) Guard — buyer has no lifestyle_tags and no deal_breaker_flags
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_buyer_has_no_tags_or_flags(): void
    {
        $buyer    = $this->makeBuyerProfile([
            'lifestyle_tags'     => [],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    // -------------------------------------------------------------------------
    // (4) Guard — property has no archetype tags, hooks, or personality context
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_insufficient_data_when_property_has_no_signals(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => [],
            'ai_marketing_hooks'      => [],
        ]);

        $result = $this->service->generate($buyer, $property, [], [], []);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    /** @test */
    public function property_with_personality_context_passes_guard_even_without_tags(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => [],
            'ai_marketing_hooks'      => [],
        ]);

        $personality = ['primary_personality' => 'Waterfront Property'];
        $result = $this->service->generate($buyer, $property, [], $personality, []);

        $this->assertNotSame('insufficient_data', $result['status']);
    }

    // -------------------------------------------------------------------------
    // (5) Output contract — all required keys present in every path
    // -------------------------------------------------------------------------

    /** @test */
    public function output_contract_shape_is_present_in_insufficient_data_path(): void
    {
        $buyer    = $this->makeBuyerProfile(['listing_type' => 'tenant']);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property);

        $this->assertContractShape($result);
    }

    /** @test */
    public function output_contract_shape_is_present_in_generated_path(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property);

        $this->assertContractShape($result);
        $this->assertTrue($result['success']);
        $this->assertSame('generated', $result['status']);
        $this->assertIsArray($result['aligned_signals']);
        $this->assertIsArray($result['conflicting_signals']);
        $this->assertIsArray($result['unresolved_signals']);
        $this->assertIsArray($result['missing_inputs']);
        $this->assertNull($result['error']);
    }

    // -------------------------------------------------------------------------
    // (6) compatibility_type is always 'buyer_property'
    // -------------------------------------------------------------------------

    /** @test */
    public function compatibility_type_is_always_buyer_property(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile();

        $generated = $this->service->generate($buyer, $property);
        $this->assertSame('buyer_property', $generated['compatibility_type']);

        $insufficientBuyer = $this->makeBuyerProfile(['listing_type' => 'tenant']);
        $guard = $this->service->generate($insufficientBuyer, $property);
        $this->assertSame('buyer_property', $guard['compatibility_type']);
    }

    // -------------------------------------------------------------------------
    // (7) listing IDs are cast to int
    // -------------------------------------------------------------------------

    /** @test */
    public function listing_ids_are_cast_to_int_in_output(): void
    {
        $buyer    = $this->makeBuyerProfile(['listing_id' => '99']);
        $property = $this->makePropertyProfile(['listing_id' => '200']);

        $result = $this->service->generate($buyer, $property);

        $this->assertSame(99,  $result['buyer_listing_id']);
        $this->assertSame(200, $result['property_listing_id']);
    }

    // -------------------------------------------------------------------------
    // (8) Context pass-throughs forwarded unchanged
    // -------------------------------------------------------------------------

    /** @test */
    public function context_arrays_are_forwarded_unchanged_in_output(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'Waterfront Buyer'];
        $personality = ['primary_personality' => 'Waterfront Property'];
        $location    = ['coastal' => ['nearest_beach_miles' => 0.5]];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, $location);

        $this->assertSame($avatar,      $result['buyer_avatar_context']);
        $this->assertSame($personality, $result['property_personality_context']);
        $this->assertSame($location,    $result['location_context']);
    }

    // -------------------------------------------------------------------------
    // Property type dimension tests (9–13)
    // -------------------------------------------------------------------------

    /** @test */
    public function property_type_aligned_when_both_present_and_match(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:SingleFamily']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'property_type');
        $this->assertNotNull($signal, 'Expected aligned signal for property_type');
        $this->assertSame('prefers-type:SingleFamily', $signal['buyer_signal']);
        $this->assertSame('type:SingleFamily',         $signal['property_signal']);
    }

    /** @test */
    public function property_type_conflicting_when_both_present_and_differ(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Condo']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['conflicting_signals'], 'property_type');
        $this->assertNotNull($signal, 'Expected conflicting signal for property_type');
    }

    /** @test */
    public function property_type_unresolved_missing_buyer_when_buyer_has_no_type_tag(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['financial:pre-approved']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'property_type');
        $this->assertNotNull($signal, 'Expected unresolved signal for property_type');
        $this->assertSame('buyer', $signal['missing_side']);
    }

    /** @test */
    public function property_type_unresolved_missing_property_when_property_has_no_type_tag(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:SingleFamily']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'property_type');
        $this->assertNotNull($signal, 'Expected unresolved signal for property_type');
        $this->assertSame('property', $signal['missing_side']);
    }

    /** @test */
    public function property_type_unresolved_both_when_neither_has_type_tag(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['financial:pre-approved']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'property_type');
        $this->assertNotNull($signal, 'Expected unresolved signal for property_type');
        $this->assertSame('both', $signal['missing_side']);
    }

    // -------------------------------------------------------------------------
    // Financing alignment tests (14–19)
    // -------------------------------------------------------------------------

    /** @test */
    public function seller_financing_aligned_when_buyer_and_property_both_have_signal(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['open-to:seller-financing', 'prefers-type:X']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'financing:seller-financed']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'financing_seller');
        $this->assertNotNull($signal, 'Expected aligned signal for financing_seller');
    }

    /** @test */
    public function seller_financing_unresolved_when_buyer_interested_but_property_lacks_tag(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['open-to:seller-financing', 'prefers-type:X']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'financing_seller');
        $this->assertNotNull($signal, 'Expected unresolved signal for financing_seller');
        $this->assertSame('property', $signal['missing_side']);
    }

    /** @test */
    public function assumable_loan_aligned_when_buyer_and_property_both_have_signal(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['open-to:assumable-loan', 'prefers-type:X']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'financing:assumable']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'financing_assumable');
        $this->assertNotNull($signal, 'Expected aligned signal for financing_assumable');
    }

    /** @test */
    public function lease_option_aligned_when_buyer_and_property_both_have_signal(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['open-to:lease-option', 'prefers-type:X']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'structure:lease-option']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'structure_lease_option');
        $this->assertNotNull($signal, 'Expected aligned signal for structure_lease_option');
    }

    /** @test */
    public function lease_purchase_aligned_when_buyer_and_property_both_have_signal(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['open-to:lease-purchase', 'prefers-type:X']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'structure:lease-purchase']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'structure_lease_purchase');
        $this->assertNotNull($signal, 'Expected aligned signal for structure_lease_purchase');
    }

    /** @test */
    public function no_financing_signals_emitted_when_buyer_has_no_financing_interest(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:SingleFamily']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily', 'financing:seller-financed']]);

        $result = $this->service->generate($buyer, $property);

        foreach (['financing_seller', 'financing_assumable', 'structure_lease_option', 'structure_lease_purchase'] as $dim) {
            $this->assertNull($this->findSignalByDimension($result['aligned_signals'], $dim), "No aligned signal expected for {$dim}");
            $this->assertNull($this->findSignalByDimension($result['unresolved_signals'], $dim), "No unresolved signal expected for {$dim}");
        }
    }

    // -------------------------------------------------------------------------
    // Amenity alignment tests (20–24)
    // -------------------------------------------------------------------------

    /** @test */
    public function pool_aligned_when_buyer_requires_pool_and_property_has_amenity_pool(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X', 'requires:pool'],
            'deal_breaker_flags' => [['flag' => 'pool_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'amenity:pool']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'amenity_pool');
        $this->assertNotNull($signal, 'Expected aligned signal for amenity_pool');
    }

    /** @test */
    public function pool_unresolved_when_buyer_requires_pool_but_property_lacks_amenity(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'pool_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'amenity_pool');
        $this->assertNotNull($signal, 'Expected unresolved signal for amenity_pool');
        $this->assertSame('property', $signal['missing_side']);
    }

    /** @test */
    public function garage_aligned_when_buyer_requires_garage_and_property_has_amenity_garage(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'garage_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'amenity:garage']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'amenity_garage');
        $this->assertNotNull($signal, 'Expected aligned signal for amenity_garage');
    }

    /** @test */
    public function garage_aligned_when_buyer_requires_garage_and_property_has_parking_garage(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'garage_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'parking:garage']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'amenity_garage');
        $this->assertNotNull($signal, 'Expected aligned signal for amenity_garage via parking:garage');
    }

    /** @test */
    public function no_amenity_signals_emitted_when_buyer_has_no_amenity_requirements(): void
    {
        $buyer    = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:SingleFamily'],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily', 'amenity:pool', 'amenity:garage']]);

        $result = $this->service->generate($buyer, $property);

        $this->assertNull($this->findSignalByDimension($result['aligned_signals'],    'amenity_pool'));
        $this->assertNull($this->findSignalByDimension($result['unresolved_signals'], 'amenity_pool'));
        $this->assertNull($this->findSignalByDimension($result['aligned_signals'],    'amenity_garage'));
        $this->assertNull($this->findSignalByDimension($result['unresolved_signals'], 'amenity_garage'));
    }

    // -------------------------------------------------------------------------
    // Waterfront dimension tests (25–30)
    // -------------------------------------------------------------------------

    /** @test */
    public function waterfront_aligned_via_deal_breaker_flag_and_feature_waterfront_tag(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'waterfront_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'feature:waterfront']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected aligned signal for waterfront');
    }

    /** @test */
    public function waterfront_aligned_via_prefers_type_waterfront_and_amenity_waterfront(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:waterfront']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected aligned signal for waterfront');
    }

    /** @test */
    public function waterfront_aligned_when_buyer_has_waterfront_signal_and_personality_is_waterfront(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);
        $personality = ['primary_personality' => 'Waterfront Property'];

        $result = $this->service->generate($buyer, $property, [], $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected aligned signal for waterfront via personality');
    }

    /** @test */
    public function waterfront_aligned_when_buyer_has_waterfront_signal_and_location_has_coastal_context(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);
        $location = ['coastal' => ['nearest_beach_miles' => 0.5]];

        $result = $this->service->generate($buyer, $property, [], [], $location);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected aligned signal for waterfront via coastal location context');
    }

    /** @test */
    public function waterfront_unresolved_when_buyer_has_waterfront_signal_but_property_lacks_all_waterfront(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Waterfront']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);

        $result = $this->service->generate($buyer, $property, [], [], []);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected unresolved signal for waterfront');
        $this->assertSame('property', $signal['missing_side']);
    }

    /** @test */
    public function no_waterfront_signal_emitted_when_buyer_has_no_waterfront_interest(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:SingleFamily']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily', 'feature:waterfront']]);

        $result = $this->service->generate($buyer, $property);

        $this->assertNull($this->findSignalByDimension($result['aligned_signals'],    'waterfront'));
        $this->assertNull($this->findSignalByDimension($result['unresolved_signals'], 'waterfront'));
    }

    // -------------------------------------------------------------------------
    // Commercial dimension tests (31–35)
    // -------------------------------------------------------------------------

    /** @test */
    public function commercial_aligned_via_flag_and_use_commercial_tag(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'commercial_interest']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'use:commercial']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'commercial_interest');
        $this->assertNotNull($signal, 'Expected aligned signal for commercial_interest');
    }

    /** @test */
    public function commercial_aligned_via_prefers_type_commercial_and_commercial_score(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Commercial']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool'],
            'commercial_score'        => 65.0,
        ]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'commercial_interest');
        $this->assertNotNull($signal, 'Expected aligned signal for commercial_interest via commercial_score');
    }

    /** @test */
    public function commercial_aligned_via_commercial_personality(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Commercial']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['amenity:pool']]);
        $personality = ['primary_personality' => 'Commercial Flexibility Property'];

        $result = $this->service->generate($buyer, $property, [], $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'commercial_interest');
        $this->assertNotNull($signal, 'Expected aligned signal for commercial_interest via personality');
    }

    /** @test */
    public function commercial_unresolved_when_buyer_has_commercial_signal_but_property_lacks_all(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:Commercial']]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['amenity:pool'],
            'commercial_score'        => null,
        ]);

        $result = $this->service->generate($buyer, $property, [], [], []);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'commercial_interest');
        $this->assertNotNull($signal, 'Expected unresolved signal for commercial_interest');
        $this->assertSame('property', $signal['missing_side']);
    }

    /** @test */
    public function no_commercial_signal_emitted_when_buyer_has_no_commercial_interest(): void
    {
        $buyer    = $this->makeBuyerProfile(['lifestyle_tags' => ['prefers-type:SingleFamily']]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:SingleFamily', 'use:commercial']]);

        $result = $this->service->generate($buyer, $property);

        $this->assertNull($this->findSignalByDimension($result['aligned_signals'],    'commercial_interest'));
        $this->assertNull($this->findSignalByDimension($result['unresolved_signals'], 'commercial_interest'));
    }

    // -------------------------------------------------------------------------
    // Budget dimension tests (36)
    // -------------------------------------------------------------------------

    /** @test */
    public function budget_dimension_always_emits_unresolved_when_no_property_price_signal(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:SingleFamily'],
            'deal_breaker_flags' => [['flag' => 'budget_ceiling_specified', 'value' => '500000']],
        ]);
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'budget');
        $this->assertNotNull($signal, 'Expected unresolved signal for budget when property has no price');
    }

    // -------------------------------------------------------------------------
    // Avatar / personality pairing tests (37–42)
    // -------------------------------------------------------------------------

    /** @test */
    public function waterfront_buyer_and_waterfront_property_pairing_is_aligned(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'Waterfront Buyer'];
        $personality = ['primary_personality' => 'Waterfront Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected aligned pairing for Waterfront Buyer ↔ Waterfront Property');
    }

    /** @test */
    public function vacation_buyer_and_coastal_lifestyle_property_pairing_is_aligned(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'Vacation Buyer'];
        $personality = ['primary_personality' => 'Coastal Lifestyle Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected aligned pairing for Vacation Buyer ↔ Coastal Lifestyle Property');
    }

    /** @test */
    public function commercial_buyer_and_commercial_flexibility_property_pairing_is_aligned(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'Commercial Buyer'];
        $personality = ['primary_personality' => 'Commercial Flexibility Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected aligned pairing for Commercial Buyer ↔ Commercial Flexibility Property');
    }

    /** @test */
    public function investor_buyer_and_investment_oriented_property_pairing_is_aligned(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'Investor Buyer'];
        $personality = ['primary_personality' => 'Investment-Oriented Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected aligned pairing for Investor Buyer ↔ Investment-Oriented Property');
    }

    /** @test */
    public function no_known_pairing_produces_unresolved_avatar_personality_signal(): void
    {
        $buyer       = $this->makeBuyerProfile();
        $property    = $this->makePropertyProfile();
        $avatar      = ['primary_avatar' => 'First-Time Buyer'];
        $personality = ['primary_personality' => 'Traditional Residential Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected unresolved for unknown pairing');
        $this->assertNull($this->findSignalByDimension($result['aligned_signals'],    'avatar_personality_pairing'));
        $this->assertNull($this->findSignalByDimension($result['conflicting_signals'], 'avatar_personality_pairing'));
    }

    /** @test */
    public function avatar_personality_unresolved_both_when_neither_provided(): void
    {
        $buyer    = $this->makeBuyerProfile();
        $property = $this->makePropertyProfile();

        $result = $this->service->generate($buyer, $property, [], [], []);

        $signal = $this->findSignalByDimension($result['unresolved_signals'], 'avatar_personality_pairing');
        $this->assertNotNull($signal, 'Expected unresolved for avatar_personality_pairing when neither provided');
        $this->assertSame('both', $signal['missing_side']);
    }

    // -------------------------------------------------------------------------
    // (43) Governance — unresolved never becomes conflicting from missing data alone
    // -------------------------------------------------------------------------

    /** @test */
    public function unresolved_signals_are_never_produced_by_conflicting_dimension_paths(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:SingleFamily', 'open-to:seller-financing'],
            'deal_breaker_flags' => [
                ['flag' => 'pool_required'],
                ['flag' => 'waterfront_required'],
                ['flag' => 'commercial_interest'],
            ],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:SingleFamily'],
            'commercial_score'        => null,
        ]);

        $result = $this->service->generate($buyer, $property);

        foreach ($result['unresolved_signals'] as $signal) {
            $this->assertArrayNotHasKey('buyer_signal',    $signal,
                "Unresolved signal for dimension '{$signal['dimension']}' should not have buyer_signal key");
            $this->assertArrayNotHasKey('property_signal', $signal,
                "Unresolved signal for dimension '{$signal['dimension']}' should not have property_signal key");
        }
    }

    // -------------------------------------------------------------------------
    // (44) Governance — no recommendation or decision language in any output
    // -------------------------------------------------------------------------

    /** @test */
    public function no_recommendation_or_decision_language_appears_in_any_output(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:Waterfront', 'open-to:seller-financing'],
            'deal_breaker_flags' => [
                ['flag' => 'pool_required'],
                ['flag' => 'waterfront_required'],
                ['flag' => 'budget_ceiling_specified', 'value' => '900000'],
            ],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:Condo', 'feature:waterfront', 'amenity:pool', 'financing:seller-financed'],
            'commercial_score'        => null,
        ]);
        $avatar      = ['primary_avatar' => 'Waterfront Buyer'];
        $personality = ['primary_personality' => 'Waterfront Property'];

        $result = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $allSignals = array_merge(
            $result['aligned_signals'],
            $result['conflicting_signals'],
            $result['unresolved_signals']
        );

        foreach ($allSignals as $signal) {
            $reason = strtolower($signal['reason'] ?? '');
            foreach (self::FORBIDDEN_TERMS as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $reason,
                    "Forbidden term '{$term}' found in reason string: \"{$signal['reason']}\""
                );
            }

            foreach (array_keys($signal) as $key) {
                $lowerKey = strtolower($key);
                foreach (self::FORBIDDEN_TERMS as $term) {
                    $this->assertStringNotContainsString(
                        $term,
                        $lowerKey,
                        "Forbidden term '{$term}' found in signal key: \"{$key}\""
                    );
                }
            }
        }

        foreach ($result['missing_inputs'] as $item) {
            $lower = strtolower((string) $item);
            foreach (self::FORBIDDEN_TERMS as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $lower,
                    "Forbidden term '{$term}' found in missing_inputs item: \"{$item}\""
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // (45) Governance — no AI or DB imports in service source
    // -------------------------------------------------------------------------

    /** @test */
    public function service_source_does_not_import_ai_or_db_classes(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/Dna/BuyerPropertyCompatibilityService.php'
        );

        $this->assertStringNotContainsString('use OpenAI',                     $source, 'Service must not import OpenAI namespace');
        $this->assertStringNotContainsString('new \OpenAI',                   $source, 'Service must not instantiate OpenAI');
        $this->assertStringNotContainsString('OpenAI::',                      $source, 'Service must not call OpenAI static methods');
        $this->assertStringNotContainsString('Illuminate\\Support\\Facades\\DB', $source,
            'Service must not import DB facade');
        $this->assertStringNotContainsString('DB::',                          $source, 'Service must not call DB::');
        $this->assertStringNotContainsString('->save(',                       $source, 'Service must not write to database');
        $this->assertStringNotContainsString('->create(',                     $source, 'Service must not create database records');
        $this->assertStringNotContainsString('->update(',                     $source, 'Service must not update database records');
        $this->assertStringNotContainsString('Http::',                        $source, 'Service must not make HTTP calls');
        $this->assertStringNotContainsString("file_get_contents('http",       $source,
            'Service must not make outbound HTTP requests');
    }

    // -------------------------------------------------------------------------
    // (46) Deterministic output for identical input
    // -------------------------------------------------------------------------

    /** @test */
    public function output_is_deterministic_for_identical_input(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:SingleFamily', 'open-to:seller-financing'],
            'deal_breaker_flags' => [['flag' => 'pool_required']],
        ]);
        $property = $this->makePropertyProfile([
            'ai_buyer_archetype_tags' => ['type:SingleFamily', 'amenity:pool'],
        ]);
        $avatar      = ['primary_avatar' => 'Waterfront Buyer'];
        $personality = ['primary_personality' => 'Waterfront Property'];

        $result1 = $this->service->generate($buyer, $property, $avatar, $personality, []);
        $result2 = $this->service->generate($buyer, $property, $avatar, $personality, []);

        $this->assertSame($result1, $result2, 'Service output must be deterministic for identical input');
    }

    // -------------------------------------------------------------------------
    // Additional — pool_required via lifestyle tag (not just deal_breaker_flag)
    // -------------------------------------------------------------------------

    /** @test */
    public function pool_aligned_when_requires_pool_lifestyle_tag_and_property_has_amenity_pool(): void
    {
        $buyer = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X', 'requires:pool'],
            'deal_breaker_flags' => [],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X', 'amenity:pool']]);

        $result = $this->service->generate($buyer, $property);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'amenity_pool');
        $this->assertNotNull($signal, 'Expected aligned signal for amenity_pool via requires:pool lifestyle tag');
    }

    // -------------------------------------------------------------------------
    // Additional — coastal_features key in location context triggers waterfront
    // -------------------------------------------------------------------------

    /** @test */
    public function waterfront_aligned_when_location_has_coastal_features_key(): void
    {
        $buyer    = $this->makeBuyerProfile([
            'lifestyle_tags'     => ['prefers-type:X'],
            'deal_breaker_flags' => [['flag' => 'waterfront_required']],
        ]);
        $property = $this->makePropertyProfile(['ai_buyer_archetype_tags' => ['type:X']]);
        $location = ['coastal_features' => ['beach_access' => true]];

        $result = $this->service->generate($buyer, $property, [], [], $location);

        $signal = $this->findSignalByDimension($result['aligned_signals'], 'waterfront');
        $this->assertNotNull($signal, 'Expected aligned waterfront from coastal_features location key');
    }
}

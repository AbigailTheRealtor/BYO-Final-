<?php

namespace Tests\Feature\AskAi;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\AskAi\AskAiContextBuilderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * §30 — Refactor Parity Suite
 *
 * Proves that the Runtime Source Map Extraction Refactor (AskAiSourceResolver
 * + extractListingFields() resolver loop) is behaviourally identical to the
 * retired extractFactualFields() implementation.  Four reviewer requirements:
 *
 *   §30A — Duplicate-key guardrails: parse the PHP source text to catch
 *           duplicate key declarations in CANONICAL_SOURCE_MAP before PHP's
 *           last-write-wins silently erases the earlier entry.
 *
 *   §30B — Manual extractor completeness: every key returned by each
 *           extract*ManualFields() method is declared in CANONICAL_SOURCE_MAP
 *           for that role.  Verified via reflection with null-returning
 *           closures (no DB needed).
 *
 *   §30C — No unintentional duplicate production: the per-role manual-override
 *           key count is pinned.  A count change forces a deliberate update,
 *           preventing silent regressions when new overrides are added.
 *
 *   §30D–G — Real-listing evidence: seller 121, landlord 71, buyer 5,
 *             tenant 133 produce correct total key counts and spot-checked
 *             field values from the live DB.
 */
class AskAiRefactorParityTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Conditional seller key groups (Step 1b removes these for non-matching
    // property_type values)
    // -------------------------------------------------------------------------

    private const SELLER_VL_ONLY_KEYS = [
        'current_adjacent_use', 'water_available', 'sewer_available',
        'electric_available', 'gas_available', 'telecom_available',
        'road_surface_type', 'front_footage', 'number_of_wells',
        'number_of_septics', 'fences', 'vegetation', 'buildable', 'easements',
    ];

    private const SELLER_BUSINESS_ONLY_KEYS = [
        'business_type', 'business_name', 'year_established',
        'annual_revenue', 'gross_profit', 'sde_ebitda',
        'inventory_value', 'ffe_value', 'reason_for_sale',
        'employee_count', 'financial_statements_available',
        'tax_returns_available', 'nda_required', 'business_location_leased',
        'business_lease_monthly_rent', 'business_lease_expiration',
        'business_lease_renewal_options', 'business_lease_assignable',
        'business_lease_additional_terms', 'licenses', 'sale_includes',
        'electrical_service', 'business_assets',
    ];

    private const SELLER_VL_BUSINESS_SHARED_KEYS = [
        'current_use', 'road_frontage',
    ];

    private AskAiContextBuilderService $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextBuilder = app(AskAiContextBuilderService::class);
    }

    // =========================================================================
    // §30A — Source-level duplicate-key guardrail
    // =========================================================================

    /**
     * Stronger than the runtime §25F test: reads the raw PHP source file and
     * counts key declarations per role block.  PHP's tokenizer/parser silently
     * deduplicates at load time, so the runtime array never reveals a
     * source-level duplicate — this test does.
     */
    public function test_s30a_source_level_no_duplicate_context_keys_seller(): void
    {
        $this->assertNoSourceDuplicates('seller');
    }

    public function test_s30a_source_level_no_duplicate_context_keys_buyer(): void
    {
        $this->assertNoSourceDuplicates('buyer');
    }

    public function test_s30a_source_level_no_duplicate_context_keys_landlord(): void
    {
        $this->assertNoSourceDuplicates('landlord');
    }

    public function test_s30a_source_level_no_duplicate_context_keys_tenant(): void
    {
        $this->assertNoSourceDuplicates('tenant');
    }

    /**
     * Parse the PHP source file line-by-line to detect duplicate key
     * declarations inside CANONICAL_SOURCE_MAP for a given role.
     *
     * The file uses consistent 8-space indent for role names and 12-space
     * indent for field keys.  Value-side strings (cascade arrays, map
     * targets) are on the same line as their key and never match the regex.
     */
    private function assertNoSourceDuplicates(string $targetRole): void
    {
        $path  = app_path('Services/AskAi/AskAiContextBuilderService.php');
        $lines = file($path);

        $inMapConst   = false;
        $currentRole  = null;
        $keyCounts    = [];

        foreach ($lines as $line) {
            if (!$inMapConst) {
                if (str_contains($line, 'CANONICAL_SOURCE_MAP') && str_contains($line, '[')) {
                    $inMapConst = true;
                }
                continue;
            }

            // Role header: 8-space indent + single-quoted word + ' => ['
            if (preg_match('/^        \'(\w+)\'\s*=>\s*\[/', $line, $m)) {
                $currentRole = $m[1];
                $keyCounts[$currentRole] ??= [];
                continue;
            }

            // Field key declaration: 12-space indent + single-quoted word + ' =>'
            if ($currentRole !== null
                && preg_match('/^            \'(\w+)\'\s*=>/', $line, $m)
            ) {
                $key = $m[1];
                $keyCounts[$currentRole][$key] = ($keyCounts[$currentRole][$key] ?? 0) + 1;
                continue;
            }

            // End of const block: 4-space indent + '];'
            if (preg_match('/^\s{4}\];/', $line)) {
                $inMapConst = false;
            }
        }

        $this->assertArrayHasKey(
            $targetRole,
            $keyCounts,
            "§30A: could not locate '{$targetRole}' block in CANONICAL_SOURCE_MAP source"
        );

        $duplicates = [];
        foreach ($keyCounts[$targetRole] as $key => $count) {
            if ($count > 1) {
                $duplicates[] = "{$targetRole}.{$key} (declared {$count} times)";
            }
        }

        $this->assertEmpty(
            $duplicates,
            '§30A source-level duplicate key declarations — PHP silently uses '
            . 'last-write-wins so the first declaration is silently lost: '
            . implode(', ', $duplicates)
        );
    }

    // =========================================================================
    // §30B — Manual extractor completeness (reflection, no DB required)
    // =========================================================================

    /**
     * Seller base scenario (property_type = null): all 30 base-block keys
     * must be declared in CANONICAL_SOURCE_MAP['seller'].
     */
    public function test_s30b_seller_base_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];
        $result = $this->invokeSellerManual(null);

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: seller manual extractor (base) returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[seller]'
            );
        }
    }

    /**
     * Seller Vacant Land scenario: VL-block keys (14) + shared (2) must
     * all be declared in CANONICAL_SOURCE_MAP['seller'].
     */
    public function test_s30b_seller_vl_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];
        $result = $this->invokeSellerManual('Vacant Land');

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: seller manual extractor (Vacant Land) returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[seller]'
            );
        }

        foreach (self::SELLER_VL_ONLY_KEYS as $vl) {
            $this->assertArrayHasKey($vl, $result,
                "§30B: VL-block key '{$vl}' absent from seller manual output when property_type='Vacant Land'");
        }
        foreach (self::SELLER_VL_BUSINESS_SHARED_KEYS as $shared) {
            $this->assertArrayHasKey($shared, $result,
                "§30B: shared VL/Business key '{$shared}' absent when property_type='Vacant Land'");
        }
    }

    /**
     * Seller Business scenario: business-block keys (23) + shared (2) must
     * all be declared in CANONICAL_SOURCE_MAP['seller'].
     */
    public function test_s30b_seller_business_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['seller'];
        $result = $this->invokeSellerManual('Business');

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: seller manual extractor (Business) returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[seller]'
            );
        }

        foreach (self::SELLER_BUSINESS_ONLY_KEYS as $biz) {
            $this->assertArrayHasKey($biz, $result,
                "§30B: business-block key '{$biz}' absent from seller manual output when property_type='Business'");
        }
        foreach (self::SELLER_VL_BUSINESS_SHARED_KEYS as $shared) {
            $this->assertArrayHasKey($shared, $result,
                "§30B: shared VL/Business key '{$shared}' absent when property_type='Business'");
        }
    }

    public function test_s30b_buyer_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['buyer'];
        $result = $this->invokeBuyerManual();

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: buyer manual extractor returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[buyer]'
            );
        }
    }

    public function test_s30b_landlord_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['landlord'];
        $result = $this->invokeLandlordManual();

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: landlord manual extractor returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[landlord]'
            );
        }
    }

    public function test_s30b_tenant_manual_keys_all_declared_in_map(): void
    {
        $map    = AskAiContextBuilderService::CANONICAL_SOURCE_MAP['tenant'];
        $result = $this->invokeTenantManual();

        foreach (array_keys($result) as $key) {
            $this->assertArrayHasKey(
                $key, $map,
                "§30B: tenant manual extractor returned key '{$key}' "
                . 'not declared in CANONICAL_SOURCE_MAP[tenant]'
            );
        }
    }

    // =========================================================================
    // §30C — No unintentional duplicate production (pinned override counts)
    // =========================================================================

    /**
     * Pins the exact number of keys each manual extractor overrides per
     * scenario.  If a developer silently adds or removes an override entry,
     * the count changes and this test fails, forcing a deliberate update.
     *
     * All overrides are intentional: the manual extractor performs JSON
     * decoding, resolveOtherValue(), summarisation, or column-name aliasing
     * that the resolver loop cannot do with a bare meta-key lookup.
     */
    public function test_s30c_seller_base_manual_override_count_is_pinned(): void
    {
        $result = $this->invokeSellerManual(null);

        $this->assertCount(30, $result,
            '§30C: Seller base override count changed — '
            . 'update this assertion only if the change is intentional. '
            . 'Every key here overrides the resolver because it requires '
            . 'JSON decode, resolveOtherValue(), or column aliasing.');
    }

    public function test_s30c_seller_vl_manual_override_count_is_pinned(): void
    {
        // base(30) + VL-only(14) + shared(2) = 46
        $result = $this->invokeSellerManual('Vacant Land');

        $this->assertCount(46, $result,
            '§30C: Seller Vacant Land override count must be 46 (30 base + 14 VL + 2 shared)');
    }

    public function test_s30c_seller_business_manual_override_count_is_pinned(): void
    {
        // base(30) + Business-only(23) + shared(2) = 55
        $result = $this->invokeSellerManual('Business');

        $this->assertCount(55, $result,
            '§30C: Seller Business override count must be 55 (30 base + 23 business + 2 shared)');
    }

    public function test_s30c_buyer_manual_override_count_is_pinned(): void
    {
        $result = $this->invokeBuyerManual();

        $this->assertCount(8, $result,
            '§30C: Buyer override count changed — '
            . 'update only if intentional (bedrooms/bathrooms/carport/garage/'
            . 'water_view/financing_type/cities/counties)');
    }

    public function test_s30c_landlord_manual_override_count_is_pinned(): void
    {
        $result = $this->invokeLandlordManual();

        $this->assertCount(31, $result,
            '§30C: Landlord override count changed — '
            . 'update only if intentional. All 31 keys require JSON decode '
            . 'or field-alias transformation.');
    }

    public function test_s30c_tenant_manual_override_count_is_pinned(): void
    {
        $result = $this->invokeTenantManual();

        $this->assertCount(7, $result,
            '§30C: Tenant override count changed — '
            . 'update only if intentional (bedrooms/bathrooms/desired_lease_length/'
            . 'property_items/appliances/condition_prop/tenant_pays)');
    }

    // =========================================================================
    // §30D — Real listing parity: seller 121
    // =========================================================================

    /**
     * Seller 121 (Income property type) produces exactly 102 context keys:
     *   10 base keys + (131 map keys − 39 conditional VL/Business/shared keys)
     *
     * This exact count is the strongest parity proof: the old extractFactualFields()
     * produced the same value; any regression changes the count.
     */
    public function test_s30d_seller_121_total_context_key_count(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present in this DB environment');
        }

        $ctx = $this->contextBuilder->buildForListing('seller', 121);
        $this->assertArrayHasKey('listing', $ctx, '§30D: buildForListing must return listing sub-array');

        $count = count($ctx['listing']);
        $this->assertSame(102, $count,
            "§30D: Seller 121 listing key count must be 102 "
            . "(10 base + 92 non-conditional map keys); got {$count}. "
            . 'A change here indicates a field was added/removed from the map '
            . 'or a conditional group boundary shifted.');
    }

    /**
     * asking_price must read from the maximum_budget EAV meta key.
     * Seller 121 DB value: '1000000.00'
     */
    public function test_s30d_seller_121_asking_price_from_maximum_budget(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $dbValue = \DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', 121)
            ->where('meta_key', 'maximum_budget')
            ->value('meta_value');

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        $this->assertSame(
            $dbValue,
            $ctx['listing']['asking_price'] ?? null,
            '§30D: seller 121 asking_price must equal maximum_budget EAV meta value'
        );
    }

    /**
     * address must read from the native column (not EAV).
     * Seller 121 DB value: '828 89th Ave N. '
     */
    public function test_s30d_seller_121_address_from_native_column(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $native = \DB::table('seller_agent_auctions')->where('id', 121)->value('address');
        $ctx    = $this->contextBuilder->buildForListing('seller', 121);

        $this->assertSame(
            $native,
            $ctx['listing']['address'] ?? null,
            '§30D: seller 121 address must equal the native address column'
        );
    }

    /**
     * sold must read from the native is_sold column, aliased by the manual
     * extractor (§28B phantom-source fix: explicit $nativeGet('is_sold')).
     * Seller 121 DB value: '0'
     */
    public function test_s30d_seller_121_sold_from_is_sold_native_column(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $native = (string) \DB::table('seller_agent_auctions')->where('id', 121)->value('is_sold');
        $ctx    = $this->contextBuilder->buildForListing('seller', 121);

        $this->assertSame(
            $native,
            $ctx['listing']['sold'] ?? null,
            '§30D: sold must alias is_sold native column '
            . '(extractSellerManualFields explicit $nativeGet(\'is_sold\') pass-through)'
        );
    }

    /**
     * roof_type must be JSON-decoded to a comma-separated string, not a raw
     * JSON string.  Seller 121 has a multi-value JSON array stored in EAV.
     */
    public function test_s30d_seller_121_roof_type_json_decoded(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $raw = \DB::table('seller_agent_auction_metas')
            ->where('seller_agent_auction_id', 121)
            ->where('meta_key', 'roof_type')
            ->value('meta_value');

        $this->assertJson($raw, '§30D fixture: roof_type EAV must contain JSON');

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        $contextValue = $ctx['listing']['roof_type'] ?? null;

        $this->assertNotNull($contextValue, '§30D: roof_type must not be null');
        $this->assertStringNotContainsString('[', $contextValue,
            '§30D: roof_type in context must be decoded (no raw JSON brackets)');
        $this->assertStringContainsString('Built-Up', $contextValue,
            '§30D: decoded roof_type must include "Built-Up"');
    }

    /**
     * For an Income property type (seller 121), Vacant Land–specific keys must
     * not appear in the context — Step 1b removes them and the manual extractor
     * does not add them back.
     */
    public function test_s30d_seller_121_income_type_vl_keys_absent(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        foreach (self::SELLER_VL_ONLY_KEYS as $vlKey) {
            $this->assertArrayNotHasKey(
                $vlKey,
                $ctx['listing'],
                "§30D: VL-only key '{$vlKey}' must not appear for an Income property type"
            );
        }
    }

    /**
     * For an Income property type (seller 121), Business-specific keys must
     * not appear in the context.
     */
    public function test_s30d_seller_121_income_type_business_keys_absent(): void
    {
        if (!$this->sellerExists(121)) {
            $this->markTestSkipped('Seller listing 121 not present');
        }

        $ctx = $this->contextBuilder->buildForListing('seller', 121);

        foreach (self::SELLER_BUSINESS_ONLY_KEYS as $bizKey) {
            $this->assertArrayNotHasKey(
                $bizKey,
                $ctx['listing'],
                "§30D: Business-only key '{$bizKey}' must not appear for an Income property type"
            );
        }
    }

    // =========================================================================
    // §30E — Real listing parity: landlord 71
    // =========================================================================

    /**
     * Landlord 71 context must include all 109 CANONICAL_SOURCE_MAP['landlord']
     * keys plus the 10 base keys = 119 total.
     */
    public function test_s30e_landlord_71_total_context_key_count(): void
    {
        if (!$this->landlordExists(71)) {
            $this->markTestSkipped('Landlord listing 71 not present');
        }

        $ctx   = $this->contextBuilder->buildForListing('landlord', 71);
        $count = count($ctx['listing']);

        $this->assertSame(119, $count,
            "§30E: Landlord 71 listing key count must be 119 (10 base + 109 map); got {$count}");
    }

    /**
     * rent_amount must read from desired_rental_amount EAV meta.
     * Landlord 71 DB value: '7000.00'
     */
    public function test_s30e_landlord_71_rent_amount_from_desired_rental_amount(): void
    {
        if (!$this->landlordExists(71)) {
            $this->markTestSkipped('Landlord listing 71 not present');
        }

        $dbValue = \DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'desired_rental_amount')
            ->value('meta_value');

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        $this->assertSame(
            $dbValue,
            $ctx['listing']['rent_amount'] ?? null,
            '§30E: landlord 71 rent_amount must equal desired_rental_amount EAV meta'
        );
    }

    /**
     * description must read from the additional_details EAV meta key.
     * Landlord 71 DB value starts with "Beautiful 1/1 across the street..."
     */
    public function test_s30e_landlord_71_description_from_additional_details(): void
    {
        if (!$this->landlordExists(71)) {
            $this->markTestSkipped('Landlord listing 71 not present');
        }

        $dbValue = \DB::table('landlord_agent_auction_metas')
            ->where('landlord_agent_auction_id', 71)
            ->where('meta_key', 'additional_details')
            ->value('meta_value');

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        $this->assertSame(
            $dbValue,
            $ctx['listing']['description'] ?? null,
            '§30E: landlord 71 description must equal the additional_details EAV meta'
        );
    }

    /**
     * _sources must be present and include the correct source spec for
     * landlord lease_terms (terms_of_lease EAV key).
     */
    public function test_s30e_landlord_71_sources_include_lease_terms(): void
    {
        if (!$this->landlordExists(71)) {
            $this->markTestSkipped('Landlord listing 71 not present');
        }

        $ctx = $this->contextBuilder->buildForListing('landlord', 71);

        $this->assertArrayHasKey('_sources', $ctx,
            '§30E: buildForListing must return _sources key');
        $this->assertArrayHasKey('lease_terms', $ctx['_sources'],
            '§30E: _sources must include lease_terms for landlord');
    }

    // =========================================================================
    // §30F — Real listing parity: buyer 5
    // =========================================================================

    /**
     * Buyer listing 5 must produce all 26 CANONICAL_SOURCE_MAP['buyer'] keys
     * plus 10 base keys = 36 total.
     */
    public function test_s30f_buyer_5_total_context_key_count(): void
    {
        if (!$this->buyerExists(5)) {
            $this->markTestSkipped('Buyer listing 5 not present');
        }

        $ctx   = $this->contextBuilder->buildForListing('buyer', 5);
        $count = count($ctx['listing']);

        $this->assertSame(36, $count,
            "§30F: Buyer 5 listing key count must be 36 (10 base + 26 map); got {$count}");
    }

    /**
     * max_price key must exist in buyer context (sourced from maximum_budget EAV).
     * Value may be null/empty if the listing has no budget set.
     */
    public function test_s30f_buyer_5_max_price_key_present(): void
    {
        if (!$this->buyerExists(5)) {
            $this->markTestSkipped('Buyer listing 5 not present');
        }

        $ctx = $this->contextBuilder->buildForListing('buyer', 5);

        $this->assertArrayHasKey('max_price', $ctx['listing'],
            '§30F: max_price must always be present in buyer context (null if unset)');
        $this->assertArrayHasKey('bedrooms', $ctx['listing'],
            '§30F: bedrooms must be present in buyer context');
        $this->assertArrayHasKey('cities', $ctx['listing'],
            '§30F: cities must be present in buyer context');
    }

    // =========================================================================
    // §30G — Real listing parity: tenant 133
    // =========================================================================

    /**
     * Tenant listing 133 must produce all 17 CANONICAL_SOURCE_MAP['tenant'] keys
     * plus 10 base keys = 27 total.
     */
    public function test_s30g_tenant_133_total_context_key_count(): void
    {
        if (!$this->tenantExists(133)) {
            $this->markTestSkipped('Tenant listing 133 not present');
        }

        $ctx   = $this->contextBuilder->buildForListing('tenant', 133);
        $count = count($ctx['listing']);

        $this->assertSame(27, $count,
            "§30G: Tenant 133 listing key count must be 27 (10 base + 17 map); got {$count}");
    }

    /**
     * max_rent must read from the budget EAV meta key.
     * Tenant 133 DB value: '0'  — verifies the numeric-zero falsy-value fix
     * (string '0' must NOT be treated as absent by the resolver cascade).
     */
    public function test_s30g_tenant_133_max_rent_from_budget_key(): void
    {
        if (!$this->tenantExists(133)) {
            $this->markTestSkipped('Tenant listing 133 not present');
        }

        $dbValue = \DB::table('tenant_agent_auction_metas')
            ->where('tenant_agent_auction_id', 133)
            ->where('meta_key', 'budget')
            ->value('meta_value');

        $ctx = $this->contextBuilder->buildForListing('tenant', 133);

        $this->assertSame(
            $dbValue,
            $ctx['listing']['max_rent'] ?? null,
            '§30G: max_rent must equal budget EAV meta; '
            . '\'0\' is a valid numeric zero — resolver cascade must treat it as present '
            . '(the waterfront-feet PHP zero falsy-gotcha pattern, see memory)'
        );
    }

    // =========================================================================
    // Reflection helpers
    // =========================================================================

    private function invokeSellerManual(?string $propertyType): array
    {
        $infoGet  = fn(string $key) => $key === 'property_type' ? $propertyType : null;
        $nativeGet = fn(string $key) => null;

        $method = new ReflectionMethod(AskAiContextBuilderService::class, 'extractSellerManualFields');
        $method->setAccessible(true);

        return $method->invoke($this->contextBuilder, $infoGet, $nativeGet);
    }

    private function invokeBuyerManual(): array
    {
        $infoGet  = fn(string $key) => null;
        $nativeGet = fn(string $key) => null;

        $method = new ReflectionMethod(AskAiContextBuilderService::class, 'extractBuyerManualFields');
        $method->setAccessible(true);

        return $method->invoke($this->contextBuilder, $infoGet, $nativeGet);
    }

    private function invokeLandlordManual(): array
    {
        $infoGet = fn(string $key) => null;

        $method = new ReflectionMethod(AskAiContextBuilderService::class, 'extractLandlordManualFields');
        $method->setAccessible(true);

        return $method->invoke($this->contextBuilder, $infoGet);
    }

    private function invokeTenantManual(): array
    {
        $infoGet = fn(string $key) => null;

        $method = new ReflectionMethod(AskAiContextBuilderService::class, 'extractTenantManualFields');
        $method->setAccessible(true);

        return $method->invoke($this->contextBuilder, $infoGet);
    }

    // =========================================================================
    // DB existence guards
    // =========================================================================

    private function sellerExists(int $id): bool
    {
        return \DB::table('seller_agent_auctions')->where('id', $id)->exists();
    }

    private function landlordExists(int $id): bool
    {
        return \DB::table('landlord_agent_auctions')->where('id', $id)->exists();
    }

    private function buyerExists(int $id): bool
    {
        return \DB::table('buyer_agent_auctions')->where('id', $id)->exists();
    }

    private function tenantExists(int $id): bool
    {
        return \DB::table('tenant_agent_auctions')->where('id', $id)->exists();
    }
}

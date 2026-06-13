<?php

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use App\Services\AgentBidMapperService;
use App\Services\LandlordAcceptedBidSummaryService;

/**
 * P1A — Behavioral regression guards for the renewal_fee_flat_fee rename.
 *
 * These tests verify the end-to-end read path:
 *   1. LandlordAcceptedBidSummaryService::resolveRenewalFeeDisplay() returns
 *      the formatted flat fee when canonical key `renewal_fee_flat_fee` is
 *      supplied (not the old misspelled key `renewal_fee_flat_free`).
 *   2. Supplying only the old key returns null (confirming the method reads
 *      the canonical key exclusively).
 *   3. The value '0' (a valid flat-fee amount) is not treated as missing —
 *      guards the strict null/empty-string migration collision logic.
 *
 * All tests are pure unit tests — no database required.
 * resolveRenewalFeeDisplay() is protected; ReflectionMethod is used to invoke it.
 */
class LandlordRenewalFlatFeeP1ARegressionTest extends TestCase
{
    private function callResolveRenewalFeeDisplay(array $data): ?string
    {
        $service = new LandlordAcceptedBidSummaryService();
        $rc      = new ReflectionClass($service);
        $method  = $rc->getMethod('resolveRenewalFeeDisplay');
        $method->setAccessible(true);
        return $method->invoke($service, $data);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Canonical key read-path
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function flat_fee_display_uses_canonical_renewal_fee_flat_fee_key(): void
    {
        $result = $this->callResolveRenewalFeeDisplay([
            'renewal_fee_type'     => 'Flat Fee',
            'renewal_fee_flat_fee' => '2000',
        ]);

        $this->assertSame('$2,000', $result,
            'resolveRenewalFeeDisplay must format renewal_fee_flat_fee as money');
    }

    /** @test */
    public function flat_fee_display_returns_null_when_only_old_misspelled_key_is_supplied(): void
    {
        $result = $this->callResolveRenewalFeeDisplay([
            'renewal_fee_type'      => 'Flat Fee',
            'renewal_fee_flat_free' => '2000',  // old misspelled key — must NOT be read
        ]);

        $this->assertNull($result,
            'resolveRenewalFeeDisplay must NOT read the old misspelled renewal_fee_flat_free key');
    }

    /** @test */
    public function flat_fee_display_returns_null_when_both_type_missing(): void
    {
        $result = $this->callResolveRenewalFeeDisplay([]);

        $this->assertNull($result,
            'resolveRenewalFeeDisplay must return null when no renewal_fee_type is set');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Preset → mapper value-transfer regression
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mapper_emits_renewal_fee_flat_fee_value_from_canonical_preset_key(): void
    {
        $mapped = AgentBidMapperService::mapFromProfile([
            'renewal_fee_flat_fee' => '3500',
        ]);

        $this->assertSame('3500', $mapped['renewal_fee_flat_fee'],
            'Mapper must propagate renewal_fee_flat_fee value from preset profileData');
    }

    /** @test */
    public function mapper_falls_back_to_old_key_for_legacy_preset_data(): void
    {
        // Profiles saved before the P1A rename stored the value under the old key.
        // The mapper backward-compat fallback must rescue this value.
        $mapped = AgentBidMapperService::mapFromProfile([
            'renewal_fee_flat_free' => '1500',  // legacy key — no canonical key present
        ]);

        $this->assertSame('1500', $mapped['renewal_fee_flat_fee'],
            'Mapper must fall back to renewal_fee_flat_free for legacy presets that pre-date P1A');
    }

    /** @test */
    public function mapper_prefers_canonical_key_over_legacy_fallback(): void
    {
        // When both keys are present, canonical takes priority.
        $mapped = AgentBidMapperService::mapFromProfile([
            'renewal_fee_flat_fee'  => '2500',
            'renewal_fee_flat_free' => '9999',  // stale/conflicting — must be ignored
        ]);

        $this->assertSame('2500', $mapped['renewal_fee_flat_fee'],
            'Mapper must prefer renewal_fee_flat_fee over the legacy fallback key');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Migration strict-comparison guard (source scan)
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function migration_collision_logic_uses_strict_string_comparison_not_empty(): void
    {
        $migrationPath = database_path(
            'migrations/2026_06_13_000001_rename_renewal_fee_flat_free_in_landlord_agent_auction_bid_metas.php'
        );

        $this->assertFileExists($migrationPath, 'P1A migration file must exist');

        $source = file_get_contents($migrationPath);

        $this->assertStringNotContainsString(
            'empty($canonicalRow->meta_value)',
            $source,
            'Migration must not use empty() for canonical value check — "0" is a valid fee'
        );
        $this->assertStringNotContainsString(
            'empty($staleRow->meta_value)',
            $source,
            'Migration must not use empty() for stale value check — "0" is a valid fee'
        );
        $this->assertStringContainsString(
            "=== ''",
            $source,
            'Migration must use strict empty-string check (=== \'\') for collision logic'
        );
    }
}

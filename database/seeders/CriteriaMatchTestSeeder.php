<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CriteriaMatchTestSeeder — DEV / STAGING ONLY
 *
 * Inserts one BuyerCriteriaAuction and one TenantCriteriaAuction (with EAV meta)
 * so the /stellar/buyer/results matching pipeline has criteria to match against the
 * seeded bridge_properties records from BridgePropertySeeder.
 *
 * PRODUCTION GUARD: this seeder aborts immediately in the production environment.
 *
 * Usage (manual invocation only):
 *   php artisan db:seed --class=CriteriaMatchTestSeeder
 *
 * Run BridgePropertySeeder first to populate bridge_properties if it is empty:
 *   php artisan db:seed --class=BridgePropertySeeder
 *
 * DO NOT add this class to DatabaseSeeder::run() — it must never run
 * automatically during deploy or a full db:seed call.
 *
 * ─── Verified criteria + expected matches (tested 2026-06-23) ───────────────
 *
 * IDs are auto-incremented and printed on each run (idempotent rerun cleans up
 * old records and creates fresh ones). Use the URLs printed to the console.
 *
 * Dev user: tenant@exp.com (id=139) — canonical account for consumer criteria/results testing.
 * buyer@exp.com is legacy; do not use it for new test workflows.
 *
 * Buyer Criteria (criteria_type=buyer):
 *   EAV: property_types=["Residential"], preferred_cities=["Clearwater","Saint Petersburg"],
 *        preferred_zip_codes=["33755","33713"], min_price=250000, max_price=500000, min_bedrooms=2
 *   Note: min_price is stored as EAV for spec alignment but BuyerCriteriaLoader does not
 *   currently read it (BuyerCriteriaPayload has no minPrice field); it will take effect
 *   automatically once the loader/payload add a minimum price filter.
 *   Expected matches (≥3) — scores verified from actual BuyerMatchService::match() run:
 *     SEED-STP-001  Saint Petersburg FL 33713  score=63  lat=27.7734 lng=-82.6655
 *     SEED-CLW-002  Clearwater FL 33762        score=63  lat=27.8898 lng=-82.7196
 *     SEED-CLW-001  Clearwater FL 33755        score=58  lat=27.9659 lng=-82.7990
 *
 * Tenant Criteria (criteria_type=tenant):
 *   EAV: cities=["Clearwater"], property_type="Residential", bedrooms="2"
 *   Expected matches (≥2):
 *     SEED-CLW-001  Clearwater FL 33755  score=63  lat=27.9659 lng=-82.7990
 *     SEED-CLW-002  Clearwater FL 33762  score=63  lat=27.8898 lng=-82.7196
 *
 * Note: TenantCriteriaLoader always returns preferred_zip_codes=[] regardless of
 * stored meta; city matching (EAV key "cities") drives all tenant location results.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CriteriaMatchTestSeeder extends Seeder
{
    private const SEEDER_MARKER_KEY   = '_seeder_marker';
    private const SEEDER_MARKER_VALUE = 'CriteriaMatchTestSeeder';

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->error(
                'CriteriaMatchTestSeeder is not allowed in the production environment. Aborting.'
            );
            return;
        }

        $this->command->info('CriteriaMatchTestSeeder: inserting dev criteria records (dev/staging only)…');

        // -----------------------------------------------------------------------
        // Resolve dev user — prefer tenant@exp.com (canonical test account for
        // consumer criteria/results workflows), fall back to first non-deleted.
        // buyer@exp.com is legacy and must not be used for new testing.
        // -----------------------------------------------------------------------
        $devUser = DB::table('users')
            ->where('email', 'tenant@exp.com')
            ->where('is_deleted', false)
            ->first();

        if (!$devUser) {
            $devUser = DB::table('users')
                ->where('is_deleted', false)
                ->orderBy('id')
                ->first();
        }

        if (!$devUser) {
            $this->command->error('No dev user found. Please ensure at least one non-deleted user exists.');
            return;
        }

        $this->command->info("Using dev user: id={$devUser->id}, email={$devUser->email}");

        // -----------------------------------------------------------------------
        // Insert Buyer Criteria
        // -----------------------------------------------------------------------
        $buyerId = $this->insertBuyerCriteria($devUser->id);
        $this->command->info("Buyer Criteria inserted: id={$buyerId}");
        $this->command->info("  → Browser test URL: /stellar/buyer/results?criteria_type=buyer&criteria_id={$buyerId}");

        // -----------------------------------------------------------------------
        // Insert Tenant Criteria
        // -----------------------------------------------------------------------
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->command->warn('tenant_criteria_auctions table does not exist — skipping Tenant Criteria insert.');
        } else {
            $tenantId = $this->insertTenantCriteria($devUser->id);
            $this->command->info("Tenant Criteria inserted: id={$tenantId}");
            $this->command->info("  → Browser test URL: /stellar/buyer/results?criteria_type=tenant&criteria_id={$tenantId}");
        }

        // -----------------------------------------------------------------------
        // Bridge property inventory check
        // -----------------------------------------------------------------------
        $bridgeCount = DB::table('bridge_properties')
            ->where('standard_status', 'Active')
            ->where('property_type', 'Residential')
            ->count();

        if ($bridgeCount === 0) {
            $this->command->warn(
                'bridge_properties has no Active/Residential records. ' .
                'Run: php artisan db:seed --class=BridgePropertySeeder'
            );
        } else {
            $this->command->info("bridge_properties Active/Residential count: {$bridgeCount}");
        }

        $this->command->info('CriteriaMatchTestSeeder: done.');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Delete any existing seeder-created buyer criteria for this user, then
     * insert a fresh BuyerCriteriaAuction with the required EAV meta rows.
     *
     * EAV keys used — must match BuyerCriteriaLoader exactly:
     *   property_types      → JSON array, required non-empty (BuyerCriteriaPayload guard)
     *   preferred_cities    → JSON array (location constraint, avoids no_location state)
     *   preferred_zip_codes → JSON array (secondary location match)
     *   max_price           → scalar integer string
     *   min_bedrooms        → scalar integer string
     *   _seeder_marker      → idempotency key; not read by loader (underscore prefix)
     */
    private function insertBuyerCriteria(int $userId): int
    {
        $this->deleteExistingBuyerCriteria($userId);

        $buyerId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Dev Test – Clearwater / Saint Petersburg',
            'max_price'   => 500000,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $metas = [
            ['meta_key' => 'property_types',      'meta_value' => json_encode(['Residential'])],
            ['meta_key' => 'preferred_cities',     'meta_value' => json_encode(['Clearwater', 'Saint Petersburg'])],
            ['meta_key' => 'preferred_zip_codes',  'meta_value' => json_encode(['33755', '33713'])],
            // min_price stored per task spec; BuyerCriteriaLoader does not currently read it
            // (BuyerCriteriaPayload has no minPrice field) but it will apply once added.
            ['meta_key' => 'min_price',            'meta_value' => '250000'],
            ['meta_key' => 'max_price',            'meta_value' => '500000'],
            ['meta_key' => 'min_bedrooms',         'meta_value' => '2'],
            ['meta_key' => self::SEEDER_MARKER_KEY, 'meta_value' => self::SEEDER_MARKER_VALUE],
        ];

        foreach ($metas as $meta) {
            DB::table('buyer_criteria_auction_metas')->insert(array_merge(
                $meta,
                ['buyer_criteria_auction_id' => $buyerId]
            ));
        }

        return $buyerId;
    }

    /**
     * Delete any existing seeder-created tenant criteria for this user, then
     * insert a fresh TenantCriteriaAuction with the required EAV meta rows.
     *
     * EAV keys used — must match TenantCriteriaLoader exactly:
     *   cities         → JSON array; TenantCriteriaLoader reads this as preferred_cities
     *   property_type  → scalar string; normalized to ['Residential'] by loader
     *   bedrooms       → scalar numeric string; mapped to min_bedrooms
     *   _seeder_marker → idempotency key; not read by loader
     *
     * Note: TenantCriteriaLoader always returns preferred_zip_codes = [] regardless
     * of stored meta; city matching is the only location mechanism for tenant criteria.
     */
    private function insertTenantCriteria(int $userId): int
    {
        $this->deleteExistingTenantCriteria($userId);

        $tenantId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'is_approved' => true,
            'is_sold'     => false,
            'is_draft'    => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $metas = [
            ['meta_key' => 'cities',               'meta_value' => json_encode(['Clearwater'])],
            ['meta_key' => 'property_type',         'meta_value' => 'Residential'],
            ['meta_key' => 'bedrooms',              'meta_value' => '2'],
            ['meta_key' => self::SEEDER_MARKER_KEY, 'meta_value' => self::SEEDER_MARKER_VALUE],
        ];

        foreach ($metas as $meta) {
            DB::table('tenant_criteria_auction_metas')->insert(array_merge(
                $meta,
                ['tenant_criteria_auction_id' => $tenantId]
            ));
        }

        return $tenantId;
    }

    /**
     * Remove any buyer_criteria_auctions (and their metas) for $userId that
     * were created by this seeder, identified by the seeder marker meta key.
     */
    private function deleteExistingBuyerCriteria(int $userId): void
    {
        $markedIds = DB::table('buyer_criteria_auction_metas')
            ->join('buyer_criteria_auctions', 'buyer_criteria_auction_metas.buyer_criteria_auction_id', '=', 'buyer_criteria_auctions.id')
            ->where('buyer_criteria_auctions.user_id', $userId)
            ->where('buyer_criteria_auction_metas.meta_key', self::SEEDER_MARKER_KEY)
            ->where('buyer_criteria_auction_metas.meta_value', self::SEEDER_MARKER_VALUE)
            ->pluck('buyer_criteria_auction_metas.buyer_criteria_auction_id')
            ->toArray();

        if (!empty($markedIds)) {
            DB::table('buyer_criteria_auction_metas')
                ->whereIn('buyer_criteria_auction_id', $markedIds)
                ->delete();
            DB::table('buyer_criteria_auctions')
                ->whereIn('id', $markedIds)
                ->delete();
            $this->command->info('Cleaned up ' . count($markedIds) . ' existing seeder Buyer Criteria record(s).');
        }
    }

    /**
     * Remove any tenant_criteria_auctions (and their metas) for $userId that
     * were created by this seeder, identified by the seeder marker meta key.
     */
    private function deleteExistingTenantCriteria(int $userId): void
    {
        if (!Schema::hasTable('tenant_criteria_auction_metas')) {
            return;
        }

        $markedIds = DB::table('tenant_criteria_auction_metas')
            ->join('tenant_criteria_auctions', 'tenant_criteria_auction_metas.tenant_criteria_auction_id', '=', 'tenant_criteria_auctions.id')
            ->where('tenant_criteria_auctions.user_id', $userId)
            ->where('tenant_criteria_auction_metas.meta_key', self::SEEDER_MARKER_KEY)
            ->where('tenant_criteria_auction_metas.meta_value', self::SEEDER_MARKER_VALUE)
            ->pluck('tenant_criteria_auction_metas.tenant_criteria_auction_id')
            ->toArray();

        if (!empty($markedIds)) {
            DB::table('tenant_criteria_auction_metas')
                ->whereIn('tenant_criteria_auction_id', $markedIds)
                ->delete();
            DB::table('tenant_criteria_auctions')
                ->whereIn('id', $markedIds)
                ->delete();
            $this->command->info('Cleaned up ' . count($markedIds) . ' existing seeder Tenant Criteria record(s).');
        }
    }
}

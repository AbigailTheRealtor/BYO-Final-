<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * LocationDnaTestSeeder
 *
 * Creates one Buyer Criteria record and one Tenant Criteria record,
 * each with a full location_dna_preferences meta payload.
 * Safe to re-run — uses updateOrCreate for both records and their metas.
 *
 * Run:  php artisan db:seed --class=LocationDnaTestSeeder
 */
class LocationDnaTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $buyerLdna = json_encode([
            'cities'             => ['Orlando'],
            'zip_codes'          => ['32801', '32803'],
            'neighborhoods'      => ['Downtown Orlando'],
            'polygon'            => null,
            'radius'             => ['lat' => 28.5383, 'lng' => -81.3792, 'miles' => 5],
            'location_notes'     => 'Prefer walkable areas near Lake Eola.',
        ]);

        $tenantLdna = json_encode([
            'cities'             => ['Tampa'],
            'zip_codes'          => ['33606', '33629'],
            'neighborhoods'      => ['Hyde Park', 'Downtown Tampa'],
            'polygon'            => null,
            'radius'             => ['lat' => 27.9506, 'lng' => -82.4572, 'miles' => 3],
            'location_notes'     => 'Prefer walkable areas near the Riverwalk. South Tampa preferred.',
        ]);

        $buyerId = DB::table('buyer_criteria_auctions')
            ->where('id', 3)
            ->value('id');

        if (! $buyerId) {
            $buyerId = DB::table('buyer_criteria_auctions')->insertGetId([
                'user_id'      => 136,
                'is_approved'  => true,
                'is_draft'     => false,
                'listing_date' => $now->toDateString(),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        DB::table('buyer_criteria_auction_metas')->updateOrInsert(
            ['buyer_criteria_auction_id' => $buyerId, 'meta_key' => 'location_dna_preferences'],
            ['meta_value' => $buyerLdna]
        );

        $tenantId = DB::table('tenant_criteria_auctions')
            ->where('id', 1)
            ->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenant_criteria_auctions')->insertGetId([
                'user_id'      => 136,
                'is_approved'  => true,
                'is_draft'     => false,
                'display_bids' => false,
                'referral_locked' => false,
                'listing_date' => $now->toDateString(),
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        DB::table('tenant_criteria_auction_metas')->updateOrInsert(
            ['tenant_criteria_auction_id' => $tenantId, 'meta_key' => 'location_dna_preferences'],
            ['meta_value' => $tenantLdna]
        );

        $this->command->info("LocationDnaTestSeeder: buyer_criteria_auctions#{$buyerId} and tenant_criteria_auctions#{$tenantId} seeded.");
    }
}

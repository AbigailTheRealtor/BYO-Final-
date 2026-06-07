<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LocationDnaSellerSeeder
 *
 * Manual test workflow:
 *   1. php artisan db:seed --class=LocationDnaSellerSeeder
 *   2. Copy the printed listing ID from the output.
 *   3. php artisan location-dna:generate seller <id>
 *
 * This seeder is intentionally NOT called from DatabaseSeeder.
 * Run it on demand to create a test seller listing with a valid Tampa/FL address.
 */
class LocationDnaSellerSeeder extends Seeder
{
    public function run(): void
    {
        $stateId = $this->ensureState('FL', 'Florida');
        $cityId  = $this->ensureCity('Tampa', $stateId);

        $user = User::factory()->create();

        $listingId = DB::table('property_auctions')->insertGetId([
            'user_id'      => $user->id,
            'is_approved'  => true,
            'sold'         => false,
            'auction_type' => 'Traditional Listing',
            'title'        => 'Location DNA Test Listing — Tampa FL',
            'address'      => '123 Main St',
            'city_id'      => $cityId,
            'state_id'     => $stateId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('property_auction_metas')->insert([
            'property_auction_id' => $listingId,
            'meta_key'            => 'zip_code',
            'meta_value'          => '33602',
        ]);

        $this->command->info("Location DNA seller listing created: ID {$listingId}");
    }

    private function ensureState(string $abbreviation, string $name): int
    {
        $existing = DB::table('us_states')->where('abbreviation', $abbreviation)->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_states')->insertGetId([
            'name'         => $name,
            'abbreviation' => $abbreviation,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function ensureCity(string $name, int $stateId): int
    {
        $existing = DB::table('us_cities')
            ->where('name', $name)
            ->where('state_id', $stateId)
            ->first();
        if ($existing) {
            return $existing->id;
        }
        return DB::table('us_cities')->insertGetId([
            'name'       => $name,
            'state_id'   => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

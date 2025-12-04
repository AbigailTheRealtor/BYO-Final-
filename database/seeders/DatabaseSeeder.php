<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->Call([
            CountryStateCityTableSeeder::class,
            FloridaCitySeeder::class,
            PropertyTypeSeeder::class,
            UserSeeder::class,
            AirConditioningTypeSeeder::class,
            ApplianceSeeder::class,
            AuctionTypeSeeder::class,
            BathroomSeeder::class,
            BedroomSeeder::class,
            FeeIncludeSeeder::class,
            FinancingSeeder::class,
            HalfBathroomSeeder::class,
            WaterExtraSeeder::class,
            WaterViewTypeSeeder::class,
            CountySeeder::class,
            HeatingFuelSeeder::class,
            AgentServiceSeeder::class,
        ]);
    }
}

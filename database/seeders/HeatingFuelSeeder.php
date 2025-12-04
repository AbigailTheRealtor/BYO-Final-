<?php

namespace Database\Seeders;

use App\Models\HeatingFuel;
use Illuminate\Database\Seeder;

class HeatingFuelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        HeatingFuel::insert([
                ["name"=>"Baseboard"],
                ["name"=>"Central"],
                ["name"=>"Electric"],
                ["name"=>"Exhaust Fans"],
                ["name"=>"Heat Pump"],
                ["name"=>"Heat Recovery Unit"],
                ["name"=>"Natural Gas"],
                ["name"=>"None"],
                ["name"=>"Oil"],
                ["name"=>"Other"],
                ["name"=>"Partial"],
                ["name"=>"Propane"],
                ["name"=>"Radiant Ceiling"],
                ["name"=>"Reverse Cycle"],
                ["name"=>"Solar"],
                ["name"=>"Space Heater"],
                ["name"=>"Wall Furnace"],
                ["name"=>"Wall Units / Window Unit"],
                ["name"=>"Zoned"],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Appliance;
use Illuminate\Database\Seeder;

class ApplianceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Appliance::insert([
            ["name"=>"Bar Fridge"],
            ["name"=>"Built-In Oven"],
            ["name"=>"Convection Oven"],
            ["name"=>"Cooktop"],
            ["name"=>"Dishwasher"],
            ["name"=>"Disposal"],
            ["name"=>"Dryer"],
            ["name"=>"Electric Water Heater"],
            ["name"=>"Exhaust Fan"],
            ["name"=>"Freezer"],
            ["name"=>"Gas Water Heater"],
            ["name"=>"Ice Maker"],
            ["name"=>"Indoor Grill"],
            ["name"=>"Kitchen Reverse Osmosis System"],
            ["name"=>"Microwave"],
            ["name"=>"None"],
            ["name"=>"Other"],
            ["name"=>"Range Electric"],
            ["name"=>"Range Gas"],
            ["name"=>"Range Hood"],
            ["name"=>"Refrigerator"],
            ["name"=>"Solar Hot Water"],
            ["name"=>"Solar Hot Water Owned"],
            ["name"=>"Solar Hot Water Rented"],
            ["name"=>"Tankless Water Heater"],
            ["name"=>"Trash Compactor"],
            ["name"=>"Washer"],
            ["name"=>"Water Filtration System"],
            ["name"=>"Water Purifier"],
            ["name"=>"Water Softener"],
            ["name"=>"Whole House R.O. System"],
            ["name"=>"Wine Refrigerator"],
        ]);
    }
}

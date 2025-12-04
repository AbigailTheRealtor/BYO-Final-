<?php

namespace Database\Seeders;

use App\Models\WaterViewType;
use Illuminate\Database\Seeder;

class WaterViewTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        WaterViewType::insert([
            ["name"=>"Bay/Harbor - Full"],
            ["name"=>"Bay/Harbor - Partial"],
            ["name"=>"Bayou"],
            ["name"=>"Beach"],
            ["name"=>"Canal"],
            ["name"=>"Creek"],
            ["name"=>"Gulf/Ocean - Full"],
            ["name"=>"Gulf/Ocean - Partial"],
            ["name"=>"Gulf/Ocean to Bay"],
            ["name"=>"Intracoastal Waterway"],
            ["name"=>"Lagoon/Estuary"],
            ["name"=>"Lake"],
            ["name"=>"Lake - Chain of Lakes"],
            ["name"=>"Marina"],
            ["name"=>"Pond"],
            ["name"=>"River"],
        ]);
    }
}

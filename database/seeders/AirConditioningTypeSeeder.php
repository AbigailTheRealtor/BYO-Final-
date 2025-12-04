<?php

namespace Database\Seeders;

use App\Models\AirConditioningType;
use Illuminate\Database\Seeder;

class AirConditioningTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* Central Air, Humidity control, Mini-Split Unit(s), Wall/Window Units, Zoned, None, Other */
        AirConditioningType::insert([
            ["name"=>"Central Air"],
            ["name"=>"Humidity Control"],
            ["name"=>"Mini-Split Unit(s)"],
            ["name"=>"Wall/Window Unit(s)"],
            ["name"=>"Zoned"],
            ["name"=>"None"],
            ["name"=>"Other"],
        ]);
    }
}

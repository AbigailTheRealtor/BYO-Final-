<?php

namespace Database\Seeders;

use App\Models\Bedroom;
use Illuminate\Database\Seeder;

class BedroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Bedroom::insert([
            ['name'=>'1 Bed'],
            ['name'=>'2 Beds'],
            ['name'=>'3 Beds'],
            ['name'=>'4 Beds'],
            ['name'=>'5 Beds'],
            ['name'=>'6 Beds'],
            ['name'=>'7 Beds'],
            ['name'=>'8 Beds'],
            ['name'=>'9 Beds'],
            ['name'=>'10+ Beds'],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\HalfBathroom;
use Illuminate\Database\Seeder;

class HalfBathroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        HalfBathroom::insert([
            ['name'=>'0.5 Bath'],
            ['name'=>'1.5 Baths'],
            ['name'=>'2.5 Baths'],
            ['name'=>'3.5 Baths'],
            ['name'=>'4.5 Baths'],
            ['name'=>'5.5 Baths'],
            ['name'=>'6.5 Baths'],
            ['name'=>'7.5 Baths'],
            ['name'=>'8.5 Baths'],
        ]);
    }
}

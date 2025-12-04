<?php

namespace Database\Seeders;

use App\Models\Bathroom;
use Illuminate\Database\Seeder;

class BathroomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Bathroom::insert([
            ['name'=>'1 Bath'],
            ['name'=>'1.5 Baths'],
            ['name'=>'2 Baths'],
            ['name'=>'2.5 Baths'],
            ['name'=>'3 Baths'],
            ['name'=>'3.5 Baths'],
            ['name'=>'4 Baths'],
            ['name'=>'4.5 Baths'],
            ['name'=>'5 Baths'],
            ['name'=>'5.5 Baths'],
            ['name'=>'6 Baths'],
            ['name'=>'6.5 Baths'],
            ['name'=>'7 Baths'],
            ['name'=>'7.5 Baths'],
            ['name'=>'8 Baths'],
            ['name'=>'8.5 Baths'],
            ['name'=>'9 Baths'],
            ['name'=>'9.5 Baths'],
            ['name'=>'10+ Baths'],
        ]);
    }
}

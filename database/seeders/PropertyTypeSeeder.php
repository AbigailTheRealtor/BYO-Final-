<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* Single Family Home,
        Townhome,
        Condo,
        Condo-Hotel,
        Villa,
        Multi-Family,
        Land/Lots,
        Manufactured Homes,
        Mobile Home,
        Modular Home,
        Duplex,
        Triplex,
        Quadruplex,
        Five or more units,
        Commercial */
        $data = [
            ["name"=>"Townhouse", 'sort' => 0 ],
            ["name"=>"Multi-family", 'sort' => 1],
            ["name"=>"Condo", 'sort' => 2],
            ["name"=>"Land/Lots", 'sort' => 3],
            ["name"=>"Apartments", 'sort' => 4],
            ["name"=>"Manufactured", 'sort' => 5],
            ["name"=>"Condo-Hotel", 'sort' => 6],
            ["name"=>"Villa", 'sort' => 7],
            ["name"=>"Single Family Home", 'sort' => 8],
            ["name"=>"Mobile Home", 'sort' => 9],
            ["name"=>"Modular Home", 'sort' => 10],
            ["name"=>"Duplex", 'sort' => 11],
            ["name"=>"Triplex", 'sort' => 12],
            ["name"=>"Quadruplex", 'sort' => 13],
            ["name"=>"Five or more units", 'sort' => 14],
            ["name"=>"Other", 'sort' => 15],
        ];
        PropertyType::insert($data);
    }
}

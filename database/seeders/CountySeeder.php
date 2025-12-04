<?php

namespace Database\Seeders;

use App\Models\County;
use Illuminate\Database\Seeder;

class CountySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        County::insert([
            ["name"=>"Alachua"],
            ["name"=>"Baker"],
            ["name"=>"Bay"],
            ["name"=>"Bradford"],
            ["name"=>"Brevard"],
            ["name"=>"Broward"],
            ["name"=>"Calhoun"],
            ["name"=>"Charlotte"],
            ["name"=>"Citrus"],
            ["name"=>"Clay"],
            ["name"=>"Collier"],
            ["name"=>"Columbia"],
            ["name"=>"DeSoto"],
            ["name"=>"Dixie"],
            ["name"=>"Duval"],
            ["name"=>"Escambia"],
            ["name"=>"Flagler"],
            ["name"=>"Franklin"],
            ["name"=>"Gadsden"],
            ["name"=>"Gilchrist"],
            ["name"=>"Glades"],
            ["name"=>"Gulf"],
            ["name"=>"Hamilton"],
            ["name"=>"Hardee"],
            ["name"=>"Hendry"],
            ["name"=>"Hernando"],
            ["name"=>"Highlands"],
            ["name"=>"Hillsborough"],
            ["name"=>"Holmes"],
            ["name"=>"Indian River"],
            ["name"=>"Jackson"],
            ["name"=>"Jefferson"],
            ["name"=>"Lafayette"],
            ["name"=>"Lake"],
            ["name"=>"Lee"],
            ["name"=>"Leon"],
            ["name"=>"Levy"],
            ["name"=>"Liberty"],
            ["name"=>"Madison"],
            ["name"=>"Manatee"],
            ["name"=>"Marion"],
            ["name"=>"Martin"],
            ["name"=>"Miami-Dade"],
            ["name"=>"Monroe"],
            ["name"=>"Nassau"],
            ["name"=>"Okaloosa"],
            ["name"=>"Okeechobee"],
            ["name"=>"Orange"],
            ["name"=>"Osceola"],
            ["name"=>"Palm Beach"],
            ["name"=>"Pasco"],
            ["name"=>"Pinellas"],
            ["name"=>"Polk"],
            ["name"=>"Putnam"],
            ["name"=>"Santa Rosa"],
            ["name"=>"Sarasota"],
            ["name"=>"Seminole"],
            ["name"=>"St. Johns"],
            ["name"=>"St. Lucie"],
            ["name"=>"Sumter"],
            ["name"=>"Suwannee"],
            ["name"=>"Taylor"],
            ["name"=>"Union"],
            ["name"=>"Volusia"],
            ["name"=>"Wakulla"],
            ["name"=>"Walton"],
            ["name"=>"Washington"],
        ]);
    }
}

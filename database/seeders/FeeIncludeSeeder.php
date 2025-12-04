<?php

namespace Database\Seeders;

use App\Models\FeeInclude;
use Illuminate\Database\Seeder;

class FeeIncludeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FeeInclude::insert([
            ["name"=>"24-Hour Guard"],
            ["name"=>"Cable TV"],
            ["name"=>"Common Area Taxes"],
            ["name"=>"Community Pool"],
            ["name"=>"Electricity"],
            ["name"=>"Escrow Reserves Fund"],
            ["name"=>"Fidelity Bond"],
            ["name"=>"Gas"],
            ["name"=>"Insurance"],
            ["name"=>"Internet"],
            ["name"=>"Maintenance Exterior"],
            ["name"=>"Maintenance Grounds"],
            ["name"=>"Maintenance Repairs"],
            ["name"=>"Manager"],
            ["name"=>"None"],
            ["name"=>"Other"],
            ["name"=>"Pest Control"],
            ["name"=>"Pool Maintenance"],
            ["name"=>"Private Road"],
            ["name"=>"Recreational Facilities"],
            ["name"=>"Security"],
            ["name"=>"Sewer"],
            ["name"=>"Trash"],
            ["name"=>"Water"],
            ["name"=>"Or N/A"],
        ]);
    }
}

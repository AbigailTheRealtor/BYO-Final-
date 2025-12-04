<?php

namespace Database\Seeders;

use App\Models\WaterExtra;
use Illuminate\Database\Seeder;

class WaterExtraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        WaterExtra::insert([
            ["name"=>"Assigned Boat Slip"],
            ["name"=>"Boat Port"],
            ["name"=>"Boat Ramp - Private"],
            ["name"=>"Boathouse"],
            ["name"=>"Boats - None Allowed"],
            ["name"=>"Bridges - Fixed"],
            ["name"=>"Bridges - No Fixed Bridges"],
            ["name"=>"Davits"],
            ["name"=>"Dock - Composite"],
            ["name"=>"Dock - Concrete"],
            ["name"=>"Dock - Covered"],
            ["name"=>"Dock - Open"],
            ["name"=>"Dock - Slip 1st Come"],
            ["name"=>"Dock - Slip Deeded Off-Site"],
            ["name"=>"Dock - Slip Deeded On-Site"],
            ["name"=>"Dock - Wood"],
            ["name"=>"Dock w/Electric"],
            ["name"=>"Dock w/o Electric"],
            ["name"=>"Dock w/o Water Supply"],
            ["name"=>"Dock w/Water Supply"],
            ["name"=>"Fishing Pier"],
            ["name"=>"Lift"],
            ["name"=>"Lift - Covered"],
            ["name"=>"Lock"],
            ["name"=>"Minimum Wake Zone"],
            ["name"=>"No Wake Zone"],
            ["name"=>"Powerboats – None Allowed"],
            ["name"=>"Private Lake Dues Required"],
            ["name"=>"Riprap"],
            ["name"=>"Sailboat Water"],
            ["name"=>"Seawall - Concrete"],
            ["name"=>"Seawall - Other"],
            ["name"=>"Skiing Allowed"],
        ]);
    }
}

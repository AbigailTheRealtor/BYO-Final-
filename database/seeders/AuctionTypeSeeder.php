<?php

namespace Database\Seeders;

use App\Models\AuctionType;
use Illuminate\Database\Seeder;

class AuctionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AuctionType::insert([
            ['name'=>"Seller's Property"],
            ['name'=>"Buyer's Criteria"],
            ['name'=>"Agent Service Needed"],
            ['name'=>"Hire Seller's Agent"],
            ['name'=>"Hire Buyer's Agent"],
        ]);
    }
}

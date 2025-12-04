<?php

namespace Database\Seeders;

use App\Models\AgentService;
use Illuminate\Database\Seeder;

class AgentServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AgentService::insert([
            ["sort" => 0, "name"=>"Buyer’s Agent (Referral)"],
            ["sort" => 1, "name"=>"Seller’s Agent (Referral)"],
            ["sort" => 2, "name"=>"Limited Listing on the MLS & this platform -$"],
            ["sort" => 3, "name"=>"Broker Opinion of Value -$"],
            ["sort" => 4, "name"=>"Arrange for appropriate vendors for an agent: Professional Photography, Videography, Appraiser, Home Staging, Home Inspector, Licensed Professional, Handyman, Title Company, or other. -$"],
            ["sort" => 5, "name"=>"Meeting Professional Photographer, Videographer, Appraiser, Home Stager, Home Inspector, Licensed Professional, Handyman, or other vendors. -$"],
            ["sort" => 6, "name"=>"Transaction management (Writing and sending out contracts, disclosures, and addendums)-$"],
            ["sort" => 7, "name"=>"Meeting a Buyer for one showing -$"],
            ["sort" => 8, "name"=>"Meeting a Buyer for multiple showings (price per showing) -$"],
            ["sort" => 9, "name"=>"Meeting a Seller"],
            ["sort" => 10, "name"=>"Hosting an Open House -$"],
            ["sort" => 11, "name"=>"Video Tour"],
            ["sort" => 12, "name"=>"Pictures tour"],
            ["sort" => 13, "name"=>"Other – Agent can manually enter the service they offer"],
        ]);
    }
}

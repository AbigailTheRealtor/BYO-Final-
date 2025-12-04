<?php

namespace Database\Seeders;

use App\Models\SellerService;
use Illuminate\Database\Seeder;

class SellerServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SellerService::insert([
            ['sort' => 0, 'name' => 'Limited Listing on the MLS & this platform'],
            ['sort' => 1, 'name' => 'Broker’s Opinion of Value'],
            ['sort' => 2, 'name' => 'Taking phone calls on the listing to answer questions for Seller'],
            ['sort' => 3, 'name' => 'Schedule showings for Seller on a listing'],
            ['sort' => 4, 'name' => 'Contract negotiation- Agent will step in to negotiate for Seller when seller has received an offer on a property.'],
            ['sort' => 5, 'name' => 'Arrange for appropriate vendors for a Seller: Professional Photography, Videography, Appraiser, Home Staging, Home Inspector, Licensed Professional, Handyman, Title Company, or other.'],
            ['sort' => 6, 'name' => 'Meeting Professional Photographer, Videographer, Appraiser, Home Stager, Home Inspector, Licensed Professional, Handyman, or other vendors.'],
            ['sort' => 7, 'name' => 'Transaction management (Writing and sending out contracts, disclosures, and addendums)'],
            ['sort' => 8, 'name' => 'Meeting a Buyer for one showing'],
            ['sort' => 9, 'name' => 'Meeting a Buyer for multiple showings'],
            ['sort' => 10, 'name' => 'Hosting an Open House'],
            ['sort' => 11, 'name' => 'Other –Seller should be able to manually enter the service they need'],
        ]);
    }
}

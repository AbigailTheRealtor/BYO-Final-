<?php

namespace Database\Seeders;

use App\Models\Financing;
use Illuminate\Database\Seeder;

class FinancingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /* ["name"=>"Assumable"],
            ["name"=>"Cash"],
            ["name"=>"Conventional"],
            ["name"=>"Exchange/Trade"],
            ["name"=>"FHA"],
            ["name"=>"Lease Option"],
            ["name"=>"Lease Purchase"],
            ["name"=>"Other"],
            ["name"=>"Private Financing Available"],
            ["name"=>"Special Funding"],
            ["name"=>"USDA Loan"],
            ["name"=>"VA Loan"],
            ["name"=>"Non-QM"],
            ["name"=>"Jumbo"] */
        Financing::insert([
            ["name"=>"Cash", 'sort' => 0 ],
            ["name"=>"Conventional", 'sort' => 1 ],
            ["name"=>"FHA", 'sort' => 2 ],
            ["name"=>"Jumbo", 'sort' => 3 ],
            ["name"=>"VA", 'sort' => 4 ],
            ["name"=>"USDA", 'sort' => 5 ],
            ["name"=>"No-Doc", 'sort' => 6 ],
            ["name"=>"Non-QM", 'sort' => 7 ],
            ["name"=>"Other", 'sort' => 8 ],
            ["name"=>"Crypto", 'sort' => 9 ],
            ["name"=>"Crypto currency", 'sort' => 10 ],
            ["name"=>"Bitcoin (BTC)", 'sort' => 11 ],
            ["name"=>"Ethereum (ETH)", 'sort' => 12 ],
            ["name"=>"Litecoin (LTC)", 'sort' => 13 ],
            ["name"=>"Bitcoin Cash (BCH)", 'sort' => 14 ],
            ["name"=>"Dash (DASH)", 'sort' => 15 ],
            ["name"=>"Ripple (XRP)", 'sort' => 16 ],
            ["name"=>"Tether (USDT)", 'sort' => 17 ],
            ["name"=>"USD Coin (USDC)", 'sort' => 18 ],
            ["name"=>"NFT", 'sort' => 19 ],
        ]);
    }
}

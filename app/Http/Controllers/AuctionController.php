<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function addListing(Request $request)
    {
        return view('add_listing');
    }
}

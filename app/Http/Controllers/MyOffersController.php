<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Support\Facades\Auth;

class MyOffersController extends Controller
{
    public function index()
    {
        $offers = Offer::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('offers.index', compact('offers'));
    }
}

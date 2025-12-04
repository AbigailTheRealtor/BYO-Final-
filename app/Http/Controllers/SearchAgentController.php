<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class SearchAgentController extends Controller
{
    public function search(Request $request)
    {
        $page_data['title'] = "Search Agents";
        $agents = User::where('user_type', 'agent');
        $page_data['count'] = $agents->count();
        $page_data['agents'] = $agents->paginate(12);
        return view('search-agents', $page_data);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SellerController extends Controller
{
    public function seller()
    {
        $page_data['title'] = "Seller";
        $page_data['users'] = User::where('user_type', 'seller')->get();
        return view('admin.seller', $page_data);
    }

    public function store(Request $request)
    {
        $user = new User();
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->name = $request->first_name.' '.$request->last_name;
        $user->user_name = $request->user_name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->user_type = 'seller';
        $user->is_approved = $request->status;
        if($user->save())
        {
            return redirect()->back()->with('success', 'Seller has been added!');
        } else {
            return redirect()->back()->with('error', 'Upable to add seller!');
        }
    }

    public function update(Request $request)
    {
        $user = User::find($request->id);
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->name = $request->first_name.' '.$request->last_name;
        $user->user_name = $request->user_name;
        $user->email = $request->email;
        if($request->password != ""):
        $user->password = Hash::make($request->password);
        endif;
        $user->user_type = 'seller';
        $user->is_approved = $request->status;
        if($user->update())
        {
            return redirect()->back()->with('success', 'Seller has been updated!');
        } else {
            return redirect()->back()->with('error', 'Unable to update seller!');
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if($user->delete())
        {
            return redirect()->back()->with('success', 'Seller has been deleted');
        } else {
            return redirect()->back()->with('error', 'Unable to delete seller');
        }
    }
}

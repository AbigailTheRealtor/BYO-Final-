<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BuyerController extends Controller
{
    public function buyer()
    {
        $page_data['title'] = "Buyer";
        $page_data['users'] = User::where('user_type', 'buyer')->get();
        return view('admin.buyer', $page_data);
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
        $user->user_type = 'buyer';
        $user->is_approved = $request->status;
        if($user->save())
        {
            return redirect()->back()->with('success', 'Buyer has been added!');
        } else {
            return redirect()->back()->with('error', 'Unable to add buyer!');
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
        $user->user_type = 'buyer';
        $user->is_approved = $request->status;
        if($user->update())
        {
            return redirect()->back()->with('success', 'Buyer has been updated!');
        } else {
            return redirect()->back()->with('error', 'Unable to update buyer!');
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if($user->delete())
        {
            return redirect()->back()->with('success', 'Buyer has been deleted');
        } else {
            return redirect()->back()->with('error', 'Unable to delete buyer');
        }
    }
}

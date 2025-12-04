<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAgentController extends Controller
{
    public function Agent()
    {
        $page_data['title'] = "Agent";
        $page_data['users'] = User::where('user_type', 'agent')->get();
        return view('admin.agent', $page_data);
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
        $user->user_type = 'agent';
        $user->is_approved = $request->status;
        if($user->save())
        {
            return redirect()->back()->with('success', 'Agent has been added!');
        } else {
            return redirect()->back()->with('error', 'Unable to add agent!');
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
        $user->user_type = 'agent';
        $user->is_approved = $request->status;
        if($user->update())
        {
            return redirect()->back()->with('success', 'Agent has been updated!');
        } else {
            return redirect()->back()->with('error', 'Unable to update agent!');
        }
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if($user->delete())
        {
            return redirect()->back()->with('success', 'Agent has been deleted');
        } else {
            return redirect()->back()->with('error', 'Unable to delete agent');
        }
    }
}

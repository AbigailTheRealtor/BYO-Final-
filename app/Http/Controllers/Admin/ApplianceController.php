<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appliance;
use Illuminate\Http\Request;

class ApplianceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Appliances";
        $page_data['counties'] = Appliance::all();
        return view('admin.appliances', $page_data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        $appliance = new Appliance();
        $appliance->name = $request->name;
        if($appliance->save())
        {
            return redirect()->back()->with('success', 'Appliance added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Appliance!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Appliance  $appliance
     * @return \Illuminate\Http\Response
     */
    public function show(Appliance $appliance)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Appliance  $appliance
     * @return \Illuminate\Http\Response
     */
    public function edit(Appliance $appliance)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Appliance  $appliance
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Appliance $appliance)
    {
        $request->validate(['name' => 'required']);
        $appliance->name = $request->name;
        if($appliance->update())
        {
            return redirect()->back()->with('success', 'Appliance updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Appliance!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Appliance  $appliance
     * @return \Illuminate\Http\Response
     */
    public function destroy(Appliance $appliance)
    {
        if($appliance->delete())
        {
            return redirect()->back()->with('success', 'Appliance deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Appliance!');
        }
    }
}

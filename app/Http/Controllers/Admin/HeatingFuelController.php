<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeatingFuel;
use Illuminate\Http\Request;

class HeatingFuelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Heating Fuels";
        $page_data['counties'] = HeatingFuel::all();
        return view('admin.heating-fuels', $page_data);
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
        $heatingFuel = new HeatingFuel();
        $heatingFuel->name = $request->name;
        if($heatingFuel->save())
        {
            return redirect()->back()->with('success', 'Heating Fuel added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Heating Fuel!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\HeatingFuel  $heatingFuel
     * @return \Illuminate\Http\Response
     */
    public function show(HeatingFuel $heatingFuel)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\HeatingFuel  $heatingFuel
     * @return \Illuminate\Http\Response
     */
    public function edit(HeatingFuel $heatingFuel)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\HeatingFuel  $heatingFuel
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, HeatingFuel $heatingFuel)
    {
        $request->validate(['name' => 'required']);
        $heatingFuel->name = $request->name;
        if($heatingFuel->update())
        {
            return redirect()->back()->with('success', 'Heating Fuel updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Heating Fuel!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HeatingFuel  $heatingFuel
     * @return \Illuminate\Http\Response
     */
    public function destroy(HeatingFuel $heatingFuel)
    {
        if($heatingFuel->delete())
        {
            return redirect()->back()->with('success', 'Heating Fuel deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Heating Fuel!');
        }
    }
}

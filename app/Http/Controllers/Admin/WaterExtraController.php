<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaterExtra;
use Illuminate\Http\Request;

class WaterExtraController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Water Extras";
        $page_data['counties'] = WaterExtra::all();
        return view('admin.water-extras', $page_data);
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
        $waterExtra = new WaterExtra();
        $waterExtra->name = $request->name;
        if($waterExtra->save())
        {
            return redirect()->back()->with('success', 'Water Extra added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Water Extra!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WaterExtra  $waterExtra
     * @return \Illuminate\Http\Response
     */
    public function show(WaterExtra $waterExtra)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WaterExtra  $waterExtra
     * @return \Illuminate\Http\Response
     */
    public function edit(WaterExtra $waterExtra)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WaterExtra  $waterExtra
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WaterExtra $waterExtra)
    {
        $request->validate(['name' => 'required']);
        $waterExtra->name = $request->name;
        if($waterExtra->update())
        {
            return redirect()->back()->with('success', 'Water Extra updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Water Extra!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WaterExtra  $waterExtra
     * @return \Illuminate\Http\Response
     */
    public function destroy(WaterExtra $waterExtra)
    {
        if($waterExtra->delete())
        {
            return redirect()->back()->with('success', 'Water Extra deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Water Extra!');
        }
    }
}

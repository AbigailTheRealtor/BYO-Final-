<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AirConditioningType;
use Illuminate\Http\Request;

class ACTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Air Conditioning Types";
        $page_data['counties'] = AirConditioningType::all();
        return view('admin.ac-types', $page_data);
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
        $airConditioningType = new AirConditioningType();
        $airConditioningType->name = $request->name;
        if($airConditioningType->save())
        {
            return redirect()->back()->with('success', 'Air Conditioning Type added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Air Conditioning Type!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AirConditioningType  $airConditioningType
     * @return \Illuminate\Http\Response
     */
    public function show(AirConditioningType $airConditioningType)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AirConditioningType  $airConditioningType
     * @return \Illuminate\Http\Response
     */
    public function edit(AirConditioningType $airConditioningType)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AirConditioningType  $airConditioningType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AirConditioningType $airConditioningType)
    {
        $request->validate(['name' => 'required']);
        $airConditioningType->name = $request->name;
        if($airConditioningType->update())
        {
            return redirect()->back()->with('success', 'Air Conditioning Type updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Air Conditioning Type!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AirConditioningType  $airConditioningType
     * @return \Illuminate\Http\Response
     */
    public function destroy(AirConditioningType $airConditioningType)
    {
        if($airConditioningType->delete())
        {
            return redirect()->back()->with('success', 'Air Conditioning Type deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Air Conditioning Type!');
        }
    }
}

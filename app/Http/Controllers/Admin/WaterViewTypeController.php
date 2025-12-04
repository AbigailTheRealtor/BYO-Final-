<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaterViewType;
use Illuminate\Http\Request;

class WaterViewTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Water Views";
        $page_data['counties'] = WaterViewType::all();
        return view('admin.water-views', $page_data);
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
        $waterViewType = new WaterViewType();
        $waterViewType->name = $request->name;
        if($waterViewType->save())
        {
            return redirect()->back()->with('success', 'Water View added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Water View!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WaterViewType  $waterViewType
     * @return \Illuminate\Http\Response
     */
    public function show(WaterViewType $waterViewType)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WaterViewType  $waterViewType
     * @return \Illuminate\Http\Response
     */
    public function edit(WaterViewType $waterViewType)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WaterViewType  $waterViewType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WaterViewType $waterViewType)
    {
        $request->validate(['name' => 'required']);
        $waterViewType->name = $request->name;
        if($waterViewType->update())
        {
            return redirect()->back()->with('success', 'Water View updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Water View!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WaterViewType  $waterViewType
     * @return \Illuminate\Http\Response
     */
    public function destroy(WaterViewType $waterViewType)
    {
        if($waterViewType->delete())
        {
            return redirect()->back()->with('success', 'Water View deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Water View!');
        }
    }
}

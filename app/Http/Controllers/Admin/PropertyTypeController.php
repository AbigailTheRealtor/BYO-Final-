<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyType;
use Illuminate\Http\Request;

class PropertyTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Property Types";
        $page_data['counties'] = PropertyType::orderBy('sort', 'asc')->get();
        return view('admin.property-types', $page_data);
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
        $propertyType = new PropertyType();
        $propertyType->name = $request->name;
        $propertyType->sort = $request->sort;
        if($propertyType->save())
        {
            return redirect()->back()->with('success', 'Property Type added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Property Type!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PropertyType  $propertyType
     * @return \Illuminate\Http\Response
     */
    public function show(PropertyType $propertyType)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PropertyType  $propertyType
     * @return \Illuminate\Http\Response
     */
    public function edit(PropertyType $propertyType)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PropertyType  $propertyType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PropertyType $propertyType)
    {
        $request->validate(['name' => 'required']);
        $propertyType->name = $request->name;
        $propertyType->sort = $request->sort;
        if($propertyType->update())
        {
            return redirect()->back()->with('success', 'Property Type updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Property Type!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PropertyType  $propertyType
     * @return \Illuminate\Http\Response
     */
    public function destroy(PropertyType $propertyType)
    {
        if($propertyType->delete())
        {
            return redirect()->back()->with('success', 'Property Type deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Property Type!');
        }
    }
}

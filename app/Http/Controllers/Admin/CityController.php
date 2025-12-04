<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Cities";
        $page_data['cities'] = City::where('state_id','3930')->get();
        $page_data['states'] = State::where('country_id','231')->where('id','3930')->get();
        return view('admin.cities', $page_data);
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
        $request->validate([
            'state_id' => 'required',
            'name' => 'required'
        ],[
            'state_id.required' => 'State is required!',
            'name.required' => 'City name is required!',
        ]);
        $city = new City();
        $city->state_id = $request->state_id;
        $city->name = $request->name;
        $city->save();
        return redirect()->back()->with('success', 'City saved successfully!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\City  $city
     * @return \Illuminate\Http\Response
     */
    public function show(City $city)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\City  $city
     * @return \Illuminate\Http\Response
     */
    public function edit(City $city)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\City  $city
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, City $city)
    {
        $request->validate([
            'state_id' => 'required',
            'name' => 'required'
        ],[
            'state_id.required' => 'State is required!',
            'name.required' => 'City name is required!',
        ]);
        $city->state_id = $request->state_id;
        $city->name = $request->name;
        $city->update();
        return redirect()->back()->with('success', 'City updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\City  $city
     * @return \Illuminate\Http\Response
     */
    public function destroy(City $city)
    {
        $city->delete();
        return redirect()->back()->with('success', 'City deleted successfully');
    }
}

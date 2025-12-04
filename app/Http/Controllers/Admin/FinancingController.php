<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financing;
use Illuminate\Http\Request;

class FinancingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Financings";
        $page_data['counties'] = Financing::orderBy('sort', 'asc')->get();
        return view('admin.financings', $page_data);
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
        $financing = new Financing();
        $financing->name = $request->name;
        $financing->sort = $request->sort;
        if($financing->save())
        {
            return redirect()->back()->with('success', 'Financing added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Financing!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Financing  $financing
     * @return \Illuminate\Http\Response
     */
    public function show(Financing $financing)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Financing  $financing
     * @return \Illuminate\Http\Response
     */
    public function edit(Financing $financing)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Financing  $financing
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Financing $financing)
    {
        $request->validate(['name' => 'required']);
        $financing->name = $request->name;
        $financing->sort = $request->sort;
        if($financing->update())
        {
            return redirect()->back()->with('success', 'Financing updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Financing!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Financing  $financing
     * @return \Illuminate\Http\Response
     */
    public function destroy(Financing $financing)
    {
        if($financing->delete())
        {
            return redirect()->back()->with('success', 'Financing deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Financing!');
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeInclude;
use Illuminate\Http\Request;

class FeeIncludeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Fee Includes";
        $page_data['counties'] = FeeInclude::all();
        return view('admin.fee-includes', $page_data);
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
        $feeInclude = new FeeInclude();
        $feeInclude->name = $request->name;
        if($feeInclude->save())
        {
            return redirect()->back()->with('success', 'Fee Include added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Fee Include!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FeeInclude  $feeInclude
     * @return \Illuminate\Http\Response
     */
    public function show(FeeInclude $feeInclude)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FeeInclude  $feeInclude
     * @return \Illuminate\Http\Response
     */
    public function edit(FeeInclude $feeInclude)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FeeInclude  $feeInclude
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FeeInclude $feeInclude)
    {
        $request->validate(['name' => 'required']);
        $feeInclude->name = $request->name;
        if($feeInclude->update())
        {
            return redirect()->back()->with('success', 'Fee Include updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update Fee Include!');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FeeInclude  $feeInclude
     * @return \Illuminate\Http\Response
     */
    public function destroy(FeeInclude $feeInclude)
    {
        if($feeInclude->delete())
        {
            return redirect()->back()->with('success', 'Fee Include deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Fee Include!');
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerService;
use Illuminate\Http\Request;

class SellerServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Seller Services";
        $page_data['counties'] = SellerService::orderBy('sort', 'asc')->get();
        return view('admin.seller-services', $page_data);
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
        $sellerService = new SellerService();
        $sellerService->name = $request->name;
        $sellerService->sort = $request->sort;
        if($sellerService->save())
        {
            return redirect()->back()->with('success', 'Seller service added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add Seller service!');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\SellerService  $sellerService
     * @return \Illuminate\Http\Response
     */
    public function show(SellerService $sellerService)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\SellerService  $sellerService
     * @return \Illuminate\Http\Response
     */
    public function edit(SellerService $sellerService)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\SellerService  $sellerService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, SellerService $sellerService)
    {
        $request->validate(['name' => 'required']);
        $sellerService->name = $request->name;
        $sellerService->sort = $request->sort;
        if($sellerService->update())
        {
            return redirect()->back()->with('success', 'Seller service updated successfully');
        } else {
            return redirect()->back()->with('error', 'Unable to update Seller service');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SellerService  $sellerService
     * @return \Illuminate\Http\Response
     */
    public function destroy(SellerService $sellerService)
    {
        if($sellerService->delete())
        {
            return redirect()->back()->with('success', 'Seller service deleted successfully');
        } else {
            return redirect()->back()->with('error', 'Unable to delete Seller service');
        }
    }
}

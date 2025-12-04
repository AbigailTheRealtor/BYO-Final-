<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentService;
use Illuminate\Http\Request;

class AgentServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Agent Services";
        $page_data['counties'] = AgentService::orderBy('sort', 'asc')->get();
        return view('admin.agent-services', $page_data);
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
        $agentService = new AgentService();
        $agentService->name = $request->name;
        $agentService->sort = $request->sort;
        $agentService->save();
        return redirect()->back()->with('success', 'Agent Service saved successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AgentService  $agentService
     * @return \Illuminate\Http\Response
     */
    public function show(AgentService $agentService)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AgentService  $agentService
     * @return \Illuminate\Http\Response
     */
    public function edit(AgentService $agentService)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AgentService  $agentService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AgentService $agentService)
    {
        $request->validate(['name' => 'required']);
        $agentService->name = $request->name;
        $agentService->sort = $request->sort;
        if($agentService->update()) {
            return redirect()->back()->with('success', 'Agent Service updated successfully');
        } else {
            return redirect()->back()->with('error', 'Unable to update Agent Service');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AgentService  $agentService
     * @return \Illuminate\Http\Response
     */
    public function destroy(AgentService $agentService)
    {
        $agentService->delete();
        return redirect()->back()->with('success', 'Agent Service deleted successfully');
    }
}

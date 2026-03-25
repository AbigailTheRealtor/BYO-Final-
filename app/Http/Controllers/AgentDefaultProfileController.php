<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AgentDefaultProfile;

class AgentDefaultProfileController extends Controller
{
    public function index()
    {
        $profiles = AgentDefaultProfile::where('user_id', Auth::id())
            ->orderBy('role_type')
            ->orderBy('property_type')
            ->get();

        return view('agent.default-profiles.index', compact('profiles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_type'     => 'required|in:tenant,landlord,seller,buyer',
            'property_type' => 'required|string',
        ]);

        $fields = $request->only([
            'bio', 'why_hire_you', 'what_sets_you_apart', 'marketing_plan',
            'reviews_links', 'website_link', 'social_media',
            'additional_details',
            'first_name', 'last_name', 'phone', 'email',
            'brokerage', 'license_no', 'nar_id', 'year_licensed',
        ]);

        AgentDefaultProfile::upsertForAgent(
            Auth::id(),
            $request->role_type,
            $request->property_type,
            $fields
        );

        return back()->with('success', 'Default profile saved successfully.');
    }

    public function destroy($id)
    {
        $profile = AgentDefaultProfile::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $profile->delete();

        return back()->with('success', 'Default profile deleted.');
    }

    public function load(Request $request)
    {
        $request->validate([
            'role_type'     => 'required|in:tenant,landlord,seller,buyer',
            'property_type' => 'required|string',
        ]);

        $profile = AgentDefaultProfile::findForAgent(
            Auth::id(),
            $request->role_type,
            $request->property_type
        );

        if (!$profile) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'data'   => $profile->profile_data,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyDnaProfile;
use App\Models\BuyerTenantDnaProfile;
use App\Services\Dna\PropertyDnaExplanationService;
use App\Services\Dna\BuyerTenantDnaExplanationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DnaProfileController extends Controller
{
    public function seller($listingId, PropertyDnaExplanationService $explanationService)
    {
        $profile = PropertyDnaProfile::where('listing_type', 'seller')
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->orderByDesc('version')
            ->first();

        Log::info('DNA Profile: seller profile page accessed', [
            'admin_user_id' => Auth::id(),
            'listing_id'    => $listingId,
            'profile_found' => $profile !== null,
        ]);

        $explanations = $profile ? $explanationService->generate($profile) : null;

        return response()
            ->view('admin.dna.seller', compact('profile', 'explanations', 'listingId'))
            ->header('Cache-Control', 'no-store');
    }

    public function landlord($listingId, PropertyDnaExplanationService $explanationService)
    {
        $profile = PropertyDnaProfile::where('listing_type', 'landlord')
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->orderByDesc('version')
            ->first();

        Log::info('DNA Profile: landlord profile page accessed', [
            'admin_user_id' => Auth::id(),
            'listing_id'    => $listingId,
            'profile_found' => $profile !== null,
        ]);

        $explanations = $profile ? $explanationService->generate($profile) : null;

        return response()
            ->view('admin.dna.landlord', compact('profile', 'explanations', 'listingId'))
            ->header('Cache-Control', 'no-store');
    }

    public function buyer($listingId, BuyerTenantDnaExplanationService $explanationService)
    {
        $profile = BuyerTenantDnaProfile::where('listing_type', 'buyer')
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->orderByDesc('version')
            ->first();

        Log::info('DNA Profile: buyer profile page accessed', [
            'admin_user_id' => Auth::id(),
            'listing_id'    => $listingId,
            'profile_found' => $profile !== null,
        ]);

        $explanations = $profile ? $explanationService->generate($profile) : null;

        return response()
            ->view('admin.dna.buyer', compact('profile', 'explanations', 'listingId'))
            ->header('Cache-Control', 'no-store');
    }

    public function tenant($listingId, BuyerTenantDnaExplanationService $explanationService)
    {
        $profile = BuyerTenantDnaProfile::where('listing_type', 'tenant')
            ->where('listing_id', $listingId)
            ->whereNull('archived_at')
            ->orderByDesc('version')
            ->first();

        Log::info('DNA Profile: tenant profile page accessed', [
            'admin_user_id' => Auth::id(),
            'listing_id'    => $listingId,
            'profile_found' => $profile !== null,
        ]);

        $explanations = $profile ? $explanationService->generate($profile) : null;

        return response()
            ->view('admin.dna.tenant', compact('profile', 'explanations', 'listingId'))
            ->header('Cache-Control', 'no-store');
    }
}

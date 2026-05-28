<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyDnaProfile;
use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DnaInspectorController extends Controller
{
    public function propertyIndex(Request $request)
    {
        $filters = $request->only([
            'listing_type',
            'listing_id',
            'version',
            'archived_at',
            'computed_at_from',
            'computed_at_to',
        ]);

        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        Log::info('DNA Inspector: property_dna_profiles index accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'property_dna_profiles',
            'filters'       => $activeFilters,
        ]);

        $query = PropertyDnaProfile::select([
            'id',
            'listing_type',
            'listing_id',
            'version',
            'source_listing_updated_at',
            'computed_at',
            'archived_at',
            'physical_score',
            'financial_score',
            'flexibility_score',
            'occupant_qualification_score',
            'marketing_score',
            'commercial_score',
            'overall_dna_completeness',
            'location_score',
            'condition_score',
            'legal_score',
            'compatibility_score',
            'walk_score',
            'transit_score',
            'bike_score',
            'school_rating',
            'flood_zone_verified',
            'estimated_monthly_utilities',
        ]);

        if (!empty($filters['listing_type'])) {
            $query->where('listing_type', $filters['listing_type']);
        }
        if (!empty($filters['listing_id'])) {
            $query->where('listing_id', $filters['listing_id']);
        }
        if (!empty($filters['version'])) {
            $query->where('version', $filters['version']);
        }
        if (isset($filters['archived_at'])) {
            if ($filters['archived_at'] === 'current') {
                $query->whereNull('archived_at');
            } elseif ($filters['archived_at'] === 'archived') {
                $query->whereNotNull('archived_at');
            }
        } else {
            $query->whereNull('archived_at');
        }
        if (!empty($filters['computed_at_from'])) {
            $query->where('computed_at', '>=', $filters['computed_at_from']);
        }
        if (!empty($filters['computed_at_to'])) {
            $query->where('computed_at', '<=', $filters['computed_at_to']);
        }

        $rows = $query->orderByDesc('computed_at')->paginate(25)->withQueryString();

        $versionCounts = PropertyDnaProfile::selectRaw('listing_type, listing_id, COUNT(*) as version_count')
            ->groupBy('listing_type', 'listing_id')
            ->get()
            ->keyBy(fn($r) => $r->listing_type . ':' . $r->listing_id);

        return response()
            ->view('admin.dna.property.index', compact('rows', 'versionCounts', 'filters'))
            ->header('Cache-Control', 'no-store');
    }

    public function propertyShow(Request $request, $id)
    {
        $pivot = PropertyDnaProfile::select(['listing_type', 'listing_id'])->findOrFail($id);

        Log::info('DNA Inspector: property_dna_profiles record accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'property_dna_profiles',
            'filters'       => [
                'listing_type' => $pivot->listing_type,
                'listing_id'   => $pivot->listing_id,
            ],
        ]);

        $allVersions = PropertyDnaProfile::select([
            'id',
            'listing_type',
            'listing_id',
            'version',
            'source_listing_updated_at',
            'computed_at',
            'archived_at',
            'physical_score',
            'financial_score',
            'flexibility_score',
            'occupant_qualification_score',
            'marketing_score',
            'commercial_score',
            'overall_dna_completeness',
            'location_score',
            'condition_score',
            'legal_score',
            'compatibility_score',
            'walk_score',
            'transit_score',
            'bike_score',
            'school_rating',
            'flood_zone_verified',
            'estimated_monthly_utilities',
        ])
            ->where('listing_type', $pivot->listing_type)
            ->where('listing_id', $pivot->listing_id)
            ->orderByDesc('version')
            ->get();

        $current  = $allVersions->firstWhere('archived_at', null);
        $archived = $allVersions->filter(fn($r) => $r->archived_at !== null)->values();

        return response()
            ->view('admin.dna.property.show', compact('current', 'archived', 'allVersions'))
            ->header('Cache-Control', 'no-store');
    }

    public function demandIndex(Request $request)
    {
        $filters = $request->only([
            'listing_type',
            'listing_id',
            'version',
            'archived_at',
            'computed_at_from',
            'computed_at_to',
        ]);

        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        Log::info('DNA Inspector: buyer_tenant_dna_profiles index accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'buyer_tenant_dna_profiles',
            'filters'       => $activeFilters,
        ]);

        $query = BuyerTenantDnaProfile::select([
            'id',
            'listing_type',
            'listing_id',
            'version',
            'source_listing_updated_at',
            'computed_at',
            'archived_at',
            'preference_completeness',
            'lifestyle_tags',
            'deal_breaker_flags',
            'commute_polygon_cache',
        ]);

        if (!empty($filters['listing_type'])) {
            $query->where('listing_type', $filters['listing_type']);
        }
        if (!empty($filters['listing_id'])) {
            $query->where('listing_id', $filters['listing_id']);
        }
        if (!empty($filters['version'])) {
            $query->where('version', $filters['version']);
        }
        if (isset($filters['archived_at'])) {
            if ($filters['archived_at'] === 'current') {
                $query->whereNull('archived_at');
            } elseif ($filters['archived_at'] === 'archived') {
                $query->whereNotNull('archived_at');
            }
        } else {
            $query->whereNull('archived_at');
        }
        if (!empty($filters['computed_at_from'])) {
            $query->where('computed_at', '>=', $filters['computed_at_from']);
        }
        if (!empty($filters['computed_at_to'])) {
            $query->where('computed_at', '<=', $filters['computed_at_to']);
        }

        $rows = $query->orderByDesc('computed_at')->paginate(25)->withQueryString();

        $versionCounts = BuyerTenantDnaProfile::selectRaw('listing_type, listing_id, COUNT(*) as version_count')
            ->groupBy('listing_type', 'listing_id')
            ->get()
            ->keyBy(fn($r) => $r->listing_type . ':' . $r->listing_id);

        return response()
            ->view('admin.dna.demand.index', compact('rows', 'versionCounts', 'filters'))
            ->header('Cache-Control', 'no-store');
    }

    public function demandShow(Request $request, $id)
    {
        $pivot = BuyerTenantDnaProfile::select(['listing_type', 'listing_id'])->findOrFail($id);

        Log::info('DNA Inspector: buyer_tenant_dna_profiles record accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'buyer_tenant_dna_profiles',
            'filters'       => [
                'listing_type' => $pivot->listing_type,
                'listing_id'   => $pivot->listing_id,
            ],
        ]);

        $allVersions = BuyerTenantDnaProfile::select([
            'id',
            'listing_type',
            'listing_id',
            'version',
            'source_listing_updated_at',
            'computed_at',
            'archived_at',
            'preference_completeness',
            'lifestyle_tags',
            'deal_breaker_flags',
            'commute_polygon_cache',
        ])
            ->where('listing_type', $pivot->listing_type)
            ->where('listing_id', $pivot->listing_id)
            ->orderByDesc('version')
            ->get();

        $current  = $allVersions->firstWhere('archived_at', null);
        $archived = $allVersions->filter(fn($r) => $r->archived_at !== null)->values();

        return response()
            ->view('admin.dna.demand.show', compact('current', 'archived', 'allVersions'))
            ->header('Cache-Control', 'no-store');
    }

    public function scoresIndex(Request $request)
    {
        $filters = $request->only([
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
            'version',
            'archived_at',
            'computed_at_from',
            'computed_at_to',
            'deal_breaker_triggered',
        ]);

        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        Log::info('DNA Inspector: listing_compatibility_scores index accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'listing_compatibility_scores',
            'filters'       => $activeFilters,
        ]);

        $query = ListingCompatibilityScore::select([
            'id',
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
            'version',
            'scoring_framework_version',
            'demand_listing_updated_at_snapshot',
            'supply_listing_updated_at_snapshot',
            'computed_at',
            'archived_at',
            'overall_score',
            'physical_match_score',
            'financial_match_score',
            'location_match_score',
            'terms_match_score',
            'deal_breaker_triggered',
            'deal_breaker_flags',
        ]);

        if (!empty($filters['demand_listing_type'])) {
            $query->where('demand_listing_type', $filters['demand_listing_type']);
        }
        if (!empty($filters['demand_listing_id'])) {
            $query->where('demand_listing_id', $filters['demand_listing_id']);
        }
        if (!empty($filters['supply_listing_type'])) {
            $query->where('supply_listing_type', $filters['supply_listing_type']);
        }
        if (!empty($filters['supply_listing_id'])) {
            $query->where('supply_listing_id', $filters['supply_listing_id']);
        }
        if (!empty($filters['version'])) {
            $query->where('version', $filters['version']);
        }
        if (isset($filters['archived_at'])) {
            if ($filters['archived_at'] === 'current') {
                $query->whereNull('archived_at');
            } elseif ($filters['archived_at'] === 'archived') {
                $query->whereNotNull('archived_at');
            }
        } else {
            $query->whereNull('archived_at');
        }
        if (!empty($filters['computed_at_from'])) {
            $query->where('computed_at', '>=', $filters['computed_at_from']);
        }
        if (!empty($filters['computed_at_to'])) {
            $query->where('computed_at', '<=', $filters['computed_at_to']);
        }
        if ($filters['deal_breaker_triggered'] !== null && $filters['deal_breaker_triggered'] !== '') {
            $query->where('deal_breaker_triggered', (bool) $filters['deal_breaker_triggered']);
        }

        $rows = $query->orderByDesc('computed_at')->paginate(25)->withQueryString();

        $versionCounts = ListingCompatibilityScore::selectRaw(
            'demand_listing_type, demand_listing_id, supply_listing_type, supply_listing_id, COUNT(*) as version_count'
        )
            ->groupBy('demand_listing_type', 'demand_listing_id', 'supply_listing_type', 'supply_listing_id')
            ->get()
            ->keyBy(fn($r) => $r->demand_listing_type . ':' . $r->demand_listing_id . ':' . $r->supply_listing_type . ':' . $r->supply_listing_id);

        return response()
            ->view('admin.dna.scores.index', compact('rows', 'versionCounts', 'filters'))
            ->header('Cache-Control', 'no-store');
    }

    public function scoresShow(Request $request, $id)
    {
        $pivot = ListingCompatibilityScore::select([
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
        ])->findOrFail($id);

        Log::info('DNA Inspector: listing_compatibility_scores record accessed', [
            'admin_user_id' => Auth::id(),
            'table'         => 'listing_compatibility_scores',
            'filters'       => [
                'demand_listing_type' => $pivot->demand_listing_type,
                'demand_listing_id'   => $pivot->demand_listing_id,
                'supply_listing_type' => $pivot->supply_listing_type,
                'supply_listing_id'   => $pivot->supply_listing_id,
            ],
        ]);

        $allVersions = ListingCompatibilityScore::select([
            'id',
            'demand_listing_type',
            'demand_listing_id',
            'supply_listing_type',
            'supply_listing_id',
            'version',
            'scoring_framework_version',
            'demand_listing_updated_at_snapshot',
            'supply_listing_updated_at_snapshot',
            'computed_at',
            'archived_at',
            'overall_score',
            'physical_match_score',
            'financial_match_score',
            'location_match_score',
            'terms_match_score',
            'deal_breaker_triggered',
            'deal_breaker_flags',
        ])
            ->where('demand_listing_type', $pivot->demand_listing_type)
            ->where('demand_listing_id', $pivot->demand_listing_id)
            ->where('supply_listing_type', $pivot->supply_listing_type)
            ->where('supply_listing_id', $pivot->supply_listing_id)
            ->orderByDesc('version')
            ->get();

        $current  = $allVersions->firstWhere('archived_at', null);
        $archived = $allVersions->filter(fn($r) => $r->archived_at !== null)->values();

        return response()
            ->view('admin.dna.scores.show', compact('current', 'archived', 'allVersions'))
            ->header('Cache-Control', 'no-store');
    }
}

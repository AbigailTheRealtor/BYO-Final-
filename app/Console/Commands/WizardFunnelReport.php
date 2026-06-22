<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WizardFunnelReport extends Command
{
    protected $signature = 'wizard:funnel-report {--role= : Filter by role (seller/buyer/landlord/tenant)} {--days=30 : Number of days to look back}';

    protected $description = 'Display a wizard completion funnel report per listing role';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $roleFilter = $this->option('role');
        $since = now()->subDays($days);

        $validRoles = ['seller', 'buyer', 'landlord', 'tenant'];
        if ($roleFilter && !in_array($roleFilter, $validRoles, true)) {
            $this->error("Invalid role '{$roleFilter}'. Valid roles: " . implode(', ', $validRoles));
            return self::FAILURE;
        }

        $roles = $roleFilter ? [$roleFilter] : $validRoles;

        $this->info("Wizard Funnel Report — last {$days} days (since {$since->toDateString()})");
        $this->line('');

        foreach ($roles as $role) {
            $this->printRoleReport($role, $since);
        }

        return self::SUCCESS;
    }

    private function printRoleReport(string $role, \Carbon\Carbon $since): void
    {
        $baseQuery = DB::table('wizard_events')
            ->where('listing_role', $role)
            ->where('created_at', '>=', $since);

        $totalEvents = (clone $baseQuery)->count();

        if ($totalEvents === 0) {
            $this->line("<fg=yellow>[ {$role} ] — no events in this period.</>");
            $this->line('');
            return;
        }

        $uniqueListings = (clone $baseQuery)
            ->whereNotNull('listing_id')
            ->distinct('listing_id')
            ->count('listing_id');

        $uniqueUsers = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $draftCount = (clone $baseQuery)->where('event_type', 'save_draft')->count();
        $submitCount = (clone $baseQuery)->where('event_type', 'submit')->count();

        $listingsWithDraftOrSubmit = (clone $baseQuery)
            ->whereNotNull('listing_id')
            ->whereIn('event_type', ['save_draft', 'submit'])
            ->distinct('listing_id')
            ->count('listing_id');

        $listingsWithSubmit = (clone $baseQuery)
            ->where('event_type', 'submit')
            ->whereNotNull('listing_id')
            ->distinct('listing_id')
            ->count('listing_id');

        $conversionRate = $listingsWithDraftOrSubmit > 0
            ? round(($listingsWithSubmit / $listingsWithDraftOrSubmit) * 100, 1)
            : 0.0;

        $avgMinutes = null;
        $createIds = (clone $baseQuery)
            ->where('mode', 'create')
            ->where('event_type', 'submit')
            ->whereNotNull('listing_id')
            ->pluck('listing_id')
            ->unique();

        if ($createIds->isNotEmpty()) {
            $firstEvents = DB::table('wizard_events')
                ->where('listing_role', $role)
                ->where('mode', 'create')
                ->whereIn('listing_id', $createIds)
                ->select('listing_id', DB::raw('MIN(created_at) as first_at'))
                ->groupBy('listing_id')
                ->get()
                ->keyBy('listing_id');

            $lastEvents = DB::table('wizard_events')
                ->where('listing_role', $role)
                ->where('mode', 'create')
                ->where('event_type', 'submit')
                ->whereIn('listing_id', $createIds)
                ->select('listing_id', DB::raw('MAX(created_at) as last_at'))
                ->groupBy('listing_id')
                ->get()
                ->keyBy('listing_id');

            $durations = [];
            foreach ($createIds as $id) {
                if (isset($firstEvents[$id], $lastEvents[$id])) {
                    $start = strtotime($firstEvents[$id]->first_at);
                    $end   = strtotime($lastEvents[$id]->last_at);
                    if ($end > $start) {
                        $durations[] = ($end - $start) / 60;
                    }
                }
            }
            $avgMinutes = count($durations) > 0 ? round(array_sum($durations) / count($durations), 1) : null;
        }

        $this->line("<fg=cyan;options=bold>[ " . strtoupper($role) . " ]</>");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total events',             $totalEvents],
                ['Unique listings touched',  $uniqueListings],
                ['Unique users',             $uniqueUsers],
                ['Save-draft actions',       $draftCount],
                ['Submit actions',           $submitCount],
                ['Listing conversion (draft→submit)', $conversionRate . '%'],
                ['Avg min create→publish',   $avgMinutes !== null ? $avgMinutes . ' min' : 'n/a'],
            ]
        );

        $stepReach = (clone $baseQuery)
            ->where('event_type', 'tab_visited')
            ->select('tab_name', DB::raw('COUNT(*) as visits'), DB::raw('COUNT(DISTINCT listing_id) as listings'))
            ->groupBy('tab_name')
            ->orderByDesc('visits')
            ->get();

        if ($stepReach->isNotEmpty()) {
            $this->line('  Step reach counts:');
            $this->table(
                ['Tab', 'Total visits', 'Unique listings'],
                $stepReach->map(fn($r) => [$r->tab_name, $r->visits, $r->listings])->toArray()
            );
        }

        $this->line('');
    }
}

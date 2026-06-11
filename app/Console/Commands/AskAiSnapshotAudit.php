<?php

namespace App\Console\Commands;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AskAiSnapshotAudit extends Command
{
    protected $signature = 'ask-ai:snapshot-audit';

    protected $description = 'Audit Ask AI knowledge snapshots: missing, failed, stale, counts, phantom keys';

    private const ROLE_MODELS = [
        'seller'   => SellerAgentAuction::class,
        'buyer'    => BuyerAgentAuction::class,
        'landlord' => LandlordAgentAuction::class,
        'tenant'   => TenantAgentAuction::class,
    ];

    public function handle(): int
    {
        $this->info('=== Ask AI Snapshot Audit ===');
        $this->newLine();

        $this->auditMissingSnapshots();
        $this->auditFailedSnapshots();
        $this->auditStaleSnapshots();
        $this->auditCountsByRole();
        $this->auditLatestVersions();
        $this->auditPhantomKeys();

        $this->info('Audit complete.');
        return self::SUCCESS;
    }

    private function auditMissingSnapshots(): void
    {
        $this->info('--- Missing Snapshots ---');

        $missing = [];

        foreach (self::ROLE_MODELS as $role => $modelClass) {
            if ($role === 'tenant' && !Schema::hasTable((new $modelClass())->getTable())) {
                $this->warn("  [{$role}] Table not found — skipping.");
                continue;
            }

            $ids = $modelClass::pluck('id')->toArray();

            $snapshotted = AskAiKnowledgeSnapshot::where('listing_type', $role)
                ->whereIn('listing_id', $ids)
                ->pluck('listing_id')
                ->unique()
                ->toArray();

            $missingIds = array_diff($ids, $snapshotted);

            foreach ($missingIds as $id) {
                $missing[] = ['role' => $role, 'listing_id' => $id];
            }
        }

        if (empty($missing)) {
            $this->line('  All listings have at least one snapshot.');
        } else {
            $this->warn('  ' . count($missing) . ' listing(s) have no snapshot:');
            $this->table(['Role', 'Listing ID'], $missing);
        }

        $this->newLine();
    }

    private function auditFailedSnapshots(): void
    {
        $this->info('--- Failed Snapshots ---');

        $failed = AskAiKnowledgeSnapshot::where('status', 'failed')
            ->orderBy('listing_type')
            ->orderBy('listing_id')
            ->orderBy('version')
            ->get(['id', 'listing_type', 'listing_id', 'version', 'error_message', 'created_at'])
            ->map(fn($r) => [
                'id'           => $r->id,
                'role'         => $r->listing_type,
                'listing_id'   => $r->listing_id,
                'version'      => $r->version,
                'error'        => mb_strimwidth($r->error_message ?? '', 0, 80, '…'),
                'created_at'   => $r->created_at?->toDateTimeString(),
            ])
            ->toArray();

        if (empty($failed)) {
            $this->line('  No failed snapshots.');
        } else {
            $this->warn('  ' . count($failed) . ' failed snapshot(s):');
            $this->table(['ID', 'Role', 'Listing ID', 'Version', 'Error', 'Created At'], $failed);
        }

        $this->newLine();
    }

    private function auditStaleSnapshots(): void
    {
        $this->info('--- Stale Snapshots (listing updated_at > snapshot built_at) ---');

        $stale = [];

        foreach (self::ROLE_MODELS as $role => $modelClass) {
            if ($role === 'tenant' && !Schema::hasTable((new $modelClass())->getTable())) {
                continue;
            }

            $listings = $modelClass::whereNotNull('updated_at')
                ->get(['id', 'updated_at']);

            foreach ($listings as $listing) {
                $latest = AskAiKnowledgeSnapshot::where('listing_type', $role)
                    ->where('listing_id', $listing->id)
                    ->where('status', 'ready')
                    ->orderByDesc('version')
                    ->first(['built_at']);

                if ($latest && $latest->built_at && $listing->updated_at > $latest->built_at) {
                    $stale[] = [
                        'role'        => $role,
                        'listing_id'  => $listing->id,
                        'updated_at'  => $listing->updated_at->toDateTimeString(),
                        'built_at'    => $latest->built_at->toDateTimeString(),
                    ];
                }
            }
        }

        if (empty($stale)) {
            $this->line('  No stale snapshots found.');
        } else {
            $this->warn('  ' . count($stale) . ' stale snapshot(s):');
            $this->table(['Role', 'Listing ID', 'Listing Updated At', 'Snapshot Built At'], $stale);
        }

        $this->newLine();
    }

    private function auditCountsByRole(): void
    {
        $this->info('--- Fact / Question / Answer Counts by Role ---');

        $rows = [];

        foreach (array_keys(self::ROLE_MODELS) as $role) {
            $snapshotIds = AskAiKnowledgeSnapshot::where('listing_type', $role)
                ->pluck('id')
                ->toArray();

            $facts     = AskAiFact::whereIn('snapshot_id', $snapshotIds)->count();
            $questions = AskAiQuestion::whereIn('snapshot_id', $snapshotIds)->count();
            $answers   = AskAiAnswer::whereIn('snapshot_id', $snapshotIds)->count();
            $snapshots = count($snapshotIds);

            $rows[] = [$role, $snapshots, $facts, $questions, $answers];
        }

        $this->table(['Role', 'Snapshots', 'Facts', 'Questions', 'Answers'], $rows);
        $this->newLine();
    }

    private function auditLatestVersions(): void
    {
        $this->info('--- Latest Snapshot Version per Listing ---');

        $rows = AskAiKnowledgeSnapshot::select('listing_type', 'listing_id', DB::raw('MAX(version) as latest_version'))
            ->groupBy('listing_type', 'listing_id')
            ->orderBy('listing_type')
            ->orderBy('listing_id')
            ->get()
            ->map(fn($r) => [$r->listing_type, $r->listing_id, $r->latest_version])
            ->toArray();

        if (empty($rows)) {
            $this->line('  No snapshots found.');
        } else {
            $this->table(['Role', 'Listing ID', 'Latest Version'], $rows);
        }

        $this->newLine();
    }

    private function auditPhantomKeys(): void
    {
        $this->info('--- Phantom / Empty Canonical Keys ---');

        $factPhantoms     = AskAiFact::whereNull('canonical_key')->orWhere('canonical_key', '')->count();
        $questionPhantoms = AskAiQuestion::whereNull('canonical_key')->orWhere('canonical_key', '')->count();
        $answerPhantoms   = AskAiAnswer::whereNull('canonical_key')->orWhere('canonical_key', '')->count();

        $rows = [
            ['ask_ai_facts',     $factPhantoms],
            ['ask_ai_questions', $questionPhantoms],
            ['ask_ai_answers',   $answerPhantoms],
        ];

        $total = $factPhantoms + $questionPhantoms + $answerPhantoms;

        $this->table(['Table', 'Rows with null/empty canonical_key'], $rows);

        if ($total > 0) {
            $this->warn("  {$total} row(s) with null or empty canonical_key found.");
        } else {
            $this->line('  No phantom canonical keys found.');
        }

        $this->newLine();
    }
}

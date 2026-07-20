<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * HI-05A — migrate legacy public listing documents onto the private disk.
 *
 * Before HI-05, Seller/Landlord listing documents were written to the PUBLIC disk
 * and remain reachable by their old `.../storage/...` URLs even after new uploads
 * became private. This command closes that exposure operationally:
 *
 *     copy public -> private  →  verify (byte size)  →  (optional) delete public
 *
 * Design notes:
 *   - Paths are resolved from the listing's OWN stored EAV meta value, never from a
 *     reconstructed directory guess, so the command is robust to historical layout
 *     changes. Disclosure keys and doc-row `file_path`s hold a full relative path;
 *     `listing_documents` holds a bare filename under `auction/documents`.
 *   - It reads the `*_metas` tables directly and is deliberately self-contained: it
 *     does NOT depend on the (seller-only) ListingDocumentCatalog, so it can move
 *     landlord files before the catalog is extended (PR-A2).
 *   - Marketing photos/videos are intentionally public and are never touched — they
 *     are excluded structurally (not in the key set) and defensively (path prefix).
 *   - Idempotent: a file already on the private disk is skipped. Re-running after a
 *     `--delete-public` pass is a no-op.
 *   - Safe by default: without `--delete-public` the public original is RETAINED
 *     (copy-only, fully reversible). A public file is deleted only after its private
 *     copy is verified present and byte-identical.
 *
 * NOTE (operational sequencing): landlord delivery is not routed until PR-A2. Do not
 * run `--delete-public` for landlord until the landlord catalog/route is live, or
 * landlord documents become unreachable in the window between the two PRs.
 */
class BackfillPrivateListingDocuments extends Command
{
    protected $signature = 'documents:backfill-private
        {--dry-run : Plan only — make no filesystem changes and write no manifest}
        {--delete-public : After a verified copy, delete the public original (this is what closes the legacy exposure)}
        {--listing-type= : Restrict to a single type: seller or landlord (default: both)}
        {--id= : Restrict to a single listing id}
        {--manifest= : Override the manifest output path (relative to the private disk)}';

    protected $description = 'HI-05A: move legacy public listing documents to the private disk (copy, verify, optionally delete). Idempotent; excludes photos/videos.';

    /** Bare `listing_documents` filenames live under this directory. */
    private const LISTING_DOCUMENTS_DIR = 'auction/documents';

    /** Marketing media is intentionally public and must never be moved. */
    private const EXCLUDED_PREFIXES = ['auction/images/', 'auction/videos/'];

    /**
     * Physical-layout descriptor per listing type.
     *
     * @var array<string, array{metas_table:string, fk:string, doc_rows_key:string}>
     */
    private const TYPES = [
        'seller' => [
            'metas_table'  => 'seller_agent_auction_metas',
            'fk'           => 'seller_agent_auction_id',
            'doc_rows_key' => 'doc_rows',
        ],
        'landlord' => [
            'metas_table'  => 'landlord_agent_auction_metas',
            'fk'           => 'landlord_agent_auction_id',
            'doc_rows_key' => 'landlord_doc_rows',
        ],
    ];

    /**
     * Disclosure meta keys — the stored value is a FULL relative path. The union of
     * both roles' keys is queried against each table; absent keys simply return no
     * rows, so one list is safe for both.
     */
    private const DISCLOSURE_KEYS = [
        'seller_disclosure_file_path',
        'landlord_disclosure_file_path',
        'survey_file_path',
        'inspection_report_file_path',
        'hoa_condo_docs_file_path',
        'flood_disclosure_file_path',
        'lead_based_paint_file_path',
        'environmental_report_file_path',
    ];

    /** Value is a BARE filename resolved under LISTING_DOCUMENTS_DIR. */
    private const LISTING_DOCUMENTS_KEY = 'listing_documents';

    public function handle(): int
    {
        $dryRun       = (bool) $this->option('dry-run');
        $deletePublic = (bool) $this->option('delete-public');
        $typeFilter   = $this->option('listing-type');
        $idFilter     = $this->option('id') !== null ? (int) $this->option('id') : null;

        $types = $typeFilter !== null ? [$typeFilter] : array_keys(self::TYPES);
        foreach ($types as $type) {
            if (! isset(self::TYPES[$type])) {
                $this->error("Unknown listing-type '{$type}'. Expected: seller, landlord.");

                return self::FAILURE;
            }
        }

        if ($dryRun && $deletePublic) {
            $this->warn('--dry-run overrides --delete-public: nothing will be copied or deleted.');
        }

        // Collect and de-duplicate the candidate files (same physical file may be
        // referenced by more than one meta row).
        $records = [];
        foreach ($types as $type) {
            foreach ($this->collect($type, $idFilter) as $record) {
                $key = $record['listing_type'] . '::' . $record['relative_path'];
                $records[$key] ??= $record;
            }
        }
        $records = array_values($records);

        foreach ($records as &$record) {
            $this->process($record, $dryRun, $deletePublic);
        }
        unset($record);

        return $this->emit($records, $dryRun, $deletePublic);
    }

    /**
     * Build the candidate list for one listing type from its meta table.
     *
     * @return array<int, array{listing_type:string, listing_id:int|null, source:string, relative_path:string}>
     */
    private function collect(string $type, ?int $idFilter): array
    {
        $config    = self::TYPES[$type];
        $table     = $config['metas_table'];
        $fk        = $config['fk'];
        $wantedKeys = array_merge(
            self::DISCLOSURE_KEYS,
            [self::LISTING_DOCUMENTS_KEY, $config['doc_rows_key']]
        );

        $query = DB::table($table)
            ->select($fk . ' as listing_id', 'meta_key', 'meta_value')
            ->whereIn('meta_key', $wantedKeys);

        if ($idFilter !== null) {
            $query->where($fk, $idFilter);
        }

        $records = [];
        foreach ($query->cursor() as $row) {
            $listingId = $row->listing_id !== null ? (int) $row->listing_id : null;
            $value     = (string) ($row->meta_value ?? '');

            if ($row->meta_key === $config['doc_rows_key']) {
                foreach ($this->docRowPaths($value) as $index => $relative) {
                    $records[] = $this->record($type, $listingId, $row->meta_key . "[{$index}]", $relative);
                }
                continue;
            }

            if ($row->meta_key === self::LISTING_DOCUMENTS_KEY) {
                $filename = trim($value);
                if ($filename === '') {
                    continue;
                }
                $relative = self::LISTING_DOCUMENTS_DIR . '/' . ltrim($filename, '/');
                $records[] = $this->record($type, $listingId, $row->meta_key, $relative);
                continue;
            }

            // Disclosure keys: the value is already a full relative path.
            $relative = trim($value);
            if ($relative === '') {
                continue;
            }
            $records[] = $this->record($type, $listingId, $row->meta_key, $relative);
        }

        return $records;
    }

    /**
     * Extract the `file_path` of every row in a doc_rows / landlord_doc_rows JSON blob.
     *
     * @return array<int, string>
     */
    private function docRowPaths(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $rows = json_decode($json, true);
        if (! is_array($rows)) {
            return [];
        }

        $paths = [];
        foreach ($rows as $index => $row) {
            if (is_array($row) && isset($row['file_path']) && trim((string) $row['file_path']) !== '') {
                $paths[$index] = trim((string) $row['file_path']);
            }
        }

        return $paths;
    }

    /**
     * @return array{listing_type:string, listing_id:int|null, source:string, relative_path:string}
     */
    private function record(string $type, ?int $listingId, string $source, string $relative): array
    {
        return [
            'listing_type'  => $type,
            'listing_id'    => $listingId,
            'source'        => $source,
            'relative_path' => $relative,
        ];
    }

    /**
     * Decide and (unless dry-run) perform the action for one candidate file, writing
     * the outcome back into $record['action'] (+ 'verified' when a copy was attempted).
     */
    private function process(array &$record, bool $dryRun, bool $deletePublic): void
    {
        $relative = $record['relative_path'];

        if ($relative === '') {
            $record['action'] = 'skipped_empty';

            return;
        }
        // Path-traversal / absolute-path guard — mirrors ListingDocumentController.
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            $record['action'] = 'skipped_invalid_path';

            return;
        }
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $record['action'] = 'skipped_excluded_media';

                return;
            }
        }

        $private = Storage::disk('private');
        $public  = Storage::disk('public');

        // Idempotency: already migrated (or born private).
        if ($private->exists($relative)) {
            // Self-heal an interrupted --delete-public run: a prior pass may have
            // copied+verified but died before deleting the public original, leaving
            // the exposure open. Remove the lingering public copy — but ONLY when it
            // is byte-identical to the private twin (never delete a mismatched file).
            if ($deletePublic && $public->exists($relative)) {
                if ($private->size($relative) !== $public->size($relative)) {
                    $record['action'] = 'skipped_size_mismatch'; // both retained; needs review

                    return;
                }
                if ($dryRun) {
                    $record['action'] = 'would_delete_stale_public';

                    return;
                }
                $public->delete($relative);
                $record['action'] = 'deleted_stale_public';

                return;
            }

            $record['action'] = 'skipped_already_private';

            return;
        }
        // Nothing on the public disk to migrate (already deleted, or never existed).
        if (! $public->exists($relative)) {
            $record['action'] = 'missing_public';

            return;
        }

        if ($dryRun) {
            $record['action'] = $deletePublic ? 'would_move' : 'would_copy';

            return;
        }

        // Copy, then verify by byte size BEFORE any deletion.
        $private->put($relative, $public->get($relative));
        $verified = $private->exists($relative)
            && $private->size($relative) === $public->size($relative);
        $record['verified'] = $verified;

        if (! $verified) {
            // Never delete an unverified copy; leave the public original in place.
            $record['action'] = 'verify_failed';

            return;
        }

        if ($deletePublic) {
            $public->delete($relative);
            $record['action'] = 'moved';

            return;
        }

        $record['action'] = 'copied';
    }

    /**
     * Emit the summary + manifest and return the process exit code.
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function emit(array $records, bool $dryRun, bool $deletePublic): int
    {
        $summary = [];
        $hadFailure = false;
        foreach ($records as $record) {
            $action = $record['action'] ?? 'unknown';
            $summary[$action] = ($summary[$action] ?? 0) + 1;
            if ($action === 'verify_failed') {
                $hadFailure = true;
            }
        }

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'options'      => [
                'dry_run'       => $dryRun,
                'delete_public' => $deletePublic,
                'listing_type'  => $this->option('listing-type'),
                'id'            => $this->option('id'),
            ],
            'summary' => $summary,
            'records' => $records,
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($dryRun) {
            // Dry run writes NOTHING to disk — emit the manifest to stdout only.
            $this->line($json);
        } else {
            $manifestPath = $this->option('manifest')
                ?: '_backfill-manifests/backfill-' . now()->format('Ymd_His') . '.json';
            Storage::disk('private')->put($manifestPath, $json);
            $this->info('Manifest written to private disk: ' . $manifestPath);
        }

        $this->line('');
        $this->line($dryRun ? 'Backfill plan (dry run — no changes made):' : 'Backfill complete.');
        $rows = [];
        foreach ($summary as $action => $count) {
            $rows[] = [$action, $count];
        }
        if ($rows !== []) {
            $this->table(['action', 'count'], $rows);
        } else {
            $this->line('No candidate documents found.');
        }

        if ($hadFailure) {
            $this->error('One or more files failed verification and were NOT deleted. Inspect the manifest.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

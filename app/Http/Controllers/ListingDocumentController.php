<?php

namespace App\Http\Controllers;

use App\Services\Documents\ListingDocumentAccessService;
use App\Services\Documents\ListingDocumentCatalog;
use Illuminate\Support\Facades\Storage;

/**
 * HI-05 — the ONLY way to reach an uploaded listing document.
 *
 * New listing documents are written to the private disk and have no public URL.
 * This controller re-checks authorization on every request via
 * ListingDocumentAccessService (owner or authorized listing agent), resolves the
 * on-disk path from the listing's own meta through the trusted catalog (never
 * from request input), and streams the file.
 *
 * Structural safety:
 *   - documentKey must exist in ListingDocumentCatalog (allow-list); otherwise 404.
 *   - the relative path is derived from the LISTING'S stored meta, not the
 *     request — so cross-listing access and URL guessing cannot select another
 *     listing's file.
 *   - a defensive traversal check rejects any '..' or absolute path.
 *
 * Legacy compatibility: records created before this change still have their file
 * physically on the PUBLIC disk. Those are served here too, so nothing breaks —
 * but note that their old direct public URL remains reachable until the
 * operational backfill (see docs/launch-audits/HI-05-private-documents.md) moves
 * them to private storage and removes the public copies.
 */
class ListingDocumentController extends Controller
{
    public function __construct(private ListingDocumentAccessService $access)
    {
    }

    public function show(string $listingType, int $listingId, string $documentKey)
    {
        if (! ListingDocumentCatalog::supportsListingType($listingType) || ! ListingDocumentCatalog::has($documentKey)) {
            abort(404);
        }

        if (! $this->access->canViewDownload(auth()->user(), $listingType, $listingId, $documentKey)) {
            abort(403, 'You are not authorized to access this document.');
        }

        $listing = $this->access->resolveListing($listingType, $listingId);
        if ($listing === null) {
            abort(404);
        }

        $entry     = ListingDocumentCatalog::get($documentKey);
        $storedVal = data_get($listing->get, $entry['path']);
        $relative  = ListingDocumentCatalog::relativePath($documentKey, is_string($storedVal) ? $storedVal : null);

        if ($relative === null || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            abort(404, 'Document not found.');
        }

        $downloadName = $entry['label'] . '.' . (pathinfo($relative, PATHINFO_EXTENSION) ?: 'pdf');
        $headers      = ['Content-Disposition' => 'inline; filename="' . $downloadName . '"'];

        return $this->stream($relative, $downloadName, $headers);
    }

    /**
     * Deliver an "additional document" row (the doc_rows / additional_documents
     * uploads). Same authorization and structural safety as show(); the file
     * path is resolved from the listing's own doc_rows meta by index, never from
     * request input.
     */
    public function additional(string $listingType, int $listingId, int $index)
    {
        if (! ListingDocumentCatalog::supportsListingType($listingType)) {
            abort(404);
        }

        if (! $this->access->canAccessListingDocuments(auth()->user(), $listingType, $listingId)) {
            abort(403, 'You are not authorized to access this document.');
        }

        $listing = $this->access->resolveListing($listingType, $listingId);
        if ($listing === null) {
            abort(404);
        }

        $rowsRaw = data_get($listing->get, 'doc_rows');
        $rows    = is_string($rowsRaw) ? (json_decode($rowsRaw, true) ?: []) : (is_array($rowsRaw) ? $rowsRaw : []);
        $relative = trim((string) ($rows[$index]['file_path'] ?? ''));

        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            abort(404, 'Document not found.');
        }

        $label        = $rows[$index]['type'] ?? $rows[$index]['label'] ?? 'Document';
        $downloadName = $label . '.' . (pathinfo($relative, PATHINFO_EXTENSION) ?: 'pdf');

        return $this->stream($relative, $downloadName, ['Content-Disposition' => 'inline; filename="' . $downloadName . '"']);
    }

    /** New uploads live on the private disk; legacy files remain on public until backfill. */
    private function stream(string $relative, string $downloadName, array $headers)
    {
        if (Storage::disk('private')->exists($relative)) {
            return Storage::disk('private')->response($relative, $downloadName, $headers);
        }
        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->response($relative, $downloadName, $headers);
        }

        abort(404, 'Document not found.');
    }
}

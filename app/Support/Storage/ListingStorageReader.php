<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * R2-D.1 (HI-05A) — centralized READ path for listing documents/media.
 *
 * The counterpart to ListingStorageWriter: it decides WHICH disk a listing file
 * is read from, adding OPTIONAL, temporary object-first reads with local
 * fallback. Local remains authoritative until an operator opts in.
 *
 * R2-D.1 wires only the PRIVATE document read seam (streamed through
 * ListingDocumentController). The public URL resolver arrives in R2-D.2; the
 * config surface for it (`public_read`) is already declared but not consumed
 * here.
 *
 * Invariants:
 *   - With private_read='local' (default) this is byte-for-byte equivalent to
 *     the previous controller logic: try the local private disk, then the local
 *     public disk (legacy files), else 404. NO object-storage call is made.
 *   - With private_read='object_first' (and the key in read scope) the private
 *     SECONDARY disk is tried FIRST, then the exact same local chain as a
 *     fallback — so an object miss, a disabled/misconfigured secondary, or a
 *     connection error degrades gracefully to local delivery.
 *   - This class NEVER performs authorization. Callers authorize first; the
 *     reader only selects the byte source. It never exposes a private file via a
 *     URL and never reads private content from a public disk.
 */
class ListingStorageReader
{
    public function __construct(private ListingStorageDisks $disks)
    {
    }

    /**
     * Build a streamed response for a PRIVATE listing document, object-first with
     * local fallback. Returns null when the file exists on no candidate disk, so
     * the caller can abort(404) exactly as before.
     */
    public function privateResponse(string $relative, string $downloadName, array $headers = []): ?Response
    {
        foreach ($this->privateReadChain($relative) as $diskName) {
            try {
                // Resolving the disk is inside the try on purpose: an undefined or
                // misconfigured secondary (Storage::disk() throws) must degrade to
                // the local fallback, exactly like an unreachable object store.
                $disk = Storage::disk($diskName);

                if (! $disk->exists($relative)) {
                    continue;
                }

                return $disk->response($relative, $downloadName, $headers);
            } catch (Throwable $e) {
                // A secondary that errors — object storage unreachable, or an
                // undefined/misconfigured secondary disk — must never fail the
                // request while a local copy may still serve it.
                continue;
            }
        }

        return null;
    }

    /**
     * The ordered list of disks to try for a private read.
     *
     * Local chain (always the fallback, and the ONLY chain by default):
     *   [ private, public ]  — the pre-existing controller behavior, where the
     *   local public disk still serves legacy documents written before HI-05.
     *
     * When object-first is active for this key the private SECONDARY is prepended.
     *
     * @return list<string>
     */
    private function privateReadChain(string $relative): array
    {
        $local = [$this->disks->privateDiskName(), $this->disks->publicDiskName()];

        if ($this->objectFirst('private_read', $relative)) {
            return array_merge([$this->privateSecondaryDisk()], $local);
        }

        return $local;
    }

    /**
     * True when object_first reads are selected for the given direction AND the
     * relative key falls within the configured read scope.
     */
    private function objectFirst(string $key, string $relative): bool
    {
        $mode = strtolower(trim((string) config("listing_storage.{$key}", 'local')));
        if ($mode !== 'object_first') {
            return false;
        }

        return $this->prefixInScope($relative);
    }

    /**
     * Scope guard: when 'read_prefixes' is non-empty, object_first applies only to
     * keys under one of those prefixes; empty means all keys are in scope.
     */
    private function prefixInScope(string $relative): bool
    {
        $raw = trim((string) config('listing_storage.read_prefixes', ''));
        if ($raw === '') {
            return true;
        }

        foreach (explode(',', $raw) as $prefix) {
            $prefix = trim($prefix);
            if ($prefix !== '' && str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function privateSecondaryDisk(): string
    {
        return (string) config('listing_storage.private_secondary_disk', 's3_private');
    }
}

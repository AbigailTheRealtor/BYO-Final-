<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * R2-C (HI-05A) — non-destructive, resumable, idempotent copy of existing local
 * listing storage to the paired object-storage secondary disks, preserving exact
 * relative keys.
 *
 * This service performs the per-object work: enumeration (filtered + traversal
 * safe), streamed copy (never buffering whole files), size + SHA-256 verification,
 * and non-clobber conflict detection. The command orchestrates options, the
 * manifest, and output. Local sources are only ever READ; nothing local is
 * deleted or modified.
 *
 * Private objects are only ever routed to the PRIVATE secondary, never a public
 * disk, and never to a secondary that advertises a public URL.
 */
class ListingObjectMigrator
{
    // Per-object statuses.
    public const MIGRATED = 'migrated';
    public const SKIPPED_IDENTICAL = 'skipped_identical';
    public const NEEDS_REVIEW = 'needs_review';
    public const CONFLICT = 'conflict';
    public const ERROR = 'error';
    public const WOULD_MIGRATE = 'would_migrate';
    public const MISSING_ON_DEST = 'missing_on_dest'; // verify-only

    // Error enum (redacted; never carries secrets/bucket/endpoint).
    public const E_NONE = 'NONE';
    public const E_SOURCE_MISSING = 'SOURCE_MISSING';
    public const E_NETWORK_TIMEOUT = 'NETWORK_TIMEOUT';
    public const E_AUTHZ_DENIED = 'AUTHZ_DENIED';
    public const E_PARTIAL_UPLOAD = 'PARTIAL_UPLOAD';
    public const E_CHECKSUM_MISMATCH = 'CHECKSUM_MISMATCH';
    public const E_UNKNOWN = 'UNKNOWN';

    private const CHUNK = 1048576; // 1 MiB

    public function __construct(private ListingStorageDisks $disks)
    {
    }

    public function sourceDiskName(bool $private): string
    {
        return $private ? $this->disks->privateDiskName() : $this->disks->publicDiskName();
    }

    /**
     * Resolve and validate the destination (object-storage) disk name.
     * Fails closed on missing config; refuses a private → public-url secondary.
     */
    public function destDiskName(bool $private): string
    {
        $name = $private
            ? (string) config('listing_storage.private_secondary_disk', 's3_private')
            : (string) config('listing_storage.public_secondary_disk', 's3_public');

        if ($name === '' || ! array_key_exists($name, (array) config('filesystems.disks', []))) {
            throw new RuntimeException('Migration secondary disk is not defined.');
        }
        if ($private && ! empty(config("filesystems.disks.{$name}.url"))) {
            throw new RuntimeException('Refusing to migrate private objects to a secondary that advertises a public URL.');
        }

        return $name;
    }

    /**
     * Enumerate migratable relative keys for a scope, filtered by prefix,
     * exclusions, and traversal safety.
     *
     * @return array<int, string>
     */
    public function enumerate(bool $private, ?string $prefix): array
    {
        $source = Storage::disk($this->sourceDiskName($private));
        $prefix = $this->sanitizePrefix($prefix);

        $keys = $source->allFiles($prefix ?? '');

        $excludePrefixes = (array) config('listing_storage.migration.exclude_prefixes', []);
        $excludeBasenames = (array) config('listing_storage.migration.exclude_basenames', []);

        $out = [];
        foreach ($keys as $key) {
            if ($this->isUnsafeKey($key)) {
                continue;
            }
            if (in_array(basename($key), $excludeBasenames, true)) {
                continue;
            }
            $skip = false;
            foreach ($excludePrefixes as $ex) {
                if ($key === $ex || str_starts_with($key, rtrim($ex, '/').'/')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $out[] = $key;
        }
        sort($out);

        return $out;
    }

    /**
     * Process one relative key. Returns a manifest record. Never throws — all
     * failures are captured as a status/error on the record.
     *
     * @param  array{dry_run?:bool, verify_only?:bool, force_conflicts?:bool}  $opts
     * @return array<string, mixed>
     */
    public function process(bool $private, string $key, array $opts = []): array
    {
        $dryRun = (bool) ($opts['dry_run'] ?? false);
        $verifyOnly = (bool) ($opts['verify_only'] ?? false);
        $force = (bool) ($opts['force_conflicts'] ?? false);

        $record = [
            'source_disk' => null,
            'dest_disk' => null,
            'relative_key' => $key,
            'size' => null,
            'local_sha256' => null,
            'dest_verification' => ['method' => null, 'sha256' => null, 'size_match' => null],
            'status' => null,
            'attempts' => 0,
            'error' => self::E_NONE,
        ];

        try {
            // Resolve disks inside the guarded block so a bad/unsafe destination
            // (e.g. private secondary with a public URL) surfaces as an ERROR
            // record rather than throwing out of the loop.
            $sourceName = $this->sourceDiskName($private);
            $destName = $this->destDiskName($private);
            $record['source_disk'] = $sourceName;
            $record['dest_disk'] = $destName;

            $src = Storage::disk($sourceName);
            $dest = Storage::disk($destName);

            if (! $src->exists($key)) {
                $record['status'] = self::ERROR;
                $record['error'] = self::E_SOURCE_MISSING;

                return $record;
            }

            $size = (int) $src->size($key);
            $localSha = $this->streamSha256($src, $key);
            $record['size'] = $size;
            $record['local_sha256'] = $localSha;

            $destExists = $dest->exists($key);

            // ---- verify-only: never writes ----
            if ($verifyOnly) {
                if (! $destExists) {
                    $record['status'] = self::MISSING_ON_DEST;

                    return $record;
                }
                $record['status'] = $this->verifyAgainstDest($dest, $key, $size, $localSha, $record)
                    ? self::SKIPPED_IDENTICAL
                    : self::CONFLICT;

                return $record;
            }

            // ---- existing destination: no-clobber unless forced ----
            if ($destExists) {
                if ($this->verifyAgainstDest($dest, $key, $size, $localSha, $record)) {
                    $record['status'] = self::SKIPPED_IDENTICAL;

                    return $record;
                }
                if (! $force) {
                    // size-match-but-different-hash => needs_review; else conflict.
                    $record['status'] = ($record['dest_verification']['size_match'] === true)
                        ? self::NEEDS_REVIEW
                        : self::CONFLICT;

                    return $record;
                }
                // force: fall through and overwrite the same key.
            }

            if ($dryRun) {
                $record['status'] = self::WOULD_MIGRATE;

                return $record;
            }

            // ---- streamed upload ----
            $record['attempts'] = 1;
            // League v1 writeStream() refuses an existing key; on a forced
            // overwrite remove the stale object first (we only reach here when
            // the destination is missing, or under --force-conflicts).
            if ($destExists) {
                $dest->delete($key);
            }
            $stream = $src->readStream($key);
            if ($stream === false || $stream === null) {
                throw new RuntimeException('Could not open source stream.');
            }
            try {
                $dest->writeStream($key, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // ---- verify the upload ----
            $ok = $this->verifyAgainstDest($dest, $key, $size, $localSha, $record);
            if (! $ok) {
                $record['status'] = self::ERROR;
                $record['error'] = ($record['dest_verification']['size_match'] === false)
                    ? self::E_PARTIAL_UPLOAD
                    : self::E_CHECKSUM_MISMATCH;

                return $record;
            }

            $record['status'] = self::MIGRATED;

            return $record;
        } catch (Throwable $e) {
            $record['status'] = self::ERROR;
            $record['error'] = $this->classify($e);

            return $record;
        }
    }

    /**
     * Compare destination against the known local size/hash, recording the method
     * and result on the record. Returns true when byte-size AND SHA-256 match.
     */
    private function verifyAgainstDest($dest, string $key, int $size, string $localSha, array &$record): bool
    {
        $destSize = (int) $dest->size($key);
        $sizeMatch = $destSize === $size;
        $destSha = $this->streamSha256($dest, $key); // re-download verification
        $record['dest_verification'] = [
            'method' => 'redownload',
            'sha256' => $destSha,
            'size_match' => $sizeMatch,
        ];

        return $sizeMatch && hash_equals($localSha, $destSha);
    }

    /** Streamed SHA-256 (never buffers the whole file). */
    private function streamSha256($disk, string $key): string
    {
        $ctx = hash_init('sha256');
        $stream = $disk->readStream($key);
        if ($stream === false || $stream === null) {
            throw new RuntimeException('Could not open stream for hashing.');
        }
        try {
            while (! feof($stream)) {
                $buf = fread($stream, self::CHUNK);
                if ($buf === false) {
                    break;
                }
                hash_update($ctx, $buf);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return hash_final($ctx);
    }

    private function classify(Throwable $e): string
    {
        $code = method_exists($e, 'getAwsErrorCode') ? (string) $e->getAwsErrorCode() : '';
        $class = get_class($e);

        if ($code === 'AccessDenied' || str_contains($class, 'Authorization')) {
            return self::E_AUTHZ_DENIED;
        }
        if (str_contains($class, 'ConnectException') || str_contains($class, 'NetworkingError') || str_contains(strtolower($e->getMessage()), 'timed out')) {
            return self::E_NETWORK_TIMEOUT;
        }

        return self::E_UNKNOWN;
    }

    /** Reject traversal / absolute keys. */
    private function isUnsafeKey(string $key): bool
    {
        return $key === '' || str_contains($key, '..') || str_starts_with($key, '/');
    }

    private function sanitizePrefix(?string $prefix): ?string
    {
        if ($prefix === null || $prefix === '') {
            return null;
        }
        if ($this->isUnsafeKey($prefix)) {
            throw new RuntimeException('Invalid migration prefix.');
        }

        return trim($prefix, '/');
    }
}

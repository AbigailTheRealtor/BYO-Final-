<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * R2-A (HI-05A) — explicitly-invoked, read-only S3 connectivity preflight.
 *
 * DISABLED BY DEFAULT. It performs NOTHING unless BOTH:
 *   - the operator passes --confirm, AND
 *   - env STORAGE_S3_PREFLIGHT_ENABLED=true
 * Otherwise it prints a "disabled" status and exits 0 without building any
 * client or touching the network. It is never scheduled and never invoked by
 * tests' normal paths.
 *
 * Read-only only: for EACH object-storage disk (s3_private and, R2-E0, s3_public)
 * a HeadBucket plus a HeadObject on a random NON-EXISTENT key under that disk's
 * own root prefix (to probe auth/authorization without listing or writing). It
 * never uploads, lists, or mutates anything. Overall status is OK only when both
 * buckets pass; otherwise the first failing status is returned.
 *
 * Output is REDACTED: it never prints bucket names, keys, secrets, endpoints,
 * account ids, regions, or object names — only a status enum.
 */
class S3PreflightCommand extends Command
{
    protected $signature = 'storage:s3-preflight
        {--confirm : Required to actually run; without it the command is a no-op}
        {--json : Emit the status as JSON}';

    protected $description = 'HI-05A R2-A: explicitly-invoked, read-only S3 connectivity preflight (disabled by default).';

    /** Status enum. */
    private const OK = 'OK';
    private const DISABLED = 'DISABLED';
    private const MISSING_CONFIG = 'MISSING_CONFIG';
    private const MISSING_ADAPTER = 'MISSING_ADAPTER';
    private const AUTH_FAILURE = 'AUTH_FAILURE';
    private const REGION_OR_ENDPOINT_MISMATCH = 'REGION_OR_ENDPOINT_MISMATCH';
    private const AUTHZ_DENIED = 'AUTHZ_DENIED';
    private const NETWORK_ERROR = 'NETWORK_ERROR';
    private const UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    public function handle(): int
    {
        $enabled = filter_var(env('STORAGE_S3_PREFLIGHT_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        if (! $this->option('confirm') || ! $enabled) {
            return $this->report(self::DISABLED, 'Preflight is disabled. Pass --confirm and set STORAGE_S3_PREFLIGHT_ENABLED=true to run.');
        }

        $status = $this->probe();

        return $this->report($status, $this->messageFor($status));
    }

    /**
     * Perform the read-only probes and return a status enum. Catches everything;
     * never rethrows credential/bucket detail.
     */
    private function probe(): string
    {
        // (1) adapter present?
        if (! class_exists('League\\Flysystem\\AwsS3v3\\AwsS3Adapter')) {
            return self::MISSING_ADAPTER;
        }

        // (2) configuration present for BOTH object-storage disks (checked without
        // echoing values). R2-E0: the public bucket is validated alongside the
        // private one so a public read-flip is preceded by a public config check.
        // Done for every disk BEFORE any network call, so an incomplete public
        // config fails closed (MISSING_CONFIG) without probing the network.
        foreach (['s3_private', 's3_public'] as $disk) {
            $cfg = (array) config("filesystems.disks.{$disk}", []);
            foreach (['key', 'secret', 'region', 'bucket'] as $required) {
                if (empty($cfg[$required])) {
                    return self::MISSING_CONFIG;
                }
            }
        }

        // (3) read-only network probes — each disk under its own root prefix.
        // OK only if every disk passes; otherwise the first failing status wins.
        foreach (['s3_private' => 'private', 's3_public' => 'public'] as $disk => $rootPrefix) {
            $status = $this->probeDisk($disk, $rootPrefix);
            if ($status !== self::OK) {
                return $status;
            }
        }

        return self::OK;
    }

    /**
     * Read-only probe of a single object-storage disk: HeadBucket + HeadObject on
     * a random non-existent key under the disk's own root prefix. Never lists or
     * mutates; catches everything and never rethrows credential/bucket detail.
     */
    private function probeDisk(string $diskName, string $rootPrefix): string
    {
        try {
            $client = Storage::disk($diskName)->getAdapter()->getClient();
            $bucket = config("filesystems.disks.{$diskName}.bucket");

            // HeadBucket — existence + auth + region.
            $client->headBucket(['Bucket' => $bucket]);

            // HeadObject on a random non-existent key — proves read authorization
            // path without listing. NotFound (404) => authorized-and-would-read.
            try {
                $client->headObject([
                    'Bucket' => $bucket,
                    'Key' => $rootPrefix.'/__preflight_nonexistent_'.bin2hex(random_bytes(8)),
                ]);
            } catch (Throwable $e) {
                $code = $this->awsCode($e);
                if ($code === 'AccessDenied' || $code === '403') {
                    return self::AUTHZ_DENIED;
                }
                // 'NotFound' / '404' is the expected, healthy result — fall through.
            }

            return self::OK;
        } catch (Throwable $e) {
            return $this->classify($e);
        }
    }

    /** Map an AWS/HTTP exception to a redacted status enum. */
    private function classify(Throwable $e): string
    {
        $code = $this->awsCode($e);

        return match ($code) {
            'InvalidAccessKeyId', 'SignatureDoesNotMatch', 'InvalidToken', 'ExpiredToken' => self::AUTH_FAILURE,
            'AccessDenied', '403' => self::AUTHZ_DENIED,
            'PermanentRedirect', 'AuthorizationHeaderMalformed', 'IllegalLocationConstraintException', 'NoSuchBucket', '301' => self::REGION_OR_ENDPOINT_MISMATCH,
            default => $this->isNetwork($e) ? self::NETWORK_ERROR : self::UNKNOWN_ERROR,
        };
    }

    /** Best-effort AWS error code extraction without leaking messages. */
    private function awsCode(Throwable $e): ?string
    {
        if (method_exists($e, 'getAwsErrorCode')) {
            $c = $e->getAwsErrorCode();
            if (! empty($c)) {
                return $c;
            }
        }
        if (method_exists($e, 'getStatusCode')) {
            $s = $e->getStatusCode();
            if (! empty($s)) {
                return (string) $s;
            }
        }

        return null;
    }

    private function isNetwork(Throwable $e): bool
    {
        $class = get_class($e);

        return str_contains($class, 'ConnectException')
            || str_contains($class, 'NetworkingError')
            || str_contains($class, 'CurlException');
    }

    private function messageFor(string $status): string
    {
        return match ($status) {
            self::OK => 'S3 reachable and authorized (read-only probe).',
            self::MISSING_ADAPTER => 'S3 Flysystem adapter is not installed.',
            self::MISSING_CONFIG => 'S3 configuration is incomplete.',
            self::AUTH_FAILURE => 'Authentication failed.',
            self::AUTHZ_DENIED => 'Authorization denied for the read probe.',
            self::REGION_OR_ENDPOINT_MISMATCH => 'Region/endpoint/bucket mismatch.',
            self::NETWORK_ERROR => 'Network error reaching object storage.',
            default => 'Unclassified error.',
        };
    }

    private function report(string $status, string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['status' => $status, 'message' => $message]));
        } else {
            $this->line("status={$status}");
            $this->line($message);
        }

        return in_array($status, [self::OK, self::DISABLED], true) ? self::SUCCESS : self::FAILURE;
    }
}

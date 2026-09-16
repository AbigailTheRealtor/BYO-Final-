<?php

namespace App\Support\VirtualDrive;

use RuntimeException;
use Throwable;

/**
 * The auth-failure stop-loss: once Google has rejected the Virtual Drive
 * browser key, no further launch is granted until a developer clears it.
 *
 * WHY THIS EXISTS
 * ---------------
 * A launch claim is granted — and counted — BEFORE the Maps JavaScript API is
 * loaded, because a refusal must mean nothing was fetched. The price of that
 * ordering is that a key Google rejects still spends a launch, and on
 * 2026-09-15 six reload-and-press attempts against one misconfigured key spent
 * six of the day's ten. Nothing about the key changes between those presses,
 * so every one after the first was certain to fail. This class turns the first
 * rejection into a standing refusal.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 * --------------------------------
 *  • It never refunds. The failed launch stays counted in the daily ledger:
 *    the tally records what was granted, and a browser's report must not be
 *    able to hand launches back.
 *  • It never clears itself. Not on reload, not at midnight, not on a new
 *    session, not on `cache:clear`. The only way out is the explicit
 *    `virtual-drive:google-auth-block --reset` command. There is no HTTP route
 *    that clears it.
 *
 * A REPORT CAN ONLY REDUCE SPEND
 * ------------------------------
 * The report arrives from the browser, so it is untrusted — which is fine,
 * because the only thing a report can do is stop launches. A forged report
 * blocks a development proof until someone resets it; it cannot start one.
 *
 * KEPT IN A FILE, NOT THE CACHE
 * -----------------------------
 * The daily ledger lives in the cache because it expires with the day. This
 * must not expire with anything, and `php artisan cache:clear` is exactly the
 * kind of routine command somebody runs while debugging a broken key. So the
 * block is a JSON file under storage/app, written atomically.
 *
 * FAIL CLOSED
 * -----------
 * A block file that exists but cannot be read or decoded is a block. The
 * ledger treats an exception from current() as a refusal.
 *
 * NEVER THE KEY
 * -------------
 * Everything recorded is reduced to a short allow-list of fields, and every
 * free-text value has the configured browser key, anything shaped like a
 * Google API key and any `key=` query parameter removed before it is stored.
 * Origins keep scheme, host and port only; URLs lose their query and fragment.
 */
final class VirtualDriveGoogleAuthBlock
{
    public const RESET_COMMAND = 'php artisan virtual-drive:google-auth-block --reset';

    private const MAX_MESSAGE = 600;

    private const MAX_URL = 300;

    private const SOURCES = ['gm_authFailure', 'console', 'gm_authFailure+console', 'launch_refused'];

    private const REFERRER_POLICIES = [
        'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin',
        'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url', 'browser-default',
    ];

    public function path(): string
    {
        $configured = config('virtual_drive.google.auth_block_path');

        return is_string($configured) && trim($configured) !== ''
            ? $configured
            : storage_path('app/virtual-drive/google-auth-failure-block.json');
    }

    /**
     * The recorded block, or null when there is none.
     *
     * @return array<string,mixed>|null
     *
     * @throws RuntimeException when a block file exists but cannot be read — the caller must refuse
     */
    public function current(): ?array
    {
        $path = $this->path();

        clearstatcache(true, $path);

        if (! file_exists($path)) {
            return null;
        }

        // A local file, opened as a stream so nothing in this class resembles a
        // network read (VirtualDriveProviderIsolationTest scans for one).
        $handle = @fopen($path, 'rb');
        $raw = $handle === false ? false : stream_get_contents($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        if (! is_string($raw)) {
            throw new RuntimeException('The Virtual Drive auth-failure block exists but could not be read.');
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The Virtual Drive auth-failure block exists but is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * Whether launches must be refused. Unreadable is blocked.
     */
    public function blocks(): bool
    {
        try {
            return $this->current() !== null;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * Record a rejection reported by the page. The first report's cause is kept;
     * a later report only fills in what the first lacked and bumps the count.
     *
     * @param  array<string,mixed>  $report  untrusted browser input
     * @param  array<string,mixed>  $server  facts the server saw itself (request origin, referer, ledger)
     * @return array<string,mixed> the stored block
     *
     * @throws RuntimeException when the block could not be persisted and read back
     */
    public function record(array $report, array $server = []): array
    {
        $incoming = $this->sanitize($report, $server);

        try {
            $existing = $this->current();
        } catch (Throwable $e) {
            // Unreadable already blocks. Overwrite with something readable rather
            // than leaving a corrupt file as the only record.
            $existing = null;
        }

        if ($existing !== null) {
            foreach ($incoming as $field => $value) {
                if (($existing[$field] ?? null) === null && $value !== null) {
                    $existing[$field] = $value;
                }
            }

            $existing['reports'] = (int) ($existing['reports'] ?? 1) + 1;
            $existing['last_reported_at'] = $incoming['reported_at'];
            $block = $existing;
        } else {
            $block = $incoming + ['reports' => 1, 'last_reported_at' => $incoming['reported_at']];
        }

        $this->write($block);

        // THE READBACK, as in the ledger: a block is only believed once it has
        // been read out of the place it was written to.
        if ($this->current() === null) {
            throw new RuntimeException('The Virtual Drive auth-failure block did not persist.');
        }

        return $block;
    }

    /**
     * Remove the block. Called only by the reset command.
     *
     * @return array<string,mixed>|null what was cleared
     */
    public function clear(): ?array
    {
        try {
            $previous = $this->current();
        } catch (Throwable $e) {
            $previous = ['unreadable' => true];
        }

        $path = $this->path();

        if (file_exists($path) && ! @unlink($path)) {
            throw new RuntimeException('The Virtual Drive auth-failure block could not be removed.');
        }

        clearstatcache(true, $path);

        return $previous;
    }

    /**
     * The fields a page or an API response may show. Already sanitized at write
     * time; filtered again here so a hand-edited file cannot publish anything else.
     *
     * @param  array<string,mixed>|null  $block
     * @return array<string,mixed>|null
     */
    public function publicView(?array $block): ?array
    {
        if ($block === null) {
            return null;
        }

        $view = [];

        foreach (['code', 'message', 'authorized_url', 'page_origin', 'request_origin', 'request_referer', 'referrer_sent',
            'referrer_policy', 'source', 'reported_at', 'reports', 'ledger_used', 'ledger_limit', 'day'] as $field) {
            $value = $block[$field] ?? null;
            $view[$field] = is_string($value) ? $this->redact($value) : (is_int($value) ? $value : null);
        }

        return $view;
    }

    /**
     * The refusal sentence, written once for the page note and the API.
     *
     * @param  array<string,mixed>|null  $block
     */
    public function refusalMessage(?array $block): string
    {
        $view = $this->publicView($block) ?? [];
        $code = $view['code'] ?? null;
        $origin = $view['request_origin'] ?? $view['page_origin'] ?? null;

        return 'Google Street View launches are blocked in this proof environment: Google rejected the browser key'
            . ($code ? ' (' . $code . ')' : '')
            . (isset($view['reported_at']) ? ' at ' . $view['reported_at'] : '')
            . ($origin ? ' on ' . $origin : '')
            . '. No launch was granted and nothing was requested from Google. The launch that failed stays '
            . 'counted. Fix the key restriction in Google Cloud, then clear the block explicitly with: '
            . self::RESET_COMMAND;
    }

    /**
     * @param  array<string,mixed>  $report
     * @param  array<string,mixed>  $server
     * @return array<string,mixed>
     */
    private function sanitize(array $report, array $server): array
    {
        $code = $this->string($report['code'] ?? null, 80);
        $source = $this->string($report['source'] ?? null, 40);
        $policy = $this->string($report['referrer_policy'] ?? null, 40);

        return [
            'code'            => $code !== null && preg_match('/^[A-Z][A-Za-z]{2,60}MapError$/', $code) === 1 ? $code : null,
            'message'         => $this->text($report['message'] ?? null, self::MAX_MESSAGE),
            'authorized_url'  => $this->url($report['authorized_url'] ?? null),
            'page_origin'     => $this->origin($report['page_origin'] ?? null),
            'referrer_sent'   => $this->url($report['referrer_sent'] ?? null),
            'referrer_policy' => in_array($policy, self::REFERRER_POLICIES, true) ? $policy : null,
            'source'          => in_array($source, self::SOURCES, true) ? $source : null,
            'request_origin'  => $this->origin($server['request_origin'] ?? null),
            'request_referer' => $this->url($server['request_referer'] ?? null),
            'ledger_used'     => isset($server['ledger_used']) && is_int($server['ledger_used']) ? $server['ledger_used'] : null,
            'ledger_limit'    => isset($server['ledger_limit']) && is_int($server['ledger_limit']) ? $server['ledger_limit'] : null,
            'day'             => $this->string($server['day'] ?? null, 10),
            'reported_at'     => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @param array<string,mixed> $block */
    private function write(array $block): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('The Virtual Drive auth-failure block directory could not be created.');
        }

        $json = json_encode($block, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new RuntimeException('The Virtual Drive auth-failure block could not be encoded.');
        }

        $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temp, $json . "\n", LOCK_EX) === false || ! @rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException('The Virtual Drive auth-failure block could not be written.');
        }
    }

    private function string(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = $this->string($value, 4000);

        return $value === null ? null : mb_substr($this->redact($value), 0, $max);
    }

    /** Scheme, host and port — nothing that could carry a key. */
    private function origin(mixed $value): ?string
    {
        $value = $this->string($value, self::MAX_URL);

        if ($value === null) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** Scheme, host, port and path; never a query string or fragment. */
    private function url(mixed $value): ?string
    {
        $origin = $this->origin($value);

        if ($origin === null) {
            return null;
        }

        $path = parse_url((string) $value, PHP_URL_PATH);

        return $this->redact(mb_substr($origin . (is_string($path) ? $path : ''), 0, self::MAX_URL));
    }

    private function redact(string $value): string
    {
        $key = VirtualDriveGoogleGate::browserKey();

        if ($key !== null) {
            $value = str_replace($key, '[redacted key]', $value);
        }

        $value = preg_replace('/AIza[0-9A-Za-z_\-]{10,}/', '[redacted key]', $value) ?? '';

        return preg_replace('/([?&]key=)[^&\s#]+/i', '$1[redacted]', $value) ?? '';
    }
}

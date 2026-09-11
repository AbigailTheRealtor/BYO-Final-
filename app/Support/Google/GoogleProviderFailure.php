<?php

namespace App\Support\Google;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Describes a failed server-side Google request WITHOUT the credential.
 *
 * WHY THE EXCEPTION MESSAGE IS NEVER LOGGED
 * -----------------------------------------
 * Guzzle writes the request URI into its exception messages —
 * "Client error: `GET https://maps.googleapis.com/maps/api/geocode/json?address=…&key=…`
 * resulted in a `403 Forbidden` response" — and redacts only URI user-info, never the query
 * string. Every server-side Google credential travels in the query string. So
 * `Log::error('…' . $e->getMessage())`, which the Google callers used to do, wrote the server
 * key into the application log the first time Google answered 4xx/5xx or a connection failed.
 *
 * {@see log()} records what an operator needs — provider, family, operation, a safe category,
 * the HTTP status, the exception class and a refusal's reason — and nothing that came from
 * the request. {@see redact()} is for the places a message must be kept (a stored error on a
 * Location DNA row): every URL loses its query string.
 */
final class GoogleProviderFailure
{
    /** The event name every failure log line carries. */
    public const EVENT = 'google_provider_failure';

    /** Refused before sending by GoogleProviderAdmissionMiddleware — nothing reached Google. */
    public const CATEGORY_REFUSED = 'refused';

    /** Google answered, with an HTTP error status. */
    public const CATEGORY_HTTP_ERROR = 'http_error';

    /** No answer: connection refused, DNS, timeout. */
    public const CATEGORY_NETWORK = 'network';

    /** Anything else — a malformed body, say. */
    public const CATEGORY_UNEXPECTED = 'unexpected';

    /**
     * One structured line. A refusal is our own decision, so it is `info`; anything else warns.
     * Never throws: logging must not turn a degraded lookup into a crash.
     */
    public static function log(Throwable $e, string $family, string $operation): void
    {
        try {
            $context = self::context($e, $family, $operation);

            $context['category'] === self::CATEGORY_REFUSED
                ? Log::info(self::EVENT, $context)
                : Log::warning(self::EVENT, $context);
        } catch (Throwable) {
            // The failure being described has already been handled by the caller.
        }
    }

    /**
     * The safe description. No message, no URL, no query string, no payload.
     *
     * @return array{provider: string, family: string, operation: string, category: string,
     *               reason: string|null, http_status: int|null, exception: string}
     */
    public static function context(Throwable $e, string $family, string $operation): array
    {
        $refused = $e instanceof GoogleProviderRequestRefused;

        return [
            'provider'    => 'google',
            'family'      => $refused ? $e->family : $family,
            'operation'   => $operation,
            'category'    => self::category($e),
            'reason'      => $refused ? $e->reason : null,
            'http_status' => $e instanceof RequestException && $e->getResponse() !== null
                ? $e->getResponse()->getStatusCode()
                : null,
            'exception'   => get_class($e),
        ];
    }

    public static function category(Throwable $e): string
    {
        return match (true) {
            $e instanceof GoogleProviderRequestRefused                     => self::CATEGORY_REFUSED,
            $e instanceof RequestException && $e->getResponse() !== null   => self::CATEGORY_HTTP_ERROR,
            $e instanceof TransferException                                => self::CATEGORY_NETWORK,
            default                                                        => self::CATEGORY_UNEXPECTED,
        };
    }

    /**
     * A message that is safe to store or show: every URL loses its query string, and any
     * `key=` parameter that appears outside a URL is blanked too.
     */
    public static function redact(string $message): string
    {
        $message = (string) preg_replace('~(https?://[^\s?#`\'"<>]+)\?[^\s`\'"<>]*~i', '$1?[redacted]', $message);

        return (string) preg_replace('~\b(key=)[^&\s`\'"<>]+~i', '$1[redacted]', $message);
    }
}

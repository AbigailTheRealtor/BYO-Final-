<?php

namespace App\Services\Bridge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BridgeApiService
{
    /** No credentials configured — the call was never attempted. */
    public const FAILURE_NOT_CONFIGURED = 'not_configured';

    /** The API answered with a non-2xx status (auth, quota, bad filter, outage). */
    public const FAILURE_HTTP_ERROR = 'http_error';

    /** The request never completed (timeout, DNS, TLS, connection refused). */
    public const FAILURE_TRANSPORT_ERROR = 'transport_error';

    protected string $baseUrl = 'https://api.bridgedataoutput.com/api/v2/OData';

    /**
     * Why the most recent fetchProperties() call returned nothing, or null when
     * it completed successfully (including a successful, legitimately empty result).
     *
     * WHY THIS EXISTS
     * ---------------
     * fetchProperties() returns [] for four unrelated situations: no matching
     * record, missing credentials, an HTTP error, and a transport failure. That
     * is fine for the bulk importers, which only ever ask "what did I get?" —
     * but a user who types their own MLS number and is told "no such listing"
     * when the truth is "we could not reach the MLS" has been told something
     * false about their property.
     *
     * Recording the reason alongside the unchanged [] return is the narrowest
     * way to let a caller tell those apart. Existing callers ignore this
     * property and are completely unaffected; the return value, the logging and
     * the swallow-don't-throw posture are all exactly as they were.
     *
     * Read it through lastFailure() immediately after the call that produced it.
     */
    private ?string $lastFailure = null;

    /**
     * The reason the last fetchProperties() call failed, or null if it succeeded.
     *
     * Only meaningful on the same instance that performed the call, and only
     * until the next one — every fetchProperties() resets it.
     *
     * Carries a stable machine-readable constant, never a provider message, a
     * status line or a URL: this value reaches an error path that a user may
     * eventually see, and nothing derived from a token-bearing request should
     * be able to travel that far.
     */
    public function lastFailure(): ?string
    {
        return $this->lastFailure;
    }

    public function fetchProperties(int $limit = 10, ?string $filter = null): array
    {
        // Cleared up front so a previous call's failure can never be mistaken
        // for this one's outcome.
        $this->lastFailure = null;

        $dataset = config('bridge.dataset');
        $token   = config('bridge.token');

        if (empty($dataset) || empty($token)) {
            Log::warning('BridgeApiService: bridge.dataset or bridge.token is missing from config. Skipping API call.');
            $this->lastFailure = self::FAILURE_NOT_CONFIGURED;
            return [];
        }

        $url = "{$this->baseUrl}/{$dataset}/Property";

        try {
            $params = [
                '$top'         => $limit,
                'access_token' => $token,
            ];

            if ($filter !== null) {
                $params['$filter'] = $filter;
            }

            $response = Http::timeout(30)->get($url, $params);

            Log::info('BridgeApiService: HTTP status ' . $response->status());

            if (!$response->successful()) {
                Log::error('BridgeApiService: API returned non-success status ' . $response->status());
                $this->lastFailure = self::FAILURE_HTTP_ERROR;
                return [];
            }

            $json = $response->json();
            return $json['value'] ?? [];
        } catch (\Throwable $e) {
            Log::error('BridgeApiService: Exception during API call — ' . $e->getMessage());
            $this->lastFailure = self::FAILURE_TRANSPORT_ERROR;
            return [];
        }
    }

    /**
     * Fetch a single page of properties using OData $top/$skip pagination.
     *
     * Unlike fetchProperties(), this method THROWS on failure so that callers
     * (e.g. LazyBridgeImportService) can distinguish a real API error from a
     * legitimately empty result page and take appropriate action.
     *
     * @param  int  $top   Page size (max records per request; Bridge API typically caps at 200).
     * @param  int  $skip  Zero-based offset — number of records to skip before this page.
     * @return array       Array of property records (empty array = end of feed, no error).
     * @throws \RuntimeException  On any HTTP error or transport failure.
     */
    public function fetchPropertiesPaginated(int $top = 200, int $skip = 0, ?string $filter = null): array
    {
        $dataset = config('bridge.dataset');
        $token   = config('bridge.token');

        if (empty($dataset) || empty($token)) {
            throw new \RuntimeException(
                'BridgeApiService: bridge.dataset or bridge.token is missing from config.'
            );
        }

        $url = "{$this->baseUrl}/{$dataset}/Property";

        $params = [
            '$top'         => $top,
            '$skip'        => $skip,
            'access_token' => $token,
        ];

        if ($filter !== null) {
            $params['$filter'] = $filter;
        }

        Log::info("BridgeApiService: paginated fetch — top={$top}, skip={$skip}" . ($filter !== null ? ", filter={$filter}" : ''));

        $response = Http::timeout(60)->get($url, $params);

        Log::info('BridgeApiService: paginated HTTP status ' . $response->status());

        if (!$response->successful()) {
            throw new \RuntimeException(
                "BridgeApiService: paginated API returned non-success status {$response->status()}"
            );
        }

        $json = $response->json();
        return $json['value'] ?? [];
    }
}

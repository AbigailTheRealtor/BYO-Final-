<?php

namespace App\Services\Bridge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BridgeApiService
{
    protected string $baseUrl = 'https://api.bridgedataoutput.com/api/v2/OData';

    public function fetchProperties(int $limit = 10, ?string $filter = null): array
    {
        $dataset = config('bridge.dataset');
        $token   = config('bridge.token');

        if (empty($dataset) || empty($token)) {
            Log::warning('BridgeApiService: bridge.dataset or bridge.token is missing from config. Skipping API call.');
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
                return [];
            }

            $json = $response->json();
            return $json['value'] ?? [];
        } catch (\Throwable $e) {
            Log::error('BridgeApiService: Exception during API call — ' . $e->getMessage());
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

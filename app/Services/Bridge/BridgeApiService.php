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
     * @param  int  $top   Page size (max records per request; Bridge API typically caps at 200).
     * @param  int  $skip  Zero-based offset — number of records to skip before this page.
     * @return array       Array of property records, or empty array on failure.
     */
    public function fetchPropertiesPaginated(int $top = 200, int $skip = 0, ?string $filter = null): array
    {
        $dataset = config('bridge.dataset');
        $token   = config('bridge.token');

        if (empty($dataset) || empty($token)) {
            Log::warning('BridgeApiService: bridge.dataset or bridge.token is missing from config. Skipping paginated API call.');
            return [];
        }

        $url = "{$this->baseUrl}/{$dataset}/Property";

        try {
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
                Log::error('BridgeApiService: paginated API returned non-success status ' . $response->status());
                return [];
            }

            $json = $response->json();
            return $json['value'] ?? [];
        } catch (\Throwable $e) {
            Log::error('BridgeApiService: Exception during paginated API call — ' . $e->getMessage());
            return [];
        }
    }
}

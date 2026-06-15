<?php

namespace App\Services\Bridge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BridgeApiService
{
    protected string $baseUrl = 'https://api.bridgedataoutput.com/api/v2/OData';

    public function fetchProperties(int $limit = 10): array
    {
        $dataset = config('bridge.dataset');
        $token   = config('bridge.token');

        if (empty($dataset) || empty($token)) {
            Log::warning('BridgeApiService: bridge.dataset or bridge.token is missing from config. Skipping API call.');
            return [];
        }

        $url = "{$this->baseUrl}/{$dataset}/Property";

        try {
            $response = Http::timeout(30)->get($url, [
                '$top'         => $limit,
                'access_token' => $token,
            ]);

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
}

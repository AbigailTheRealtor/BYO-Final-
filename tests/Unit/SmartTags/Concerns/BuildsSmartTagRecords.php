<?php

namespace Tests\Unit\SmartTags\Concerns;

use App\Services\SmartTags\Derivation\BridgeRecordAccessor;
use App\Services\SmartTags\Derivation\TagEvidence;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagState;

/**
 * Pure record builders for Smart Tag unit tests — no database.
 */
trait BuildsSmartTagRecords
{
    /**
     * A Bridge record from a real fixture, with native columns derived the way
     * BridgePropertyNormalizer derives them.
     *
     * @param array<string, mixed> $rawOverrides null removes a key
     */
    protected function bridgeFixture(string $name, array $rawOverrides = []): BridgeRecordAccessor
    {
        $raw = json_decode((string) file_get_contents(__DIR__ . '/../../../fixtures/mls/bridge/' . $name . '.json'), true);

        foreach ($rawOverrides as $key => $value) {
            if ($value === null) {
                unset($raw[$key]);
            } else {
                $raw[$key] = $value;
            }
        }

        return $this->bridgeRecord($raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    protected function bridgeRecord(array $raw): BridgeRecordAccessor
    {
        $columns = [
            'property_type'       => $raw['PropertyType'] ?? null,
            'pool_private_yn'     => $raw['PoolPrivateYN'] ?? null,
            'waterfront_yn'       => $raw['WaterfrontYN'] ?? null,
            'garage_yn'           => $raw['GarageYN'] ?? null,
            'new_construction_yn' => $raw['NewConstructionYN'] ?? null,
            'water_view_yn'       => $raw['STELLAR_WaterViewYN'] ?? null,
        ];

        return new BridgeRecordAccessor($columns, $raw);
    }

    /**
     * @param array<string, mixed> $meta values are stored as the wizards store them
     */
    protected function nativeMeta(array $meta): NativeMetaValueReader
    {
        $stored = [];
        foreach ($meta as $key => $value) {
            $stored[$key] = is_array($value) ? json_encode($value) : $value;
        }

        return new NativeMetaValueReader($stored);
    }

    /**
     * @param TagEvidence[] $evidence
     * @return array<string, string> tag => state
     */
    protected function states(array $evidence): array
    {
        $out = [];
        foreach ($evidence as $item) {
            $out[$item->tagKey] = $item->state->value;
        }
        ksort($out);

        return $out;
    }

    /**
     * @param TagEvidence[] $evidence
     * @return string[]
     */
    protected function presentKeys(array $evidence): array
    {
        $keys = [];
        foreach ($evidence as $item) {
            if ($item->state === SmartTagState::Present) {
                $keys[] = $item->tagKey;
            }
        }
        sort($keys);

        return $keys;
    }
}

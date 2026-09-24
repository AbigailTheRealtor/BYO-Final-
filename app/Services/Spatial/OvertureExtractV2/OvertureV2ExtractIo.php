<?php

namespace App\Services\Spatial\OvertureExtractV2;

/**
 * Serialises a lane to NDJSON bytes and fingerprints it. Pure — it builds strings; the offline
 * command is what writes them to disk. Fixed JSON flags and the result's fixed record order make
 * the bytes, and so the SHA-256, a function of the input rows alone.
 */
final class OvertureV2ExtractIo
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** @param list<OvertureV2Record> $records */
    public static function toNdjson(array $records): string
    {
        $out = '';
        foreach ($records as $record) {
            $out .= json_encode($record->toArray(), self::JSON_FLAGS) . "\n";
        }

        return $out;
    }

    public static function checksum(string $ndjson): string
    {
        return hash('sha256', $ndjson);
    }

    /** @param array<string, mixed> $manifest */
    public static function manifestJson(array $manifest): string
    {
        return json_encode($manifest, self::JSON_FLAGS | JSON_PRETTY_PRINT) . "\n";
    }
}

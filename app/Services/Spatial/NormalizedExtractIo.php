<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2).
 *
 * Reader/writer for the canonical NDJSON normalized extract. The ONLY module
 * that touches the extract file format.
 *
 * Determinism + idempotency (asserted by tests): writes apply a canonical total
 * order (category_key, then source_ref) before serializing, so the same set of
 * records — in any input order — produces byte-identical output. json_encode
 * uses fixed flags and NormalizedPlaceRecord::toArray() fixes the key order.
 */
final class NormalizedExtractIo
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Canonical NDJSON string for a set of records (trailing LF, or '' when empty).
     *
     * @param iterable<NormalizedPlaceRecord> $records
     */
    public function toNdjson(iterable $records): string
    {
        $sorted = $this->canonicalize($records);
        if ($sorted === []) {
            return '';
        }

        $lines = [];
        foreach ($sorted as $record) {
            $lines[] = json_encode($record->toArray(), self::JSON_FLAGS);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Write the canonical extract to $path. Returns the record count written.
     *
     * @param iterable<NormalizedPlaceRecord> $records
     */
    public function writeFile(string $path, iterable $records): int
    {
        $sorted = $this->canonicalize($records);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $this->toNdjson($sorted));

        return count($sorted);
    }

    /**
     * Parse an NDJSON extract string into records. Blank lines ignored.
     *
     * @return NormalizedPlaceRecord[]
     */
    public function fromNdjson(string $ndjson): array
    {
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $ndjson) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $records[] = NormalizedPlaceRecord::fromArray($decoded);
        }

        return $records;
    }

    /** @return NormalizedPlaceRecord[] */
    public function readFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Normalized extract not found: {$path}");
        }

        return $this->fromNdjson((string) file_get_contents($path));
    }

    /**
     * Canonical total order: (category_key, source_ref). Stable + deterministic.
     *
     * @param iterable<NormalizedPlaceRecord> $records
     * @return NormalizedPlaceRecord[]
     */
    private function canonicalize(iterable $records): array
    {
        $arr = is_array($records) ? array_values($records) : iterator_to_array($records, false);

        usort($arr, static function (NormalizedPlaceRecord $a, NormalizedPlaceRecord $b): int {
            return [$a->category_key, $a->source_ref] <=> [$b->category_key, $b->source_ref];
        });

        return $arr;
    }
}

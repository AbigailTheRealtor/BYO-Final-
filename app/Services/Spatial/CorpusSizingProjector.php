<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B2 / B4).
 *
 * Turns a row COUNT into a storage projection using the ACCEPTED planning
 * proxies (owner decision), with NO cluster:
 *   • total storage  ≈ 450 bytes / row
 *   • composite GiST ≈  94 bytes / row
 *
 * These proxies are planning constants, not measurements. The live Q2 harness
 * replaces the row COUNTS (Pinellas / Florida / CONUS) with measured values; it
 * does NOT change these per-row bytes. Pure and deterministic.
 */
final class CorpusSizingProjector
{
    public function __construct(
        private readonly int $bytesPerRowTotal = 450,
        private readonly int $gistBytesPerRow = 94,
    ) {
    }

    public static function fromConfig(array $sizing): self
    {
        return new self(
            (int) ($sizing['bytes_per_row_total'] ?? 450),
            (int) ($sizing['gist_bytes_per_row'] ?? 94),
        );
    }

    /**
     * @return array{rows:int,total_bytes:int,gist_bytes:int,total_human:string,gist_human:string}
     */
    public function project(int $rowCount): array
    {
        if ($rowCount < 0) {
            throw new \InvalidArgumentException("Row count must be >= 0; got {$rowCount}.");
        }

        $totalBytes = $rowCount * $this->bytesPerRowTotal;
        $gistBytes = $rowCount * $this->gistBytesPerRow;

        return [
            'rows'        => $rowCount,
            'total_bytes' => $totalBytes,
            'gist_bytes'  => $gistBytes,
            'total_human' => $this->humanBytes($totalBytes),
            'gist_human'  => $this->humanBytes($gistBytes),
        ];
    }

    /**
     * Project a set of named row counts (e.g. per region or per category), with
     * a summed TOTAL row appended.
     *
     * @param array<string,int> $counts
     * @return array<string,array{rows:int,total_bytes:int,gist_bytes:int,total_human:string,gist_human:string}>
     */
    public function projectMany(array $counts): array
    {
        $out = [];
        $sum = 0;
        foreach ($counts as $label => $n) {
            $out[$label] = $this->project((int) $n);
            $sum += (int) $n;
        }
        $out['TOTAL'] = $this->project($sum);

        return $out;
    }

    public function bytesPerRowTotal(): int
    {
        return $this->bytesPerRowTotal;
    }

    public function gistBytesPerRow(): int
    {
        return $this->gistBytesPerRow;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = (float) $bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return $i === 0
            ? "{$bytes} B"
            : sprintf('%.2f %s', $value, $units[$i]);
    }
}

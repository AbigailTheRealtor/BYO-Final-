<?php

namespace App\Services\Location\AddressCorpus;

/**
 * Bounded-memory counters for a corpus dry run.
 *
 * MEMORY IS THE DESIGN CONSTRAINT, NOT AN OPTIMISATION
 * ----------------------------------------------------
 * A national scan is ~98 million rows. Anything this class keeps per row would
 * be multiplied by that, so it keeps nothing per row: every statistic is either
 * a counter or a set whose cardinality is bounded by the real world (67
 * counties, ~1,500 Florida ZIPs, a handful of Placement values).
 *
 * Those sets are still capped. A corrupt file with a million distinct "counties"
 * would otherwise defeat the whole point while looking like a successful run, so
 * a dimension that exceeds {@see self::MAX_DISTINCT} stops collecting and counts
 * the overflow instead. A truncated dimension says so in the report rather than
 * quietly reporting a subset as if it were the whole.
 *
 * SOURCE-NEUTRAL BY CONSTRUCTION
 * ------------------------------
 * This class knows nothing about NAD, NENA, or any county. It counts a
 * {@see CorpusAddressRecord}, and every field it reads was already resolved by
 * whichever normalizer produced that record — including the placement label and
 * the precision. It used to call `NadPlacementMap` directly, which quietly made
 * the "generic" audit a NAD-only audit: a NENA county's `POINTTYPE` would have
 * been scored against a vocabulary that has nothing to do with it.
 *
 * The one remaining source-shaped input is which raw column names to count for
 * presence, and that is passed in by the caller rather than assumed.
 *
 * WHAT THIS CLASS CANNOT DO
 * -------------------------
 * Duplicate-address detection is deliberately absent. Counting how many distinct
 * normalized lines exist among 12 million rows means remembering 12 million
 * strings, which is exactly the thing this class refuses to do. That statistic
 * is computed outside, by spilling the lines to disk and sorting them — see the
 * command. Keeping it out of here is what stops "just one more set" from
 * reintroducing unbounded growth.
 */
final class AddressCorpusDryRunReport
{
    /** Per-dimension distinct-value ceiling. Above it, the dimension truncates. */
    public const MAX_DISTINCT = 20000;

    // ── scan ────────────────────────────────────────────────────────────────
    public int $rowsScanned = 0;
    public int $rowsInState = 0;
    public int $accepted    = 0;
    public int $rejected    = 0;

    /** @var array<string, int> reason => count */
    public array $rejectReasons = [];

    // ── field presence (counted over in-state rows) ─────────────────────────
    /** @var array<string, int> field => count of rows where it was absent/empty */
    public array $missingFields = [];

    /** The NAD column set, kept as the default so existing callers are unchanged. */
    public const TRACKED_FIELDS = [
        'UUID', 'AddNo_Full', 'StNam_Full', 'Post_City', 'Inc_Muni',
        'Zip_Code', 'Latitude', 'Longitude', 'Placement', 'SubAddress', 'Unit', 'County',
    ];

    /**
     * Raw column names whose emptiness this run counts.
     *
     * Passed in because it is the one genuinely source-shaped thing here: a NENA
     * county publishes `POSTALCOMM` and `ADDRNUM`, and counting NAD's column
     * names against it would report every field missing on every row — a report
     * that looks like a catastrophic data problem and is actually a bug.
     *
     * @var list<string>
     */
    public readonly array $trackedFields;

    /** @param list<string>|null $trackedFields */
    public function __construct(?array $trackedFields = null)
    {
        $this->trackedFields = $trackedFields ?? self::TRACKED_FIELDS;
    }

    // ── distinct dimensions ─────────────────────────────────────────────────
    /** @var array<string, array<string, int>> dimension => value => count */
    private array $distinct = [];

    /** @var array<string, int> dimension => values dropped after the cap */
    private array $overflow = [];

    // ── locality / unit provenance ──────────────────────────────────────────
    public int $localityPostCity = 0;
    public int $localityIncMuni  = 0;
    public int $localityNone     = 0;

    public int $unitSubAddress = 0;
    public int $unitFallback   = 0;
    public int $unitNone       = 0;

    // ── placement ───────────────────────────────────────────────────────────
    public int $placementNull        = 0;
    public int $placementRecognised  = 0;
    public int $placementUnrecognised = 0;

    /**
     * Rows whose state or county came from source configuration, not from data.
     *
     * A single-county NENA feed publishes neither, because inside that county
     * both are obvious. Supplying them is legitimate; not saying so would not be,
     * which is why this is a counter in the report rather than a silent default.
     */
    public int $injectedJurisdiction = 0;

    /**
     * Placement label => how the producing normalizer resolved it.
     *
     * @var array<string, array{recognised: bool, precision: string|null}>
     */
    private array $placementResolutions = [];

    public function countRowScanned(): void
    {
        $this->rowsScanned++;
    }

    public function countInState(): void
    {
        $this->rowsInState++;
    }

    /** @param array<string, mixed> $row */
    public function countFieldPresence(array $row): void
    {
        foreach ($this->trackedFields as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $this->missingFields[$field] = ($this->missingFields[$field] ?? 0) + 1;
            }
        }
    }

    public function countReject(string $reason): void
    {
        $this->rejected++;
        $this->rejectReasons[$reason] = ($this->rejectReasons[$reason] ?? 0) + 1;
    }

    public function countAccepted(CorpusAddressRecord $record): void
    {
        $this->accepted++;

        $this->addDistinct('county', $record->county !== '' ? $record->county : '(blank)');
        $this->addDistinct('zip', $record->address->normalizedZip5() ?: '(blank)');
        $this->addDistinct('city', $record->city !== '' ? strtolower($record->city) : '(blank)');
        $this->addDistinct('precision', $record->precision?->value ?? '(undecided)');

        match ($record->localitySource) {
            'post_city' => $this->localityPostCity++,
            'inc_muni'  => $this->localityIncMuni++,
            default     => $this->localityNone++,
        };

        match ($record->unitSource) {
            'subaddress' => $this->unitSubAddress++,
            'unit'       => $this->unitFallback++,
            default      => $this->unitNone++,
        };

        if ($record->stateProvenance === 'injected' || $record->countyProvenance === 'injected') {
            $this->injectedJurisdiction++;
        }

        // The placement classification arrives already resolved by whichever
        // source owns that vocabulary. This class used to call NadPlacementMap
        // directly, which meant the "generic" audit could only ever audit NAD —
        // and a NENA county's `POINTTYPE` would have been scored against a
        // vocabulary that has nothing to do with it.
        if ($record->placementLabel === '') {
            $this->placementNull++;
            $this->addDistinct('placement', '(null)');

            return;
        }

        $this->addDistinct('placement', $record->placementLabel);

        // What each observed placement value resolved to, recorded once so the
        // renderer can show it without asking a source-specific map. This is the
        // last thing that used to force the report to know about NAD.
        $this->placementResolutions[$record->placementLabel] ??= [
            'recognised' => $record->placementRecognised,
            'precision'  => $record->precision?->value,
        ];

        if ($record->placementRecognised) {
            $this->placementRecognised++;
        } else {
            $this->placementUnrecognised++;
        }
    }

    /**
     * How a placement value resolved, as the producing normalizer decided.
     *
     * @return array{recognised: bool, precision: string|null}
     */
    public function placementResolution(string $label): array
    {
        return $this->placementResolutions[$label] ?? ['recognised' => false, 'precision' => null];
    }

    private function addDistinct(string $dimension, string $value): void
    {
        $bucket = $this->distinct[$dimension] ?? [];

        if (isset($bucket[$value])) {
            $this->distinct[$dimension][$value]++;

            return;
        }

        if (count($bucket) >= self::MAX_DISTINCT) {
            $this->overflow[$dimension] = ($this->overflow[$dimension] ?? 0) + 1;

            return;
        }

        $this->distinct[$dimension][$value] = 1;
    }

    /** @return array<string, int> value => count, descending */
    public function distinctValues(string $dimension): array
    {
        $values = $this->distinct[$dimension] ?? [];
        arsort($values);

        return $values;
    }

    public function distinctCount(string $dimension): int
    {
        return count($this->distinct[$dimension] ?? []);
    }

    public function wasTruncated(string $dimension): bool
    {
        return ($this->overflow[$dimension] ?? 0) > 0;
    }

    /** Percentage of a numerator against in-state rows, to one decimal. */
    public function pctOfInState(int $n): float
    {
        return $this->rowsInState === 0 ? 0.0 : round($n * 100 / $this->rowsInState, 1);
    }

    /** Percentage against accepted rows, to one decimal. */
    public function pctOfAccepted(int $n): float
    {
        return $this->accepted === 0 ? 0.0 : round($n * 100 / $this->accepted, 1);
    }
}

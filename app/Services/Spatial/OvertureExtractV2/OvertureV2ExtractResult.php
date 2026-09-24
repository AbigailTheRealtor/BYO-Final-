<?php

namespace App\Services\Spatial\OvertureExtractV2;

/**
 * The outcome of one `overture-extract-v2` run: the two lanes, and an accounting in which every
 * bounding-box row appears exactly once. Immutable, deterministic, pure.
 *
 * THREE NUMBERS, NEVER ONE. {@see baseCount()} is the corpus. {@see supplementaryCount()} is the
 * matcher-analysis lane beside it. {@see matcherAnalysisCount()} is their sum and is labelled as
 * such everywhere it is printed — it is never a corpus size, and nothing here offers "total rows".
 *
 * Records are held in wire order: base by (category_key, source_ref), supplementary by
 * (source_category, source_ref), byte-wise — so the same input in any order gives the same files.
 */
final class OvertureV2ExtractResult
{
    /** @var list<OvertureV2Record> */
    private array $base = [];
    /** @var list<OvertureV2Record> */
    private array $supplementary = [];
    /** @var array<string, int> rejection code => rows */
    private array $rejections = [];

    private function __construct(
        private readonly OvertureExtractV2Config $config,
        private readonly int $inputRows,
    ) {
    }

    /**
     * @param array<string, OvertureV2Record|string> $outcomes id => record or rejection code
     */
    public static function fromOutcomes(
        OvertureExtractV2Config $config,
        int $inputRows,
        int $missingId,
        int $duplicateRows,
        int $duplicateIds,
        array $outcomes,
    ): self {
        $r = new self($config, $inputRows);
        $r->rejections = array_fill_keys(OvertureV2Rejection::all(), 0);
        $r->rejections[OvertureV2Rejection::MISSING_SOURCE_ID] = $missingId;
        $r->rejections[OvertureV2Rejection::DUPLICATE_SOURCE_ID] = $duplicateRows;
        $r->duplicateIds = $duplicateIds;

        foreach ($outcomes as $outcome) {
            if ($outcome instanceof OvertureV2Record) {
                if ($outcome->isBase()) {
                    $r->base[] = $outcome;
                } else {
                    $r->supplementary[] = $outcome;
                }
                continue;
            }
            if (! array_key_exists($outcome, $r->rejections)) {
                throw new InvalidOvertureExtractV2("unknown rejection code {$outcome}");
            }
            $r->rejections[$outcome]++;
        }

        // strcmp, never <=>: PHP 8 compares numeric-looking strings as numbers, and the wire order
        // is promised byte-wise whatever the id format.
        usort($r->base, static fn (OvertureV2Record $a, OvertureV2Record $b): int => strcmp((string) $a->categoryKey, (string) $b->categoryKey) ?: strcmp($a->sourceRef, $b->sourceRef));
        usort($r->supplementary, static fn (OvertureV2Record $a, OvertureV2Record $b): int => strcmp((string) $a->sourceCategory, (string) $b->sourceCategory) ?: strcmp($a->sourceRef, $b->sourceRef));

        return $r;
    }

    private int $duplicateIds = 0;

    /** @return list<OvertureV2Record> the corpus lane, in wire order */
    public function base(): array
    {
        return $this->base;
    }

    /** @return list<OvertureV2Record> the matcher-analysis lane, in wire order */
    public function supplementary(): array
    {
        return $this->supplementary;
    }

    public function inputRows(): int
    {
        return $this->inputRows;
    }

    public function baseCount(): int
    {
        return count($this->base);
    }

    public function supplementaryCount(): int
    {
        return count($this->supplementary);
    }

    /** Base + supplementary: rows handed to offline matcher analysis. NOT a corpus count. */
    public function matcherAnalysisCount(): int
    {
        return $this->baseCount() + $this->supplementaryCount();
    }

    /** @return array<string, int> every rejection code, in check order, zeros included */
    public function rejections(): array
    {
        return $this->rejections;
    }

    public function rejectedCount(): int
    {
        return array_sum($this->rejections);
    }

    /** Every bounding-box row is in exactly one of: base, supplementary, one rejection. */
    public function isFullyAccounted(): bool
    {
        return $this->inputRows === $this->baseCount() + $this->supplementaryCount() + $this->rejectedCount();
    }

    /**
     * The eligibility funnel, derived from the single outcome of each row. Each stage is the
     * previous one minus the rejections of the checks between them.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $rj = $this->rejections;
        $valid = $this->inputRows - $rj[OvertureV2Rejection::MISSING_SOURCE_ID] - $rj[OvertureV2Rejection::DUPLICATE_SOURCE_ID]
            - $rj[OvertureV2Rejection::MALFORMED_FIELD] - $rj[OvertureV2Rejection::MALFORMED_GEOMETRY]
            - $rj[OvertureV2Rejection::OUTSIDE_BOUNDING_BOX];
        $confidence = $valid - $rj[OvertureV2Rejection::MALFORMED_CONFIDENCE] - $rj[OvertureV2Rejection::CONFIDENCE_BELOW_FLOOR];
        $status = $confidence - $rj[OvertureV2Rejection::STATUS_PERMANENTLY_CLOSED] - $rj[OvertureV2Rejection::STATUS_UNRECOGNISED];
        $candidates = $status - $this->baseCount();

        return [
            'bbox_input_rows' => $this->inputRows,
            'valid_identity_geometry_in_box' => $valid,
            'confidence_eligible' => $confidence,
            'confidence_and_status_eligible' => $status,
            'base_category_eligible' => $this->baseCount(),
            'supplementary_candidates' => $candidates,
            'supplementary_selected' => $this->supplementaryCount(),
            'supplementary_rule_not_satisfied' => $rj[OvertureV2Rejection::TAXONOMY_NULL] + $rj[OvertureV2Rejection::TAXONOMY_NOT_ALLOWLISTED],
        ];
    }

    /** @return array<string, mixed> tallies for the manifest; every map is key-sorted */
    public function tallies(): array
    {
        $count = static function (array $records, callable $key): array {
            $out = [];
            foreach ($records as $rec) {
                $k = $key($rec) ?? '(none)';
                $out[$k] = ($out[$k] ?? 0) + 1;
            }
            ksort($out, SORT_STRING);

            return $out;
        };

        $lanes = [];
        foreach ($this->supplementary as $rec) {
            foreach ($rec->rescueLanes as $lane) {
                $lanes[$lane] = ($lanes[$lane] ?? 0) + 1;
            }
        }
        ksort($lanes, SORT_STRING);

        return [
            'base_by_category' => $count($this->base, static fn (OvertureV2Record $r) => $r->categoryKey),
            'base_by_source_category' => $count($this->base, static fn (OvertureV2Record $r) => $r->sourceCategory),
            'base_by_region' => $count($this->base, static fn (OvertureV2Record $r) => $r->address['region']),
            'base_by_status' => $count($this->base, static fn (OvertureV2Record $r) => $r->operatingStatusKnown ? 'explicit_open' : 'unknown'),
            'supplementary_by_role' => $count($this->supplementary, static fn (OvertureV2Record $r) => $r->supplementaryRole),
            'supplementary_by_source_category' => $count($this->supplementary, static fn (OvertureV2Record $r) => $r->sourceCategory),
            'supplementary_rescue_candidates_by_lane' => $lanes,
            'duplicate_source_ids' => $this->duplicateIds,
        ];
    }

    /** @return array<string, mixed> */
    public function accounting(): array
    {
        return [
            'counts' => [
                'base_corpus_rows' => $this->baseCount(),
                'supplementary_rows' => $this->supplementaryCount(),
                'matcher_analysis_rows' => $this->matcherAnalysisCount(),
                'matcher_analysis_rows_note' => 'base + supplementary; NOT a corpus count',
                'rejected_rows' => $this->rejectedCount(),
                'bbox_input_rows' => $this->inputRows,
                'fully_accounted' => $this->isFullyAccounted(),
            ],
            'funnel' => $this->funnel(),
            'rejections' => $this->rejections,
            'tallies' => $this->tallies(),
            'recipe' => $this->config->pins(),
        ];
    }
}

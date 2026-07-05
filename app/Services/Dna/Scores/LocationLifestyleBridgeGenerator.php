<?php

namespace App\Services\Dna\Scores;

use App\Models\DnaScore;
use App\Models\PropertyLocationDna;
use App\Services\Dna\Scores\Contracts\DnaScoreGenerator;
use Illuminate\Support\Carbon;

/**
 * LocationLifestyleBridgeGenerator — brings the five EXISTING Location DNA
 * lifestyle scores (property_location_dna.lifestyle_json, LDNA_LIFESTYLE_V1)
 * into the unified dna_scores layer with F4 confidence + F5 explanations, per
 * the roadmap's "reuse, don't rebuild" principle.
 *
 * GOVERNANCE:
 *   - READ-ONLY of property_location_dna (never writes/modifies it).
 *   - Writes ONLY to dna_scores. No AI, no external calls, no recomputation of
 *     the location scores themselves — it bridges what LocationDnaLifestyle-
 *     ScoreService already produced.
 *
 * Confidence: these scores are objective geospatial enrichment. A score of 0
 * means the underlying thematic distance was absent (SCORE_ABSENT), so it is
 * bridged at low completeness/confidence; a computed score (even "far") means
 * the data was present.
 */
class LocationLifestyleBridgeGenerator implements DnaScoreGenerator
{
    public const VERSION = 'LOCATION_BRIDGE_V1';

    /** Objective geospatial enrichment: reliable, not certain. */
    private const SOURCE_RELIABILITY = 85;

    /** lifestyle_json key => [dna_scores score_key, human label]. */
    private const LDNA_SCORES = [
        'coastal_score'     => ['location_coastal', 'Coastal'],
        'walkability_score' => ['location_walkability', 'Walkability'],
        'convenience_score' => ['location_convenience', 'Convenience'],
        'commuter_score'    => ['location_commuter', 'Commuter'],
        'family_score'      => ['location_family', 'Family'],
    ];

    public function key(): string
    {
        return 'location_lifestyle_bridge';
    }

    /**
     * DnaScoreGenerator entrypoint (§ Phase 13). Delegates to generateForListing,
     * which is retained for existing direct callers.
     *
     * @param array{generated_by?:string,only_stale?:bool} $options
     * @return array<int,DnaScore>
     */
    public function generate(string $listingType, int $listingId, array $options = []): array
    {
        return $this->generateForListing($listingType, $listingId, $options);
    }

    /**
     * Bridge all five location lifestyle scores for one listing into dna_scores.
     *
     * @param array{generated_by?:string,only_stale?:bool} $options origin metadata
     *        (§ Phase 13); generated_by defaults to 'system'. When only_stale is
     *        true, a score whose persisted generator_version AND source_version
     *        already match is skipped.
     * @return array<int,DnaScore> the persisted rows (empty if no Location DNA)
     */
    public function generateForListing(string $listingType, int $listingId, array $options = []): array
    {
        $generatedBy = $options['generated_by'] ?? 'system';
        $onlyStale   = (bool) ($options['only_stale'] ?? false);

        $record = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->first();

        $lifestyle = $record?->lifestyle_json;
        if (! is_array($lifestyle)) {
            return [];
        }

        $sourceVersion = $lifestyle['version'] ?? null;
        $rows = [];

        foreach (self::LDNA_SCORES as $sourceKey => [$scoreKey, $label]) {
            if (! array_key_exists($sourceKey, $lifestyle) || ! is_numeric($lifestyle[$sourceKey])) {
                continue;
            }

            if ($onlyStale && $this->isCurrent($listingType, $listingId, $scoreKey, $sourceVersion)) {
                continue; // both generator and source versions already current
            }

            $value = (int) $lifestyle[$sourceKey];

            // 0 => underlying thematic input absent; otherwise present (incl. "far").
            $completeness = $value === 0 ? 30 : 100;
            $confidence   = (int) floor($completeness * self::SOURCE_RELIABILITY / 100);
            $confidence   = min($confidence, $completeness); // §F4 non-inflating

            $rows[] = DnaScore::updateOrCreate(
                [
                    'listing_type' => $listingType,
                    'listing_id'   => $listingId,
                    'score_key'    => $scoreKey,
                    'side'         => 'property',
                ],
                [
                    'value'             => $value,
                    'data_completeness' => $completeness,
                    'confidence'        => $confidence,
                    'explanation'       => $label . ' ' . $value . ' — derived from Location DNA enrichment (' . ($sourceVersion ?? 'unknown') . ').',
                    'inputs_json'       => [
                        'source'          => 'property_location_dna.lifestyle_json',
                        'source_key'      => $sourceKey,
                        'source_version'  => $sourceVersion,
                        'lifestyle_categories' => $lifestyle['lifestyle_categories'] ?? null,
                    ],
                    'version'           => self::VERSION,
                    // Provenance (§ Phase 13). source_version is the upstream
                    // Location DNA lifestyle version this score was bridged from.
                    'generated_by'      => $generatedBy,
                    'generator_version' => self::VERSION,
                    'source_version'    => $sourceVersion,
                    'computed_at'       => Carbon::now(),
                ]
            );
        }

        return $rows;
    }

    /**
     * True when the persisted bridged score already carries BOTH the current
     * generator version and the current upstream source version — so a bulk
     * (version-aware) pass may skip re-bridging it.
     */
    private function isCurrent(string $listingType, int $listingId, string $scoreKey, ?string $sourceVersion): bool
    {
        $stored = DnaScore::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->where('score_key', $scoreKey)
            ->where('side', 'property')
            ->first(['generator_version', 'source_version']);

        return $stored !== null
            && $stored->generator_version === self::VERSION
            && $stored->source_version === $sourceVersion;
    }
}

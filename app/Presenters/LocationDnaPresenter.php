<?php

namespace App\Presenters;

use App\Models\PropertyLocationDna;
use Illuminate\Support\Collection;

class LocationDnaPresenter
{
    private const SCORE_KEYS = [
        'coastal_score'     => 'Coastal',
        'walkability_score' => 'Walkability',
        'convenience_score' => 'Convenience',
        'commuter_score'    => 'Commuter',
        'family_score'      => 'Family',
    ];

    public static function lifestyleScores(PropertyLocationDna $dna): array
    {
        $lifestyle = $dna->lifestyle_json ?? [];
        $scores = [];
        foreach (self::SCORE_KEYS as $key => $label) {
            if (isset($lifestyle[$key])) {
                $scores[$label] = (int) $lifestyle[$key];
            }
        }
        arsort($scores);
        return $scores;
    }

    public static function lifestyleLabels(PropertyLocationDna $dna): array
    {
        $lifestyle = $dna->lifestyle_json ?? [];
        return $lifestyle['lifestyle_categories'] ?? [];
    }

    public static function locationNarrative(PropertyLocationDna $dna): ?string
    {
        $lifestyle = $dna->lifestyle_json ?? [];
        return $lifestyle['location_narrative'] ?? null;
    }

    public static function lifestyleVersion(PropertyLocationDna $dna): ?string
    {
        $lifestyle = $dna->lifestyle_json ?? [];
        return $lifestyle['version'] ?? null;
    }

    public static function poisByCategory(Collection $pois, int $topN = 3): array
    {
        $grouped = [];
        foreach ($pois->groupBy('poi_category') as $category => $group) {
            $grouped[$category] = $group->take($topN)->values();
        }
        ksort($grouped);
        return $grouped;
    }

    public static function geocodeInfo(PropertyLocationDna $dna): array
    {
        $summary = $dna->summary_json ?? [];
        return $summary['geocode'] ?? [];
    }
}

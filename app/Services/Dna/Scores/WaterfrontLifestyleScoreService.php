<?php

namespace App\Services\Dna\Scores;

use App\Services\Canonical\CanonicalListing;
use App\Services\Dna\Scores\Contracts\SymmetricScoreService;
use App\Services\Dna\Scores\Support\ScalarScoreHelpers;

/**
 * WaterfrontLifestyleScoreService — Beyond-MLS Wave 1 scalar score
 * (§8 Waterfront-Lifestyle: the on-water living experience, broader than
 * boating).
 *
 * Symmetric on the shared 0–100 axis:
 *   - PROPERTY: waterfront status, water access type, water view, frontage.
 *   - DEMAND: how much the searcher wants water (view_preference).
 *
 * GOVERNANCE: deterministic; no AI, no external calls, no DB writes; reads ONLY
 * canonical fields (§F1); F4 confidence + F5 explanation; §F3-safe (objective
 * attributes + self-declared view preference only).
 */
class WaterfrontLifestyleScoreService implements SymmetricScoreService
{
    use ScalarScoreHelpers;

    public const VERSION  = 'WATERFRONT_LIFESTYLE_V1';
    public const SCORE_KEY = 'waterfront_lifestyle';

    private const WATER_WORDS = ['water', 'lake', 'gulf', 'ocean', 'canal', 'bay', 'river', 'intracoastal', 'pond', 'waterfront', 'beach'];

    public function scoreKey(): string
    {
        return self::SCORE_KEY;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function scoreProperty(CanonicalListing $listing): array
    {
        $waterfront = $listing->get('property.waterfront');           // ?bool
        $access     = $listing->get('property.water_access');         // ?array
        $view       = $listing->get('property.water_view');           // ?array
        $frontage   = $listing->get('property.water_frontage_feet');  // ?float

        $completeness = 0;
        if ($listing->present('property.waterfront'))          $completeness += 35;
        if ($listing->present('property.water_view'))          $completeness += 20;
        if ($listing->present('property.water_access'))        $completeness += 20;
        if ($listing->present('property.water_frontage_feet')) $completeness += 15;
        if ($listing->present('property.view_preference'))     $completeness += 10;

        $inputs = [
            'waterfront'          => $waterfront,
            'water_access'        => $access,
            'water_view'          => $view,
            'water_frontage_feet' => $frontage,
        ];

        if ($completeness === 0) {
            return $this->result(self::SCORE_KEY, 'property', self::VERSION, null, 0,
                'Insufficient data to compute a Waterfront-Lifestyle score.', $inputs);
        }

        $hasAccess = $listing->present('property.water_access');
        $hasView   = $listing->present('property.water_view') && ! $this->containsAny($view, ['none']);
        $waterOriented = ($waterfront === true) || $hasAccess || $hasView;

        if (! $waterOriented) {
            return $this->result(self::SCORE_KEY, 'property', self::VERSION, 5, $completeness,
                'Waterfront-Lifestyle 5: not a waterfront property.', $inputs);
        }

        $value = 50;
        $clauses = [];

        if ($waterfront === true) { $value += 15; $clauses[] = 'direct waterfront'; }
        if ($hasAccess) { $value += 10; $clauses[] = 'water access'; }
        if ($hasView) { $value += 10; $clauses[] = 'water view'; }

        if ($frontage !== null) {
            if ($frontage >= 80) { $value += 15; $clauses[] = $this->num($frontage) . ' ft frontage'; }
            elseif ($frontage >= 30) { $value += 8; $clauses[] = $this->num($frontage) . ' ft frontage'; }
            elseif ($frontage > 0) { $value += 3; }
        }

        $value = max(0, min(100, $value));
        $summary = $clauses === [] ? 'water-oriented' : implode('; ', $clauses);

        return $this->result(self::SCORE_KEY, 'property', self::VERSION, $value, $completeness,
            'Waterfront-Lifestyle ' . $value . ': ' . $summary . '.', $inputs);
    }

    public function scoreDemand(CanonicalListing $listing): array
    {
        $viewPref = $listing->get('demand.view_preference'); // ?array

        $completeness = $listing->present('demand.view_preference') ? 100 : 0;
        $inputs = ['view_preference' => $viewPref];

        if ($completeness === 0) {
            return $this->result(self::SCORE_KEY, 'demand', self::VERSION, null, 0,
                'Insufficient data to compute a waterfront preference weight.', $inputs);
        }

        if ($this->containsAny($viewPref, self::WATER_WORDS)) {
            return $this->result(self::SCORE_KEY, 'demand', self::VERSION, 80, $completeness,
                'Waterfront preference 80: searcher prefers a water view/setting.', $inputs);
        }

        return $this->result(self::SCORE_KEY, 'demand', self::VERSION, 20, $completeness,
            'Waterfront preference 20: no water preference indicated.', $inputs);
    }
}

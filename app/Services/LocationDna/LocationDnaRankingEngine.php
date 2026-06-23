<?php

namespace App\Services\LocationDna;

/**
 * LocationDnaRankingEngine
 *
 * Scores and re-ranks a set of POI candidates for a given category using
 * per-category profiles from LocationDnaRankingProfileService.
 *
 * For each candidate, four sub-scores are computed (each 0–100):
 *   category_match_score      — type-overlap match vs profile preferred/penalized lists
 *   review_confidence_score   — review count relative to per-category confidence threshold
 *   consumer_relevance_score  — weighted combination of rating and review confidence
 *   ranking_score             — final weighted sum of all sub-scores + distance score
 *
 * A ranking_reasons_json array captures human-readable positive/negative signals.
 *
 * Candidates are returned sorted by ranking_score descending (rank 1 = highest scoring).
 *
 * This class performs no I/O, no DB access, and no API calls. Pure computation only.
 */
class LocationDnaRankingEngine
{
    /**
     * Score the given candidates for the category and return them sorted
     * by ranking_score descending with scores and reasons attached.
     *
     * @param  string $category     Canonical category key (e.g. 'grocery_store')
     * @param  array  $candidates   Array of raw Google Places result objects
     * @param  float  $sourceLat    Source property latitude
     * @param  float  $sourceLng    Source property longitude
     * @return array                Candidates with score fields attached, sorted by ranking_score desc
     */
    public function rankCandidates(
        string $category,
        array  $candidates,
        float  $sourceLat,
        float  $sourceLng,
    ): array {
        if (empty($candidates)) {
            return [];
        }

        $profile = LocationDnaRankingProfileService::getProfile($category);

        // Compute distances for all candidates (needed for normalized distance score)
        $distances = [];
        foreach ($candidates as $i => $place) {
            $lat = (float) ($place['geometry']['location']['lat'] ?? 0);
            $lng = (float) ($place['geometry']['location']['lng'] ?? 0);
            $distances[$i] = $this->haversineDistanceMiles($sourceLat, $sourceLng, $lat, $lng);
        }

        $maxDistance = max($distances) ?: 1.0;

        $scored = [];
        foreach ($candidates as $i => $place) {
            $types           = $place['types'] ?? [];
            $rating          = isset($place['rating']) ? (float) $place['rating'] : null;
            $reviewCount     = (int) ($place['user_ratings_total'] ?? 0);
            $distanceMiles   = $distances[$i];
            $name            = $place['name'] ?? '';

            $reasons = [];

            // ── category_match_score (0–100) ──────────────────────────────────
            $matchScore = $this->computeCategoryMatchScore($types, $profile, $name, $reasons);

            // ── review_confidence_score (0–100) ───────────────────────────────
            $confidenceScore = $this->computeReviewConfidenceScore(
                $reviewCount,
                $profile['min_confidence_reviews'],
                $reasons
            );

            // ── consumer_relevance_score (0–100) ─────────────────────────────
            $relevanceScore = $this->computeConsumerRelevanceScore(
                $rating,
                $reviewCount,
                $confidenceScore,
                $profile,
                $reasons
            );

            // ── distance score (0–100, inverted — nearer = higher) ────────────
            $distanceScore = $this->computeDistanceScore($distanceMiles, $maxDistance, $reasons);

            // ── ranking_score — final weighted sum ────────────────────────────
            $totalWeight = $profile['match_weight']
                + $profile['review_weight']
                + $profile['relevance_weight']
                + $profile['distance_weight'];

            if ($totalWeight <= 0) {
                $totalWeight = 1.0;
            }

            $rankingScore = (
                $matchScore      * $profile['match_weight']
                + $confidenceScore * $profile['review_weight']
                + $relevanceScore  * $profile['relevance_weight']
                + $distanceScore   * $profile['distance_weight']
            ) / $totalWeight;

            $scored[$i] = array_merge($place, [
                '_ranking' => [
                    'category_match_score'     => round($matchScore, 2),
                    'review_confidence_score'  => round($confidenceScore, 2),
                    'consumer_relevance_score' => round($relevanceScore, 2),
                    'ranking_score'            => round($rankingScore, 2),
                    'ranking_reasons_json'     => $reasons,
                ],
            ]);
        }

        // Sort by ranking_score descending
        usort($scored, fn($a, $b) => $b['_ranking']['ranking_score'] <=> $a['_ranking']['ranking_score']);

        return array_values($scored);
    }

    // =========================================================================
    // Score computation methods
    // =========================================================================

    /**
     * Compute category_match_score (0–100).
     *
     * Base score: 50 (neutral — we have a result at all).
     * Preferred type hit: +25 per match (capped at +40 total boost).
     * Penalized type hit: -30 per match (capped at -50 total penalty).
     * Final clamp: [0, 100].
     */
    private function computeCategoryMatchScore(
        array  $types,
        array  $profile,
        string $name,
        array  &$reasons,
    ): float {
        $score = 50.0;
        $boostTotal   = 0.0;
        $penaltyTotal = 0.0;

        foreach ($profile['preferred_types'] as $pType) {
            if (in_array($pType, $types, true)) {
                $boost = 25.0;
                $boostTotal += $boost;
                $reasons[] = "+ {$pType} type";
                if ($boostTotal >= 40.0) {
                    break;
                }
            }
        }

        foreach ($profile['penalized_types'] as $badType) {
            if (in_array($badType, $types, true)) {
                $penalty = 30.0;
                $penaltyTotal += $penalty;
                $reasons[] = "- penalized type: {$badType}";
                if ($penaltyTotal >= 50.0) {
                    break;
                }
            }
        }

        $score = $score + min($boostTotal, 40.0) - min($penaltyTotal, 50.0);

        return max(0.0, min(100.0, $score));
    }

    /**
     * Compute review_confidence_score (0–100).
     *
     * Uses a logarithmic scale so the curve is meaningful across a wide range:
     *   0 reviews  → 0
     *   min_confidence_reviews → ~70
     *   5× min_confidence_reviews → 100
     */
    private function computeReviewConfidenceScore(
        int    $reviewCount,
        int    $minConfidence,
        array  &$reasons,
    ): float {
        if ($reviewCount <= 0) {
            $reasons[] = '- no reviews';
            return 0.0;
        }

        if ($minConfidence <= 0) {
            $minConfidence = 30;
        }

        // Logarithmic confidence: reaches 100 at 5× the min_confidence threshold
        $maxReviews = $minConfidence * 5;
        $score = (log($reviewCount + 1) / log($maxReviews + 1)) * 100.0;
        $score = max(0.0, min(100.0, $score));

        if ($reviewCount >= $minConfidence * 5) {
            $reasons[] = "+ very high review count ({$reviewCount})";
        } elseif ($reviewCount >= $minConfidence) {
            $reasons[] = "+ {$reviewCount} reviews";
        } elseif ($reviewCount >= 10) {
            $reasons[] = "~ {$reviewCount} reviews (below confidence threshold of {$minConfidence})";
        } else {
            $reasons[] = "- only {$reviewCount} reviews";
        }

        return round($score, 2);
    }

    /**
     * Compute consumer_relevance_score (0–100).
     *
     * Combines rating (0–5 scaled to 0–100) weighted by confidence (0–1).
     * A place with no rating gets a neutral mid-point score.
     */
    private function computeConsumerRelevanceScore(
        ?float $rating,
        int    $reviewCount,
        float  $confidenceScore,
        array  $profile,
        array  &$reasons,
    ): float {
        if ($rating === null) {
            $reasons[] = '~ no rating available';
            return 40.0;
        }

        $minConfidence     = $profile['min_confidence_reviews'];
        $highRating        = $profile['relevance_signals']['high_rating_threshold'] ?? 4.0;
        $highReviewCount   = $profile['relevance_signals']['high_review_threshold'] ?? 50;

        // Rating scaled 0→100 (5.0★ = 100, 0.0★ = 0)
        $ratingScore = ($rating / 5.0) * 100.0;

        // Confidence multiplier: linear ramp from 0 to 1.0 over [0, min_confidence_reviews]
        $confidenceMultiplier = min($reviewCount / max($minConfidence, 1), 1.0);

        // Blend: 60% weighted-rating + 40% pure-rating to avoid crushing decent low-volume places
        $score = 0.60 * ($ratingScore * $confidenceMultiplier) + 0.40 * $ratingScore;
        $score = max(0.0, min(100.0, $score));

        if ($rating >= $highRating && $reviewCount >= $highReviewCount) {
            $reasons[] = "+ high rating ({$rating}★) with {$reviewCount} reviews";
        } elseif ($rating >= $highRating) {
            $reasons[] = "+ good rating ({$rating}★)";
        } elseif ($rating < 3.0) {
            $reasons[] = "- low rating ({$rating}★)";
        }

        return round($score, 2);
    }

    /**
     * Compute distance score (0–100), inverted so nearer = higher.
     *
     * Nearest candidate gets 100, farthest gets ~20 (not 0, to avoid harshly
     * penalizing modestly-farther excellent results).
     */
    private function computeDistanceScore(
        float  $distanceMiles,
        float  $maxDistance,
        array  &$reasons,
    ): float {
        if ($maxDistance <= 0) {
            return 100.0;
        }

        // Scale: nearest → 100, farthest → 20
        $normalised = $distanceMiles / $maxDistance;
        $score = 100.0 - ($normalised * 80.0);
        $score = max(20.0, min(100.0, $score));

        if ($distanceMiles < 0.5) {
            $reasons[] = '+ very close distance';
        } elseif ($distanceMiles > $maxDistance * 0.75) {
            $reasons[] = '- farther distance';
        } elseif ($distanceMiles > $maxDistance * 0.50) {
            $reasons[] = '~ slightly farther distance';
        }

        return round($score, 2);
    }

    /**
     * Haversine formula — straight-line distance between two coordinates in miles.
     */
    private function haversineDistanceMiles(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $earthRadius = 3958.8;
        $dLat        = deg2rad($lat2 - $lat1);
        $dLng        = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

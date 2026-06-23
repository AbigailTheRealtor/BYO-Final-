<?php

namespace App\Services\LocationDna;

/**
 * LocationDnaRankingProfileService
 *
 * Provides per-category ranking profiles used by LocationDnaRankingEngine.
 *
 * Each profile defines:
 *   preferred_types      — Google type strings that boost category_match_score
 *   penalized_types      — Google type strings that reduce category_match_score
 *   review_weight        — weight of review_confidence_score in final ranking_score (0.0–1.0)
 *   distance_weight      — weight of inverted distance score in final ranking_score (0.0–1.0)
 *   relevance_weight     — weight of consumer_relevance_score in final ranking_score (0.0–1.0)
 *   match_weight         — weight of category_match_score in final ranking_score (0.0–1.0)
 *   min_confidence_reviews — review count at which a place gets full confidence weight
 *   relevance_signals    — named signals affecting consumer_relevance_score
 *
 * Weights within a profile should sum to approximately 1.0 but are normalized
 * by the engine, so exact sums are not required.
 */
class LocationDnaRankingProfileService
{
    /**
     * Return the ranking profile for the given category key.
     * Returns the 'default' profile if no specific profile is defined.
     */
    public static function getProfile(string $category): array
    {
        return static::profiles()[$category] ?? static::profiles()['default'];
    }

    /**
     * Return all ranking profiles.
     */
    public static function profiles(): array
    {
        return [

            // ── Grocery Store ─────────────────────────────────────────────────
            // Supermarkets and full-service grocery chains are strongly preferred.
            // Gas stations and convenience stores are penalized heavily because
            // Google dual-types them as grocery_or_supermarket.
            'grocery_store' => [
                'preferred_types'       => ['supermarket', 'grocery_or_supermarket'],
                'penalized_types'       => ['gas_station', 'convenience_store', 'car_wash'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.25,
                'distance_weight'       => 0.10,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 200,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Restaurant / Closest Dining ───────────────────────────────────
            // Rating and review confidence matter most. Distance is secondary.
            'restaurant' => [
                'preferred_types'       => ['restaurant', 'meal_delivery', 'meal_takeaway'],
                'penalized_types'       => ['bar', 'night_club', 'gas_station'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.40,
                'match_weight'          => 0.15,
                'distance_weight'       => 0.15,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.2,
                ],
            ],

            // ── Top Rated Dining ──────────────────────────────────────────────
            // Same profile as restaurant — engine applies extra confidence weighting
            // on this derived category via the quality-score formula in the distance service.
            'top_rated_dining' => [
                'preferred_types'       => ['restaurant', 'meal_delivery', 'meal_takeaway', 'cafe'],
                'penalized_types'       => ['bar', 'night_club', 'gas_station'],
                'review_weight'         => 0.40,
                'relevance_weight'      => 0.40,
                'match_weight'          => 0.10,
                'distance_weight'       => 0.10,
                'min_confidence_reviews' => 100,
                'relevance_signals'     => [
                    'high_review_threshold' => 200,
                    'high_rating_threshold' => 4.5,
                ],
            ],

            // ── Beach ─────────────────────────────────────────────────────────
            // Named access points and public beaches outrank bare municipality names.
            // Review volume strongly signals a recognized beach destination.
            'beach' => [
                'preferred_types'       => ['natural_feature', 'park', 'point_of_interest'],
                'penalized_types'       => ['locality', 'political', 'administrative_area_level_2'],
                'review_weight'         => 0.35,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.10,
                'min_confidence_reviews' => 100,
                'relevance_signals'     => [
                    'high_review_threshold' => 500,
                    'high_rating_threshold' => 4.3,
                ],
            ],

            // ── Beach Access ──────────────────────────────────────────────────
            'beach_access' => [
                'preferred_types'       => ['point_of_interest', 'park', 'natural_feature'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.20,
                'min_confidence_reviews' => 20,
                'relevance_signals'     => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Park ──────────────────────────────────────────────────────────
            // Developed recreational parks with many reviews (city/county parks)
            // outrank unnamed natural features or small preserves.
            'park' => [
                'preferred_types'       => ['park', 'point_of_interest'],
                'penalized_types'       => ['natural_feature', 'locality', 'political', 'sublocality'],
                'review_weight'         => 0.35,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.10,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 200,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Waterfront Park ───────────────────────────────────────────────
            'waterfront_park' => [
                'preferred_types'       => ['park', 'point_of_interest'],
                'penalized_types'       => ['natural_feature', 'locality'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.15,
                'min_confidence_reviews' => 30,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Dog Park ─────────────────────────────────────────────────────
            'dog_park' => [
                'preferred_types'       => ['park', 'point_of_interest'],
                'penalized_types'       => ['natural_feature', 'locality'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.15,
                'min_confidence_reviews' => 20,
                'relevance_signals'     => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── School ────────────────────────────────────────────────────────
            // Distance matters most for schools. Rating signal is weaker because
            // many schools lack Google reviews.
            // Accredited institutions (school, university, secondary_school) are
            // preferred so they reliably outrank enrichment studios and coaching
            // businesses that slip past the name-based exclusion filter.
            'school' => [
                'preferred_types'       => [
                    'school',
                    'university',
                    'secondary_school',
                    'primary_school',
                    'point_of_interest',
                ],
                'penalized_types'       => [
                    'locality',
                    'political',
                    'gym',
                    'beauty_salon',
                    'spa',
                ],
                'review_weight'         => 0.15,
                'relevance_weight'      => 0.20,
                'match_weight'          => 0.25,
                'distance_weight'       => 0.40,
                'min_confidence_reviews' => 10,
                'relevance_signals'     => [
                    'high_review_threshold' => 20,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Hospital ──────────────────────────────────────────────────────
            // Legitimate acute-care facilities (hospital, emergency_room,
            // medical_center, urgent_care, doctor) are strongly preferred.
            // Boosted match_weight ensures a real hospital reliably outranks
            // any specialist-only or aesthetic-medicine practice that slips
            // past the exclusion filter.
            'hospital' => [
                'preferred_types'       => [
                    'hospital',
                    'emergency_room',
                    'medical_center',
                    'urgent_care',
                    'doctor',
                    'health',
                ],
                'penalized_types'       => ['veterinary_care', 'pharmacy', 'beauty_salon', 'spa'],
                'review_weight'         => 0.20,
                'relevance_weight'      => 0.25,
                'match_weight'          => 0.35,
                'distance_weight'       => 0.20,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Pharmacy ──────────────────────────────────────────────────────
            'pharmacy' => [
                'preferred_types'       => ['pharmacy', 'drugstore'],
                'penalized_types'       => ['veterinary_care', 'hospital', 'health'],
                'review_weight'         => 0.20,
                'relevance_weight'      => 0.30,
                'match_weight'          => 0.25,
                'distance_weight'       => 0.25,
                'min_confidence_reviews' => 20,
                'relevance_signals'     => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 3.5,
                ],
            ],

            // ── Golf Course ───────────────────────────────────────────────────
            // Regulation courses preferred. Mini-golf and adventure golf are
            // already excluded upstream by the exclusion filter.
            'golf_course' => [
                'preferred_types'       => ['point_of_interest', 'establishment'],
                'penalized_types'       => ['amusement_park', 'tourist_attraction'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.30,
                'match_weight'          => 0.15,
                'distance_weight'       => 0.25,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Marina ────────────────────────────────────────────────────────
            'marina' => [
                'preferred_types'       => ['point_of_interest', 'establishment'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.30,
                'relevance_weight'      => 0.30,
                'match_weight'          => 0.15,
                'distance_weight'       => 0.25,
                'min_confidence_reviews' => 30,
                'relevance_signals'     => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Boat Ramp ─────────────────────────────────────────────────────
            'boat_ramp' => [
                'preferred_types'       => ['point_of_interest', 'park'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.25,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.30,
                'min_confidence_reviews' => 20,
                'relevance_signals'     => [
                    'high_review_threshold' => 30,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Gym ───────────────────────────────────────────────────────────
            'gym' => [
                'preferred_types'       => ['gym', 'health', 'point_of_interest'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.20,
                'min_confidence_reviews' => 30,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Fitness Center ────────────────────────────────────────────────
            'fitness_center' => [
                'preferred_types'       => ['gym', 'health', 'point_of_interest'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.20,
                'min_confidence_reviews' => 30,
                'relevance_signals'     => [
                    'high_review_threshold' => 100,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Gas Station ───────────────────────────────────────────────────
            // Convenience use — proximity is the dominant signal. A gas station
            // one block away is far more useful than an excellent one two miles
            // out. Review volume matters little; fuel quality is largely uniform.
            // Penalize restaurant/bar types to avoid misclassified multi-use sites.
            'gas_station' => [
                'preferred_types'        => ['gas_station', 'fuel'],
                'penalized_types'        => ['restaurant', 'bar'],
                'review_weight'          => 0.10,
                'relevance_weight'       => 0.15,
                'match_weight'           => 0.20,
                'distance_weight'        => 0.55,
                'min_confidence_reviews' => 20,
                'relevance_signals'      => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Transit Station ───────────────────────────────────────────────
            // Proximity is overwhelmingly the primary value — a transit stop
            // five blocks away is nearly useless vs. one on the corner. Review
            // and rating signals carry almost no consumer weight for transit.
            // Penalize parking facilities which Google sometimes tags as transit.
            'transit_station' => [
                'preferred_types'        => [
                    'transit_station',
                    'subway_station',
                    'bus_station',
                    'train_station',
                    'light_rail_station',
                ],
                'penalized_types'        => ['parking'],
                'review_weight'          => 0.05,
                'relevance_weight'       => 0.10,
                'match_weight'           => 0.20,
                'distance_weight'        => 0.65,
                'min_confidence_reviews' => 10,
                'relevance_signals'      => [
                    'high_review_threshold' => 30,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Coffee Shop ───────────────────────────────────────────────────
            // Quality matters — a great independent café or recognized chain is
            // preferred over a fast-food drive-through serving coffee as a side.
            // Review volume is the strongest quality signal here (a Starbucks
            // with 1 000 reviews is not the same as a no-name gas-station café).
            // Prefers cafe/coffee_shop types; penalizes fast-food proxies.
            'coffee_shop' => [
                'preferred_types'        => ['cafe', 'coffee_shop'],
                'penalized_types'        => ['fast_food', 'meal_takeaway'],
                'review_weight'          => 0.30,
                'relevance_weight'       => 0.25,
                'match_weight'           => 0.15,
                'distance_weight'        => 0.30,
                'min_confidence_reviews' => 50,
                'relevance_signals'      => [
                    'high_review_threshold' => 200,
                    'high_rating_threshold' => 4.2,
                ],
            ],

            // ── Shopping Center ───────────────────────────────────────────────
            'shopping_center' => [
                'preferred_types'       => ['shopping_mall', 'department_store', 'point_of_interest'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.35,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.20,
                'min_confidence_reviews' => 50,
                'relevance_signals'     => [
                    'high_review_threshold' => 200,
                    'high_rating_threshold' => 4.0,
                ],
            ],

            // ── Default fallback profile ──────────────────────────────────────
            'default' => [
                'preferred_types'       => ['point_of_interest', 'establishment'],
                'penalized_types'       => ['locality', 'political'],
                'review_weight'         => 0.25,
                'relevance_weight'      => 0.30,
                'match_weight'          => 0.20,
                'distance_weight'       => 0.25,
                'min_confidence_reviews' => 30,
                'relevance_signals'     => [
                    'high_review_threshold' => 50,
                    'high_rating_threshold' => 4.0,
                ],
            ],
        ];
    }
}

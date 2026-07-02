<?php

namespace App\Services\Dna\Scores;

use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Canonical\CanonicalListing;
use App\Services\Dna\Confidence\ConfidenceCalculator;
use App\Services\Dna\Scores\Contracts\SymmetricScoreService;

/**
 * PetFriendlinessScoreService — Beyond-MLS Wave 1 / Phase 2 first slice.
 *
 * Deterministic, symmetric Pet-Friendliness scoring on the shared 0–100 axis
 * (§8 of the roadmap):
 *   - PROPERTY side: how accommodating a listing's pet POLICY is.
 *   - DEMAND side: how much pet-friendliness matters to a searcher (their pet
 *     PROFILE), expressed as a preference weight on the same axis.
 *
 * Every result carries a data_completeness (§F4 coverage), a non-inflating
 * confidence (§F4), and a neutral, factual explanation (§F5).
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service MUST NEVER:
 *   - Make external API calls or invoke any AI/OpenAI/Ask AI pipeline.
 *   - Write to the database (persistence lives in the generic SymmetricScoreDnaGenerator).
 *   - Read a source-specific column — it reads ONLY canonical fields (§F1).
 *
 * FAIR HOUSING (§F3):
 *   Service-animal / support-animal status is a proxy for DISABILITY, a
 *   protected class. It is therefore DELIBERATELY EXCLUDED from every score and
 *   from data-completeness. Service/support animals must be accommodated as a
 *   matter of law regardless of pet policy; they are not a "friendliness"
 *   scoring input and must never influence ranking. (C1/C3.)
 * ==================================================================================
 */
class PetFriendlinessScoreService implements SymmetricScoreService
{
    public const VERSION   = 'PET_FRIENDLINESS_V1';
    public const SCORE_KEY  = 'pet_friendliness';
    public const SIDE_PROPERTY = 'property';
    public const SIDE_DEMAND   = 'demand';

    public function scoreKey(): string
    {
        return self::SCORE_KEY;
    }

    /**
     * Data-completeness weights for the property (policy) side. Sum = 100.
     * pets_allowed is the decisive field, hence the dominant weight.
     */
    private const PROPERTY_WEIGHTS = [
        'pet.policy.pets_allowed'           => 40,
        'pet.policy.max_weight_lbs'         => 20,
        'pet.policy.species_allowed'        => 20,
        'pet.policy.has_breed_restrictions' => 10,
        'fees'                              => 10, // any of deposit/fee/monthly/rent
    ];

    /** Data-completeness weights for the demand (profile) side. Sum = 100. */
    private const DEMAND_WEIGHTS = [
        'pet.profile.has_pets'   => 55,
        'pet.profile.count'      => 20,
        'pet.profile.weight_lbs' => 20,
        'species_or_breed'       => 5, // species OR breed present
    ];

    private const FEE_KEYS = [
        'pet.policy.deposit_amount',
        'pet.policy.monthly_fee',
        'pet.policy.rent',
        'pet.policy.fee',
    ];

    /**
     * Score the PROPERTY (supply) side from a listing's pet policy.
     *
     * @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string}
     */
    public function scoreProperty(CanonicalListing $listing): array
    {
        $petsAllowed = $listing->get('pet.policy.pets_allowed');       // ?bool
        $maxWeight   = $listing->get('pet.policy.max_weight_lbs');     // ?float
        $species     = $listing->get('pet.policy.species_allowed');    // ?array
        $hasBreed    = $listing->get('pet.policy.has_breed_restrictions'); // ?bool

        $feePresent = false;
        $recurringFee = false;
        $oneTimeFee = false;
        foreach (self::FEE_KEYS as $k) {
            if ($listing->present($k)) {
                $feePresent = true;
                $amount = (float) $listing->get($k, 0);
                if (in_array($k, ['pet.policy.monthly_fee', 'pet.policy.rent'], true) && $amount > 0) {
                    $recurringFee = true;
                } elseif ($amount > 0) {
                    $oneTimeFee = true;
                }
            }
        }

        // ── data completeness ────────────────────────────────────────────────
        $completeness = 0;
        if ($listing->present('pet.policy.pets_allowed'))           $completeness += self::PROPERTY_WEIGHTS['pet.policy.pets_allowed'];
        if ($listing->present('pet.policy.max_weight_lbs'))         $completeness += self::PROPERTY_WEIGHTS['pet.policy.max_weight_lbs'];
        if ($listing->present('pet.policy.species_allowed'))        $completeness += self::PROPERTY_WEIGHTS['pet.policy.species_allowed'];
        if ($listing->present('pet.policy.has_breed_restrictions')) $completeness += self::PROPERTY_WEIGHTS['pet.policy.has_breed_restrictions'];
        if ($feePresent)                                            $completeness += self::PROPERTY_WEIGHTS['fees'];

        $inputs = [
            'pets_allowed'           => $petsAllowed,
            'max_weight_lbs'         => $maxWeight,
            'species_allowed'        => $species,
            'has_breed_restrictions' => $hasBreed,
            'has_pet_fees'           => $feePresent,
        ];

        // Decisive input absent → withhold a value, report zero confidence.
        if ($petsAllowed === null) {
            return $this->result(
                self::SIDE_PROPERTY,
                null,
                $completeness,
                0,
                'Insufficient pet-policy data to compute a Pet-Friendliness score.',
                $inputs
            );
        }

        if ($petsAllowed === false) {
            return $this->result(
                self::SIDE_PROPERTY,
                5,
                $completeness,
                ConfidenceCalculator::derive($completeness, ByoListingAdapter::SOURCE_RELIABILITY),
                'Pet-Friendliness 5: pets are not permitted at this property.',
                $inputs
            );
        }

        // pets permitted → build up from a base.
        $value = 55;
        $clauses = ['pets permitted'];

        if ($maxWeight === null) {
            $value += 15;
            $clauses[] = 'no stated weight limit';
        } elseif ($maxWeight >= 60) {
            $value += 15;
            $clauses[] = 'generous weight limit (' . $this->num($maxWeight) . ' lbs)';
        } elseif ($maxWeight >= 30) {
            $value += 8;
            $clauses[] = 'weight limit ' . $this->num($maxWeight) . ' lbs';
        } else {
            $value += 2;
            $clauses[] = 'low weight limit (' . $this->num($maxWeight) . ' lbs)';
        }

        if (is_array($species) && count($species) >= 2) {
            $value += 15;
            $clauses[] = 'multiple species allowed';
        } elseif (is_array($species) && count($species) === 1) {
            $value += 8;
            $clauses[] = 'one species allowed';
        } else {
            $value += 5; // unknown species policy — neutral
        }

        if ($hasBreed === false) {
            $value += 10;
            $clauses[] = 'no breed restrictions';
        } elseif ($hasBreed === true) {
            $value += 2;
            $clauses[] = 'breed restrictions apply';
        } else {
            $value += 5; // unknown — neutral
        }

        if ($recurringFee) {
            $value += 0;
            $clauses[] = 'recurring pet fee';
        } elseif ($oneTimeFee) {
            $value += 3;
            $clauses[] = 'one-time pet fee';
        } elseif ($feePresent) {
            $value += 5;
            $clauses[] = 'no pet fees';
        } else {
            $value += 3; // fees unknown — neutral
        }

        $value = $this->clampScore($value);

        return $this->result(
            self::SIDE_PROPERTY,
            $value,
            $completeness,
            ConfidenceCalculator::derive($completeness, ByoListingAdapter::SOURCE_RELIABILITY),
            'Pet-Friendliness ' . $value . ': ' . implode('; ', $clauses) . '.',
            $inputs
        );
    }

    /**
     * Score the DEMAND (searcher) side — how much pet-friendliness matters,
     * as a preference weight on the same 0–100 axis.
     *
     * @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string}
     */
    public function scoreDemand(CanonicalListing $listing): array
    {
        $hasPets = $listing->get('pet.profile.has_pets');     // ?bool
        $count   = $listing->get('pet.profile.count');        // ?float
        $weight  = $listing->get('pet.profile.weight_lbs');   // ?float
        $species = $listing->get('pet.profile.species');      // ?array
        $breed   = $listing->get('pet.profile.breed');        // ?string

        $completeness = 0;
        if ($listing->present('pet.profile.has_pets'))   $completeness += self::DEMAND_WEIGHTS['pet.profile.has_pets'];
        if ($listing->present('pet.profile.count'))      $completeness += self::DEMAND_WEIGHTS['pet.profile.count'];
        if ($listing->present('pet.profile.weight_lbs')) $completeness += self::DEMAND_WEIGHTS['pet.profile.weight_lbs'];
        if ($listing->present('pet.profile.species') || $listing->present('pet.profile.breed')) {
            $completeness += self::DEMAND_WEIGHTS['species_or_breed'];
        }

        $inputs = [
            'has_pets'   => $hasPets,
            'count'      => $count,
            'weight_lbs' => $weight,
            'species'    => $species,
            'breed'      => $breed,
        ];

        if ($hasPets === null) {
            return $this->result(
                self::SIDE_DEMAND,
                null,
                $completeness,
                0,
                'Insufficient pet-profile data to compute a pet-priority weight.',
                $inputs
            );
        }

        if ($hasPets === false) {
            return $this->result(
                self::SIDE_DEMAND,
                10,
                $completeness,
                ConfidenceCalculator::derive($completeness, ByoListingAdapter::SOURCE_RELIABILITY),
                'Pet priority 10: no pets indicated; pet-friendliness is a low priority.',
                $inputs
            );
        }

        $value = 65;
        $clauses = ['has pets'];

        if ($count !== null && $count >= 2) {
            $value += 15;
            $clauses[] = 'multiple pets (' . $this->num($count) . ')';
        } elseif ($count !== null && $count >= 1) {
            $value += 8;
            $clauses[] = 'one pet';
        } else {
            $value += 5;
        }

        if ($weight !== null && $weight >= 50) {
            $value += 15;
            $clauses[] = 'large pet (' . $this->num($weight) . ' lbs)';
        } elseif ($weight !== null && $weight >= 25) {
            $value += 8;
            $clauses[] = 'medium pet (' . $this->num($weight) . ' lbs)';
        } elseif ($weight !== null) {
            $value += 3;
            $clauses[] = 'small pet (' . $this->num($weight) . ' lbs)';
        } else {
            $value += 5;
        }

        if ((is_array($species) && $species !== []) || ($breed !== null && $breed !== '')) {
            $value += 5;
        } else {
            $value += 2;
        }

        $value = $this->clampScore($value);

        return $this->result(
            self::SIDE_DEMAND,
            $value,
            $completeness,
            ConfidenceCalculator::derive($completeness, ByoListingAdapter::SOURCE_RELIABILITY),
            'Pet priority ' . $value . ': ' . implode('; ', $clauses) . '.',
            $inputs
        );
    }

    /**
     * @param array<string,mixed> $inputs
     * @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string}
     */
    private function result(string $side, ?int $value, int $completeness, int $confidence, string $explanation, array $inputs): array
    {
        return [
            'score_key'         => self::SCORE_KEY,
            'side'              => $side,
            'value'             => $value,
            'data_completeness' => max(0, min(100, $completeness)),
            'confidence'        => $confidence,
            'explanation'       => $explanation,
            'inputs'            => $inputs,
            'version'           => self::VERSION,
        ];
    }

    private function clampScore(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}

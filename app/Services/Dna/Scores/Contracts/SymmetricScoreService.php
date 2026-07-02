<?php

namespace App\Services\Dna\Scores\Contracts;

use App\Services\Canonical\CanonicalListing;

/**
 * SymmetricScoreService — the contract every Beyond-MLS scalar DNA score
 * implements so a single generator can persist any of them (§8 symmetric axis).
 *
 * A score exposes both sides of the 0–100 axis:
 *   - scoreProperty(): the supply-side score (how well a listing embodies it).
 *   - scoreDemand():   the demand-side preference weight (how much it matters).
 *
 * Each returns the standard result contract established in Phase 2:
 *   ['score_key','side','value'(?int),'data_completeness'(int),
 *    'confidence'(int),'explanation'(string),'inputs'(array),'version'(string)]
 */
interface SymmetricScoreService
{
    public function scoreKey(): string;

    /** @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string} */
    public function scoreProperty(CanonicalListing $listing): array;

    /** @return array{score_key:string,side:string,value:?int,data_completeness:int,confidence:int,explanation:string,inputs:array,version:string} */
    public function scoreDemand(CanonicalListing $listing): array;
}

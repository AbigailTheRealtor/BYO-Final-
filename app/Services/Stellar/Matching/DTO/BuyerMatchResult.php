<?php

namespace App\Services\Stellar\Matching\DTO;

use App\Models\BridgeProperty;

class BuyerMatchResult
{
    public string $listingKey;
    public int $totalScore;
    public array $categoryScores;
    public array $whyThisMatches;
    public array $tradeoffs;
    public array $cautionFlags;
    public array $missingData;
    public BridgeProperty $listing;

    // git-C9 (Plan-C5, F3/F8) — additive, default-null report slots populated by a later slice
    // (git-C10's BuyerMatchResultBuilder). The batch card path (mapOne) never reads them and
    // toArray() below is intentionally left unchanged, so existing consumers are unaffected.
    public ?array $whyNot = null;
    public ?array $confidence = null;
    public ?array $recommendations = null;

    public function __construct(
        string $listingKey,
        int $totalScore,
        array $categoryScores,
        BridgeProperty $listing,
        array $whyThisMatches = [],
        array $tradeoffs = [],
        array $cautionFlags = [],
        array $missingData = [],
        ?array $whyNot = null,
        ?array $confidence = null,
        ?array $recommendations = null
    ) {
        $this->listingKey      = $listingKey;
        $this->totalScore      = $totalScore;
        $this->categoryScores  = $categoryScores;
        $this->listing         = $listing;
        $this->whyThisMatches  = $whyThisMatches;
        $this->tradeoffs       = $tradeoffs;
        $this->cautionFlags    = $cautionFlags;
        $this->missingData     = $missingData;
        $this->whyNot          = $whyNot;
        $this->confidence      = $confidence;
        $this->recommendations = $recommendations;
    }

    public function toArray(): array
    {
        return [
            'listing_key'     => $this->listingKey,
            'total_score'     => $this->totalScore,
            'category_scores' => $this->categoryScores,
            'why_this_matches' => $this->whyThisMatches,
            'tradeoffs'       => $this->tradeoffs,
            'caution_flags'   => $this->cautionFlags,
            'missing_data'    => $this->missingData,
        ];
    }
}

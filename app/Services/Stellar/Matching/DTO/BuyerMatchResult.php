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

    public function __construct(
        string $listingKey,
        int $totalScore,
        array $categoryScores,
        BridgeProperty $listing,
        array $whyThisMatches = [],
        array $tradeoffs = [],
        array $cautionFlags = [],
        array $missingData = []
    ) {
        $this->listingKey     = $listingKey;
        $this->totalScore     = $totalScore;
        $this->categoryScores = $categoryScores;
        $this->listing        = $listing;
        $this->whyThisMatches = $whyThisMatches;
        $this->tradeoffs      = $tradeoffs;
        $this->cautionFlags   = $cautionFlags;
        $this->missingData    = $missingData;
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

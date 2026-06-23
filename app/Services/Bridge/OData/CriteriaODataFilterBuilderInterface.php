<?php

namespace App\Services\Bridge\OData;

use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

interface CriteriaODataFilterBuilderInterface
{
    /**
     * Build a valid OData $filter string from the given criteria payload.
     *
     * - String literals are single-quoted per the OData 4.0 specification.
     * - Numeric comparisons use no quotes.
     * - Clauses are joined with ' and '.
     * - Null payload fields are omitted rather than producing a malformed filter.
     *
     * @param  BuyerCriteriaPayload $payload
     * @return string  The complete $filter expression (never empty; always includes StandardStatus clause).
     */
    public function build(BuyerCriteriaPayload $payload): string;
}

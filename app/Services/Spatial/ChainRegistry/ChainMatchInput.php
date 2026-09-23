<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * One corpus-v2 place row, reduced to the fields chain identity may read. Raw values: the
 * matcher normalises them itself so no caller can pre-normalise differently.
 *
 * `brandName` and `brandWikidata` are SEPARATE inputs on purpose. The v1 place normaliser folds
 * the brand QID into `brand` when `brand.names` is absent; a QID-shaped brand name is therefore
 * treated as no brand name at all, and identity by QID reads only `brandWikidata`.
 *
 * `categoryKey` is the CANONICAL v2 key (null for a token outside the import list).
 * `sourceCategory` is the raw `taxonomy.primary` token; it is read only when `categoryKey` is
 * null, and only to decide whether a chain declares a source-category rescue for that token
 * (chain-registry-v2, decision 1). It never widens the import list.
 *
 * There are no coordinates and no address here, deliberately: identity is decided from one row,
 * so co-branding or chain membership can never be inferred from proximity.
 */
final class ChainMatchInput
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $brandName,
        public readonly ?string $brandWikidata,
        public readonly ?string $categoryKey,
        public readonly ?string $operatingStatus = null,
        public readonly ?string $sourceCategory = null,
    ) {
    }
}

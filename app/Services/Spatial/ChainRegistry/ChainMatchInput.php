<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * One corpus-v2 place row, reduced to the five fields chain identity may read. Raw values: the
 * matcher normalises them itself so no caller can pre-normalise differently.
 *
 * `brandName` and `brandWikidata` are SEPARATE inputs on purpose. The v1 place normaliser folds
 * the brand QID into `brand` when `brand.names` is absent; a QID-shaped brand name is therefore
 * treated as no brand name at all, and identity by QID reads only `brandWikidata`.
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
    ) {
    }
}

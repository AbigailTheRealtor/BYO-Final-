<?php

namespace App\Services\ListingImport\Sync;

use App\Services\ListingImport\MlsFieldMap;
use App\Support\Listing\MlsFactVocabulary;
use App\Support\Listing\PropertyTypeVocabulary;

/**
 * The one place a canonical MLS fact becomes a listing meta value.
 *
 * Both write paths run through here — the owner-scoped quick import and the
 * unattended live sync — so there is exactly one answer to "which field does
 * this fact land in, and in what vocabulary". That is the whole point: PR #125
 * established the import mapping, and the risk a sync feature introduces is a
 * second, lookalike mapping that drifts from it. There is no second mapping.
 *
 * WHAT DIFFERS BETWEEN THE TWO MODES IS PRECEDENCE, AND ONLY PRECEDENCE.
 * ---------------------------------------------------------------------
 * MODE_IMPORT — a populated field is left alone. The user is editing this draft
 * and may have corrected something the feed got wrong; pressing import again
 * must not revert them. Pinned by MlsReimportBehaviourTest.
 *
 * MODE_SYNC — Stellar wins for the facts it owns. The owner's live-sync contract
 * makes the MLS authoritative for beds, baths, square footage, year built,
 * property type, features, HOA and taxes, and requires them to follow the feed
 * with no manual re-import. Which facts those are is decided by
 * {@see MlsSyncFieldPolicy}, not here.
 *
 * Everything else — the array splitting, the '*' target convention, the
 * property-type translation, the flooring filter, the MlsFactVocabulary pass,
 * the furnished merge — is identical in both modes, because it is the mapping,
 * and the mapping is shared.
 */
final class MlsFactProjection
{
    public const MODE_IMPORT = 'import';
    public const MODE_SYNC   = 'sync';

    /**
     * Project canonical facts into the meta writes they imply.
     *
     * Returns only the keys that should actually be written — a fact that maps
     * nowhere, translates to nothing, or is blocked by precedence simply does
     * not appear in the result. The caller persists what comes back and does not
     * re-decide anything.
     *
     * @param  array<string,mixed>  $facts     canonical key => feed value
     * @param  array<string,mixed>  $existing  the listing's current meta, flat
     * @return array<string,mixed>             meta key => value to write
     */
    public function project(
        string $role,
        array $facts,
        array $existing,
        string $mode,
        ?string $sourcePropertyType = null,
    ): array {
        $isSync = ($mode === self::MODE_SYNC);

        // The map is the full role map on import, and the ownership-filtered
        // subset on sync. Asking the policy for the map — rather than filtering
        // afterwards — means a fact the policy excludes has no target at all and
        // cannot be written by any later branch of this method.
        $map = $isSync
            ? MlsSyncFieldPolicy::syncableTargets($role)
            : MlsFieldMap::forRole($role);

        $writes = [];

        foreach ($facts as $canonicalKey => $value) {
            // Furnished is a MERGE, not a copy, and only where the target is the
            // seller's building_features list. Handled before the precedence
            // guard because the guard would skip an already-populated array, and
            // merging into one is the entire point.
            if ($canonicalKey === 'furnished') {
                $merged = $this->projectFurnished($map, $existing, (string) $value);

                if ($merged !== null) {
                    $writes['building_features'] = $merged;
                }

                continue;
            }

            $target = $map[$canonicalKey] ?? null;

            if ($target === null || $target === '') {
                continue;
            }

            // The asking price is the one fact whose safety depends on the
            // SOURCE record's type rather than on the field alone: a sale
            // ListPrice written into a landlord's rent field publishes a
            // purchase price as a monthly rent.
            if ($canonicalKey === 'price'
                && ! MlsSyncFieldPolicy::allowsPriceSync($role, $sourcePropertyType)) {
                continue;
            }

            $isArray = str_starts_with($target, '*');
            $metaKey = ltrim($target, '*');

            // Belt to the policy's braces. A future MlsFieldMap entry pointing at
            // a BYO term cannot be written by sync even if nobody remembered to
            // exclude its canonical key.
            if ($isSync && MlsSyncFieldPolicy::isProtectedMetaKey($metaKey)) {
                continue;
            }

            if (! $isSync && $this->hasValue($existing[$metaKey] ?? null)) {
                continue;
            }

            $stored = $isArray
                ? array_values(array_filter(array_map('trim', explode(',', (string) $value))))
                : $value;

            // Stored in BidYourOffer vocabulary, exactly as the manual flow
            // stores it, so an MLS-linked listing drives the same conditionals
            // everywhere downstream. The feed's own wording is preserved
            // separately as provenance.
            if ($canonicalKey === 'property_type') {
                $stored = PropertyTypeVocabulary::forRole((string) $stored, $role);
            }

            // Flooring lands in a fixed multi-select. A value outside that list
            // would store fine and then never render as chosen.
            if ($canonicalKey === 'flooring') {
                $stored = MlsFactVocabulary::filterFloorCoverings((array) $stored);

                if ($stored === []) {
                    continue;
                }
            }

            $stored = MlsFactVocabulary::toFormValue($canonicalKey, $stored);

            if ($stored === null || $stored === '' || $stored === []) {
                continue;
            }

            $writes[$metaKey] = $stored;
        }

        return $writes;
    }

    /**
     * The furnishing label merged into building_features, or null when there is
     * nothing to write.
     *
     * ONLY building_features, which is Seller's target. The landlord map also
     * carries a `furnished` entry pointing at `tenant_require`, a single-select
     * Furnishings control — merging a label into it is meaningless, and its
     * blade binds the same variable it iterates for options, so a written value
     * would not render as chosen anyway.
     *
     * @param  array<string,string>  $map
     * @param  array<string,mixed>   $existing
     */
    private function projectFurnished(array $map, array $existing, string $value): ?array
    {
        $metaKey = ltrim((string) ($map['furnished'] ?? ''), '*');

        if ($metaKey !== 'building_features') {
            return null;
        }

        $merged = MlsFactVocabulary::mergeFurnishedFeature($existing[$metaKey] ?? null, $value);

        return $merged === [] ? null : $merged;
    }

    /** Does this stored value count as already answered? */
    private function hasValue(mixed $current): bool
    {
        return is_array($current)
            ? $current !== []
            : ($current !== null && $current !== '');
    }
}

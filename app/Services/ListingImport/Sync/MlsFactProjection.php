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

        // Which property type this listing actually IS, in BYO vocabulary, and
        // which canonical keys that type's form renders an input for.
        //
        // THIS IS THE FEATURE-APPLICABILITY GATE, AND IT WAS ONLY EVER APPLIED
        // ON THE OTHER IMPORT PATH. MlsFieldMap::propertyTypeApplicability() has
        // existed since the URL/text importer needed it, and
        // HasMlsImport::buildImportPreview() is the only thing that consulted
        // it. Quick Import and sync write through this projection instead, which
        // knew nothing about it — so an Income listing was written
        // `garage_needed` even though the seller Income form renders no garage
        // control, and the value became state the user can neither see nor
        // correct: invisible data with the authority of an import behind it.
        //
        // It matters most for Income precisely because of the other half of this
        // change. "Residential Income" used to normalise to `Residential`, so a
        // multi-family sale inherited Residential-only applicability by being
        // mislabelled. Fixing the label alone would have moved the listing into
        // the Income track while it kept writing Residential-only fields; the
        // label and the applicability have to move together or the fix is
        // cosmetic.
        $effectiveType = $this->effectivePropertyType($role, $facts, $existing, $isSync);
        $typeScope     = MlsFieldMap::propertyTypeApplicability($role);

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

            // A key absent from the scope map is applicable to every type — the
            // same default HasMlsImport uses, so this is an additive gate rather
            // than a reinterpretation of the existing mapping.
            //
            // A key that IS scoped and whose type we cannot establish is
            // skipped, not written. That is the fail-closed reading: the harm
            // this gate exists to prevent is writing a field the form does not
            // render, and an unknown type cannot rule that out.
            if (isset($typeScope[$canonicalKey])
                && ! in_array($effectiveType, $typeScope[$canonicalKey], true)) {
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
     * Canonical keys whose native destination can never hold the whole feed
     * value, however the record reads. See completelyWrittenKeys().
     *
     * @var array<string,string>
     */
    private const LOSSY_BY_DESIGN = [
        'furnished'      => 'merged into building_features as at most one label',
        'lot_size_acres' => 'an exact acreage stored as the select\'s acreage band',
    ];

    /**
     * Canonical keys whose feed value a fresh import writes into this role's
     * form COMPLETELY.
     *
     * This is the question MLS Property Details asks before it declines to
     * repeat a fact, and it is not the question "does MlsFieldMap name a
     * target". A key can have a target and still land nowhere — the control is
     * not rendered for this property type, or the destination vocabulary cannot
     * represent the feed's value — or land only in part: a multi-value source
     * reduced to a single-select, a furnishing merged in as one label, an exact
     * acreage reduced to a band. Hiding the MLS Details row in any of those cases
     * is how a fact disappeared from the listing at both layers at once.
     *
     * Answered by running THIS projection — MODE_IMPORT, an empty draft, one
     * fact at a time. So the answer is the mapping's own rather than a lookalike
     * of it; another fact aimed at the same meta key cannot answer for this one;
     * and it depends only on the record, so re-importing the same record gives
     * the same answer whatever the user has since typed into the draft.
     *
     * @param  array<string,mixed>  $facts  canonical key => feed value, as the prefill emits it
     * @return array<string,true>
     */
    public function completelyWrittenKeys(string $role, array $facts, ?string $sourcePropertyType = null): array
    {
        $map = MlsFieldMap::forRole($role);
        $out = [];

        foreach ($facts as $canonicalKey => $value) {
            $target = $map[$canonicalKey] ?? null;

            if ($target === null || $target === '' || isset(self::LOSSY_BY_DESIGN[$canonicalKey])) {
                continue;
            }

            // property_type rides along because applicability is judged against it.
            $single = [$canonicalKey => $value];

            if (array_key_exists('property_type', $facts)) {
                $single['property_type'] = $facts['property_type'];
            }

            $writes  = $this->project($role, $single, [], self::MODE_IMPORT, $sourcePropertyType);
            $metaKey = ltrim($target, '*');

            if (array_key_exists($metaKey, $writes)
                && $this->holdsEverySourceValue($canonicalKey, $value, $writes[$metaKey])) {
                $out[$canonicalKey] = true;
            }
        }

        return $out;
    }

    /**
     * Did the write keep every value the feed sent for this key?
     *
     * Only two destinations can take part of a value: the Seller Business Type
     * single-select holds one entry of the feed's list, and the landlord floor
     * covering multi-select keeps only the coverings it offers. Every other
     * write carries the whole value or nothing at all.
     */
    private function holdsEverySourceValue(string $canonicalKey, mixed $source, mixed $written): bool
    {
        return match ($canonicalKey) {
            'business_type' => count($this->sourceItems($source)) === 1,
            'flooring'      => count((array) $written) === count($this->sourceItems($source)),
            default         => true,
        };
    }

    /**
     * A list fact split back into its items — the prefill emits lists as the
     * comma-joined string every consumer of that pipeline already expects.
     *
     * @return list<string>
     */
    private function sourceItems(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(
            array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $items),
            static fn (string $v) => $v !== '',
        )));
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

    /**
     * The listing's property type in BidYourOffer vocabulary, for applicability.
     *
     * Whichever value this projection would leave on the listing wins — see the
     * precedence note in the body. Normalised on the way out either way, since a
     * stored value can predate the vocabulary and the scope map is written in
     * BidYourOffer words.
     *
     * Returns '' when neither source says anything — which matches nothing in
     * the scope map, so every type-gated key is skipped.
     *
     * @param  array<string,mixed>  $facts
     * @param  array<string,mixed>  $existing
     */
    private function effectivePropertyType(string $role, array $facts, array $existing, bool $isSync): string
    {
        $incoming = trim((string) ($facts['property_type'] ?? ''));
        $stored   = trim((string) ($existing['property_type'] ?? ''));

        // Follow the SAME precedence this projection applies to property_type
        // itself, so the type the features are judged against is the type the
        // listing will actually have when the writes land.
        //
        // On IMPORT a populated stored value wins, because the user wins: a
        // seller who corrected the feed's Residential to Income has a listing
        // that renders the Income form, and judging applicability against the
        // feed's word would write that form's absent garage control back in on
        // the next re-import — reverting the correction by a side door while
        // property_type itself was correctly left alone.
        //
        // On SYNC the incoming value wins, because Stellar owns property_type
        // there and the features must move with it in the same pass.
        $raw = $isSync
            ? ($incoming !== '' ? $incoming : $stored)
            : ($stored !== '' ? $stored : $incoming);

        return $raw === '' ? '' : PropertyTypeVocabulary::forRole($raw, $role);
    }

    /** Does this stored value count as already answered? */
    private function hasValue(mixed $current): bool
    {
        return is_array($current)
            ? $current !== []
            : ($current !== null && $current !== '');
    }
}

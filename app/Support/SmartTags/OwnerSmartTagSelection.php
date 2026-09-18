<?php

namespace App\Support\SmartTags;

/**
 * The Seller/Landlord picker's FORM STATE: which canonical keys the owner has
 * ticked, as the listing wizard stores and restores them.
 *
 * WHY FORM STATE AT ALL, when manual evidence already exists. Because a draft
 * save inserts a NEW listing row (`SAVE_AS_NEW_DRAFT`) and Smart Tags are
 * deliberately never derived for drafts — so evidence cannot be where a
 * half-finished wizard remembers what the owner ticked. This is an ordinary
 * listing answer, saved beside `interior_features` and `pool_type`, and manual
 * evidence is DERIVED from it at publish exactly as structured evidence is
 * derived from those. One listing answer, one derivation, the existing shape.
 *
 * NOT THE WRITE BOUNDARY, and deliberately weaker than one. {@see sanitize()}
 * only keeps values that are canonical keys a listing owner could tick
 * SOMEWHERE — it cannot check the context, because a draft may not have a
 * property type yet, and pruning on a half-filled form would silently discard
 * selections the owner made before changing their mind about the property type.
 * The real gate is {@see SmartTagSelectionPolicy}, applied against the STORED
 * property type inside {@see \App\Services\SmartTags\ManualSmartTagWriter}.
 *
 * Pure: no container, no database, no config beyond the taxonomy.
 */
final class OwnerSmartTagSelection
{
    /**
     * The listing meta key. A plain sibling of every other Offer Listing answer;
     * no migration, no schema change, and no source rule reads it — the deriver
     * reads only the fields named in config/smart_tag_sources.php.
     */
    public const META_KEY = 'smart_tag_owner_selections';

    /**
     * Whatever the wizard stored, back as canonical keys.
     *
     * Accepts the JSON array this class writes, a bare array (a value the
     * component still holds in memory), and a single string, because meta values
     * arrive in every shape {@see NativeMetaValueReader} documents. Anything
     * unreadable is an empty selection, never a guess.
     *
     * @return string[]
     */
    public static function decode(mixed $stored): array
    {
        if (is_array($stored)) {
            return self::sanitize($stored);
        }

        if (! is_string($stored) || trim($stored) === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        if (is_array($decoded)) {
            return self::sanitize($decoded);
        }

        return self::sanitize([$stored]);
    }

    /**
     * @param array<int, mixed> $keys
     */
    public static function encode(array $keys): string
    {
        return (string) json_encode(array_values(self::sanitize($keys)));
    }

    /**
     * Canonical keys a listing owner may tick somewhere, deduplicated, in the
     * taxonomy's display order so a stored value does not depend on click order.
     *
     * Context is NOT checked here. See the class docblock.
     *
     * @param array<int, mixed> $requested
     * @return string[]
     */
    public static function sanitize(array $requested): array
    {
        $kept = [];

        foreach ($requested as $value) {
            if (! is_string($value) || isset($kept[$value])) {
                continue;
            }

            $definition = SmartTagTaxonomy::get($value);

            // isOwnerSelectable() already covers active and not-pending-review.
            if ($definition === null || ! $definition->isOwnerSelectable()) {
                continue;
            }

            $kept[$value] = $definition->displayOrder;
        }

        asort($kept);

        return array_keys($kept);
    }
}

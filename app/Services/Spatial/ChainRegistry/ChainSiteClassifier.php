<?php

namespace App\Services\Spatial\ChainRegistry;

/**
 * The site-level CONTRACT the later site builder (PR 5) must honour, as a pure function:
 * given one chain's memberships that the caller has ALREADY grouped into one physical site,
 * decide what that site is. It does not group, de-duplicate, measure distance or read
 * coordinates — grouping is PR 5's job and never happens here.
 *
 * Each member's role is taken from the registry's format and must agree with the membership's
 * own role field; a disagreement is refused, never resolved in either direction.
 *
 * Rules, in order (design §9):
 *   1. any storefront member      → storefront; site format `store_with_fuel` when the chain
 *                                   permits it and a fuel member is present, else the
 *                                   representative storefront's format
 *   2. else any department member → storefront_unconfirmed (never upgraded)
 *   3. else fuel members only     → fuel_only if the chain permits it, else unsupported_format
 *
 * The representative storefront format is chosen without reference to input order: a format
 * with a name pattern (the more specific claim) before a default, then by format key.
 */
final class ChainSiteClassifier
{
    public function __construct(private readonly ChainRegistry $registry)
    {
    }

    /** @param list<ChainMembership> $members */
    public function classify(string $brandKey, array $members): ChainSiteClassification
    {
        $chain = $this->registry->chain($brandKey);

        if ($members === []) {
            throw new \InvalidArgumentException('a site needs at least one membership');
        }

        $byRole = [ChainRole::STOREFRONT => [], ChainRole::DEPARTMENT => [], ChainRole::FUEL => []];
        foreach ($members as $m) {
            if (! $m instanceof ChainMembership || $m->brandKey !== $brandKey) {
                throw new \InvalidArgumentException("every member must be a {$brandKey} membership");
            }
            // The registry's format decides the role, never the membership's own field: a stored
            // membership whose role disagrees with its format (a "storefront" grocery_department)
            // is corrupt, and trusting it is exactly how a department would be promoted.
            $format = $chain->format($m->formatKey);
            if ($format->role !== $m->role) {
                throw new \InvalidArgumentException(
                    "membership role {$m->role} disagrees with {$brandKey}.{$format->key} role {$format->role}"
                );
            }
            $byRole[$format->role][] = $format;
        }

        $hasFuel = $byRole[ChainRole::FUEL] !== [];

        if ($byRole[ChainRole::STOREFRONT] !== []) {
            $representative = self::representative($byRole[ChainRole::STOREFRONT]);

            if ($hasFuel && ($chain->fuelSites['store_with_fuel'] ?? false) === true) {
                // Internal metadata only: no visible "with fuel" suffix (decision 10).
                return new ChainSiteClassification(
                    $brandKey,
                    ChainSiteClassification::STATUS_STOREFRONT,
                    ChainSiteClassification::SITE_FORMAT_STORE_WITH_FUEL,
                    $representative->userVisible ? $representative->label : null,
                    true,
                );
            }

            return new ChainSiteClassification(
                $brandKey,
                ChainSiteClassification::STATUS_STOREFRONT,
                $representative->key,
                $representative->userVisible ? $representative->label : null,
                $hasFuel,
            );
        }

        if ($byRole[ChainRole::DEPARTMENT] !== []) {
            return new ChainSiteClassification(
                $brandKey,
                ChainSiteClassification::STATUS_STOREFRONT_UNCONFIRMED,
                self::representative($byRole[ChainRole::DEPARTMENT])->key,
                null,
                $hasFuel,
            );
        }

        if (($chain->fuelSites['fuel_only'] ?? false) === true) {
            return new ChainSiteClassification(
                $brandKey,
                ChainSiteClassification::STATUS_FUEL_ONLY,
                ChainSiteClassification::SITE_FORMAT_FUEL_ONLY,
                ChainSiteClassification::FUEL_ONLY_QUALIFIER,
                true,
            );
        }

        return new ChainSiteClassification(
            $brandKey,
            ChainSiteClassification::STATUS_UNSUPPORTED_FORMAT,
            null,
            null,
            true,
        );
    }

    /** @param list<ChainFormat> $formats */
    private static function representative(array $formats): ChainFormat
    {
        usort($formats, static function (ChainFormat $a, ChainFormat $b): int {
            if ($a->isDefault() !== $b->isDefault()) {
                return $a->isDefault() ? 1 : -1;
            }

            return strcmp($a->key, $b->key);
        });

        return $formats[0];
    }
}

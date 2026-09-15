<?php

namespace App\Support\SmartTags;

/**
 * The canonical (property class × transaction) a listing or search is in.
 *
 * Seven contexts, and only seven. Every tag declares which of them it applies
 * to; a listing whose property type does not resolve to one of these has no
 * context at all and receives no tags (see {@see SmartTagContextResolver}).
 */
enum SmartTagContext: string
{
    case ResidentialSale  = 'residential.sale';
    case IncomeSale       = 'income.sale';
    case CommercialSale   = 'commercial.sale';
    case BusinessSale     = 'business.sale';
    case LandSale         = 'land.sale';
    case ResidentialLease = 'residential.lease';
    case CommercialLease  = 'commercial.lease';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public function isSale(): bool
    {
        return str_ends_with($this->value, '.sale');
    }

    public function isLease(): bool
    {
        return str_ends_with($this->value, '.lease');
    }
}

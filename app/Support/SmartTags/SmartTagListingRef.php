<?php

namespace App\Support\SmartTags;

use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * (listing_type, listing_id) as one immutable value, so the two halves of a
 * listing's address can never be passed separately and mismatched.
 */
final class SmartTagListingRef
{
    public function __construct(
        public readonly SmartTagListingType $type,
        public readonly int $id,
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('A Smart Tag listing id must be a positive integer.');
        }
    }

    public static function fromModel(Model $model): self
    {
        $type = match (true) {
            $model instanceof BridgeProperty       => SmartTagListingType::Bridge,
            $model instanceof SellerAgentAuction   => SmartTagListingType::SellerAgent,
            $model instanceof LandlordAgentAuction => SmartTagListingType::LandlordAgent,
            default => throw new InvalidArgumentException(
                'Smart Tags do not attach to ' . get_class($model) . '.'
            ),
        };

        return new self($type, (int) $model->getKey());
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}

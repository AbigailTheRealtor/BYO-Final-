<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasListingId
{
    protected static function bootHasListingId()
    {
        static::creating(function ($model) {
            if (empty($model->listing_id)) {
                $model->listing_id = static::generateListingId();
            }
        });
    }

    public static function generateListingId()
    {
        $prefix = static::getListingIdPrefix();
        
        do {
            $uniqueId = $prefix . '-' . strtoupper(Str::random(8));
        } while (static::where('listing_id', $uniqueId)->exists());

        return $uniqueId;
    }

    protected static function getListingIdPrefix()
    {
        $className = class_basename(static::class);
        $prefixes = [
            'TenantAgentAuction' => 'TAA',
            'LandlordAgentAuction' => 'LAA',
            'BuyerAgentAuction' => 'BAA',
            'SellerAgentAuction' => 'SAA',
            'PropertyAuction' => 'PA',
            'BuyerCriteriaAuction' => 'BCA',
            'TenantCriteriaAuction' => 'TCA',
            'LandlordAuction' => 'LA',
        ];

        return $prefixes[$className] ?? 'LST';
    }
}

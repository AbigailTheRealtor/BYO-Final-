<?php

namespace App\Models;

use App\Traits\HasListingId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferAuction extends Model
{
    use HasFactory, HasListingId;

    protected $fillable = [
        'user_id',
        'listing_id',
        'title',
        'is_draft',
        'is_approved',
        'is_sold',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_draft'    => 'boolean',
        'is_sold'     => 'boolean',
    ];

    protected $attributes = [
        'is_approved' => true,
        'is_draft'    => true,
        'is_sold'     => false,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metas()
    {
        return $this->hasMany(OfferAuctionMeta::class);
    }

    public function saveMeta($key, $val)
    {
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
        }
        return $this->metas()->updateOrCreate(['meta_key' => $key], ['meta_value' => $val]);
    }

    public function deleteMeta($key)
    {
        return $this->metas()->where('meta_key', $key)->delete();
    }

    public function info($key)
    {
        $data = $this->metas->where('meta_key', $key);
        if ($data->count() > 0) {
            return $data->first()->meta_value;
        }
        return false;
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_sold) return 'Accepted';
        $metaStatus = $this->metas->where('meta_key', 'listing_status')->first()?->meta_value;
        if (in_array($metaStatus, ['Accepted', 'Withdrawn', 'Expired'])) {
            return $metaStatus;
        }
        $expiry = $this->metas->where('meta_key', 'listing_expiration')->first()?->meta_value;
        if ($expiry && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiry))) {
            return 'Expired';
        }
        return $metaStatus ?: 'Active';
    }

    public function showingAvailabilities()
    {
        return $this->hasMany(ShowingAvailability::class);
    }

    public function showings()
    {
        return $this->hasMany(Showing::class);
    }

    public function scopeShowingEligible($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->from('offer_auction_metas')
                ->whereColumn('offer_auction_metas.offer_auction_id', 'offer_auctions.id')
                ->where('offer_auction_metas.meta_key', 'user_type')
                ->whereIn('offer_auction_metas.meta_value', ['seller', 'landlord']);
        });
    }
}

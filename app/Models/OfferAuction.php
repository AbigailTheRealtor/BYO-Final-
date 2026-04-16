<?php

namespace App\Models;

use App\Traits\HasListingId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferAuction extends Model
{
    use HasFactory, HasListingId;

    protected $appends = ['get'];

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

    public function meta()
    {
        return $this->hasMany(OfferAuctionMeta::class);
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
        return $this->meta()->updateOrCreate(['meta_key' => $key], ['meta_value' => $val]);
    }

    public function deleteMeta($key)
    {
        return $this->meta()->where('meta_key', $key)->delete();
    }

    public function info($key)
    {
        $data = $this->meta->where('meta_key', $key);
        if ($data->count() > 0) {
            return $data->first()->meta_value;
        }
        return false;
    }

    public function getGetAttribute()
    {
        $data  = [];
        $metas = OfferAuctionMeta::where('offer_auction_id', $this->id)->get();
        foreach ($metas as $row) {
            $decoded = json_decode($row->meta_value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $value = $decoded;
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_sold) return 'Accepted';
        $metaStatus = $this->info('listing_status');
        if (in_array($metaStatus, ['Accepted', 'Withdrawn', 'Expired'])) {
            return $metaStatus;
        }
        $expiry = $this->info('listing_expiration');
        if ($expiry && \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($expiry))) {
            return 'Expired';
        }
        return $metaStatus ?: 'Active';
    }
}

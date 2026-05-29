<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasCompatibilityPreferences;

class TenantAgentAuctionBid extends Model
{
    use HasFactory, HasCompatibilityPreferences;
    protected $appends = ["get"];
    protected $with = ['meta'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(TenantAgentAuction::class, 'tenant_agent_auction_id', 'id')->withDefault();
    }

    public function meta()
    {
        return $this->hasMany(TenantAgentAuctionBidMeta::class);
    }

    public function counterTerms()
    {
        return $this->hasMany(TenantCounterBidding::class, 'tenant_agent_auction_bid_id');
    }

    public function counterBids()
    {
        return $this->hasMany(TenantCounterBidding::class, 'tenant_agent_auction_bid_id');
    }

    public function acceptedBidSummary()
    {
        return $this->hasOne(AcceptedBidSummary::class, 'accepted_bid_id');
    }

    public function getBidStatusAttribute(): string
    {
        if ($this->acceptedBidSummary()->exists()) {
            return 'Accepted';
        }
        
        $latestCounter = $this->counterTerms()->latest()->first();
        if ($latestCounter) {
            return 'Countered';
        }
        
        $isRejected = $this->info('is_rejected');
        if ($isRejected === '1' || $isRejected === 1 || $isRejected === true) {
            return 'Rejected';
        }
        
        return 'Active';
    }

    public function saveMeta($key, $val)
    {
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
    }

    public function info($key)
    {
        $data = $this->meta->where('meta_key', $key);
        if ($data->count() > 0) {
            return $data->first()->meta_value;
        } else {
            return false;
        }
    }

    public function getGetAttribute()
    {
        $data = [];
        $metas = $this->meta;
        foreach ($metas as $row) {
            $metaValue = $row->meta_value ?? '';
            if ($metaValue !== '' && is_string($metaValue)) {
                $decoded = json_decode($metaValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = $metaValue;
                }
            } else {
                $value = $metaValue;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }
}

<?php

namespace App\Models;

use App\Traits\HasListingId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantAgentAuction extends Model
{
    use HasFactory, HasListingId;
    protected $appends = ["get", "status"];
    protected $with = ['meta'];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_draft'    => 'boolean',
    ];

    protected $attributes = [
        'is_approved' => true,
        'is_draft' => false,
        'is_sold' => false,
    ];

    public function getStatusAttribute()
    {
        $isSold = in_array($this->is_sold, [true, 'true', 1, '1'], true);
        if ($isSold) {
            return 'Hired Agent';
        }
        $metaStatus = $this->info('listing_status');
        if ($metaStatus === 'Hired Agent') {
            return 'Hired Agent';
        }
        if ($metaStatus === 'Pending') {
            return 'Pending';
        }
        if ($this->auction_ended) {
            return 'Expired';
        }
        return 'Active';
    }

    public function isBiddingPeriodType(): bool
    {
        return in_array($this->auction_type, ['Auction (Timer)', 'Bidding Period']);
    }

    public function isBiddingPeriodActive(): bool
    {
        return $this->isBiddingPeriodType() && !$this->auction_ended && $this->is_approved && !$this->is_sold;
    }

    public function bidData()
    {
        return $this->hasMany(TenantAgentAuctionBid::class, 'tenant_agent_auction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCreatedByAgent(): bool
    {
        return optional($this->user)->user_type === 'agent';
    }

    public function bot_questions()
    {
        return $this->morphMany(BotQuestion::class, 'auction');
    }

    public function unanswered_bot_questions()
    {
        return $this->morphMany(UnansweredBotQuestion::class, 'auction');
    }

    public function chat_tokens()
    {
        return $this->morphMany(AuctionChatToken::class, 'auction');
    }

    public function bids()
    {
        return $this->hasMany(TenantAgentAuctionBid::class);
    }

    public function meta()
    {
        return $this->hasMany(TenantAgentAuctionMeta::class);
    }

    public function saveMeta($key, $val)
    {
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
        }
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
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
        } else {
            return false;
        }
    }

    public function getGetAttribute()
    {
        $data = [];
        $metas = $this->meta;
        foreach ($metas as $row) {
            if ($row->meta_value === null) {
                $data[$row->meta_key] = null;
                continue;
            }
            $decoded = json_decode($row->meta_value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $value = $decoded;
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        return new class($data) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function __get($name) { return $this->data[$name] ?? null; }
            public function __isset($name) { return isset($this->data[$name]); }
            public function toArray(): array { return $this->data; }
        };
    }
}

<?php

namespace App\Models;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PropertyAuction extends Model
{
    use HasFactory;
    protected $appends = ["get"];
    protected $fillable = ['starting_price', 'sold', 'is_paid', 'sold_date', 'auction_ended', 'display_bids', 'auction_type', 'auction_length'];
    
    protected $attributes = [
        'is_approved' => true,
        'is_draft' => false,
        'sold' => false,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->hasMany(PropertyAuctionBid::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'property_auction_id', 'id');
    }

    public function meta()
    {
        return $this->hasMany(PropertyAuctionMeta::class);
    }

    public function saveMeta($key, $val)
    {
        return $this->meta()->updateOrCreate(["meta_key" => $key], ["meta_value" => $val]);
    }
    public function get_meta($meta_key)
    {
        $meta = $this->hasMany(PropertyAuctionMeta::class)
            ->where('meta_key', $meta_key)
            ->first();

        if ($meta) {
            return $meta->meta_value;
        }

        return null;
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
        $metas = PropertyAuctionMeta::where('property_auction_id', $this->id)->get();
        foreach ($metas as $row) {
            // $decoded = json_decode($row->meta_value, true);
            // if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            //     $value = $decoded;
            // } else {
            //     $value = $row->meta_value;
            // }
            $data[$row->meta_key] = $row->meta_value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAuctionBid extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = ['price', 'accepted', 'accepted_date'];

    /**
     * Get the user that owns the PropertyAuctionBid
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(PropertyAuction::class, 'property_auction_id', 'id')->withDefault();
    }

    public function meta()
    {
        return $this->hasMany(PropertyAuctionBidMeta::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'property_auction_bid_id', 'id');
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
        $metas = PropertyAuctionBidMeta::where('property_auction_bid_id', $this->id)->get();
        foreach ($metas as $row) {
            if (gettype(json_decode($row->meta_value)) == 'array') {
                $value = json_decode($row->meta_value);
            } else {
                $value = $row->meta_value;
            }
            $data[$row->meta_key] = $value;
        }
        $collection = new Collection();
        $collection->push((object) $data);
        return $collection->first();
    }
}

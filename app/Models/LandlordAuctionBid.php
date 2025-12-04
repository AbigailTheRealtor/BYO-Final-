<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordAuctionBid extends Model
{
    use HasFactory;
    protected $appends = ["get"];

    public function meta()
    {
        return $this->hasMany(LandlordAuctionBidMeta::class);
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(LandlordAuction::class, 'landlord_auction_id', 'id')->withDefault();
    }

    public function getGetAttribute()
    {
        $data = [];
        $metas = LandlordAuctionBidMeta::where('landlord_auction_bid_id', $this->id)->get();
        foreach ($metas as $row) {
            $data[$row->meta_key] = $row->meta_value;
        }
        $collection = new Collection();
        $collection->push($data);
        return (object) $collection->first();
    }
}

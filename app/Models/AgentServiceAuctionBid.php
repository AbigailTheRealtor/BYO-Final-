<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentServiceAuctionBid extends Model
{
    use HasFactory;
    protected $appends = ["get"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(AgentServiceAuction::class, 'agent_service_auction_id', 'id')->withDefault();
    }

    public function meta()
    {
        return $this->hasMany(AgentServiceAuctionBidMeta::class);
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
        $metas = AgentServiceAuctionBidMeta::where('agent_service_auction_bid_id', $this->id)->get();
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

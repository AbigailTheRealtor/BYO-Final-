<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAuctionMeta extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['property_auction_id', 'meta_key', 'meta_value'];

    public function auction()
    {
        return $this->belongsTo(PropertyAuction::class);
    }
}

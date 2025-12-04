<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordAuctionMeta extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'landlord_auction_meta';
    protected $fillable = ['landlord_auction_id', 'meta_key', 'meta_value'];

    public function auction()
    {
        return $this->belongsTo(LandlordAuction::class);
    }
}

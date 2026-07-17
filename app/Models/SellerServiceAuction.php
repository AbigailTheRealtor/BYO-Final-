<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerServiceAuction extends Model
{
    use HasFactory;
    
    protected $attributes = [
        'is_approved' => true,
    ];

    // B1.3 Money Precision: native money column cast to fixed-scale decimal.
    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bot_questions()
    {
        return $this->morphMany(BotQuestion::class, 'auction');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function county()
    {
        return $this->belongsTo(County::class);
    }

    public function bids()
    {
        return $this->hasMany(SellerServiceAuctionBid::class);
    }
}

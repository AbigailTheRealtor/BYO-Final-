<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerServiceAuctionBid extends Model
{
    use HasFactory;

    // B1.3 Money Precision: native money columns cast to fixed-scale decimals.
    protected $casts = [
        'brokerage' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

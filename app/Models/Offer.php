<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'offer_auction_id',
        'parent_offer_id',
        'role',
        'listing_snapshot',
        'status',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'listing_snapshot' => 'array',
        'submitted_at'     => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function offerAuction()
    {
        return $this->belongsTo(OfferAuction::class, 'offer_auction_id');
    }

    public function parentOffer()
    {
        return $this->belongsTo(Offer::class, 'parent_offer_id');
    }

    public function childOffers()
    {
        return $this->hasMany(Offer::class, 'parent_offer_id');
    }

    public function metas()
    {
        return $this->hasMany(OfferMeta::class);
    }

    public function eventLogs()
    {
        return $this->hasMany(OfferEventLog::class);
    }
}

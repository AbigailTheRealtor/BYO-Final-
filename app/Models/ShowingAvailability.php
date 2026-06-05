<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShowingAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_auction_id',
        'user_id',
        'available_date',
        'start_time',
        'end_time',
        'notes',
        'max_showings',
    ];

    protected $casts = [
        'available_date' => 'date',
        'max_showings'   => 'integer',
    ];

    public function offerAuction()
    {
        return $this->belongsTo(OfferAuction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function showings()
    {
        return $this->hasMany(Showing::class);
    }

    public function scopeForEligibleListings($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->from('offer_auction_metas')
                ->whereColumn('offer_auction_metas.offer_auction_id', 'showing_availabilities.offer_auction_id')
                ->where('offer_auction_metas.meta_key', 'user_type')
                ->whereIn('offer_auction_metas.meta_value', ['seller', 'landlord']);
        });
    }
}

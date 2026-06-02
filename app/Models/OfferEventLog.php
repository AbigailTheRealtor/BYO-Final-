<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferEventLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'offer_id',
        'actor_id',
        'actor_role',
        'event_type',
        'from_status',
        'to_status',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

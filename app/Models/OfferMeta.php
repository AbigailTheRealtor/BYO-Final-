<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'meta_key',
        'meta_value',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerListingInquiry extends Model
{
    protected $fillable = [
        'auction_id',
        'type',
        'name',
        'email',
        'phone',
        'preferred_date',
        'preferred_time',
        'message',
        'question',
        'status',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'preferred_date' => 'date',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ByaBetaAccessLog extends Model
{
    protected $table = 'bya_beta_access_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'listing_compatibility_score_id',
        'allowed',
        'denial_reason',
        'created_at',
    ];

    protected $casts = [
        'allowed'    => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new RuntimeException('BYA beta access logs are append-only and cannot be modified or deleted.');
        });

        static::deleting(function () {
            throw new RuntimeException('BYA beta access logs are append-only and cannot be modified or deleted.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listingCompatibilityScore()
    {
        return $this->belongsTo(ListingCompatibilityScore::class, 'listing_compatibility_score_id');
    }
}

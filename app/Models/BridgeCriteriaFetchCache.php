<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BridgeCriteriaFetchCache extends Model
{
    protected $table = 'bridge_criteria_fetch_cache';

    protected $fillable = [
        'criteria_hash',
        'role',
        'last_fetched_at',
        'record_count',
        'expires_at',
    ];

    protected $casts = [
        'last_fetched_at' => 'datetime',
        'expires_at'      => 'datetime',
        'record_count'    => 'integer',
    ];
}

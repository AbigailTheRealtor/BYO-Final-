<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AskAiUsageLog extends Model
{
    protected $fillable = [
        'listing_type',
        'listing_id',
        'user_id',
        'ip_address',
        'question_hash',
        'question_type',
        'status',
        'success',
        'model',
        'response_time_ms',
        'error_code',
    ];
}

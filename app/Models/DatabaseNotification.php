<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

class DatabaseNotification extends BaseDatabaseNotification
{
    protected $table = 'notifications'; // Use your custom table

    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',      // JSON column cast to array
        'read_at' => 'datetime',
    ];
}

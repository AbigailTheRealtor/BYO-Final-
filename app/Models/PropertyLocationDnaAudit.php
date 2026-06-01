<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PropertyLocationDnaAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'listing_type',
        'listing_id',
        'event_type',
        'status',
        'source',
        'input_snapshot',
        'output_snapshot',
        'error',
        'created_at',
    ];

    protected $casts = [
        'input_snapshot'  => 'array',
        'output_snapshot' => 'array',
        'created_at'      => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new LogicException('PropertyLocationDnaAudit is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('PropertyLocationDnaAudit is append-only and cannot be deleted.');
        });
    }
}

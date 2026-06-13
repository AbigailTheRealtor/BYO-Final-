<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records recommendation interaction events with attribution.
 * Only interactions through a recommendation surface have from_recommendation = true.
 * Normal listing interactions are never attributed to recommendations.
 * Rows are append-only and never updated.
 */
class RecommendationInteraction extends Model
{
    public $timestamps = false;

    protected $table = 'recommendation_interactions';

    protected $fillable = [
        'bid_type',
        'bid_id',
        'role',
        'property_type',
        'event_type',
        'from_recommendation',
        'recommendation_surface',
        'user_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'from_recommendation' => 'boolean',
        'metadata'            => 'array',
        'created_at'          => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

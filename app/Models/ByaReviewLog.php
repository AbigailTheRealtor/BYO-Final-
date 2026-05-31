<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ByaReviewLog extends Model
{
    protected $table = 'bya_review_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    const STATUSES = [
        'pending_review'     => 'Pending Review',
        'in_review'          => 'In Review',
        'approved'           => 'Approved',
        'approved_with_notes'=> 'Approved with Notes',
        'flagged'            => 'Flagged',
        'rejected'           => 'Rejected',
    ];

    const CHECKLIST_ITEMS = [
        'protected_class_references' => 'Protected-class references',
        'proxy_characteristics'      => 'Proxy characteristics',
        'steering_language'          => 'Steering language',
        'recommendation_language'    => 'Recommendation language',
        'ranking_language'           => 'Ranking language',
        'suitability_language'       => 'Suitability language',
    ];

    protected $fillable = [
        'listing_compatibility_score_id',
        'reviewer_user_id',
        'status',
        'notes',
        'fair_housing_checklist',
        'created_at',
    ];

    protected $casts = [
        'fair_housing_checklist' => 'array',
        'created_at'             => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new RuntimeException('BYA review logs are append-only and cannot be modified or deleted.');
        });

        static::deleting(function () {
            throw new RuntimeException('BYA review logs are append-only and cannot be modified or deleted.');
        });
    }

    public function listingCompatibilityScore()
    {
        return $this->belongsTo(ListingCompatibilityScore::class, 'listing_compatibility_score_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}

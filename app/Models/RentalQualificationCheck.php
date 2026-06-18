<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalQualificationCheck extends Model
{
    protected $fillable = [
        'landlord_listing_id',
        'user_id',
        'name',
        'email',
        'phone',
        'estimated_credit_score',
        'monthly_household_income',
        'employment_status',
        'eviction_history',
        'bankruptcy_history',
        'number_of_occupants',
        'additional_notes',
        'status',
    ];

    public function landlordListing(): BelongsTo
    {
        return $this->belongsTo(LandlordAgentAuction::class, 'landlord_listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

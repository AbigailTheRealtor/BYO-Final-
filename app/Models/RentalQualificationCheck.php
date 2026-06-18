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
        'employment_status_other',
        'income_source',
        'has_pets',
        'pet_details',
        'smoking',
        'eviction_history',
        'bankruptcy_history',
        'criminal_background',
        'criminal_background_other',
        'landlord_reference_available',
        'employment_verification_available',
        'income_verification_available',
        'consent_to_screening',
        'number_of_occupants',
        'desired_move_in_date',
        'applicant_profile',
        'additional_notes',
        'status',
    ];

    protected $casts = [
        'consent_to_screening' => 'boolean',
        'desired_move_in_date' => 'date',
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

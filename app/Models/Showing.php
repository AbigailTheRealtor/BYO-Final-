<?php

namespace App\Models;

use App\Enums\ShowingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Showing extends Model
{
    use HasFactory;

    protected $fillable = [
        'showing_availability_id',
        'offer_auction_id',
        'requester_id',
        'requested_by_agent',
        'requested_date',
        'requested_start_time',
        'requested_end_time',
        'status',
        'requester_message',
        'owner_message',
        'approved_date',
        'approved_start_time',
        'approved_end_time',
        'canceled_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_by_agent' => 'boolean',
        'requested_date'     => 'date',
        'approved_date'      => 'date',
        'canceled_at'        => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public function showingAvailability()
    {
        return $this->belongsTo(ShowingAvailability::class);
    }

    public function offerAuction()
    {
        return $this->belongsTo(OfferAuction::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', ShowingStatus::REQUESTED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [ShowingStatus::APPROVED, ShowingStatus::REQUESTED]);
    }

    public function scopeForListing($query, $listingId)
    {
        return $query->where('offer_auction_id', $listingId);
    }

    public function isRequested(): bool
    {
        return $this->status === ShowingStatus::REQUESTED;
    }

    public function isApproved(): bool
    {
        return $this->status === ShowingStatus::APPROVED;
    }

    public function isDeclined(): bool
    {
        return $this->status === ShowingStatus::DECLINED;
    }

    public function isCanceled(): bool
    {
        return $this->status === ShowingStatus::CANCELED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ShowingStatus::COMPLETED;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcceptedBidSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_type',
        'listing_id',
        'accepted_bid_id',
        'accepted_counter_id',
        'tenant_user_id',
        'agent_user_id',
        'summary_html',
        'summary_pdf_path',
        'tenant_signature_name',
        'tenant_signed_at',
        'tenant_ip_address',
        'tenant_timezone',
        'tenant_user_agent',
        'agent_signature_name',
        'agent_signed_at',
        'agent_ip_address',
        'agent_timezone',
        'agent_user_agent',
    ];

    protected $casts = [
        'tenant_signed_at' => 'datetime',
        'agent_signed_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(TenantAgentAuction::class, 'listing_id');
    }

    public function bid()
    {
        return $this->belongsTo(TenantAgentAuctionBid::class, 'accepted_bid_id');
    }

    public function counter()
    {
        return $this->belongsTo(TenantCounterBidding::class, 'accepted_counter_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_user_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function acknowledgementDocuments()
    {
        return $this->hasMany(AcknowledgementDocument::class, 'accepted_bid_summary_id');
    }

    public function isTenantSigned()
    {
        return !empty($this->tenant_signature_name) && !empty($this->tenant_signed_at);
    }

    public function isOwnerSigned()
    {
        return $this->isTenantSigned();
    }

    public function isAgentSigned()
    {
        return !empty($this->agent_signature_name) && !empty($this->agent_signed_at);
    }

    public function isFullySigned()
    {
        return $this->isTenantSigned() && $this->isAgentSigned();
    }

    public function getSignatureStatus()
    {
        if ($this->isFullySigned()) {
            return 'Acknowledged by Both Parties';
        } elseif ($this->isTenantSigned()) {
            return 'Awaiting Agent Acknowledgement';
        } elseif ($this->isAgentSigned()) {
            return 'Awaiting Listing Creator Acknowledgement';
        }
        return 'Pending Acknowledgement';
    }
}

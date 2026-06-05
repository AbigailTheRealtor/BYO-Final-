<?php

namespace App\Models;

use App\Services\HireAgentLeadMatcherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HireAgentLead extends Model
{
    protected $table = 'hire_agent_leads';

    protected $fillable = [
        'source_listing_type',
        'source_listing_id',
        'source_listing_role',
        'source_property_type',
        'lead_source',
        'representation_type',
        'selected_property_type',
        'requester_name',
        'requester_email',
        'requester_phone',
        'message',
        'requester_user_id',
        'target_agent_id',
        'matched_preset_id',
        'preset_match_status',     // 'matched' | 'no_match' | 'multiple_matches'
        'source_listing_title',
        'source_listing_url',
        'status',                  // new | pending | accepted | declined | closed
        'viewed_at',
        'responded_at',
        'accepted_at',
        'declined_at',
    ];

    protected $casts = [
        'viewed_at'    => 'datetime',
        'responded_at' => 'datetime',
        'accepted_at'  => 'datetime',
        'declined_at'  => 'datetime',
    ];

    // ── Write-once target_agent_id ─────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function (self $lead) {
            if ($lead->isDirty('target_agent_id') && ! is_null($lead->getOriginal('target_agent_id'))) {
                $lead->target_agent_id = $lead->getOriginal('target_agent_id');
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_agent_id');
    }

    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function matchedPreset(): BelongsTo
    {
        return $this->belongsTo(AgentDefaultProfile::class, 'matched_preset_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public static function forAgent(int $agentId)
    {
        return static::where('target_agent_id', $agentId);
    }

    // ── Lifecycle methods ──────────────────────────────────────────────────

    public function markViewed(): bool
    {
        if ($this->viewed_at) {
            return false;
        }
        return $this->update([
            'viewed_at' => now(),
            'status'    => $this->status === 'new' ? 'pending' : $this->status,
        ]);
    }

    public function markAccepted(): bool
    {
        if (in_array($this->status, ['accepted', 'declined', 'closed'])) {
            return false;
        }
        return $this->update([
            'status'       => 'accepted',
            'accepted_at'  => now(),
            'responded_at' => $this->responded_at ?? now(),
        ]);
    }

    public function markDeclined(): bool
    {
        if (in_array($this->status, ['accepted', 'declined', 'closed'])) {
            return false;
        }
        return $this->update([
            'status'       => 'declined',
            'declined_at'  => now(),
            'responded_at' => $this->responded_at ?? now(),
        ]);
    }

    public function markResponded(): bool
    {
        if ($this->responded_at) {
            return false;
        }
        return $this->update(['responded_at' => now()]);
    }

    public function markClosed(): bool
    {
        if ($this->status === 'closed') {
            return false;
        }
        return $this->update(['status' => 'closed']);
    }

    // ── Label helpers ──────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new'      => 'New',
            'pending'  => 'Pending',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'closed'   => 'Closed',
            default    => ucfirst($this->status ?? ''),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'new'      => 'bg-primary',
            'pending'  => 'bg-warning text-dark',
            'accepted' => 'bg-success',
            'declined' => 'bg-secondary',
            'closed'   => 'bg-dark',
            default    => 'bg-secondary',
        };
    }

    public function sourceListingTypeLabel(): string
    {
        return match ($this->source_listing_type) {
            'seller_offer'   => 'Seller Listing',
            'buyer_offer'    => 'Buyer Listing',
            'landlord_offer' => 'Landlord Listing',
            'tenant_offer'   => 'Tenant Listing',
            default          => ucfirst(str_replace('_', ' ', $this->source_listing_type ?? '')),
        };
    }

    /** @deprecated Use sourceListingTypeLabel() */
    public function listingTypeLabel(): string
    {
        return $this->sourceListingTypeLabel();
    }

    public function representationTypeLabel(): string
    {
        return match ($this->representation_type) {
            'seller'   => "Seller's Agent",
            'buyer'    => "Buyer's Agent",
            'landlord' => "Landlord's Agent",
            'tenant'   => "Tenant's Agent",
            default    => ucfirst($this->representation_type ?? ''),
        };
    }

    /** @deprecated Use representationTypeLabel() */
    public function repTypeLabel(): string
    {
        return $this->representationTypeLabel();
    }

    public function selectedPropertyTypeLabel(): string
    {
        return static::propertyLabel($this->selected_property_type ?? '');
    }

    /** @deprecated Use selectedPropertyTypeLabel() */
    public function propertyTypeLabel(): string
    {
        return $this->selectedPropertyTypeLabel();
    }

    public static function propertyLabel(string $value): string
    {
        return match ($value) {
            'residential' => 'Residential',
            'commercial'  => 'Commercial',
            'income'      => 'Income / Multi-family',
            'business'    => 'Business',
            'vacant_land' => 'Vacant Land',
            default       => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    /**
     * Human-readable title for the matched preset, e.g. "Residential · Buyer Agent".
     * Falls back to property label only when role label is unavailable.
     */
    public function matchedPresetTitle(): ?string
    {
        $preset = $this->matchedPreset;
        if (! $preset) {
            return null;
        }
        $propertyLabel = AgentDefaultProfile::propertyLabel($preset->property_type);
        $roleLabel     = AgentDefaultProfile::roleLabel($preset->role_type);
        return $propertyLabel . ' · ' . $roleLabel;
    }

    public function presetMatchStatusLabel(): string
    {
        return match ($this->preset_match_status) {
            'matched'          => 'Preset matched',
            'multiple_matches' => 'Multiple matches',
            'no_match'         => 'No preset match',
            default            => ucfirst($this->preset_match_status ?? 'no_match'),
        };
    }

    /**
     * Resolve the source listing URL from the stored snapshot or regenerate it.
     */
    public function resolvedListingUrl(): ?string
    {
        if ($this->source_listing_url) {
            return $this->source_listing_url;
        }
        return HireAgentLeadMatcherService::listingUrl(
            $this->source_listing_type ?? '',
            (int) $this->source_listing_id
        );
    }

    /**
     * Ordered event timeline for the agent detail page.
     *
     * @return array<array{label:string, at:\Carbon\Carbon|null, done:bool}>
     */
    public function eventTimeline(): array
    {
        return [
            ['label' => 'Lead submitted',   'at' => $this->created_at,   'done' => true],
            ['label' => 'Viewed by agent',  'at' => $this->viewed_at,    'done' => (bool) $this->viewed_at],
            ['label' => 'Response sent',    'at' => $this->responded_at, 'done' => (bool) $this->responded_at],
            ['label' => 'Outcome recorded', 'at' => $this->accepted_at ?? $this->declined_at,
             'done'  => in_array($this->status, ['accepted', 'declined', 'closed'])],
        ];
    }
}

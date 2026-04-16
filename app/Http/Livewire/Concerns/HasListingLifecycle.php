<?php

namespace App\Http\Livewire\Concerns;

/**
 * HasListingLifecycle — Phase 3 shared lifecycle trait.
 *
 * Provides the common status/draft/workflow properties and helpers that are
 * shared across ALL listing workflow types (Hire Agent, Offer, future modes).
 *
 * RULES:
 *  - No hire-agent-specific logic here.
 *  - No offer-specific logic here.
 *  - Only universal lifecycle concepts: draft state, approval, status label,
 *    workflow_type identifier, and the success/error flash helpers.
 *
 * TenantAgentAuction does NOT use this trait (it predates the engine and is
 * too large to refactor safely). OfferAuction and all future components WILL.
 */
trait HasListingLifecycle
{
    // Properties declared in the trait — inherited by the component.
    // NOTE: $workflow_type is intentionally NOT here; each component declares
    // it with its own default value ('hire_agent', 'offer', etc.) so PHP does
    // not raise an incompatible trait-composition error.
    public bool   $isDraft        = true;
    public bool   $isApproved     = true;
    public bool   $isSold         = false;
    public string $listing_status = 'Active';

    public string $flashMessage = '';
    public string $flashType    = '';

    protected function flashSuccess(string $msg): void
    {
        $this->flashMessage = $msg;
        $this->flashType    = 'success';
    }

    protected function flashError(string $msg): void
    {
        $this->flashMessage = $msg;
        $this->flashType    = 'danger';
    }

    public function clearFlash(): void
    {
        $this->flashMessage = '';
        $this->flashType    = '';
    }

    public function getStatusLabel(): string
    {
        if ($this->isDraft)    return 'Draft';
        if (!$this->isApproved) return 'Pending Approval';
        if ($this->isSold)     return 'Accepted';
        return $this->listing_status ?: 'Active';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->getStatusLabel()) {
            'Draft'            => 'secondary',
            'Pending Approval' => 'warning',
            'Active'           => 'primary',
            'Accepted'         => 'success',
            'Withdrawn'        => 'danger',
            'Expired'          => 'danger',
            default            => 'secondary',
        };
    }
}

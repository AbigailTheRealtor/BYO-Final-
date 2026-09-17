<?php

namespace App\Services\SmartTags;

use App\Models\User;
use App\Services\HireAgent\HireAgentProposalAccess;
use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingFlag;
use App\Support\Listing\ListingWorkflow;
use App\Support\SmartTags\SmartTagListingRef;

/**
 * May this user change the manual Smart Tags on this native listing?
 *
 * The existing listing permission, applied without inventing a new one:
 *
 *   • the listing owner only — the same rule ResolvesOwnedAuction enforces on
 *     Seller/Landlord Offer Listing Edit (which passes no assigned-agent type),
 *     checked through HireAgentProposalAccess::isListingOwner(), the canonical
 *     integer owner comparison that refuses a null on either side;
 *   • an Offer Listing row only — ListingWorkflowResolver decides; Hire Agent
 *     rows share the table and never receive property tags;
 *   • not archived.
 *
 * Bridge rows have no owner and are never owner-editable. There is no admin
 * bypass, matching the Offer Listing edit path.
 */
class SmartTagListingAuthorizer
{
    public function __construct(
        private readonly HireAgentProposalAccess $ownership,
        private readonly ListingWorkflowResolver $workflows,
    ) {
    }

    public function authorize(?User $actor, SmartTagListingRef $listing): SmartTagAuthorizationDecision
    {
        if ($actor === null || $actor->getKey() === null) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::UNAUTHENTICATED);
        }

        if (! $listing->type->isNative()) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::NOT_OWNER_EDITABLE);
        }

        $class = $listing->type->modelClass();
        $model = $class::query()->find($listing->id);

        if ($model === null) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::NOT_FOUND);
        }

        if (! $this->ownership->isListingOwner((int) $actor->getKey(), $model)) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::NOT_OWNER);
        }

        if (! $this->workflows->matches($model, ListingWorkflow::OFFER_LISTING)) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::NOT_OFFER_LISTING);
        }

        if (ListingFlag::isTrue($model->getAttribute('is_archived'))) {
            return SmartTagAuthorizationDecision::deny(SmartTagAuthorizationDecision::ARCHIVED);
        }

        return SmartTagAuthorizationDecision::allow($model);
    }
}

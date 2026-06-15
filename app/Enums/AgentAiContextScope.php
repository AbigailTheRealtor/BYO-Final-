<?php

namespace App\Enums;

enum AgentAiContextScope: string
{
    case PublicListingSeller   = 'public_listing_seller';
    case PublicListingLandlord = 'public_listing_landlord';
    case BuyerCriteria         = 'buyer_criteria';
    case TenantCriteria        = 'tenant_criteria';
    case AgentProfile          = 'agent_profile';
}

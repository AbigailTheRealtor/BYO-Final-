# Part J — tenant viewer-auth findings (from code inspection)

## Canonical tenant listing
- Model: `TenantAgentAuction`, table `tenant_agent_auctions`, owner col `user_id`, PK `id`.
- Ask AI endpoint uses integer PK `id`. `tenant_criteria_auction` listing_type is ALIASED to `tenant_agent_auctions`.

## Authorized landlord/agent (fail-closed, verifiable)
- PRIMARY: `accepted_bid_summaries` where `listing_type='tenant'` AND `listing_id=:id` AND `agent_user_id=:requesterId`.
  - Model `AcceptedBidSummary`. `tenant_user_id` = listing owner; `agent_user_id` = accepted counterparty (agent/landlord).
  - Existence of row = finalized/accepted deal (this is what `getBidStatusAttribute` treats as "Accepted").
- Acceptance via `tenant_agent_auction_bids.accepted` varchar is loosely typed — DO NOT trust; gate on the summary row.

## DENY (cannot confidently verify)
- `tenant_criteria_auction_bids` (ID-space mismatch with Ask AI routing).
- `auction_chat_*`, `agent_ai_chat_*`, `hire_agent_leads` (no approved-relationship semantics; guest/nullable/wrong direction).

## Scope rule
- guest (no user) -> public
- owner (user_id == requester) -> owner
- tenant listing + accepted_bid_summaries(agent_user_id==requester) -> authorized
- else -> public (redacted)

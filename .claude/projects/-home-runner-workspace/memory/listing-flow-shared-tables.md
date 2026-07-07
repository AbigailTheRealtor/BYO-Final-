---
name: listing-flow-shared-tables
description: Hire Agent and Create Offer Listing both write to the same *_agent_auctions tables; separated only by workflow_type meta
metadata:
  type: project
---

The two listing systems share DB tables. Both "Create Offer Listing" (BidYourOffer, `app/Http/Livewire/OfferListing/*`) and "Hire Agent" (BidYourAgent, `app/Http/Livewire/Hire*Agent/*` + `TenantAgentAuction.php`) persist to the SAME `{role}_agent_auctions` tables + `{role}_agent_auction_metas` EAV. They are told apart only by:
- meta `workflow_type='offer_listing'` (Offer Listing sets this — grep `workflow_type` in `SellerOfferListing.php`)
- fallback heuristic constant `OFFER_LISTING_META_KEYS` (defined in `SellerOfferListingController.php`) — historically included `auction_type` + `brokerage_relationship`, which Hire Agent ALSO writes → draft-mixing bug (Hire rows leaking into Offer "My Listings"/drafts). Discriminator queries live in `DashboardController.php` (the `workflow_type` filter in the dashboard counts + My Listings methods).

**Launch decision (2026-06-28, owner Abigail):** fix draft-mixing via POSITIVE two-sided tagging going forward — tag Hire rows `workflow_type='hire_agent'`, keep Offer rows `'offer_listing'`. Legacy untagged rows classified via the *safest* heuristic (keep only Offer-EXCLUSIVE keys; drop ambiguous keys Hire also writes). NO destructive migration / production backfill in launch phase. Must not hide Offer rows or move Hire rows into Offer drafts. Related: removing Listing Type (`auction_type`) from Hire Agent (Phase 1 item 1) also removes a leak vector. See [[launch-blocker-backlog-tracking]].

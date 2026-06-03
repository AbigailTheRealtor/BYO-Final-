# Offer UI Integration Audit

**Date:** 2026-06-02
**Status:** Pre-wiring reference document — no application code was created or modified.
**Verification:** `git diff --name-only` must show only `resources/docs/OFFER_UI_INTEGRATION_AUDIT.md`.

---

## Section 1 — Scope and Methodology

### What was searched

The following patterns were used across the entire codebase (`app/`, `resources/`, `routes/`) using
ripgrep and direct file reads:

| Pattern | Purpose |
|---|---|
| `Offer::create` | Locate direct Offer model instantiation |
| `offer->status` / `->status =` | Locate direct Offer.status mutation sites |
| `OfferEventLog` / `OfferEventLog::create` | Locate event-log write sites |
| `OfferWorkflowFacade` | Locate any HTTP or Livewire wiring of the facade |
| `accept` / `reject` / `withdraw` / `counter` / `submit` | Locate action handler candidates |
| `offer_auction` / `offerAuction` / `OfferAuction` | Locate OfferAuction (listing) access patterns |
| `parent_offer_id` | Locate counter-offer chain references |
| `submitted` / `countered` | Locate status-label references outside the service layer |
| All named service classes (full class names) | Confirm service existence and absence from controllers/views |

### Tools used

- `ripgrep` content search (via grep tool) across `app/` and `resources/`
- Direct file reads of every controller, Livewire component, and service referenced in the task
- `routes/web.php` full scan (case-insensitive) for `offer`
- Directory listing of `app/Services/Offers/` to confirm service inventory
- Directory listing of `app/Http/Livewire/` for Offer-related components
- Existence check of all four public listing view files

### What is excluded from scope

The following systems are covered by `OFFER_SYSTEM_DO_NOT_TOUCH.md` and are **not** subject to this
audit. They appear in this document only where they share file or route space with Offer surfaces, in
order to document the boundary clearly.

- **BidYourAgent (BYA) legacy bid system** — `PropertyAuction`, `PropertyAuctionBid`, and all
  four role-specific BYA bid controllers.
- **Property DNA / Location DNA / Marketing Intelligence** — all services under
  `app/Services/Dna/` and `app/Services/LocationDna/`.
- **Ask AI / Chatbot** — `OpenAiClientService`, `AskAiContextBuilderService`, and all
  `ASK_AI_*.md` documentation files.
- **Accepted Bid Summary** — `AcceptedBidSummary` model, all four role-specific summary
  services, `AcceptedBidSummaryController`, and associated views.
- **Counterbid system** — all `Counter*` models, all eight Countered-Terms controllers, and
  all counterbid Blade/Livewire views.

---

## Section 2 — Offer System Service Layer — Current State

### Service inventory

All twelve service classes exist under `app/Services/Offers/` and are fully implemented with test
coverage. None of them is wired to any HTTP route, controller action, or Livewire component.

| Class | File | Responsibility |
|---|---|---|
| `OfferWorkflowFacade` | `OfferWorkflowFacade.php` | Thin facade delegating to the five action services. Public methods: `submit`, `counter`, `accept`, `reject`, `withdraw`, `expire`, `timeline`. |
| `OfferSubmissionService` | `OfferSubmissionService.php` | Transitions `draft → submitted`; validates via state machine; writes `offer_submitted` event log. |
| `OfferCounterService` | `OfferCounterService.php` | Transitions parent to `countered`; creates child offer via `Offer::create()`; writes `offer_countered` event log. |
| `OfferDecisionService` | `OfferDecisionService.php` | Handles `accept`, `reject`, `withdraw` transitions; writes corresponding event log entries. |
| `OfferExpirationService` | `OfferExpirationService.php` | Transitions `submitted`/`countered → expired`; writes `offer_expired` event log. |
| `OfferEventLogService` | `OfferEventLogService.php` | Append-only writer for `OfferEventLog`; single `log()` method; never updates or deletes. |
| `OfferStateMachineService` | `OfferStateMachineService.php` | Enforces the allowed-transitions matrix; exposes `validateTransition()`. Carries `APPROVED_STATUSES`, `ACTIVE_STATUSES`, `FINAL_STATUSES`, `ALLOWED_TRANSITIONS` constants. |
| `OfferPermissionService` | `OfferPermissionService.php` | Role-gated permission checks: `canSubmit`, `canCounter`, `canAccept`, `canReject`, `canWithdraw`, `canExpire`, `canViewTimeline`. |
| `OfferAvailableActionsService` | `OfferAvailableActionsService.php` | Aggregates all seven `OfferPermissionService` checks into a single `forOffer()` response array. |
| `OfferHistoryService` | `OfferHistoryService.php` | Read-only queries against `OfferEventLog`: `forOffer`, `forOfferId`, `latestForOffer`. Does not write. |
| `OfferTimelineBuilder` | `OfferTimelineBuilder.php` | Builds a display-ready timeline array from the full negotiation chain. Uses `OfferNegotiationChainService` + `OfferHistoryService`. Does not mutate anything. |
| `OfferNegotiationChainService` | `OfferNegotiationChainService.php` | Traverses `parent_offer_id` links to assemble the root offer and full chain collection. Read-only. |

### Status mutation isolation — confirmed

`Offer.status` is assigned (written) in exactly four locations, all inside `app/Services/Offers/`:

| File | Line | Assignment | Context |
|---|---|---|---|
| `OfferSubmissionService.php` | 74 | `$offer->status = 'submitted'` | Inside `submit()` after state-machine validation passes. Followed immediately by `$offer->save()`. |
| `OfferCounterService.php` | 78 | `$parent->status = 'countered'` | Inside `counter()` after state-machine validation passes. Sets the parent offer to `countered` before creating the child offer via `Offer::create()`. Followed immediately by `$parent->save()`. |
| `OfferDecisionService.php` | 59 | `$offer->status = $toStatus` | Inside the shared `transition()` private method; covers accept, reject, withdraw. Followed immediately by `$offer->save()`. |
| `OfferExpirationService.php` | 73 | `$offer->status = 'expired'` | Inside `expire()` after state-machine validation passes. Followed immediately by `$offer->save()`. |

Zero occurrences of `offer->status =` or `parent->status =` (targeting an `Offer` record) exist in
any controller, Livewire component, Blade view, or any file outside `app/Services/Offers/`.
**Risk: LOW** — mutations are fully isolated inside the service layer.

### `Offer::create` isolation — confirmed

`Offer::create()` is called in exactly one location:

| File | Line | Context |
|---|---|---|
| `OfferCounterService.php` | 95 | Creates the child counter-offer after parent transitions to `countered`. |

Zero other occurrences exist in controllers, Livewire components, or Blade files.

### `OfferWorkflowFacade` wiring — confirmed absent

`OfferWorkflowFacade` is referenced only in its own class definition
(`app/Services/Offers/OfferWorkflowFacade.php`). No controller, route file, Livewire component,
or service provider imports or calls it.

---

## Section 3 — Current Routes — Offer-Related

### Offer Listing (OfferAuction model) routes

These routes operate on the `OfferAuction` model (`offer_auctions` table). They have no relation to
the `Offer` workflow model.

| Route name | Method | URI | Handler | Notes |
|---|---|---|---|---|
| `offer.listing.seller` | GET | `/offer-listing/seller` | `SellerOfferListing` Livewire | Create flow — frozen |
| `offer.listing.buyer` | GET | `/offer-listing/buyer` | `BuyerOfferListing` Livewire | Create flow — frozen |
| `offer.listing.landlord` | GET | `/offer-listing/landlord` | `LandlordOfferListing` Livewire | Create flow — frozen |
| `offer.listing.tenant` | GET | `/offer-listing/tenant/{user_type?}` | `TenantOfferListing` Livewire | Create flow — frozen |
| `offer.listing.seller.edit` | GET | `/offer-listing/seller/edit/{auctionId}` | `SellerOfferListingEdit` Livewire | Edit flow — frozen |
| `offer.listing.buyer.edit` | GET | `/offer-listing/buyer/edit/{auctionId}` | `BuyerOfferListingEdit` Livewire | Edit flow — frozen |
| `offer.listing.landlord.edit` | GET | `/offer-listing/landlord/edit/{auctionId}` | `LandlordOfferListingEdit` Livewire | Edit flow — frozen |
| `offer.listing.tenant.edit` | GET | `/offer-listing/tenant/edit/{auctionId}` | `TenantOfferListingEdit` Livewire | Edit flow — frozen |
| `offer.listing.seller.view` | GET | `/offer-listing/seller/view/{id}` | `SellerOfferListingController@view` | Public listing view |
| `offer.listing.buyer.view` | GET | `/offer-listing/buyer/view/{id}` | `BuyerOfferListingController@view` | Public listing view |
| `offer.listing.landlord.view` | GET | `/offer-listing/landlord/view/{id}` | `LandlordOfferListingController@view` | Public listing view |
| `offer.listing.tenant.view` | GET | `/offer-listing/tenant/view/{id}` | `TenantOfferListingController@view` | Public listing view |
| `offer.listing.seller.searchListing` | GET | `/search/seller-listings` | `SellerOfferListingController@searchOfferListings` | Search — read-only |
| `offer.listing.buyer.searchListing` | GET | `/search/buyer-listings` | `BuyerOfferListingController@searchOfferListings` | Search — read-only |
| `offer.listing.landlord.searchListing` | GET | `/search/rental-properties` | `LandlordOfferListingController@searchOfferListings` | Search — read-only |
| `offer.listing.tenant.searchListing` | GET | `/search/tenant-listings` | `TenantOfferListingController@searchOfferListings` | Search — read-only |
| `offer.listing.seller.question` | POST | `/offer-listing/seller/{auction}/question` | `SellerOfferListingController@submitQuestion` | Inquiry — no Offer model access |
| `offer.listing.seller.showing` | POST | `/offer-listing/seller/{auction}/showing` | `SellerOfferListingController@submitShowing` | Showing request — no Offer model access |
| `offer.listing.buyer.question` | POST | `/offer-listing/buyer/{auction}/question` | `BuyerOfferListingController@submitQuestion` | Inquiry — no Offer model access |
| `offer.listing.landlord.question` | POST | `/offer-listing/landlord/{auction}/question` | `LandlordOfferListingController@submitQuestion` | Inquiry — no Offer model access |
| `offer.listing.landlord.showing` | POST | `/offer-listing/landlord/{auction}/showing` | `LandlordOfferListingController@submitShowing` | Showing request — no Offer model access |
| `offer.listing.tenant.question` | POST | `/offer-listing/tenant/{auction}/question` | `TenantOfferListingController@submitQuestion` | Inquiry — no Offer model access |
| `offer.listing.create` | GET | `/offer/listing/{offer_type?}` | `OfferAuction` Livewire (workflow engine) | Workflow-engine mode |
| `offer.listing.draft` | GET | `/offer/listing/draft/{listingId}` | `OfferAuction` Livewire (workflow engine) | Workflow-engine draft mode |
| `offer.listing.view` | GET | `/offer/listing/view/{id}` | `AgentController@offerListingView` | Agent read-only view; requires `offerPlayoffAccess` |
| `agent.offer-listings` | GET | `/agent/offer-listings` | `AgentController@offerListings` | Agent index; requires `offerPlayoffAccess` |
| `offerListings` | GET | `/admin/offer/listings` | `AdminController@offerListings` | Admin index |
| `offerListing.approve` | POST | `/admin/offer/listing/approve/{id}` | `AdminController@approveOfferListing` | Mutates `OfferAuction.is_approved` only |
| `offerListing.reject` | POST | `/admin/offer/listing/reject/{id}` | `AdminController@rejectOfferListing` | Mutates `OfferAuction.is_approved` / `is_draft` only |

**Note on admin approve/reject:** These routes set `OfferAuction.is_approved` and `OfferAuction.is_draft`.
They do **not** touch the `Offer` model or `Offer.status` in any way.

**Dev-only duplicates (APP_ENV=local only):** Routes `dev.offer-listing.tenant`,
`dev.offer-listing.tenant.edit`, `dev.offer-listing.buyer`, `dev.offer-listing.buyer.edit`, etc. are
registered only in the local environment as smoke-test routes for the duplicated OfferListing Livewire
components.

### Offer Workflow (Offer model) routes — NONE EXIST

There are **zero** routes in `routes/web.php` that:

- Accept a POST, PUT, PATCH, or DELETE to create, submit, accept, reject, withdraw, counter, or
  expire an `Offer` record.
- Call any method on `OfferWorkflowFacade`.
- Reference the `Offer` model for anything other than passive reads inside the service layer.

This is the central gap that must be addressed before any Offer workflow UI can function.

---

## Section 4 — Current Controllers

| Controller | Methods relevant to Offer surfaces | Offer model access | Risk |
|---|---|---|---|
| `AdminController` | `offerListings()` — paginates `OfferAuction` records for admin review; `approveOfferListing($id)` — sets `OfferAuction.is_approved = true`, saves, fires `OfferListingStatusNotification`; `rejectOfferListing($id)` — sets `OfferAuction.is_approved = false` and `is_draft = true`, saves, fires notification | None. Operates exclusively on `OfferAuction`. | LOW — correct scope; isolated from `Offer` workflow. |
| `AgentController` | `offerListings()` — reads BYA agent auction models (Seller/Buyer/Landlord/Tenant agent auction); `offerListingView(int $id)` — reads `OfferAuction` by id; both are read-only | None. `offerListingView` uses `OfferAuctionModel`, not `Offer`. | NONE |
| `SellerOfferListingController` | `view($id)`, `submitQuestion(Request, $auctionId)`, `submitShowing(Request, $auctionId)`, `searchOfferListings(Request)` — all operate on `SellerAgentAuction` / listing EAV meta | None | NONE |
| `BuyerOfferListingController` | `view($id)`, `submitQuestion(Request, $auction)`, `searchOfferListings(Request)` — all operate on `BuyerAgentAuction` / listing EAV meta | None | NONE |
| `LandlordOfferListingController` | `view(int|string $id)`, `submitQuestion(Request, $auction)`, `submitShowing(Request, $auction)`, `searchOfferListings(Request)` — all operate on `LandlordAgentAuction` / listing EAV meta | None | NONE |
| `TenantOfferListingController` | `view(int $id)`, `submitQuestion(Request, $auction)`, `searchOfferListings(Request)` — all operate on `TenantAgentAuction` / listing EAV meta | None | NONE |
| `PropertyAuctionController` | Legacy BYA bids; operates on `PropertyAuction` and `PropertyAuctionBid` models only | None | NONE — **DO NOT TOUCH** |

**No controller in the codebase calls any method on `OfferWorkflowFacade`.**

---

## Section 5 — Current Livewire Components

### Offer-related Livewire components

| Component class | File | Role | Offer model access | Risk |
|---|---|---|---|---|
| `OfferAuction` (workflow engine) | `app/Http/Livewire/OfferAuction.php` | Listing creation / draft / publish for the workflow-engine offer mode. `saveDraft()` and `submitListing()` call `OfferAuctionModel::save()` and `saveAllMeta()`. Does not touch `Offer`. | None — confirmed by direct code search. | LOW — operates on `OfferAuction` only. |
| `SellerOfferListing` | `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | Seller listing creation wizard | None | **DO NOT TOUCH** — frozen per `OFFER_SYSTEM_DO_NOT_TOUCH.md` |
| `SellerOfferListingEdit` | `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` | Seller listing edit wizard | None | **DO NOT TOUCH** |
| `BuyerOfferListing` | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | Buyer listing creation wizard | None | **DO NOT TOUCH** |
| `BuyerOfferListingEdit` | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` | Buyer listing edit wizard | None | **DO NOT TOUCH** |
| `LandlordOfferListing` | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | Landlord listing creation wizard | None | **DO NOT TOUCH** |
| `LandlordOfferListingEdit` | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php` | Landlord listing edit wizard | None | **DO NOT TOUCH** |
| `TenantOfferListing` | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` | Tenant listing creation wizard | None | **DO NOT TOUCH** |
| `TenantOfferListingEdit` | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php` | Tenant listing edit wizard | None | **DO NOT TOUCH** |

### Legacy counter-term Livewire components (Blade-only directories confirmed)

The following Livewire Blade directories are frozen per `OFFER_SYSTEM_DO_NOT_TOUCH.md`. Their PHP
component classes (where they exist) operate on legacy `CounterTerm` / `CounterBidding` models and
have no access to the `Offer` model.

- `resources/views/livewire/buyer-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/buyer-agent-auction-counter-term-tabs/`
- `resources/views/livewire/landlord-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/seller-agent-auction-counter-tabs/`
- `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/tenant-agent-auction-counter-term-tabs/`

**No Livewire component in the codebase calls any method on `OfferWorkflowFacade`.**

---

## Section 6 — Current Views and Blades

### Offer-surface Blade files and their current offer-action buttons

| View file | Current offer-action buttons | Operating model | Offer model access | Risk for Offer wiring |
|---|---|---|---|---|
| `resources/views/livewire/offer-auction.blade.php` | "Publish Listing" button (`wire:click="submitListing"`); "Save Draft" link (`wire:click="saveDraft"`) | `OfferAuction` (listing) | None | NONE — additive placement is safe |
| `resources/views/admin/offerListings.blade.php` | "Approve" button (POST `admin.offerListing.approve`); "Reject" button (POST `admin.offerListing.reject`) | `OfferAuction.is_approved` / `is_draft` | None | LOW — these buttons act on the listing, not the Offer workflow |
| `resources/views/agent/offer-listing-view.blade.php` | Read-only display of `OfferAuction` detail; no action buttons touching `Offer` | `OfferAuction` | None | NONE |
| `resources/views/agent/offer-listings.blade.php` | Agent index of offer listings; read-only list with links | `OfferAuction` / BYA agent auction models | None | NONE |

### Four public listing view pages — legacy BYA buttons (DO NOT TOUCH)

The four public listing pages each contain existing "Bid Now", Accept, Reject, and Counter Bid
buttons. These buttons operate exclusively on legacy BYA bid models (`PropertyAuctionBid`,
`BuyerCriteriaAuctionBid`, etc.) and the corresponding BYA controllers. They have no connection to
the `Offer` model. Their full guard logic, line numbers, and route names are documented in
`OFFER_BUTTON_PLACEMENT_AUDIT.md`.

| View file | Protected by | Notes |
|---|---|---|
| `resources/views/seller_property/view.blade.php` | `OFFER_SYSTEM_DO_NOT_TOUCH.md` | Potential additive insertion zone: lines 3252–3294 per `OFFER_BUTTON_PLACEMENT_AUDIT.md` |
| `resources/views/buyer_criteria/view.blade.php` | `OFFER_SYSTEM_DO_NOT_TOUCH.md` | Potential additive insertion zone: lines 1472–1497 per `OFFER_BUTTON_PLACEMENT_AUDIT.md` |
| `resources/views/landlord_auction/view.blade.php` | `OFFER_SYSTEM_DO_NOT_TOUCH.md` | Potential additive insertion zone: lines 1456–1480 per `OFFER_BUTTON_PLACEMENT_AUDIT.md`. Note: `$my_bid` is hard-coded `''` at line 1454 — unique anomaly vs. the other three listing types. |
| `resources/views/tenant_criteria/view.blade.php` | `OFFER_SYSTEM_DO_NOT_TOUCH.md` | Potential additive insertion zone: lines 622–647 per `OFFER_BUTTON_PLACEMENT_AUDIT.md` |

**These four files must not be modified.** New Offer workflow buttons must be added additively in the
designated insertion zones documented in `OFFER_BUTTON_PLACEMENT_AUDIT.md`.

---

## Section 7 — Direct Offer.status Mutation Inventory

### All mutation sites — confirmed

| File | Line | Exact assignment | Wrapping context |
|---|---|---|---|
| `app/Services/Offers/OfferSubmissionService.php` | 74 | `$offer->status = 'submitted';` | Inside `submit()` after `$this->stateMachine->validateTransition()` returns allowed. Followed immediately by `$offer->save()`. |
| `app/Services/Offers/OfferCounterService.php` | 78 | `$parent->status = 'countered';` | Inside `counter()` after state-machine validation passes. Sets the parent offer to `countered` before creating the child via `Offer::create()`. Followed immediately by `$parent->save()`. |
| `app/Services/Offers/OfferDecisionService.php` | 59 | `$offer->status = $toStatus;` | Inside the private `transition()` method, shared by `accept()`, `reject()`, and `withdraw()`. Followed immediately by `$offer->save()`. |
| `app/Services/Offers/OfferExpirationService.php` | 73 | `$offer->status = 'expired';` | Inside `expire()` after state-machine validation passes. Followed immediately by `$offer->save()`. |

### Confirmed zero occurrences in non-service files

The following file groups were searched and contain **zero** direct `Offer.status` assignments:

- All controllers under `app/Http/Controllers/` — the `status =` occurrences found there operate on
  `CounterTerm.status` (integers `0`/`1`) and `BuyerCounterTerm.status`, not on `Offer.status`.
- All Livewire components under `app/Http/Livewire/` — the `status` property assignments are
  `listing_status`, `occupant_status`, `current_status`, and `pre_approval_status` (all strings on
  Livewire component properties, not Eloquent model mutations).
- All Blade files under `resources/views/`.

**Risk: LOW** — status mutations are fully isolated inside `app/Services/Offers/`.

---

## Section 8 — Direct OfferEventLog Write Inventory

### Write site — confirmed single location

| File | Line | Call | Notes |
|---|---|---|---|
| `app/Services/Offers/OfferEventLogService.php` | 37 | `return OfferEventLog::create([...])` | The only `OfferEventLog::create()` call in the codebase. |

### Confirmed zero occurrences outside OfferEventLogService

The following file groups were searched and contain **zero** direct `OfferEventLog::create()` calls:

- All controllers under `app/Http/Controllers/`
- All Livewire components under `app/Http/Livewire/`
- All Blade files under `resources/views/`
- All other services under `app/Services/` (including the four BYA-related services)
- All Console commands and Jobs

`OfferHistoryService` reads from `OfferEventLog` (via `OfferEventLog::where()`), but never writes to
it — confirmed by direct code read.

**Risk: NONE** — append-only discipline is already enforced. The centralised writer pattern is in
place and unbroken.

---

## Section 9 — Gaps Before Wiring OfferWorkflowFacade

The following items are entirely absent from the codebase. None of these gaps represent a regression
or bug — they are the planned implementation surface for future tasks.

| Gap | Detail |
|---|---|
| No HTTP route to create an `Offer` draft | No POST/GET exists to create a new `Offer` record. The `offers` table and `Offer` model exist; there is no UI, form, or controller action to populate them. |
| No HTTP route to submit an `Offer` | No route calls `OfferWorkflowFacade::submit()` or `OfferSubmissionService::submit()`. |
| No HTTP route to accept an `Offer` | No route calls `OfferWorkflowFacade::accept()` or `OfferDecisionService::accept()`. |
| No HTTP route to reject an `Offer` | No route calls `OfferWorkflowFacade::reject()` or `OfferDecisionService::reject()`. |
| No HTTP route to withdraw an `Offer` | No route calls `OfferWorkflowFacade::withdraw()` or `OfferDecisionService::withdraw()`. |
| No HTTP route to counter an `Offer` | No route calls `OfferWorkflowFacade::counter()` or `OfferCounterService::counter()`. |
| No HTTP route to expire an `Offer` | No route calls `OfferWorkflowFacade::expire()` or `OfferExpirationService::expire()`. |
| No controller calls any `OfferWorkflowFacade` method | Confirmed across all 40+ controllers in `app/Http/Controllers/`. |
| No Livewire component calls any `OfferWorkflowFacade` method | Confirmed across all Livewire components in `app/Http/Livewire/`. |
| No UI button or form on any listing view page acts on an `Offer` record | The four public listing view pages contain BYA bid buttons only. No "Make Offer", "Submit Offer", "Accept Offer", "Reject Offer", "Withdraw Offer", or "Counter Offer" button linked to the `Offer` workflow model exists anywhere. |
| No `Offer` creation form | There is no view, form, or Blade partial that creates a new `Offer` model record. Test factories are the only current creation path. |
| No timeline / history view wired to `OfferTimelineBuilder` | `OfferTimelineBuilder::buildForOffer()` exists and is fully implemented. No Blade view, controller action, or route renders its output. |
| No scheduled command or job wired to `OfferExpirationService` | `OfferExpirationService::expire()` exists. No Artisan command, scheduled job, or queue worker calls it. |
| `OfferRepository` exists but is not wired to the workflow services | `app/Repositories/OfferRepository.php` exists and provides read-only query methods (`findById`, `findWithRelations`, `findByAuction`, `findActiveByAuction`, `findChildren`, `findParent`, `getEventHistory`, `getOfferMeta`, `getAcceptedOfferForAuction`, `loadRelationships`). It does **not** handle status transitions or event-log writes. The `OFFER_WORKFLOW_LIFECYCLE_MAP.md` envisions `OfferRepository` as the central status-transition hub; that role is not yet implemented — the service classes currently call `Offer::save()` directly. |

---

## Section 10 — Recommended Safe Implementation Order

The following sequence minimises rework by ensuring each dependency layer is stable before the
components that depend on it are built. It is additive only throughout — no existing file is
modified at any step.

1. **Add `OfferController`** — Create a new controller (e.g., `app/Http/Controllers/OfferController.php`)
   with `store` (create draft `Offer`), `submit`, `accept`, `reject`, `withdraw`, and `counter`
   methods. Each method delegates exclusively to `OfferWorkflowFacade`. Register corresponding
   routes in `routes/web.php` under `auth` + `offerPlayoffAccess` middleware, using route names in
   the pattern `offer.{action}` (e.g., `offer.submit`, `offer.accept`).

2. **Add "Make Offer / Submit Offer" form and button to appropriate listing view pages** — Additive
   placement only, per the insertion zones documented in `OFFER_BUTTON_PLACEMENT_AUDIT.md`. Do not
   alter any existing markup. Guard the new button with the same role logic as the existing "Bid Now"
   button for each listing type.

3. **Add "Accept / Reject / Withdraw / Counter" action buttons to offer detail / accordion sections**
   — Per the role guards and insertion zones documented in `OFFER_BUTTON_PLACEMENT_AUDIT.md`.
   Additive placement only. Note the inconsistencies flagged in that document (Buyer Criteria uses
   `is_sold` in Accept guard but `sold` in Counter guard; `$my_bid` is hard-coded `''` in Landlord;
   Tenant Criteria Counter Bid guard differs from the other three listing types) — these must be
   reviewed and resolved before new buttons are placed.

4. **Add offer detail page and offer timeline view** — A dedicated Blade view or Livewire component
   to display a single `Offer` record, its terms, its current status, and the `OfferTimelineBuilder`
   output. Accessible to both transacting parties and admin per role guards.

5. **Add scheduled Artisan command wired to `OfferExpirationService`** — Create an Artisan command
   (e.g., `offers:expire-pending`) that queries for `Offer` records where `expires_at < now()` and
   `status` is still `submitted` or `countered`, and calls `OfferWorkflowFacade::expire()` for each.
   Register in `app/Console/Kernel.php`.

---

## Section 11 — DO NOT TOUCH List

This list is reproduced from `OFFER_SYSTEM_DO_NOT_TOUCH.md` Quick Reference Checklist and extended
with files identified by this audit that must not be modified during Offer workflow wiring work.

### From OFFER_SYSTEM_DO_NOT_TOUCH.md

- [ ] The file is **not** one of the eight Create/Edit Offer Listing Blade or Livewire PHP files:
  - `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`
  - `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php`
  - `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`
  - `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php`
  - `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`
  - `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php`
  - `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php`
  - `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php`
  - All eight corresponding Blade views under `resources/views/livewire/offer-listing/`
- [ ] The change does **not** touch `initializeLimitedService()` in any way.
- [ ] The change does **not** alter an existing tooltip, placeholder, or service list entry.
- [ ] The change does **not** modify BYA agent-bid controllers, services, or views:
  - `app/Http/Controllers/BuyerAgentAuctionBidController.php`
  - `app/Http/Controllers/SellerAgentAuctionController.php`
  - `app/Http/Controllers/LandlordAgentAuctionBidController.php`
  - `app/Http/Controllers/TenantAgentAuctionBidController.php`
  - `app/Services/AgentBidMapperService.php`
  - `app/Services/Bya/ByaCompatibilityAccessResolver.php`
  - `app/Services/Dna/Compatibility/ByaCompatibilityReportService.php`
- [ ] The change does **not** modify accepted bid summary services, controller, or views.
- [ ] The change does **not** modify counterbid models, controllers, or views.
- [ ] The change does **not** modify Ask AI services or documentation files.
- [ ] The change does **not** modify Property DNA, Location DNA, or Marketing Intelligence services.

### Additional files identified by this audit

The following files have been confirmed to be part of the BYA legacy bid layer. They must not be
modified during Offer workflow wiring work in addition to those listed above:

- `app/Http/Controllers/PropertyAuctionController.php` — Legacy BYA property auction controller.
  Operates on `PropertyAuction` and `PropertyAuctionBid` models only. **DO NOT TOUCH.**
- `resources/views/seller_property/view.blade.php` — Contains legacy BYA bid buttons; new Offer
  workflow buttons must be added additively only in the zones documented in
  `OFFER_BUTTON_PLACEMENT_AUDIT.md`. Existing markup must not be changed.
- `resources/views/buyer_criteria/view.blade.php` — Same constraint as seller_property/view.
- `resources/views/landlord_auction/view.blade.php` — Same constraint; note the `$my_bid = ''`
  anomaly at line 1454 is in the existing code and must not be "fixed" as part of Offer wiring work.
- `resources/views/tenant_criteria/view.blade.php` — Same constraint.

> **If any checklist box cannot be checked, the future task that authorises the exception must be
> cited by name before proceeding.**

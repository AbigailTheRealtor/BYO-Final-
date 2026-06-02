# Offer System — Do Not Touch Boundary Document

> **Audience:** Every agent, developer, or contributor working on Offer System tasks.
> **Purpose:** Enumerate every area of the codebase that must remain untouched during Offer System work. Read this document before starting any Offer System task.

---

## The Additive-Only Rule

All Offer System work must be **additive only**.

- You may add new files, new routes, new components, new fields, and new UI sections alongside existing code.
- You may **not** modify, refactor, reorder, rename, or delete anything in a protected area unless a future task explicitly names and authorizes the specific change.

> **"This document protects existing functionality during Offer System work, but it does not permanently block future authorized changes. Any exception must be named clearly in a future task."**

This boundary applies only to Offer System workstreams. Future tasks that explicitly target Property DNA, Location DNA, Marketing Intelligence, Ask AI, BYA, Counterbids, or Accepted Bid Summaries may supersede portions of this document when clearly authorized.

When in doubt, add — do not modify.

---

## Protected Areas

### 1. Listing Creation Flows

The full wizard flows for creating and editing offer listings for all four roles are frozen. Do not modify any step logic, tab order, field sets, validation rules, or submission handling in these Livewire components:

| Role | Create Component | Edit Component |
|---|---|---|
| Seller | `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` |
| Buyer | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListingEdit.php` |
| Landlord | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php` |
| Tenant | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` | `app/Http/Livewire/OfferListing/Tenant/TenantOfferListingEdit.php` |

Their corresponding Blade views are equally frozen:

- `resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php`
- `resources/views/livewire/offer-listing/seller/offer-seller-listing-edit.blade.php`
- `resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php`
- `resources/views/livewire/offer-listing/buyer/offer-buyer-listing-edit.blade.php`
- `resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php`
- `resources/views/livewire/offer-listing/landlord/offer-landlord-listing-edit.blade.php`
- `resources/views/livewire/offer-listing/tenant/offer-tenant-listing.blade.php`
- `resources/views/livewire/offer-listing/tenant/offer-tenant-listing-edit.blade.php`

---

### 2. `initializeLimitedService()` — Permanently Frozen

`initializeLimitedService()` is a legacy function present in **all eight** Create Offer Listing Blade files listed above (create and edit for all four roles).

**Never touch this function for any reason.** Specifically:

- Do not modify its logic.
- Do not extract, deduplicate, or refactor any code inside it.
- Do not remove or alter the `requiredFields.forEach` loops inside it.
- Do not remove or alter the `if (!field.value)` checks inside it.
- Do not remove or alter the `isElementVisible` helper defined inside it, even if a seemingly identical helper exists elsewhere.
- Do not remove or alter the `checkFormValidity` call inside it, even if a duplicate exists outside it.

All validation cleanup tasks apply only to the Full Service scope — never inside this function.

---

### 3. Existing Tooltips

All existing tooltip text, tooltip trigger markup (`data-toggle="tooltip"`, `title="..."`, Alpine `x-tooltip`, or any other tooltip mechanism), and tooltip initialization scripts in offer listing views must not be changed. New tooltips may be added alongside existing ones.

---

### 4. Existing Placeholders

All `placeholder="..."` attribute values on existing form inputs across offer listing Blade files must not be changed. New fields may carry their own placeholders.

---

### 5. Existing Service Lists

The service option lists presented in offer listing forms (Full Service and Limited Service offerings, checkboxes, multi-selects, and preset service arrays) must not be modified. This includes:

- Service labels and values in Blade views.
- Default service arrays in Livewire component PHP files.
- Service option arrays in `AgentDefaultProfile` preset data structures.
- Any config arrays driving service display (e.g., `config/offer_services.php` or equivalent).

New services may only be added in a clearly additive way (appended, never replacing an existing entry).

---

### 6. BidYourAgent (BYA) Agent-Bid Logic

The existing agent-bidding system — sometimes referred to as BidYourAgent or BYA — must not be modified. This includes:

**Controllers**
- `app/Http/Controllers/BuyerAgentAuctionBidController.php`
- `app/Http/Controllers/SellerAgentAuctionController.php`
- `app/Http/Controllers/LandlordAgentAuctionBidController.php`
- `app/Http/Controllers/TenantAgentAuctionBidController.php`

**Services**
- `app/Services/AgentBidMapperService.php`
- `app/Services/Bya/ByaCompatibilityAccessResolver.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityReportService.php`

**Views**
- `resources/views/my-bids/agent-bids.blade.php`
- `resources/views/my-bids/hire-buyer-agent-bids.blade.php`
- `resources/views/my-bids/hire-seller-agent-bids.blade.php`
- `resources/views/my-bids/hire-landlord-agent-bids.blade.php`

Do not change bid field mappings, bid validation logic, compatibility scoring inputs, or the agent-bid view structure.

---

### 7. Accepted Bid Summary Logic

The accepted bid summary system is frozen unless a future task explicitly authorizes a targeted change. This includes:

**Models**
- `app/Models/AcceptedBidSummary.php`
- `app/Models/AcknowledgementDocument.php`

**Services**
- `app/Services/AcceptedBidSummaryService.php`
- `app/Services/SellerAcceptedBidSummaryService.php`
- `app/Services/BuyerAcceptedBidSummaryService.php`
- `app/Services/LandlordAcceptedBidSummaryService.php`

**Controller**
- `app/Http/Controllers/AcceptedBidSummaryController.php`

**Views**
- `resources/views/accepted_bid_summary/view.blade.php`
- `resources/views/accepted_bid_summary/sign.blade.php`
- `resources/views/components/bid-detail-layout.blade.php` (shared `x-bid-detail-layout` component)

Do not change summary generation logic, PDF cache invalidation behavior, or the shared bid-detail layout component.

---

### 8. Counterbid Logic

The counterbid system is frozen unless a future task explicitly authorizes a targeted change. This includes:

**Models**
- `app/Models/CounterBid.php`
- `app/Models/CounterTerm.php`
- `app/Models/BuyerCounterBidding.php`
- `app/Models/BuyerCounterTerm.php`
- `app/Models/SellerCounterTerm.php`
- `app/Models/LandlordCounterBidding.php`
- `app/Models/LandlordCounterTerm.php`
- `app/Models/TenantCounterBidding.php`
- `app/Models/TenantCounterTerm.php`
- `app/Models/AgentCounterTerm.php`
- All corresponding `*Meta.php` models (e.g., `BuyerCounterBiddingMeta.php`, `LandlordCounterTermMeta.php`, etc.)

**Controllers**
- `app/Http/Controllers/CounterBidController.php`
- `app/Http/Controllers/CounteredTerms.php`
- `app/Http/Controllers/AgentCounteredTermsController.php`
- `app/Http/Controllers/BuyerCounteredTermsController.php`
- `app/Http/Controllers/SellerCounterBidController.php`
- `app/Http/Controllers/SellerCounteredTermsController.php`
- `app/Http/Controllers/LandlordCounteredTermsController.php`
- `app/Http/Controllers/TenantCounteredTermsController.php`

**Views (all files within these directories)**
- `resources/views/agent_counter_terms/`
- `resources/views/buyer_counter_terms/`
- `resources/views/seller_counter_terms/`
- `resources/views/landlord_counter_terms/`
- `resources/views/tenant_counter_terms/`

**Livewire counter-term tab views**
- `resources/views/livewire/buyer-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/buyer-agent-auction-counter-term-tabs/`
- `resources/views/livewire/landlord-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/seller-agent-auction-counter-tabs/`
- `resources/views/livewire/tenant-agent-auction-bid-counter-tabs/`
- `resources/views/livewire/tenant-agent-auction-counter-term-tabs/`

---

### 9. AI / Ask AI Documents and Chatbot Integrations

The existing AI-powered features and their documentation are frozen. Do not modify:

**Services**
- `app/Services/Ai/OpenAiClientService.php`
- `app/Services/AskAi/AskAiContextBuilderService.php`

**Documentation**
- `resources/docs/ASK_AI_ROADMAP_AND_GUARDRAILS.md`
- `resources/docs/ASK_AI_KNOWLEDGE_MAP.md`

Do not change API client configuration, prompt assembly, context-building logic, or any chatbot route/controller wired to these services. New AI features must use new services and must not alter the existing pipeline.

---

### 10. Property DNA / Location DNA / Marketing Intelligence Systems

These intelligence systems are frozen. Do not modify any service, model, or view within:

**Property DNA**
- `app/Services/Dna/PropertyDnaGenerator.php`
- `app/Services/Dna/PropertyIntelligenceProfileService.php`
- `app/Services/Dna/PropertyDnaExplanationService.php`

**BYA Compatibility (DNA sub-system)**
- `app/Services/Dna/Compatibility/ByaCompatibilityReportService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityAlignmentService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityComparisonService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityExplanationService.php`
- `app/Services/Dna/Compatibility/ByaCompatibilityNarrativeService.php`
- `app/Services/Dna/Compatibility/ByaAgentResponseNormalizationService.php`
- `app/Services/Dna/Compatibility/ByaNormalizationService.php`

**Location DNA**
- `app/Services/LocationDna/LocationDnaAuditService.php`
- `app/Services/LocationDna/LocationDnaGeocodeService.php`
- `app/Services/LocationDna/LocationDnaIntelligenceContextService.php`
- `app/Services/LocationDna/LocationDnaLifestyleScoreService.php`
- `app/Services/LocationDna/LocationDnaMarketingContextService.php`
- `app/Services/LocationDna/LocationDnaPoiDistanceService.php`
- `app/Services/LocationDna/LocationDnaPropertyContextService.php`
- `app/Services/LocationDna/LocationDnaSummaryService.php`

**Marketing Intelligence**
- `app/Services/Dna/AiMarketingReportGeneratorService.php`
- `app/Services/Dna/AiMarketingReportOrchestratorService.php`
- `app/Services/Dna/AiMarketingReportAgentRevisionService.php`
- `app/Services/Dna/AiMarketingReportOwnerApprovalService.php`
- `app/Services/Dna/AiMarketingReportPersistenceService.php`
- `app/Services/Dna/AiMarketingReportPublicationService.php`
- `app/Services/Dna/AiMarketingReportReviewService.php`
- `app/Services/Dna/PropertyMarketingContextService.php`
- `app/Services/Dna/PropertyMarketingBriefService.php`
- `app/Services/Dna/PropertyMarketingReadinessService.php`

Do not change DNA generation inputs, scoring algorithms, report orchestration order, or any public interface these services expose to controllers and views.

---

## Quick Reference Checklist

Before touching any file during an Offer System task, confirm:

- [ ] The file is **not** one of the eight Create/Edit Offer Listing Blade or Livewire PHP files.
- [ ] The change does **not** touch `initializeLimitedService()` in any way.
- [ ] The change does **not** alter an existing tooltip, placeholder, or service list entry.
- [ ] The change does **not** modify BYA agent-bid controllers, services, or views.
- [ ] The change does **not** modify accepted bid summary services, controller, or views.
- [ ] The change does **not** modify counterbid models, controllers, or views.
- [ ] The change does **not** modify Ask AI services or documentation files.
- [ ] The change does **not** modify Property DNA, Location DNA, or Marketing Intelligence services.
- [ ] If any box cannot be checked, the future task that authorizes the exception must be cited by name before proceeding.

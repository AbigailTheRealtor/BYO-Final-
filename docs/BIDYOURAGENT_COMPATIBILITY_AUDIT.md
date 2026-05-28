# BidYourAgent Compatibility Audit
## Phase A — Representation Preferences & Compatibility Field Mapping

**Document Status:** Read-only audit. No code, schema, migration, route, or configuration file was modified to produce this document.
**Audit Date:** 2026-05-28
**Scope:** All four consumer-side Hire Agent listing flows (Seller, Buyer, Landlord, Tenant) and all four agent bid/proposal Livewire components.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current File Inventory](#2-current-file-inventory)
3. [Role-by-Role Question Inventory — Seller](#3-role-by-role-question-inventory--seller)
4. [Role-by-Role Question Inventory — Buyer](#4-role-by-role-question-inventory--buyer)
5. [Role-by-Role Question Inventory — Landlord](#5-role-by-role-question-inventory--landlord)
6. [Role-by-Role Question Inventory — Tenant](#6-role-by-role-question-inventory--tenant)
7. [Shared Compatibility Traits](#7-shared-compatibility-traits)
8. [Role-Specific Compatibility Traits](#8-role-specific-compatibility-traits)
9. [Property-Type Conditional Logic](#9-property-type-conditional-logic)
10. [Existing Field / Meta Key Inventory](#10-existing-field--meta-key-inventory)
11. [Agent Bid / Proposal Gap Analysis](#11-agent-bid--proposal-gap-analysis)
12. [Trait-to-Field Mapping Recommendation](#12-trait-to-field-mapping-recommendation)
13. [Fields That Must NOT Drive Compatibility](#13-fields-that-must-not-drive-compatibility)
14. [Recommended Agent-Side Proposal Structure](#14-recommended-agent-side-proposal-structure)
15. [Compatibility Governance Rules](#15-compatibility-governance-rules)
16. [Future Implementation Phases](#16-future-implementation-phases)

---

## 1. Executive Summary

This document is the Phase A deliverable of the BidYourAgent compatibility system. Its purpose is to exhaustively map every Representation Preferences & Compatibility question currently collected on the consumer (listing) side of the platform and to identify precisely what is missing on the agent bid/proposal side, so that Phases B through F can be designed and implemented without guesswork or rework.

The audit covers four consumer-facing listing flows: Hire Seller Agent, Hire Buyer Agent, Hire Landlord Agent, and Hire Tenant Agent. Each flow contains a full-service-only "Representation Preferences & Compatibility" wizard tab. The tab collects structured responses about how the consumer prefers to communicate, negotiate, make decisions, and be represented — information that is completely invisible to agents when they submit a bid today.

The central finding is stark and unambiguous: **all four consumer-side listing flows collect between 16 and 22 compatibility fields each, while all four agent bid Livewire components collect zero matching fields.** Agents bid blindly on every listing. There is no agent-side counterpart to any consumer compatibility question, no trait normalization layer, no scoring column for representation compatibility in the `listing_compatibility_scores` table, and no UI element in any bid form that acknowledges the existence of the consumer's compatibility preferences.

The platform's existing `listing_compatibility_scores` table already supports four score dimensions (`physical_match_score`, `financial_match_score`, `location_match_score`, `terms_match_score`) with an append-only versioned architecture, but it has no `representation_compatibility_score` column. The consumer-side data is being collected and persisted via the established EAV `saveMeta`/`loadMeta` pattern under a JSON blob stored to the role-specific `*_agent_auction_metas` table, but it currently drives no matching, scoring, or display logic.

This document provides the ground-truth field inventory (Sections 3–6), a cross-role trait analysis (Sections 7–9), the full meta-key storage map (Section 10), a per-role gap analysis (Section 11), a normalized trait-to-field mapping (Section 12), a boundary definition for fields that must be kept separate from compatibility scoring (Section 13), a planning-level recommendation for agent-side proposal fields (Section 14), the governance rules that must constrain all phases (Section 15), and the phased implementation roadmap (Section 16).

No implementation detail sufficient to write code is provided for Phases B through F. This document is a discovery and planning artifact only.

---

## 2. Current File Inventory

### 2.1 Consumer-Side Files Containing `compatibility_preferences`

The following files were confirmed to contain `compatibility_preferences` via source inspection:

| File | Role | Type |
|---|---|---|
| `resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/representation-compatibility.blade.php` | Seller | Blade tab (full service only) |
| `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/representation-compatibility.blade.php` | Buyer | Blade tab (full service only) |
| `resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/representation-compatibility.blade.php` | Landlord | Blade tab (full service only) |
| `resources/views/livewire/tenant-agent-auction-tabs/commission-based/representation-compatibility.blade.php` | Tenant | Blade tab (full service only) |
| `app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php` | Seller | Livewire component (create/edit) |
| `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php` | Buyer | Livewire component (create/edit) |
| `app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php` | Landlord | Livewire component (create/edit) |
| `app/Http/Livewire/TenantAgentAuction.php` | Tenant | Livewire component (create/edit, shared multi-role) |

### 2.2 Agent Bid Components — Zero Compatibility Fields

The following four agent bid Livewire components were inspected in full. **None of them declares a `compatibility_preferences` property, a `compatibility_response` property, or any field that corresponds to any consumer-side compatibility question.**

| File | Role | Compatibility Fields |
|---|---|---|
| `app/Http/Livewire/Seller/SellerAgentAuctionBid.php` | Seller agent bid | **Zero** |
| `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php` | Buyer agent bid | **Zero** |
| `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php` | Landlord agent bid | **Zero** |
| `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php` | Tenant agent bid | **Zero** |

### 2.3 Scoring Infrastructure

| File | Purpose |
|---|---|
| `app/Models/ListingCompatibilityScore.php` | Eloquent model for the scoring table |
| `database/migrations/2026_05_27_000003_create_listing_compatibility_scores_table.php` | Schema for the append-only score table |

---

## 3. Role-by-Role Question Inventory — Seller

**Livewire property path:** `compatibility_preferences.seller_specific.*`
**Storage:** EAV meta via `saveMeta`/`loadMeta` → `seller_agent_auction_metas`

The Seller blade uses the Select2 + `wire:ignore` + `@this.set()` pattern for all `<select>` elements. Free-text inputs use `wire:model` directly. Required fields are validated server-side against `compatibility_preferences.seller_specific.<key>`.

### Section 1 — Communication Preferences

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 1 | `communication_style` | Preferred Communication Style | Single-select | **Yes** | "Frequent & Proactive — I like regular updates", "As-Needed Updates — Contact me when something important comes up", "Available On-Demand — I'll reach out when I have questions", "Structured Check-Ins — Scheduled meetings/calls at agreed intervals" |
| 2 | `preferred_contact_method` | Preferred Contact Method(s) | Multi-select | No | "Phone Call", "Text/SMS", "Email", "Video Call", "In-Person Meeting" |
| 3 | `response_time_expectation` | Expected Agent Response Time | Single-select | No | "Within 1 Hour", "Within a Few Hours", "Same Day", "Next Business Day" |

### Section 2 — Negotiation Style

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 4 | `negotiation_style` | Negotiation Style | Single-select | **Yes** | "Aggressive — Push for Maximum Profit", "Balanced — Fair & Reasonable", "Flexible — Prioritize Quick Sale", "Collaborative — Seller & Buyer Both Win" |
| 5 | `willing_to_negotiate_on` | Areas You Are Willing to Negotiate On | Multi-select | No | "Price Reductions", "Closing Costs", "Repairs / Credits", "Possession Date", "Contingency Waivers", "Inclusions / Exclusions", "Not Open to Negotiation" |
| 6 | `firm_on_price` | Firm on Asking Price | Single-select | No | "Yes — Firm on Price", "Somewhat — Open to Reasonable Offers", "Flexible — Willing to Negotiate Significantly" |

### Section 3 — Primary Transaction Goal

| # | Meta Sub-key | Label | Field Type | Required | Options / Notes |
|---|---|---|---|---|---|
| 7 | `primary_transaction_goal` | Primary Transaction Goal | Single-select | **Yes** | "Maximum Sale Price", "Quick Sale", "Minimal Disruption", "Specific Closing Timeline", "Other" |
| 8 | `primary_transaction_goal_other` | *(companion input)* | Text input | Conditional | Shown only when "Other" is selected |
| 9 | `target_sale_timeline` | Target Sale Timeline | Text input | No | Free text (e.g., "30–60 days") |
| 10 | `flexibility_on_timeline` | Timeline Flexibility | Single-select | No | "Very Flexible", "Somewhat Flexible", "Firm on Timeline" |
| 11 | `post_sale_plan` | Post-Sale Plans | Single-select | No | "Purchasing Another Property", "Renting", "Relocating Out of Area", "Moving to Family / Friends", "Undecided" |

### Section 4 — Representation Priorities

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 12 | `representation_priorities` | Representation Priorities | Multi-select | **Yes** | "Market Expertise", "Strong Negotiator", "High Communication & Responsiveness", "Local Connections & Network", "Marketing Strategy", "Staging / Presentation Expertise", "Digital & Social Media Marketing", "Transaction Management & Coordination" |
| 13 | `qualities_most_important` | Agent Qualities Most Important to You | Multi-select | No | "Honesty & Transparency", "Patience", "Assertiveness", "Attention to Detail", "Tech-Savvy", "Empathy", "Proactivity" |
| 14 | `past_agent_experience` | Past Experience Working with a Real Estate Agent | Single-select | No | "First Time Working with an Agent", "Positive Experience with Past Agent(s)", "Negative Experience with Past Agent(s)", "Mixed Experience" |
| 15 | `what_did_not_work_before` | What Did Not Work Well with Past Agents | Textarea | No | Free text, max 2000 chars |

### Section 5 — Decision-Making Style

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 16 | `decision_making_style` | Decision-Making Style | Single-select | No | "Independent — I Decide Quickly", "Collaborative — I Value Agent Input", "Cautious — I Need Time to Think", "Data-Driven — Show Me the Numbers" |
| 17 | `involvement_level` | Involvement Level | Single-select | No | "Very Involved — Part of every decision", "Moderately Involved — Major steps only", "Mostly Hands-Off — I trust my agent" |
| 18 | `additional_decision_makers` | Decision Makers Involved | Text input | No | Free text (e.g., "Spouse, Co-owner") |

### Section 6 — Working Style Preferences

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 19 | `preferred_agent_working_style` | Preferred Agent Working Style | Single-select | **Yes** | "Proactive & Takes Initiative — Anticipates needs before I ask", "Consultative & Guides Me — Explains options and leads me through decisions", "Responsive & Available — I reach out and they respond promptly", "Process-Oriented & Detail-Focused — Thorough, organized, and precise" |
| 20 | `showing_availability` | Showing Availability | Multi-select | No | "Weekday Mornings", "Weekday Afternoons", "Weekday Evenings", "Weekend Mornings", "Weekend Afternoons", "Weekend Evenings", "Flexible / Anytime" |
| 21 | `open_house_preference` | Open House Preference | Single-select | No | "Strongly Prefer Open Houses", "Open to It", "Prefer Not To", "No Open Houses" |
| 22 | `additional_compatibility_notes` | Additional Compatibility Notes | Textarea | No | Free text, max 2000 chars |

**Total Seller Fields:** 22 (5 required, 17 optional)

---

## 4. Role-by-Role Question Inventory — Buyer

**Livewire property path:** `compatibility_preferences.buyer_specific.*`
**Storage:** EAV meta via `saveMeta`/`loadMeta` → `buyer_agent_auction_metas`

The Buyer blade uses Select2 + `wire:ignore` for `<select>` elements with `data-compat-field` attributes (a slightly different binding pattern from Seller). Free-text inputs and textareas use `wire:model.defer` directly. AlpineJS `x-data` / `x-show` controls companion "Other" inputs. Required fields are validated server-side.

### Section 1 — Buyer Goals & Priorities

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 1 | `primary_transaction_goal` | Primary Transaction Goal | Single-select | **Yes** | "Primary Residence", "Vacation / Secondary Home", "Investment Property", "Fix & Flip", "Commercial Use", "Land Purchase", "Other" |
| 2 | `primary_transaction_goal_other` | *(companion input)* | Text input | Conditional | Shown when "Other" selected |
| 3 | `representation_priorities` | Representation Priorities | Multi-select | **Yes** | "Price Negotiation", "Speed of Transaction", "Finding Off-Market Properties", "Contract Protection", "Communication & Updates", "Neighborhood Expertise", "Investment Analysis", "First-Time Buyer Guidance", "Relocation Assistance", "Other" |
| 4 | `representation_priorities_other` | *(companion input)* | Text input | Conditional | Shown when "Other" is in the selection |
| 5 | `risk_tolerance` | Risk Tolerance Level | Single-select | No | "Very Conservative", "Conservative", "Moderate", "Aggressive", "Very Aggressive" |
| 6 | `decision_making_style` | Decision-Making Style | Single-select | No | "Quick Decisions", "Careful & Deliberate", "Collaborative with Agent", "Research-Driven", "Flexible / Situational" |
| 7 | `timeline_flexibility` | Timeline Flexibility | Single-select | No | "Very Flexible", "Somewhat Flexible", "Limited Flexibility", "Strict Timeline" |

### Section 2 — Communication & Working Style

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 8 | `communication_style` | Communication Style | Single-select | **Yes** | "Frequent Updates (Daily)", "Regular Updates (Every Few Days)", "Weekly Updates", "Only When Necessary", "As-Needed / On-Demand" |
| 9 | `preferred_contact_method` | Preferred Contact Method(s) | Multi-select | No | "Phone Call", "Text Message", "Email", "Video Call (Zoom / FaceTime)", "In-Person Meetings", "Any Method — I'm flexible" |
| 10 | `availability_windows` | Availability / Best Times to Reach You | Text input | No | Free text (e.g., "Weekday evenings after 6pm") |
| 11 | `communication_frequency` | Meeting / Showing Preference *(note: stored under `communication_frequency`)* | Single-select | No | "In-Person Only", "Virtual Tours Accepted", "Agent Pre-Screens for Me", "Flexible / No Preference" |

> **Naming note:** The `communication_frequency` sub-key stores showing/meeting preference data, not contact frequency data. The UI label ("Meeting / Showing Preference") does not match the storage key name. This inconsistency should be addressed when the normalized trait layer is designed in Phase B.

### Section 3 — Negotiation & Representation

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 12 | `negotiation_style` | Negotiation Style | Single-select | **Yes** | "Aggressive Negotiator", "Firm but Fair", "Collaborative", "Offer Full Price to Win", "Guided by Agent" |
| 13 | `preferred_agent_working_style` | Preferred Agent Working Style | Single-select | **Yes** | "Highly Proactive", "Responsive Partner", "Advisor / Consultant", "Full-Service Concierge", "Hands-Off Facilitator", "Other" |
| 14 | `preferred_agent_working_style_other` | *(companion input)* | Text input | Conditional | Shown when "Other" selected |
| 15 | `support_level` | Expected Level of Agent Support | Single-select | No | "Minimal – Self-Sufficient", "Moderate – Key Touchpoints", "High – Guided Throughout", "Full White-Glove Service" |
| 16 | `deal_breakers` | Non-Negotiable Requirements / Deal Breakers | Text input | No | Free text |
| 17 | `additional_compatibility_notes` | Additional Notes for Agent | Textarea | No | Free text, max 2000 chars |

**Total Buyer Fields:** 17 (5 required, 12 optional)

---

## 5. Role-by-Role Question Inventory — Landlord

**Livewire property path:** `compatibility_preferences.landlord_specific.*`
**Storage:** EAV meta via `saveMeta`/`loadMeta` → `landlord_agent_auction_metas`

The Landlord blade uses `wire:model` directly on `<select>` elements (no Select2 / `wire:ignore` wrapper) for most fields, except `representation_priorities` which uses the Select2 + `wire:ignore` pattern. AlpineJS `x-on:change` / `x-show` handles conditional "Other" companion inputs. Required fields are validated server-side.

### Section 1 — Landlord Goals & Leasing Priorities

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 1 | `primary_leasing_goal` | Primary Leasing Goal | Single-select | **Yes** | "Maximize Monthly Rent", "Long-Term Stable Tenant", "Minimize Vacancy Time", "High-Quality Tenant Profile", "Build Portfolio Cash Flow", "Property Appreciation & Upkeep", "Other" |
| 2 | `primary_leasing_goal_other` | *(companion input)* | Text input | Conditional | Shown when "Other" selected |
| 3 | `tenant_type_preference` | Preferred Tenant Type | Single-select | No | "Individual / Family", "Young Professionals", "Students", "Corporate / Relocation", "Small Business", "Retail Business", "Office Tenant", "No Preference", "Other" |
| 4 | `tenant_type_preference_other` | *(companion input)* | Text input | Conditional | Shown when "Other" selected |
| 5 | `lease_duration_preference` | Preferred Lease Duration | Single-select | No | "Month-to-Month", "3–6 Months", "6–12 Months", "1 Year", "2+ Years", "Flexible / Negotiable" |
| 6 | `property_management_involvement` | Level of Involvement in Day-to-Day Management | Single-select | No | "Hands-Off (Agent Manages All)", "Minimal Involvement", "Occasional Check-Ins", "Actively Involved", "Self-Manage After Placement" |

### Section 2 — Communication & Working Style

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 7 | `communication_style` | Preferred Communication Style | Single-select | **Yes** | "Email Only", "Phone Calls Preferred", "Text / SMS Preferred", "Video Calls Preferred", "In-Person Meetings", "Platform Messaging", "Flexible / Any Method" |
| 8 | `preferred_contact_method` | Preferred Contact Frequency *(note: stored under `preferred_contact_method`)* | Single-select | No | "Daily Updates", "Every Few Days", "Weekly Check-Ins", "Only Major Milestones", "Only When I Ask" |
| 9 | `response_time_expectation` | Expected Agent Response Time | Single-select | No | "Within 1 Hour", "Within a Few Hours", "Same Business Day", "Within 24 Hours", "Within 48 Hours", "Flexible" |
| 10 | `preferred_agent_working_style` | Preferred Agent Working Style | Single-select | **Yes** | "Proactive & Assertive", "Consultative & Advisory", "Data-Driven & Analytical", "Relationship-Focused", "Tech-Forward & Efficient", "Traditional & Personalized" |

> **Naming note:** The `preferred_contact_method` sub-key stores contact *frequency* options (Daily Updates, Weekly Check-Ins, etc.), not contact *method* options. The UI label ("Preferred Contact Frequency") is accurate, but the storage key name is misleading. This inconsistency matches a similar pattern seen in the Seller role where `preferred_contact_method` correctly stores method data. The Landlord implementation should be flagged for key rename or remapping during Phase B normalization.

### Section 3 — Negotiation & Representation

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 11 | `negotiation_style` | Negotiation Style | Single-select | **Yes** | "Firm on Terms", "Open to Negotiation", "Collaborative Win-Win", "Market-Rate Anchored", "Flexible Case-by-Case" |
| 12 | `representation_priorities` | Representation Priorities | Multi-select | **Yes** | "Tenant Screening & Vetting", "Marketing & Advertising", "Lease Negotiation", "Legal & Lease Documentation", "Showings & Open Houses", "Market Pricing Guidance", "Move-In Coordination", "Ongoing Communication & Updates" |
| 13 | `risk_tolerance` | Risk Tolerance | Single-select | No | "Low – Strict Screening Only", "Moderate – Standard Criteria", "Flexible – Case-by-Case", "High – Willing to Work With Most Tenants" |
| 14 | `concessions_willingness` | Willingness to Offer Concessions | Single-select | No | "Not Open to Concessions", "Open to Minor Concessions", "Willing to Negotiate Concessions", "Actively Offering Concessions" |
| 15 | `lease_terms_flexibility` | Flexibility on Lease Terms | Single-select | No | "Firm – Standard Terms Only", "Somewhat Flexible", "Very Flexible", "Fully Negotiable" |
| 16 | `additional_representation_notes` | Additional Notes on Representation Preferences | Textarea | No | Free text, max 2000 chars |

**Total Landlord Fields:** 16 (4 required, 12 optional)

---

## 6. Role-by-Role Question Inventory — Tenant

**Livewire property path:** `compatibility_preferences.tenant_specific.*`
**Storage:** EAV meta via `saveMeta`/`loadMeta` → `tenant_agent_auction_metas`

The Tenant blade uses Select2 + `wire:ignore` for all `<select>` elements. Free-text inputs use `wire:model.defer`. Companion "Other" wrappers are controlled via plain `id`-based `display: none/block` toggling via JavaScript (not AlpineJS). Required fields are validated server-side.

### Section 1 — Tenant Goals & Rental Priorities

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 1 | `primary_rental_goal` | Primary Rental Goal | Single-select | **Yes** | "Find a long-term home", "Temporary / short-term housing", "Relocating for work", "Downsizing", "Upsizing", "Investment search", "Other" |
| 2 | `other_primary_rental_goal` | *(companion input)* | Text input | Conditional | Shown when "Other" selected; max 500 chars |
| 3 | `representation_priorities` | Representation Priorities | Multi-select | **Yes** | "Neighborhood / location", "Budget management", "Speed of placement", "Lease negotiation", "Property condition", "Pet-friendly options", "Accessibility features", "School district", "Other" |
| 4 | `other_representation_priorities` | *(companion input)* | Text input | Conditional | Shown when "Other" is in selection; max 500 chars |
| 5 | `timeline_urgency` | Move-In Timeline Urgency | Single-select | No | "Immediate (Within 2 Weeks)", "Within 30 Days", "1–2 Months", "2–3 Months", "3–6 Months", "6+ Months", "Exploring Options Only", "Other" |
| 6 | `other_timeline_urgency` | *(companion input)* | Text input | Conditional | Shown when "Other" selected; max 500 chars |
| 7 | `budget_flexibility` | Budget Flexibility | Single-select | No | "Fixed – no flexibility", "Slightly flexible (±5%)", "Moderately flexible (±10–15%)", "Very flexible (negotiable)" |

### Section 2 — Communication & Working Style

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 8 | `communication_style` | Preferred Communication Style | Single-select | **Yes** | "Email", "Phone calls", "Text / SMS", "Video calls", "In-person meetings", "Other" |
| 9 | `other_communication_style` | *(companion input)* | Text input | Conditional | Shown when "Other" selected; max 500 chars |
| 10 | `contact_frequency` | Preferred Contact Frequency | Single-select | No | "Daily", "Every few days", "Weekly", "Only on major updates", "As needed" |
| 11 | `preferred_contact_method` | Preferred Contact Time of Day *(note: stored under `preferred_contact_method`)* | Single-select | No | "Morning", "Afternoon", "Evening", "Anytime" |
| 12 | `preferred_agent_working_style` | Preferred Agent Working Style | Single-select | **Yes** | "Highly proactive – send regular updates without prompting", "Collaborative – frequent check-ins and joint decisions", "Efficient – contact me only when needed", "Full service – handle everything and keep me informed" |
| 13 | `most_important_agent_traits` | Most Important Agent Traits | Multi-select | No | "Honesty and Transparency", "Strong Communication", "Market Knowledge", "Negotiation Skills", "Responsiveness", "Local Expertise", "Client-Focused Approach", "Technology-Savvy", "Attention to Detail", "Problem-Solving Ability", "Professional Network", "Other" |
| 14 | `other_most_important_agent_traits` | *(companion input)* | Text input | Conditional | Shown when "Other" is in selection; max 500 chars |
| 15 | `desired_level_of_agent_involvement` | Desired Level of Agent Involvement | Single-select | No | "Fully Delegated – Agent manages everything, minimal input needed", "Mostly Delegated – Agent leads, I approve key decisions", "Collaborative – We work together equally throughout", "Mostly Hands-On – I lead, Agent supports and advises", "Other" |
| 16 | `other_desired_level_of_agent_involvement` | *(companion input)* | Text input | Conditional | Shown when "Other" selected; max 500 chars |

> **Naming note:** The `preferred_contact_method` sub-key stores time-of-day preference data (Morning, Afternoon, Evening, Anytime), not contact method data. This is a semantic inconsistency identical to the one in the Landlord role. Phase B normalization should address the key naming across all four roles.

### Section 3 — Negotiation & Representation

| # | Meta Sub-key | Label | Field Type | Required | Options |
|---|---|---|---|---|---|
| 17 | `negotiation_style` | Negotiation Style | Single-select | **Yes** | "Aggressive – push hard for the best deal", "Collaborative – find mutually beneficial terms", "Conservative – prioritize securing a property over terms", "Flexible – adapt based on property and market" |
| 18 | `decision_making_style` | Decision-Making Style | Single-select | No | "Quick – ready to commit fast", "Deliberate – need time to consider options", "Research-driven – want all facts before deciding", "Collaborative – involve family / partner in decisions" |
| 19 | `concerns_or_barriers` | Concerns or Barriers | Textarea | No | Free text, max 2000 chars |
| 20 | `additional_compatibility_notes` | Additional Compatibility Notes | Textarea | No | Free text, max 2000 chars |

**Total Tenant Fields:** 20 (4 required, 16 optional)

---

## 7. Shared Compatibility Traits

The following conceptual traits appear across two or more roles under the same or closely related sub-key names. Option values differ between roles even for the same logical trait — this must be accounted for in the Phase B normalized trait design.

### 7.1 `communication_style`

Present in all four roles. All four collect how frequently or through what method the consumer wants to communicate, but the option vocabulary and framing differ materially.

| Role | Key | Option Framing |
|---|---|---|
| Seller | `communication_style` | Frequency-oriented ("Frequent & Proactive", "As-Needed Updates", etc.) |
| Buyer | `communication_style` | Frequency-oriented ("Frequent Updates (Daily)", "Weekly Updates", etc.) |
| Landlord | `communication_style` | Method-oriented ("Email Only", "Phone Calls Preferred", "Text / SMS Preferred", etc.) |
| Tenant | `communication_style` | Method-oriented ("Email", "Phone calls", "Text / SMS", "Video calls", etc.) |

Note: Seller and Buyer measure *frequency*. Landlord and Tenant measure *channel/method* despite using the same key name. This is an existing inconsistency in the consumer-side data that must be resolved in Phase B.

### 7.2 `negotiation_style`

Present in all four roles. Options are role-specific in vocabulary but all measure the consumer's desired negotiation posture.

| Role | Key | Representative Options |
|---|---|---|
| Seller | `negotiation_style` | "Aggressive — Push for Maximum Profit", "Balanced — Fair & Reasonable", "Flexible — Prioritize Quick Sale", "Collaborative — Seller & Buyer Both Win" |
| Buyer | `negotiation_style` | "Aggressive Negotiator", "Firm but Fair", "Collaborative", "Offer Full Price to Win", "Guided by Agent" |
| Landlord | `negotiation_style` | "Firm on Terms", "Open to Negotiation", "Collaborative Win-Win", "Market-Rate Anchored", "Flexible Case-by-Case" |
| Tenant | `negotiation_style` | "Aggressive – push hard for the best deal", "Collaborative – find mutually beneficial terms", "Conservative – prioritize securing a property over terms", "Flexible – adapt based on property and market" |

### 7.3 `preferred_agent_working_style`

Present in all four roles. All four measure the consumer's preferred agent operating style, though option vocabulary differs significantly.

| Role | Key | Representative Options |
|---|---|---|
| Seller | `preferred_agent_working_style` | "Proactive & Takes Initiative", "Consultative & Guides Me", "Responsive & Available", "Process-Oriented & Detail-Focused" |
| Buyer | `preferred_agent_working_style` | "Highly Proactive", "Responsive Partner", "Advisor / Consultant", "Full-Service Concierge", "Hands-Off Facilitator", "Other" |
| Landlord | `preferred_agent_working_style` | "Proactive & Assertive", "Consultative & Advisory", "Data-Driven & Analytical", "Relationship-Focused", "Tech-Forward & Efficient", "Traditional & Personalized" |
| Tenant | `preferred_agent_working_style` | "Highly proactive – send regular updates without prompting", "Collaborative – frequent check-ins and joint decisions", "Efficient – contact me only when needed", "Full service – handle everything and keep me informed" |

### 7.4 `representation_priorities`

Present in all four roles. The options are entirely role-specific in content but the structural pattern (multi-select with an optional "Other" companion) is consistent across Seller, Buyer, and Tenant. Landlord uses a fixed non-"Other" option set.

### 7.5 `decision_making_style`

Present in three roles (Seller, Buyer, Tenant). All measure how quickly or independently the consumer makes decisions. Option vocabulary differs by role.

| Role | Key | Representative Options |
|---|---|---|
| Seller | `decision_making_style` | "Independent — I Decide Quickly", "Collaborative — I Value Agent Input", "Cautious — I Need Time to Think", "Data-Driven — Show Me the Numbers" |
| Buyer | `decision_making_style` | "Quick Decisions", "Careful & Deliberate", "Collaborative with Agent", "Research-Driven", "Flexible / Situational" |
| Tenant | `decision_making_style` | "Quick – ready to commit fast", "Deliberate – need time to consider options", "Research-driven – want all facts before deciding", "Collaborative – involve family / partner in decisions" |

Not present in Landlord.

### 7.6 `response_time_expectation`

Present in Seller and Landlord roles. Absent from Buyer and Tenant (the Buyer role captures showing/meeting preference under `communication_frequency` instead; Tenant captures preferred contact time under the misnamed `preferred_contact_method`).

### 7.7 `risk_tolerance`

Present in Buyer and Landlord roles. Absent from Seller and Tenant.

| Role | Framing |
|---|---|
| Buyer | Willingness to waive contingencies in a competitive market |
| Landlord | Willingness to accept tenants with imperfect credit or screening histories |

---

## 8. Role-Specific Compatibility Traits

The following fields appear in only one role and have no equivalent in the other three.

### 8.1 Seller-Only Fields

| Sub-key | Description |
|---|---|
| `firm_on_price` | How firm the seller is on their listing price |
| `willing_to_negotiate_on` | Specific transaction elements open to negotiation (multi-select) |
| `post_sale_plan` | What the seller plans to do after the sale |
| `showing_availability` | Days and times when property showings are permitted (multi-select) |
| `open_house_preference` | Whether and how strongly the seller wants open houses |
| `involvement_level` | How involved the seller wants to be in day-to-day decisions |
| `additional_decision_makers` | Other parties involved in the selling decision |
| `qualities_most_important` | Personal agent qualities valued beyond professional skills |
| `past_agent_experience` | Prior experience working with a real estate agent |
| `what_did_not_work_before` | Specific negative past-agent behaviors to avoid |
| `target_sale_timeline` | Free-text target completion timeline |
| `flexibility_on_timeline` | How strictly the target timeline must be met |

### 8.2 Buyer-Only Fields

| Sub-key | Description |
|---|---|
| `risk_tolerance` | Buyer's appetite for transactional risk (contingency waivers, competitive offers) |
| `timeline_flexibility` | How flexible the buyer's purchase timeline is |
| `support_level` | Desired level of hands-on agent guidance throughout the process |
| `deal_breakers` | Absolute must-haves or instant disqualifiers (free text) |
| `availability_windows` | Best times for the agent to reach the buyer (free text) |
| `communication_frequency` *(storage key)* | Meeting and showing format preference |

### 8.3 Landlord-Only Fields

| Sub-key | Description |
|---|---|
| `primary_leasing_goal` | The landlord's highest priority outcome from leasing |
| `tenant_type_preference` | Preferred tenant profile category |
| `tenant_type_preference_other` | Free-text companion for tenant type |
| `lease_duration_preference` | Preferred lease agreement length |
| `property_management_involvement` | Degree of landlord involvement after tenant placement |
| `concessions_willingness` | Openness to offering tenant incentives |
| `lease_terms_flexibility` | Flexibility on adjusting lease terms to attract a qualified tenant |

### 8.4 Tenant-Only Fields

| Sub-key | Description |
|---|---|
| `primary_rental_goal` | The tenant's primary reason for seeking a rental property |
| `timeline_urgency` | How soon the tenant needs to move in |
| `budget_flexibility` | How much the tenant can stretch their rental budget |
| `most_important_agent_traits` | Agent personal qualities valued most (multi-select, 12 options) |
| `desired_level_of_agent_involvement` | Degree of delegation the tenant wants from their agent |
| `concerns_or_barriers` | Rental-search concerns or circumstances (free text textarea) |
| `contact_frequency` | Preferred update cadence (stored under correct key unlike Landlord/Buyer) |

---

## 9. Property-Type Conditional Logic

### 9.1 Consumer-Side Compatibility Blades

After full inspection of all four compatibility blade files, **no property-type branching exists within any of the four compatibility tabs.** All compatibility questions are displayed identically regardless of whether the listing's `property_type` is Residential, Commercial, Business Opportunity, Income Property, or Vacant Land.

This means:
- Seller's `showing_availability` is shown even for Commercial or Vacant Land listings where "showings" may not apply in the traditional sense.
- Landlord's `tenant_type_preference` offers options like "Small Business", "Retail Business", and "Office Tenant" alongside "Individual / Family" for all property types, including Residential — the field is not filtered to commercial-only contexts.
- Tenant's `representation_priorities` includes "Pet-friendly options" and "School district" even for commercial rental searches.

### 9.2 Consumer-Side Livewire Components

The Livewire components (`SellerAgentAuction`, `BuyerAgentAuction`, `LandLordAgentAuction`, `TenantAgentAuction`) initialize `compatibility_preferences` with flat key-value defaults. None of the inspected component code contains property-type-conditional logic that alters which compatibility sub-keys are initialized, saved, or loaded.

### 9.3 Implication for Phase C Design

The absence of property-type branching today creates an opportunity: Phase C (agent-side compatibility field design) can optionally introduce property-type-conditional rendering of agent response fields to better align question relevance with the listing context. This would be a net improvement over the current consumer-side design.

---

## 10. Existing Field / Meta Key Inventory

### 10.1 Consumer-Side Compatibility Storage Map

All compatibility data is stored via the EAV `saveMeta` / `loadMeta` pattern. The full `compatibility_preferences` array is serialized as a JSON blob into the meta table under the key `compatibility_preferences` for each listing record.

| Full Dot-Notation Path | Storage Mechanism | Persisted To (Table) |
|---|---|---|
| `compatibility_preferences.seller_specific.*` (22 sub-keys) | JSON blob via EAV `saveMeta` / `loadMeta` | `seller_agent_auction_metas` |
| `compatibility_preferences.buyer_specific.*` (17 sub-keys) | JSON blob via EAV `saveMeta` / `loadMeta` | `buyer_agent_auction_metas` |
| `compatibility_preferences.landlord_specific.*` (16 sub-keys) | JSON blob via EAV `saveMeta` / `loadMeta` | `landlord_agent_auction_metas` |
| `compatibility_preferences.tenant_specific.*` (20 sub-keys) | JSON blob via EAV `saveMeta` / `loadMeta` | `tenant_agent_auction_metas` |

The `TenantAgentAuction` Livewire component (which serves as a shared multi-role component) initializes all four role namespaces (`seller_specific`, `buyer_specific`, `landlord_specific`, `tenant_specific`) within its `$compatibility_preferences` property, though only `tenant_specific` is rendered by the compatibility Blade tab for Tenant listings.

### 10.2 Scoring Table — Current State

The `listing_compatibility_scores` table (created by migration `2026_05_27_000003_create_listing_compatibility_scores_table.php`) has the following score columns:

| Column | Type | Status |
|---|---|---|
| `overall_score` | decimal(5,2) | Present |
| `physical_match_score` | decimal(5,2) | Present |
| `financial_match_score` | decimal(5,2) | Present |
| `location_match_score` | decimal(5,2) | Present |
| `terms_match_score` | decimal(5,2) | Present |
| `representation_compatibility_score` | — | **Does not exist** |

The table uses an append-only versioned architecture (`version`, `scoring_framework_version`, `archived_at`, `computed_at`) that is well-suited to accommodate a new score dimension without disrupting existing scores. However, the column must be added and the scoring framework version must be incremented before any compatibility scoring can be persisted.

### 10.3 Agent-Side Storage — Current State

The four agent bid models (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`) and their corresponding meta tables contain no `compatibility_preferences` or `compatibility_response` data of any kind.

---

## 11. Agent Bid / Proposal Gap Analysis

This section states, per role, that the agent bid components collect zero compatibility response fields. For each role, the consumer-side required fields are listed as the most critical gaps, since these are the fields for which compatibility scoring is both possible and most meaningful.

### 11.1 Seller Agent Bid — Gap Analysis

**Component:** `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`
**Compatibility fields in component:** **Zero**

Consumer questions with no agent-side counterpart (required fields marked `*`):

- `communication_style` *
- `preferred_contact_method`
- `response_time_expectation`
- `negotiation_style` *
- `willing_to_negotiate_on`
- `firm_on_price`
- `primary_transaction_goal` *
- `target_sale_timeline`
- `flexibility_on_timeline`
- `post_sale_plan`
- `representation_priorities` *
- `qualities_most_important`
- `past_agent_experience`
- `decision_making_style`
- `involvement_level`
- `preferred_agent_working_style` *
- `showing_availability`
- `open_house_preference`

No agent has ever been asked their preferred communication style, whether they prefer to work with sellers who are firm or flexible on price, whether they are comfortable with the seller's decision-making pace, or whether their working style matches the seller's stated preference. All 22 consumer fields are currently unmatched.

### 11.2 Buyer Agent Bid — Gap Analysis

**Component:** `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`
**Compatibility fields in component:** **Zero**

Consumer questions with no agent-side counterpart (required fields marked `*`):

- `primary_transaction_goal` *
- `representation_priorities` *
- `risk_tolerance`
- `decision_making_style`
- `timeline_flexibility`
- `communication_style` *
- `preferred_contact_method`
- `availability_windows`
- `communication_frequency` (showing preference)
- `negotiation_style` *
- `preferred_agent_working_style` *
- `support_level`
- `deal_breakers`

No agent has ever been asked whether they are comfortable with a buyer's risk appetite, how they typically support first-time buyers, or how they match a buyer's preferred negotiation posture. All 17 consumer fields are currently unmatched.

### 11.3 Landlord Agent Bid — Gap Analysis

**Component:** `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`
**Compatibility fields in component:** **Zero**

Consumer questions with no agent-side counterpart (required fields marked `*`):

- `primary_leasing_goal` *
- `tenant_type_preference`
- `lease_duration_preference`
- `property_management_involvement`
- `communication_style` *
- `preferred_contact_method` (contact frequency)
- `response_time_expectation`
- `preferred_agent_working_style` *
- `negotiation_style` *
- `representation_priorities` *
- `risk_tolerance`
- `concessions_willingness`
- `lease_terms_flexibility`

No agent has ever been asked whether they specialize in placing corporate tenants, whether they work with landlords who want hands-off management, or whether they approach lease negotiations as firm-term or fully-negotiable practitioners. All 16 consumer fields are currently unmatched.

### 11.4 Tenant Agent Bid — Gap Analysis

**Component:** `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`
**Compatibility fields in component:** **Zero**

Consumer questions with no agent-side counterpart (required fields marked `*`):

- `primary_rental_goal` *
- `representation_priorities` *
- `timeline_urgency`
- `budget_flexibility`
- `communication_style` *
- `contact_frequency`
- `preferred_contact_method` (time of day)
- `preferred_agent_working_style` *
- `most_important_agent_traits`
- `desired_level_of_agent_involvement`
- `negotiation_style` *
- `decision_making_style`
- `concerns_or_barriers`

No agent has ever been asked whether they can respond to an immediate (within 2 weeks) move-in need, whether they are comfortable with a budget-tight tenant whose flexibility is ±5%, or whether their communication style matches what the tenant has specified. All 20 consumer fields are currently unmatched.

---

## 12. Trait-to-Field Mapping Recommendation

The following table maps each of the 11 normalized compatibility traits from the brief to the consumer-side fields that could populate it, and notes the current absence of any agent-side counterpart across all four roles.

| Normalized Trait | Consumer-Side Source Fields | Agent-Side Field Exists? |
|---|---|---|
| `communication_style` | Seller: `communication_style` (frequency); Buyer: `communication_style` (frequency); Landlord: `communication_style` (channel); Tenant: `communication_style` (channel) | **No** — absent from all four bid components |
| `communication_frequency` | Seller: *(implicit in `communication_style`)*; Buyer: *(implicit)*; Landlord: `preferred_contact_method` (misnamed, stores frequency); Tenant: `contact_frequency` | **No** — absent from all four bid components |
| `responsiveness_expectation` | Seller: `response_time_expectation`; Landlord: `response_time_expectation`; Buyer: *(no direct equivalent)*; Tenant: *(no direct equivalent)* | **No** — absent from all four bid components |
| `negotiation_style` | Seller: `negotiation_style`; Buyer: `negotiation_style`; Landlord: `negotiation_style`; Tenant: `negotiation_style` | **No** — absent from all four bid components |
| `guidance_level` | Seller: `involvement_level`; Buyer: `support_level`; Landlord: `property_management_involvement`; Tenant: `desired_level_of_agent_involvement` | **No** — absent from all four bid components |
| `decision_making_style` | Seller: `decision_making_style`; Buyer: `decision_making_style`; Tenant: `decision_making_style`; Landlord: *(absent)* | **No** — absent from all four bid components |
| `transaction_pace` | Seller: `flexibility_on_timeline`, `target_sale_timeline`; Buyer: `timeline_flexibility`; Tenant: `timeline_urgency`; Landlord: *(no direct equivalent)* | **No** — absent from all four bid components |
| `risk_tolerance` | Buyer: `risk_tolerance`; Landlord: `risk_tolerance`; Seller: *(no direct equivalent)*; Tenant: *(no direct equivalent)* | **No** — absent from all four bid components |
| `collaboration_style` | Seller: `preferred_agent_working_style`; Buyer: `preferred_agent_working_style`; Landlord: `preferred_agent_working_style`; Tenant: `preferred_agent_working_style` | **No** — absent from all four bid components |
| `representation_philosophy` | Seller: `representation_priorities`; Buyer: `representation_priorities`; Landlord: `representation_priorities`; Tenant: `representation_priorities` | **No** — absent from all four bid components |
| `property_strategy_fit` | Seller: `primary_transaction_goal`, `post_sale_plan`, `firm_on_price`; Buyer: `primary_transaction_goal`, `risk_tolerance`; Landlord: `primary_leasing_goal`, `tenant_type_preference`; Tenant: `primary_rental_goal`, `budget_flexibility` | **No** — absent from all four bid components |

---

## 13. Fields That Must NOT Drive Compatibility

The following categories of fields exist in the platform and must be kept strictly separate from the representation compatibility system:

**Broker Compensation & Commission Fields**

Fields such as `purchase_fee_type`, `purchase_fee_percentage`, `purchase_fee_flat`, `lease_fee_type`, `commission_structure`, `commission_structure_type`, `referral_percentage`, `retainer_fee_option`, `early_termination_fee_option`, and all their sub-variants are present in both the consumer listing forms and agent bid forms. These fields define the financial terms of the broker representation agreement. They determine what a consumer pays and what an agent earns.

These are transaction terms, not representation compatibility traits. Including them in compatibility scoring would conflate "does this agent suit my working style?" with "does this agent's price match my budget?" — two entirely different evaluative questions. Compensation compatibility, if measured at all, belongs in the existing `financial_match_score` or `terms_match_score` dimensions, not in a representation compatibility layer.

**Services Lists**

Fields such as `services`, `other_services`, and `flat_fee_services` describe what tactical tasks the agent will perform. These are deliverables and scope-of-work items. They are already incorporated in the existing match score helpers (`SellerBidMatchScoreHelper`, `BuyerBidMatchScoreHelper`, `LandlordBidMatchScoreHelper`, `TenantBidMatchScoreHelper`). They must not be re-scored under the compatibility dimension.

**Agency Agreement & Brokerage Relationship Fields**

Fields such as `agency_agreement_timeframe`, `brokerage_relationship`, and `protection_period` are legal-agreement terms. They describe the structure and duration of the agency contract, not the interpersonal or professional style compatibility between agent and consumer.

**Protected-Class and Fair Housing-Sensitive Data**

No field that relates to race, color, national origin, religion, sex, familial status, disability, age, or any other class protected under the Fair Housing Act, the Equal Credit Opportunity Act, or applicable state law may be introduced into the compatibility system at any phase. This prohibition is absolute and is restated in the Governance Rules (Section 15).

---

## 14. Recommended Agent-Side Proposal Structure

The following is a **planning recommendation only**. No implementation detail, SQL DDL, migration code, Livewire component code, Blade markup, or validation rule is specified here. This section describes what the agent-side compatibility fields should look like conceptually, organized by role, so that Phase C can begin with a clear target.

### Design Principle

The agent-side responses should use the same `compatibility_preferences` JSON pattern already established on the consumer side, stored under a new `agent_response` sub-key to distinguish agent data from consumer data. This means the agent's compatibility responses would be accessed at a path such as `compatibility_preferences.agent_response.seller.*`, maintaining architectural consistency with the existing EAV meta pattern.

The agent responses should be stored in the bid meta table for the corresponding role (e.g., `seller_agent_auction_bid_metas` for Seller agent bids).

### 14.1 Seller Agent Bid — Proposed Response Fields

The agent should be asked to respond to questions that mirror the consumer's compatibility preferences, organized under the same section headings:

**Communication Preferences**
- How the agent typically communicates with clients (frequency/style)
- Channels the agent uses most reliably
- Typical response time commitment

**Negotiation Approach**
- The agent's personal negotiation philosophy when representing sellers
- Whether the agent tends to hold firm or find middle ground

**Transaction Strategy**
- How the agent approaches seller timelines
- Whether the agent has experience with the consumer's stated primary goal (e.g., quick sale, max price)

**Working Style**
- The agent's self-described working style
- Availability for showings
- Stance on open houses as a sales strategy

### 14.2 Buyer Agent Bid — Proposed Response Fields

**Buyer Goals & Priorities**
- Types of buyer transactions the agent specializes in
- How the agent handles competitive offer situations

**Communication & Working Style**
- The agent's communication rhythm with buyer clients
- Preferred contact channels and typical availability

**Negotiation & Representation**
- The agent's negotiation posture on behalf of buyers
- Level of support provided (especially for first-time buyers)
- How the agent handles buyer timelines

### 14.3 Landlord Agent Bid — Proposed Response Fields

**Leasing Strategy**
- The agent's approach to tenant screening
- Lease duration preferences the agent typically recommends
- Experience with the landlord's stated tenant type

**Communication & Working Style**
- How the agent communicates with landlord clients during a lease-up
- Update frequency the agent typically provides

**Negotiation & Representation**
- The agent's stance on lease term negotiation
- Posture on concessions (does the agent typically recommend them?)

### 14.4 Tenant Agent Bid — Proposed Response Fields

**Rental Search Approach**
- Types of rental goals the agent specializes in supporting
- How the agent handles urgent timelines

**Communication & Working Style**
- The agent's communication style and update frequency with tenant clients
- Preferred contact channels

**Negotiation & Representation**
- How the agent approaches lease negotiation for tenants
- Decision-support style (how much guidance the agent provides)

---

## 15. Compatibility Governance Rules

The following rules govern the design and operation of the BidYourAgent compatibility system across all phases. They are non-negotiable constraints.

1. **Professional Representation Compatibility Only.** The compatibility system measures only professional representation style traits: how agents and consumers prefer to communicate, negotiate, make decisions, and collaborate. It never measures personal characteristics, demographic traits, or lifestyle factors.

2. **No Protected-Class Traits.** The system must not collect, store, score, or display any information related to race, color, national origin, religion, sex, familial status, disability, age, sexual orientation, marital status, source of income, or any other class protected by the Fair Housing Act, the Equal Credit Opportunity Act, applicable state law, or Replit platform policy. This prohibition applies at every phase, in every field, in every prompt, and in every AI-generated explanation.

3. **AI Advisory Only — No Hidden Weighting.** Any AI-generated compatibility explanation is advisory and informational. It must never claim to be a definitive match decision. Score weights, if any, must be documented and disclosed in the scoring framework version string. Hidden or undisclosed weighting is prohibited.

4. **No Hidden Weighting.** All scoring weights applied to compatibility traits must be recorded in the `scoring_framework_version` field of the `listing_compatibility_scores` table and documented in the platform's internal scoring specification. No trait may receive a weight that differs from its documented weight.

5. **Fair Housing Compliance at Every Phase.** Every field label, tooltip, option value, AI explanation, and score display must be reviewed for Fair Housing compliance before shipping. Ambiguous language that could imply protected-class preference must be revised or removed. The prohibition extends to option values that may appear neutral but carry implicit protected-class correlations in practice.

6. **Separation from Financial Terms.** Compensation, commission, fees, services lists, and agency agreement terms must never contribute to the representation compatibility score. These are transaction terms and belong in existing financial and terms score dimensions.

---

## 16. Future Implementation Phases

The following phases are listed as planning bullets only. No implementation detail for Phase B through F is designed in this document.

- **Phase A — Audit and Mapping** *(this document)*: Exhaustive field inventory of consumer-side compatibility questions across all four roles; gap analysis confirming zero agent-side counterparts; normalized trait mapping; governance rule documentation.

- **Phase B — Normalized Trait Design**: Define the canonical set of normalized compatibility traits that serve as the cross-role comparison layer. Resolve the naming inconsistencies identified in Sections 7 and 10 (e.g., `preferred_contact_method` storing different data types across roles). Produce a trait specification document that maps each raw consumer sub-key and each proposed agent sub-key to a single normalized trait, with role-specific option-value crosswalk tables.

- **Phase C — Agent Proposal Field Design**: Design the agent-side compatibility response fields for all four bid forms, following the structural recommendation in Section 14. Define the Blade tab, Livewire component additions, validation rules, EAV meta storage keys, and save/load plumbing. No code is written in this document.

- **Phase D — Compatibility Scoring Rules**: Design the scoring logic that compares normalized consumer traits against normalized agent response traits to produce a `representation_compatibility_score`. Decide the weighting model, how to handle missing/unanswered fields, and how to integrate the score into the `listing_compatibility_scores` table (new column, bumped `scoring_framework_version`).

- **Phase E — AI Explanation Layer**: Design the AI-generated plain-language explanation of the compatibility score shown to consumers. Define prompt structure, guardrails for Fair Housing compliance, tone guidelines, and the mechanism for surfacing the explanation in the bid card UI.

- **Phase F — Consumer Comparison UI**: Design the consumer-facing interface that displays each bidding agent's representation compatibility score and explanation alongside their existing match scores. Define how scores are sorted, filtered, and presented, and how consumers can explore the reasoning behind a compatibility rating.

# Agent Bid Wizard — Consumer → Agent Bid → Preset Field Map

> **Purpose:** Cross-reference document mapping every consumer listing field to its corresponding agent bid field and preset key, with exact option-value parity analysis for the Matchmaker compatibility dimensions scored by `ByaCompatibilityComparisonService`.

---

## 0. Scoring Architecture — How Parity Works

`ByaCompatibilityComparisonService::resolveRelationship()` uses **case-sensitive exact string equality** (`(string)$a === (string)$b`) for scalar values and sorted-array equality for multi-select arrays. There is **no normalizer** translating between consumer and agent strings at scoring time. The comparison layer receives already-normalized `BYA_NORM_V1` and `BYA_AGENT_NORM_V1` payloads and compares their `value` fields directly.

**Relationship outcomes:**
- `same` — values are exactly equal (after string cast / array sort)
- `different` — values are non-null but do not match
- `unknown` — either side is null (field absent or unanswered)
- `similar` — **reserved for future governance-defined mappings; never emitted in Phase I**

**Consequence for option values:** For a consumer's selection to score `same` against an agent's selection, the stored `value=""` attribute in the consumer's blade must be byte-for-byte identical to the stored `value=""` attribute in the agent's blade.

---

## 1. The 12 Comparison Dimensions

`ByaCompatibilityComparisonService` compares 12 dimensions. Two (`technology_preference`, `market_education_preference`) are permanently `unknown` because neither profile has a source field for them yet. The remaining 10 are analyzed below.

---

### 1.1 `communication_style` → trait `communication_channel`

**Consumer field crosswalk (per `ByaNormalizationService`):**
| Role | Raw key | Type |
|------|---------|------|
| Seller | `preferred_contact_method` | multi-select array |
| Buyer  | `preferred_contact_method` | multi-select array |
| Landlord | `communication_style` | **single scalar** |
| Tenant   | `communication_style` | **single scalar** |

**Agent field:** `communication_preferences.agent_communication_channels` → multi-select array  
**Agent stored values:** `"Phone Call"`, `"Text Message"`, `"Email"`, `"Video Call"`, `"In-Person Meeting"`, `"Messaging App"`

**Parity audit by role:**

| Role | Consumer option → stored value | Agent value | Result |
|------|-------------------------------|-------------|--------|
| Seller | "Phone Call" | "Phone Call" | ✅ `same` (when arrays equal) |
| Seller | "Text / SMS" → `"Text Message"` *(fixed)* | "Text Message" | ✅ `same` |
| Seller | "Email" → `"Email"` | "Email" | ✅ `same` |
| Seller | "Video Call" → `"Video Call"` | "Video Call" | ✅ `same` |
| Seller | "In-Person Meeting" → `"In-Person Meeting"` | "In-Person Meeting" | ✅ `same` |
| Buyer | "Phone Call", "Text Message", "Email", "Video Call" | same | ✅ `same` |
| Buyer | "In-Person Meetings" → `"In-Person Meeting"` *(fixed)* | "In-Person Meeting" | ✅ `same` |
| Buyer | "Any Method – I'm flexible" → `"Any Method"` | *(no agent equivalent)* | always `different` |
| Landlord | Any value (single scalar) | Agent stores array | ⚠️ **Type mismatch — always `different`** |
| Tenant | Any value (single scalar) | Agent stores array | ⚠️ **Type mismatch — always `different`** |

> **Root cause for Landlord/Tenant:** These roles' forms collect communication channel as a single-select (one preference), while the agent form is a multi-select array. The comparison service treats mixed scalar/array as never identical. Resolving this requires a future form redesign to make these multi-select.

---

### 1.2 `communication_frequency` → trait `communication_frequency`

**Consumer field crosswalk:**
| Role | Raw key | Notes |
|------|---------|-------|
| Seller | `communication_style` | Stores frequency-philosophy strings ("Frequent & Proactive", etc.) |
| Buyer  | `communication_style` | Stores frequency-philosophy strings ("Frequent Updates (Daily)", etc.) |
| Landlord | `preferred_contact_method` | Stores cadence strings aligned to agent |
| Tenant   | `contact_frequency` | Stores cadence strings aligned to agent |

**Agent field:** `communication_preferences.agent_communication_frequency` → single scalar  
**Agent stored values:** `"Daily Updates"`, `"Every Few Days"`, `"Weekly"`, `"At Key Milestones"`, `"As Needed"`

**Parity audit by role:**

| Role | Consumer stored value | Agent value | Result |
|------|----------------------|-------------|--------|
| Seller | "Frequent & Proactive", "As-Needed Updates", "Available On-Demand", "Structured Check-Ins" | (none match) | ⚠️ **Always `different`** — semantic mismatch (philosophy vs. cadence) |
| Buyer  | "Frequent Updates (Daily)", "Regular Updates (Every Few Days)", "Weekly Updates", "Only When Necessary", "As-Needed / On-Demand" | (none match) | ⚠️ **Always `different`** — semantic mismatch |
| Landlord | `"Daily Updates"` | "Daily Updates" | ✅ `same` |
| Landlord | `"Every Few Days"` | "Every Few Days" | ✅ `same` |
| Landlord | `"Weekly"` *(was "Weekly Check-Ins" — fixed)* | "Weekly" | ✅ `same` |
| Landlord | `"At Key Milestones"` *(was "Only Major Milestones" — fixed)* | "At Key Milestones" | ✅ `same` |
| Landlord | `"As Needed"` *(was "Only When I Ask" — fixed)* | "As Needed" | ✅ `same` |
| Tenant | `"Daily Updates"` *(was "Daily" — fixed)* | "Daily Updates" | ✅ `same` |
| Tenant | `"Every Few Days"` *(was "Every few days" — fixed)* | "Every Few Days" | ✅ `same` |
| Tenant | `"Weekly"` | "Weekly" | ✅ `same` |
| Tenant | `"At Key Milestones"` *(was "Only on major updates" — fixed)* | "At Key Milestones" | ✅ `same` |
| Tenant | `"As Needed"` *(was "As needed" — fixed)* | "As Needed" | ✅ `same` |

> **Note for Seller/Buyer:** Their `communication_style` raw key stores a frequency-philosophy description ("Frequent & Proactive") rather than the cadence strings used by Landlord/Tenant. These values will always differ from the agent's cadence options. A future form update for Seller/Buyer would need to replace that single "communication style" question with the cadence dropdown.

---

### 1.3 `availability_expectation` → trait `responsiveness_expectation`

**Consumer field crosswalk:**
| Role | Raw key | Present? |
|------|---------|----------|
| Seller | `response_time_expectation` | ✅ |
| Buyer  | *(absent)* | ❌ always `unknown` |
| Landlord | `response_time_expectation` | ✅ |
| Tenant | *(absent — `preferred_contact_method` stores time-of-day, not response time)* | ❌ always `unknown` |

**Agent field:** `communication_preferences.agent_response_time_commitment` → single scalar  
**Agent stored values:** `"Within 1 Hour"`, `"Within a Few Hours"`, `"Same Business Day"`, `"Within 24 Hours"`, `"Within 48 Hours"`

**Parity audit:**

| Role | Consumer stored value | Agent value | Result |
|------|----------------------|-------------|--------|
| Seller | `"Within 1 Hour"` | "Within 1 Hour" | ✅ `same` |
| Seller | `"Within a Few Hours"` | "Within a Few Hours" | ✅ `same` |
| Seller | `"Same Business Day"` *(was "Same Day" — fixed)* | "Same Business Day" | ✅ `same` |
| Seller | `"Next Business Day"` | *(no agent equivalent)* | always `different` |
| Landlord | `"Within 1 Hour"`, `"Within a Few Hours"`, `"Same Business Day"`, `"Within 24 Hours"`, `"Within 48 Hours"` | same strings | ✅ `same` for all 5 |
| Landlord | `"Flexible"` | *(no agent equivalent)* | always `different` |
| Buyer | — | — | always `unknown` |
| Tenant | — | — | always `unknown` |

---

### 1.4 `negotiation_style` → trait `negotiation_style`

**Consumer raw key:** `negotiation_style` (all 4 roles)  
**Agent field:** `negotiation_approach.agent_negotiation_style` → single scalar  
**Agent stored values:** `"Assertive"`, `"Collaborative"`, `"Methodical"`, `"Adaptive"`, `"Conservative"`

**Parity audit:**

| Role | Consumer stored values | Exact match possible? |
|------|----------------------|-----------------------|
| Seller | "Aggressive — Push for Maximum Profit", "Balanced — Fair & Reasonable", "Flexible — Prioritize Quick Sale", "Collaborative — Seller & Buyer Both Win" | ❌ None match — framing mismatch |
| Buyer | "Aggressive Negotiator", "Firm but Fair", **`"Collaborative"`**, "Offer Full Price to Win", "Guided by Agent" | ✅ "Collaborative" → `same` |
| Landlord | "Firm on Terms", "Open to Negotiation", "Collaborative Win-Win", "Market-Rate Anchored", "Flexible Case-by-Case" | ❌ None match |
| Tenant | "Aggressive – push hard for the best deal", "Collaborative – find mutually beneficial terms", "Conservative – prioritize securing a property over terms", "Flexible – adapt based on property and market" | ❌ None match (suffixes prevent exact equality) |

> **Note:** For Seller, Landlord, and Tenant, the consumer options use descriptive narrative strings while the agent options use single-word style labels. String alignment would require changing three consumer forms to use bare words like "Assertive", "Collaborative", etc. — a UX trade-off for a future form revision.

---

### 1.5 `advisor_expectation` → trait `guidance_level`

**Consumer field crosswalk:**
| Role | Raw key | Consumer options |
|------|---------|-----------------|
| Seller | `involvement_level` | "Very Involved — Part of every decision", "Moderately Involved — Major steps only", "Mostly Hands-Off — I trust my agent" |
| Buyer  | `support_level` | "Minimal – Self-Sufficient", "Moderate – Key Touchpoints", "High – Guided Throughout", "Full White-Glove Service" |
| Landlord | `property_management_involvement` | "Hands-Off (Agent Manages All)", "Minimal Involvement", "Occasional Check-Ins", "Actively Involved", "Self-Manage After Placement" |
| Tenant | `desired_level_of_agent_involvement` | "Fully Delegated – Agent manages everything…", "Mostly Delegated – Agent leads…", "Collaborative – We work together…", "Mostly Hands-On – I lead…" |

**Agent stored values:** `"Hands-On"`, `"Balanced"`, `"Advisory"`, `"Minimal"`

**Verdict:** ⚠️ **Always `different` (all roles).** Consumer options use verbose narrative strings; agent options use single-word style labels. The dimensions also differ semantically (consumer = desired involvement level; agent = guidance style). No exact string matches are possible without a future form redesign.

---

### 1.6 `property_search_involvement` → trait `collaboration_style`

**Consumer raw key:** `preferred_agent_working_style` (all 4 roles)  
**Agent field:** `collaboration_preferences.agent_collaboration_style` → single scalar  
**Agent stored values:** `"Highly Proactive"`, `"Steady & Systematic"`, `"Flexible & Responsive"`, `"Team-Oriented"`

**Parity audit:**

| Role | Consumer stored values | Exact match possible? |
|------|----------------------|-----------------------|
| Seller | "Proactive & Takes Initiative", "Consultative & Guides Me", "Responsive & Available", "Process-Oriented & Detail-Focused" | ❌ None match |
| Buyer  | **`"Highly Proactive"`**, "Responsive Partner", "Advisor / Consultant", "Full-Service Concierge", "Hands-Off Facilitator", "Other" | ✅ "Highly Proactive" → `same` |
| Landlord | "Proactive & Assertive", "Consultative & Advisory", "Data-Driven & Analytical", "Relationship-Focused", "Tech-Forward & Efficient", "Traditional & Personalized" | ❌ None match |
| Tenant | "Highly proactive – send regular updates without prompting", "Collaborative – frequent check-ins…", "Efficient – contact me only when needed", "Full service – handle everything…" | ❌ Suffixes prevent exact match; note Tenant has lowercase "p" in "proactive" |

---

### 1.7 `decision_speed` → trait `transaction_pace`

**Consumer field crosswalk:**
| Role | Raw key | Consumer options |
|------|---------|-----------------|
| Seller | `flexibility_on_timeline` | "Very Flexible", "Somewhat Flexible", "Firm on Timeline" |
| Buyer  | `timeline_flexibility` | "Very Flexible", "Somewhat Flexible", "Limited Flexibility", "Strict Timeline" |
| Landlord | *(absent)* | always `unknown` |
| Tenant | `timeline_urgency` | "Immediate (Within 2 Weeks)", "Within 30 Days", "1–2 Months", etc. |

**Agent stored values:** `"Fast-Paced"`, `"Moderate"`, `"Patient"`, `"Client-Driven"`

**Verdict:** ⚠️ **Always `different` or `unknown`.** Consumer captures urgency/flexibility ("Firm on Timeline"); agent captures pace style ("Fast-Paced"). Fundamentally different dimensions — cannot be aligned by string changes.

---

### 1.8 `transaction_guidance_level` → trait `decision_making_style`

**Consumer field crosswalk:**
| Role | Raw key | Consumer options |
|------|---------|-----------------|
| Seller | `decision_making_style` | "Independent — I Decide Quickly", "Collaborative — I Value Agent Input", "Cautious — I Need Time to Think", "Data-Driven — Show Me the Numbers" |
| Buyer  | `decision_making_style` | "Quick Decisions", "Careful & Deliberate", "Collaborative with Agent", "Research-Driven", "Flexible / Situational" |
| Landlord | *(absent)* | always `unknown` |
| Tenant | `decision_making_style` | "Quick – ready to commit fast", "Deliberate – need time to consider options", "Research-driven – want all facts before deciding", "Collaborative – involve family / partner in decisions" |

**Agent stored values:** `"Data-Driven"`, `"Options-Based"`, `"Recommendation-First"`, `"Collaborative Discussion"`

**Verdict:** ⚠️ **Always `different` or `unknown`.** Consumer describes their own decision-making style; agent describes how they support client decisions. Different perspectives on different concepts — cannot be aligned by string changes.

---

### 1.9 `risk_tolerance` → trait `risk_tolerance`

**Consumer field crosswalk:**
| Role | Raw key | Present? |
|------|---------|----------|
| Seller | *(absent)* | ❌ always `unknown` |
| Buyer  | `risk_tolerance` | ✅ |
| Landlord | `risk_tolerance` | ✅ |
| Tenant | *(absent)* | ❌ always `unknown` |

**Agent stored values:** `"Conservative"`, `"Balanced"`, `"Opportunistic"`, `"Adaptive"`

**Parity audit:**

| Role | Consumer stored values | Exact match possible? |
|------|----------------------|-----------------------|
| Buyer | "Very Conservative", **`"Conservative"`**, "Moderate", "Aggressive", "Very Aggressive" | ✅ "Conservative" → `same` |
| Landlord | "Low – Strict Screening Only", "Moderate – Standard Criteria", "Flexible – Case-by-Case", "High – Willing to Work With Most Tenants" | ❌ None match — different framing (screening strictness vs. general risk posture) |

---

### 1.10 `personality_style` → trait `representation_philosophy`

**Consumer:** Seller only, raw key `past_agent_experience` → single scalar. Buyer/Landlord/Tenant always `unknown`.  
**Seller stored values:** `"First Time Working with Agent"`, `"Positive Experience"`, `"Negative Experience"`, `"Mixed Experience"`

**Agent field:** `representation_philosophy.agent_representation_philosophy` → multi-select array  
**Agent stored values:** `"Fiduciary-First"`, `"Transparent Communication"`, `"Full-Service Partnership"`, `"Education-Focused"`, `"Results-Oriented"`, `"Long-Term Relationship"`

**Verdict:** ⚠️ **Always `different` (Seller) or `unknown` (others).** Scalar vs. array type mismatch plus completely different semantic dimensions (past experience vs. professional philosophy).

---

### 1.11 Dimensions permanently `unknown` (no source fields)

| Dimension | Consumer trait | Agent trait | Status |
|-----------|---------------|-------------|--------|
| `technology_preference` | null | null | Placeholder — awaiting future profile schema |
| `market_education_preference` | null | null | Placeholder — awaiting future profile schema |

---

## 2. Parity Fix Summary

The following option value corrections were made to align stored consumer values with agent stored values for exact-equality scoring:

| File | Field | Old `value=""` | New `value=""` | Display label (unchanged) |
|------|-------|---------------|---------------|--------------------------|
| Seller `representation-compatibility.blade.php` | `preferred_contact_method` | `Text/SMS` | `Text Message` | "Text / SMS" |
| Seller `representation-compatibility.blade.php` | `response_time_expectation` | `Same Day` | `Same Business Day` | "Same Business Day" |
| Buyer `representation-compatibility.blade.php` | `preferred_contact_method` | `In-Person` | `In-Person Meeting` | "In-Person Meetings" |
| Landlord `representation-compatibility.blade.php` | `preferred_contact_method` (frequency) | `Weekly Check-Ins` | `Weekly` | "Weekly Check-Ins" |
| Landlord `representation-compatibility.blade.php` | `preferred_contact_method` (frequency) | `Only Major Milestones` | `At Key Milestones` | "Only Major Milestones" |
| Landlord `representation-compatibility.blade.php` | `preferred_contact_method` (frequency) | `Only When I Ask` | `As Needed` | "Only When I Ask" |
| Tenant `representation-compatibility.blade.php` | `contact_frequency` | `Daily` | `Daily Updates` | "Daily" |
| Tenant `representation-compatibility.blade.php` | `contact_frequency` | `Every few days` | `Every Few Days` | "Every few days" |
| Tenant `representation-compatibility.blade.php` | `contact_frequency` | `Only on major updates` | `At Key Milestones` | "Only on major updates" |
| Tenant `representation-compatibility.blade.php` | `contact_frequency` | `As needed` | `As Needed` | "As needed" |

---

## 3. Scoring Capability Summary (Phase I)

| Dimension | Can produce `same`? | Roles that can score `same` | Notes |
|-----------|--------------------|-----------------------------|-------|
| `communication_style` | ✅ Partial | Seller, Buyer | Landlord/Tenant: scalar vs. array type mismatch |
| `communication_frequency` | ✅ Partial | Landlord, Tenant | Seller/Buyer: semantic mismatch in raw key |
| `availability_expectation` | ✅ Partial | Seller (3/4 values), Landlord (5/6 values) | Buyer/Tenant: absent |
| `negotiation_style` | ✅ Partial | Buyer only ("Collaborative") | Other roles: narrative vs. label mismatch |
| `property_search_involvement` | ✅ Partial | Buyer only ("Highly Proactive") | Other roles: narrative vs. label mismatch |
| `risk_tolerance` | ✅ Partial | Buyer only ("Conservative") | Landlord: framing mismatch; Seller/Tenant: absent |
| `advisor_expectation` | ❌ Never | None | All roles: narrative vs. label mismatch |
| `decision_speed` | ❌ Never | None | Urgency/flexibility vs. pace style — different concepts |
| `transaction_guidance_level` | ❌ Never | None | Consumer style vs. agent support method — different concepts |
| `personality_style` | ❌ Never | None | Scalar vs. array + semantic mismatch |
| `technology_preference` | ❌ Never | None | Placeholder — no source fields |
| `market_education_preference` | ❌ Never | None | Placeholder — no source fields |

---

## 4. Consumer → Agent Bid → Preset Field Map (All Roles)

### 4.1 Shared fields (all 4 roles)

| Consumer Listing Field / Meta Key | Agent Bid Property | Preset Key (`buildProfileData`) | Notes |
|---|---|---|---|
| `bio` (agent overview) | `$bio` | `bio` | Agent writes their own bio; no consumer counterpart |
| `why_hire_you` | `$why_hire_you` | `why_hire_you` | — |
| `what_sets_you_apart` | `$what_sets_you_apart` | `what_sets_you_apart` | — |
| `marketing_plan` | `$marketing_plan` | `marketing_plan` | — |
| `additional_details` | `$additional_details` | `additional_details` | — |
| `website_link` | `$website_link` | `website_link` | Saved as single string (first array element) |
| `reviews_links` | `$reviews_links` | `reviews_links` | JSON array of `{text: url}` (Seller/Buyer/Tenant) or `{url: url}` (Landlord) |
| `social_media` | `$social_media` | `social_media` | JSON array of `{platform, url/text}` |
| `awards_recognition` | `$awards_recognition` | `awards_recognition` | Social Proof section |
| `sold_listed_examples` | `$sold_listed_examples` | `sold_listed_examples` | Role-specific label per blade |
| `marketing_success_examples` | `$marketing_success_examples` | `marketing_success_examples` | Role-specific label per blade |
| `presentation_link` | `$presentation_link` | `presentation_link` | Virtual presentation video URL |
| `business_card_link` | `$business_card_link` | `business_card_link` | Virtual business card link |
| `business_card_stored_path` | `$business_card_stored_path` | `business_card_stored_path` | Saved file path |
| `promoMaterials` | `$promoMaterials` | `promoMaterials` | JSON array of marketing material entries |
| `year_licensed` | `$year_licensed` | `year_licensed` | — |
| `first_name`, `last_name` | `$first_name`, `$last_name` | `first_name`, `last_name` | Prefers bid data; falls back to user profile |
| `phone`, `email` | `$phone`, `$email` | `phone`, `email` | — |
| `brokerage`, `license_no`, `nar_id` | `$brokerage`, `$license_no`, `$nar_id` | `brokerage`, `license_no`, `nar_id` | — |
| `avg_response_time` | `$avg_response_time` | `avg_response_time` | — |
| `availability_status` | `$availability_status` | `availability_status` | — |
| `evenings_available`, `weekends_available` | `$evenings_available`, `$weekends_available` | `evenings_available`, `weekends_available` | — |
| `years_experience`, `transactions_last_12_months`, `is_full_time` | same | same | Experience & Service Area tab |
| `primary_areas_served`, `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` | same | same | — |
| `services` | `$services` | `services` | JSON array of selected services |
| `other_services` | `$other_services` | `other_services` | Custom/other services |
| `compatibility_agent_response` | Saved via `saveMeta` | _(not preset — per-listing)_ | Working Style & Compatibility tab |

### 4.2 Role-specific fields

| Role | Agent Bid Property | Preset Key | Notes |
|------|-------------------|------------|-------|
| **Seller** | `commission_structure`, `commission_structure_type`, fee fields | same | Commission tab fields |
| **Seller** | `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | same | — |
| **Seller** | `seller_leasing_*` fields | same | Leasing sub-tab |
| **Buyer** | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage` | same | — |
| **Buyer** | `retainer_fee_option`, `retainer_fee_amount` | same | — |
| **Landlord** | `commission_structure`, `lease_type`, `lease_value` | same | — |
| **Landlord** | `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | same | — |
| **Landlord** | `photo_enhancements`, `custom_enhancement` | same | — |
| **Tenant** | `commission_structure`, `lease_fee_type`, fee fields | same | — |
| **Tenant** | `referral_fee_percent` | _(not preset — conditional)_ | Only when listing is agent-created |

---

## 5. Key Design Decisions

- **No normalizer at comparison time:** `ByaCompatibilityComparisonService` compares stored values directly. Option strings in consumer and agent blades must be byte-for-byte identical for a `same` result. The `ByaNormalizationService` and `ByaAgentResponseNormalizationService` only standardize structure (trait key routing, slot shape) — they do not rewrite option string values.
- **"similar" is reserved:** The comparison service never emits `similar` in Phase I. It is reserved for future governance-defined similarity tables (e.g. near-equivalent response time tiers).
- **Preset does not include compatibility responses:** The `compatibility_agent_response` array is listing-specific — agents tailor their Working Style responses per-listing. It is saved via `saveMeta` but excluded from the default profile preset intentionally.
- **Social Proof fields are presetable:** `awards_recognition`, `sold_listed_examples`, `marketing_success_examples` are in `buildProfileData()` / `loadDefaultProfile()` so agents can save and reuse across bids.
- **Role-specific Social Proof copy:** Each role's agent-presentation blade uses role-appropriate field labels (Sales/Listings for Seller; Buyer transactions for Buyer; Rental placements for Landlord/Tenant).
- **Structural gaps documented, not patched:** Dimensions that always produce `different` or `unknown` due to semantic or type mismatches (§1.5 `advisor_expectation`, §1.7 `decision_speed`, §1.8 `transaction_guidance_level`, §1.10 `personality_style`) are documented here for future form revision — not patched with hacks that would mislead users.

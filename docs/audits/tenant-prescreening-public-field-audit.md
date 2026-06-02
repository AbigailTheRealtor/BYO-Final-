# Tenant Public Pre-Screening — Compliance Audit Report

**Date:** June 2, 2026
**Auditor:** Agent Task #1813
**Scope:** Public display of pre-screening / tenant-detail fields in `resources/views/offer-listing/tenant/view.blade.php`
**Status:** FINAL — read-only audit; no code changes made

> **Legal disclaimer:** This report flags compliance risks and recommends access-level policies. It does not constitute legal advice. All flagged items should be reviewed with qualified legal counsel before any policy change is implemented.

---

## 1. Summary Table

| Field | Meta Key | Current Access | Recommended Access | Risk Category |
|---|---|---|---|---|
| Prior Eviction | `prior_eviction` | Open public | **Agent / Landlord gated** | Fair Housing Act — disparate impact |
| Prior Eviction (explanation) | `eviction_explanation` | Not rendered (not in section) | N/A | — |
| Prior Felony | `prior_felony` | Open public | **Remove from public view entirely** | Fair Housing Act — disparate impact |
| Prior Felony (explanation) | `prior_felony_explanation` | Not rendered (not in section) | N/A | — |
| Monthly Income | `monthly_income` | Open public | **Authenticated users only (signed-in landlords/agents)** | Financial privacy — CCPA / state consumer data law |
| Min Annual Net Income | `minimum_annual_net_income` | Open public | **Authenticated users only (signed-in landlords/agents)** | Financial privacy — CCPA / state consumer data law |
| Credit Score Range | `credit_score_range` | Open public | **Authenticated users only (signed-in landlords/agents)** | Financial privacy — CCPA / FCRA adjacent |
| Credit / Screening Concerns | `screening_concerns` | Open public | **Authenticated users only (signed-in landlords/agents)** | Financial privacy + PII free-text risk |
| Current Status | `current_status` | Open public | Open public (acceptable) | Low risk |
| Rental Purpose | `rental_purpose` | Open public | Open public (acceptable) | Low risk |
| Smoking Preference | `smoking_preference` | Open public | Open public (acceptable) | Low risk |
| Accessibility Requirements | `accessibility_requirements` | Open public | **Agent / Landlord gated** | Fair Housing Act — disability protected class |
| Commute Destination ZIP | `commute_destination_zip` | Open public | Open public (acceptable) | Low risk |
| Max Commute (minutes) | `max_commute_minutes` | Open public | Open public (acceptable) | Low risk |
| Commute Mode | `commute_mode` | Open public | Open public (acceptable) | Low risk |
| "No Evictions" badge (hero) | `prior_eviction` | Open public | **Remove from public Standout Box** | Fair Housing Act — disparate impact |
| "No Prior Felony" badge (hero) | `prior_felony` | Open public | **Remove from public Standout Box** | Fair Housing Act — disparate impact (HIGH) |
| Service Animal | `service_animal` | Open public (Pets section) | **Agent / Landlord gated** | Fair Housing Act — disability protected class |
| Support / Emotional Support Animal | `support_animal` / `emotional_support_animal` | Open public (Pets section) | **Agent / Landlord gated** | Fair Housing Act — disability protected class |

---

## 2. Fields Inspected — Source Code Location

All fields below are rendered in the open public view with no authentication check. They are accessible to any anonymous visitor who navigates to a tenant offer listing URL.

**Pre-Screening / Tenant Details section** (`#section-prescreening`, view lines 1112–1146):

```
prior_eviction          → $yesNo($str('prior_eviction'))
prior_felony            → $yesNo($str('prior_felony'))
monthly_income          → $fmtMoney($str('monthly_income'))
minimum_annual_net_income → $fmtMoney($str('minimum_annual_net_income'))
credit_score_range      → $str('credit_score_range')
screening_concerns      → $str('screening_concerns')
current_status          → $str('current_status')
rental_purpose          → $str('rental_purpose')
smoking_preference      → $str('smoking_preference')
accessibility_requirements → $str('accessibility_requirements')
commute_destination_zip → $str('commute_destination_zip')
max_commute_minutes     → $str('max_commute_minutes')
commute_mode            → $yesNo($str('commute_mode'))
```

**Hero Standout Box badges** (view lines 363–375, rendered in hero section lines 543–568):

```
prior_eviction === 'No'   → badge label: "No Evictions"     (strong: true)
prior_felony   === 'No'   → badge label: "No Prior Felony"  (strong: true)
```

Both `strong: true` badges are eligible to appear in the "Why This Tenant Stands Out" prose box, which publicly promotes these characteristics as a selling point.

**Pets & Occupancy section** (view lines 1073–1084) — included here because it contains disability-adjacent fields rendered publicly:

```
service_animal              → $yesNo($str('service_animal'))
support_animal / emotional_support_animal → $yesNo(...)
```

---

## 3. Fair Housing Act Classification — Criminal and Eviction History Fields

### 3.1 Regulatory Background

In April 2016, HUD issued guidance titled *Office of General Counsel Guidance on Application of Fair Housing Act Standards to the Use of Criminal Records by Providers of Housing and Real Estate-Related Transactions* (HUD OGC, April 4, 2016). The core finding:

> Blanket criminal-history screening policies can violate the Fair Housing Act under a disparate-impact theory because criminal justice outcomes are known to fall disproportionately on members of racial and national-origin protected classes. A policy that categorically excludes individuals with any criminal history — without individualized assessment — cannot be shown to serve a legitimate housing interest.

While the 2016 guidance addressed landlord screening *policies*, it establishes that criminal-history data is a legally sensitive signal in housing decisions. A platform that publicly advertises a tenant's criminal history status as a merit attribute ("No Prior Felony") facilitates the exact filtering the HUD guidance warns against: landlords can use the public display as a pre-filter, creating a disparate-impact pathway without ever articulating a policy.

Similarly, eviction history is a protected-class proxy risk. Several jurisdictions (New York City Human Rights Law; Seattle Fair Chance Housing Ordinance; California AB 2925 / local ordinances) restrict or prohibit using eviction records in housing decisions. The CFPB and HUD have both noted that eviction data skews along racial and income lines, raising analogous disparate-impact concerns.

### 3.2 `prior_felony` — Recommend: Remove from public view entirely

**Risk level: HIGH**

- Displaying `prior_felony = Yes` publicly stigmatizes a tenant using a characteristic that carries the highest Fair Housing disparate-impact exposure of any field in this section.
- Even displaying `prior_felony = No` publicly is problematic: it signals to landlords that "No Prior Felony" is a desirable filter, which implicitly devalues listings where the value is "Yes" or not disclosed.
- There is no defensible public-interest reason for any anonymous visitor (competitor, neighbor, researcher) to see a tenant's criminal record status on a public URL.

**Recommended policy:** Do not render `prior_felony` (or any explanation text) in the public view at any access tier. Reserve this field strictly for the listing owner and, if appropriate under a future verified-landlord gate, for verified landlords during a formal match/inquiry flow only.

### 3.3 `prior_eviction` — Recommend: Agent / Landlord gated (not fully removed)

**Risk level: MEDIUM-HIGH**

- Eviction history carries a similar but slightly less settled Fair Housing disparate-impact risk than criminal history. HUD guidance is less explicit on eviction records than on criminal records, but state and local law is moving in a restrictive direction.
- Unlike criminal history, eviction history is a bona fide housing criterion (prior non-payment of rent is a legitimate landlord concern), so a blanket prohibition on the information is less well-established.
- Making it publicly accessible to any anonymous visitor goes beyond any legitimate housing purpose. A verified landlord conducting a formal inquiry has a defensible interest; a general public audience does not.

**Recommended policy:** Gate `prior_eviction` behind authentication (signed-in, verified landlord or agent). Do not render it in the open anonymous public view.

---

## 4. Financial Privacy Classification — Income and Credit Fields

### 4.1 Regulatory Background

The California Consumer Privacy Act (CCPA / CPRA) and its counterparts in Virginia (VCDPA), Colorado (CPA), and other states treat financial information — including income ranges and credit-related data — as personal information subject to privacy protections. While these laws primarily regulate data collection and sharing by businesses, publicly displaying a consumer's income and credit range on an indexed, unauthenticated web page creates disclosure risks:

- Search engines may index the page and cache the data.
- The page is accessible to any third party without notice to the data subject.
- No consent capture exists in the current flow that explains to tenants that their income and credit range will be publicly displayed.

The Fair Credit Reporting Act (FCRA) is less directly applicable here because the data is self-reported rather than obtained from a consumer reporting agency, but the sensitivity of credit information is well-established.

### 4.2 `monthly_income` and `minimum_annual_net_income` — Recommend: Authenticated users only

**Risk level: MEDIUM**

A tenant's monthly income and minimum annual net income are personal financial data. There is no legitimate purpose for making these figures visible to anonymous public visitors. Potential landlords conducting a genuine inquiry can be authenticated before seeing this information.

**Recommended policy:** Render income fields only for signed-in users (authentication gate). Do not render in the open public view. Minimally, require email verification or landlord account status.

### 4.3 `credit_score_range` — Recommend: Authenticated users only

**Risk level: MEDIUM**

Credit score ranges are consumer financial data. Self-reported credit score is not an FCRA-regulated consumer report, but it is sensitive personal financial information. Publicly displaying it on an unauthenticated URL:

- Exposes the tenant to potential identity-profiling risk if combined with other page data (name, location, income).
- Creates a data inventory on the platform that, if breached, contains a consumer's self-reported financial profile.

**Recommended policy:** Gate behind authentication. Same tier as income fields.

### 4.4 `screening_concerns` — Recommend: Authenticated users only

**Risk level: MEDIUM**

This is a free-text field where tenants may disclose explanatory information about their credit history, past evictions, prior felonies, or other screening issues. It can contain highly sensitive PII in narrative form (e.g., "I had a bankruptcy in 2019 due to medical debt"). The unpredictable nature of free-text input makes this the highest-privacy-risk field in the section after criminal history.

**Recommended policy:** Gate behind authentication (same tier as income and credit). Given the free-text nature, legal review should consider whether this field's content requires additional safeguards (e.g., visible only to the listing owner, not even to authenticated landlords, until a formal match is established).

---

## 5. Remaining Pre-Screening Fields — Classification

### 5.1 `accessibility_requirements` — Recommend: Agent / Landlord gated

**Risk level: MEDIUM**

This field discloses whether a tenant has physical accessibility needs. Disability is a protected class under the Fair Housing Act (42 U.S.C. § 3604(f)). Landlords are prohibited from refusing to rent, or from making housing unavailable, on the basis of disability.

Displaying a tenant's accessibility requirements publicly:
- Signals disability status to any viewer, not just prospective landlords.
- Could enable discriminatory filtering: a landlord who does not want to provide reasonable accommodations could pre-screen out tenants with stated accessibility needs before any formal inquiry.
- May also create a Fair Housing Act § 3604(c) exposure — "making, printing, or publishing" a notice that indicates a preference or limitation with respect to disability status.

**Recommended policy:** Gate behind authentication. Visible only to verified landlords and agents in a formal match context, and to the listing owner.

### 5.2 `current_status` — Recommend: Open public (acceptable)

**Risk level: LOW**

Indicates the tenant's current housing situation (e.g., "Currently Renting," "Month-to-Month," "Looking Immediately"). This is benign market context. It does not correlate with a protected class and does not constitute sensitive personal or financial data.

**Recommended policy:** No change; open public rendering is appropriate.

### 5.3 `rental_purpose` — Recommend: Open public (acceptable)

**Risk level: LOW**

Indicates the intended use of the rental (e.g., "Primary Residence," "Short-Term," "Commercial"). This is a standard property-search preference. Some caution is warranted if "rental_purpose" values in the underlying form include proxies for familial status (e.g., "Student Housing Only") — if such values exist, they should not be rendered as a public display on the *landlord's* listing (not applicable here, this is the tenant listing). As a tenant self-description, no protected-class concern is raised.

**Recommended policy:** No change; open public rendering is appropriate.

### 5.4 `smoking_preference` — Recommend: Open public (acceptable)

**Risk level: LOW**

Smoking status is not a protected class under the Fair Housing Act at the federal level or in the majority of jurisdictions. It is a standard preference disclosure in rental listings. No compliance risk identified.

**Recommended policy:** No change; open public rendering is appropriate.

### 5.5 `commute_destination_zip`, `max_commute_minutes`, `commute_mode` — Recommend: Open public (acceptable)

**Risk level: LOW**

These are geographic preference and lifestyle preference fields. They do not disclose sensitive personal data. A ZIP code for commute destination does not constitute an address; it is a general area preference.

The `commute_mode` field is currently rendered through the `$yesNo()` helper, which is technically a display bug (commute mode is a selection value, not a boolean), but the content itself presents no compliance risk.

**Recommended policy:** No change; open public rendering is appropriate.

---

## 6. Standout Box Badges — Hero Section

### 6.1 Badge Architecture

The hero Standout Box (view lines 363–375, 553–568) displays up to five badges drawn from the `$heroBadges` array. Two of these badges draw directly from criminal and eviction history:

| Badge Label | Source Expression | `strong` Flag |
|---|---|---|
| "No Evictions" | `$str('prior_eviction') === 'No'` | `true` |
| "No Prior Felony" | `$str('prior_felony') === 'No'` | `true` |

Both carry `'strong' => true`, which qualifies them to appear in the "Why This Tenant Stands Out" prose box that renders when two or more `strong` badges exist. That prose box concatenates the badge labels into a promotional sentence (e.g., "No Evictions and No Prior Felony.") displayed publicly in the hero section.

### 6.2 "No Prior Felony" Badge — Recommend: Remove from public Standout Box

**Risk level: HIGH**

This badge advertises a tenant's lack of criminal history as a positive market differentiator. The practical effect is identical to advertising criminal history as a disqualifying factor for tenants who cannot display the badge:

- Anonymous landlord visitors learn which tenants self-report no felony and which do not (either by presence of the badge, absence of it, or the corresponding field value in the Pre-Screening section).
- The badge encourages landlords to associate badge presence with desirability, creating a felony-based filtering incentive.
- This exposure is compounded by the Standout prose box, which turns the badge into a prominently promoted headline attribute.

This is the single highest-priority field change recommended in this audit. The HUD 2016 guidance is unambiguous that criminal history cannot be used as a categorical screening criterion; facilitating that screening via a public badge undermines the guidance's intent even if the platform itself is not the decision-maker.

**Recommended policy:** Remove "No Prior Felony" from the set of eligible Standout badge sources. Do not render criminal history status as a public badge in any form.

### 6.3 "No Evictions" Badge — Recommend: Remove from public Standout Box

**Risk level: MEDIUM-HIGH**

The eviction-history badge carries the same structural problem as the felony badge: it promotes a screening criterion as a desirable public attribute. Even though eviction-record use in housing decisions is a more complex legal area than criminal records, the act of publicly advertising "No Evictions" as a standout quality invites the same filtering behavior.

In jurisdictions with source-of-income, eviction-sealing, or fair-chance housing ordinances, advertising eviction-free status as a merit badge may itself constitute a form of implicit discrimination by designation.

**Recommended policy:** Remove "No Evictions" from the eligible Standout badge sources. If eviction history must be disclosed at all, it belongs in a gated context available only to verified landlords during a formal match flow.

---

## 7. Adjacent Section — Pets & Occupancy (Disability Fields)

The Pets & Occupancy section (view lines 1073–1084) is outside the `#section-prescreening` block but is rendered on the same public page. It includes:

- `service_animal` — publicly shown as "Yes / No"
- `support_animal` / `emotional_support_animal` — publicly shown as "Yes / No"

Under the Fair Housing Act, service animals and emotional support animals are associated with disability. A landlord cannot refuse housing on the basis of a tenant's need for a service or emotional support animal. Publicly displaying this information:

- Signals disability status to all anonymous visitors.
- Enables pre-filtering by landlords who prefer to avoid reasonable accommodation requests, before a formal inquiry is ever made.
- May implicate FHA § 3604(c) exposure (public notice indicating preference or limitation based on disability).

**Recommended policy:** Gate `service_animal`, `support_animal`, and `emotional_support_animal` behind authentication (same tier as `accessibility_requirements`). These fields are disability indicators under settled FHA interpretation and should not be publicly displayed to anonymous visitors.

> **Note:** `breed_restrictions` and `has_breed_restrictions` in the same section are not disability-related and carry no compliance risk. Those fields may remain publicly visible.

---

## 8. Recommended Access-Level Policy — Summary

The following tiers are used in this report:

| Tier | Definition |
|---|---|
| **Open public** | Accessible to any anonymous visitor; no login required |
| **Authenticated users only** | Requires a logged-in account; no role restriction |
| **Agent / Landlord gated** | Requires authentication AND a verified landlord or agent role |
| **Not displayed** | Removed from view entirely regardless of access level |

### Final Policy Recommendations

| Field Group | Fields | Recommended Tier | Rationale |
|---|---|---|---|
| Criminal history — section | `prior_felony` | **Not displayed** | Highest FHA disparate-impact risk; no legitimate public-view purpose |
| Criminal history — badge | "No Prior Felony" badge | **Not displayed** | Same as above; amplified by hero prominence |
| Eviction history — section | `prior_eviction` | **Agent / Landlord gated** | FHA disparate-impact risk; legitimate landlord interest exists in gated context |
| Eviction history — badge | "No Evictions" badge | **Not displayed** | Public badge invites filtering; higher risk than gated display |
| Income fields | `monthly_income`, `minimum_annual_net_income` | **Authenticated users only** | Consumer financial data; no anonymous-public purpose |
| Credit data | `credit_score_range` | **Authenticated users only** | Consumer financial data; sensitive personal information |
| Free-text screening | `screening_concerns` | **Authenticated users only** (minimum); consider **Agent / Landlord gated** | Free-text PII; may contain sensitive financial or criminal narrative |
| Disability indicators — pre-screening | `accessibility_requirements` | **Agent / Landlord gated** | FHA protected class; public display enables discriminatory filtering |
| Disability indicators — pets section | `service_animal`, `support_animal`, `emotional_support_animal` | **Agent / Landlord gated** | FHA protected class; same exposure as accessibility_requirements |
| Low-risk lifestyle fields | `current_status`, `rental_purpose`, `smoking_preference`, `commute_destination_zip`, `max_commute_minutes`, `commute_mode` | **Open public** | No protected-class correlation; no sensitive personal data |

---

## 9. Priority Order for Policy Implementation

1. **CRITICAL — Remove "No Prior Felony" badge and `prior_felony` from public view.** Highest Fair Housing disparate-impact exposure on the platform. No action from legal counsel is required to support removing criminal history from anonymous public display.

2. **HIGH — Remove "No Evictions" badge from public Standout Box.** The badge format amplifies the risk beyond what the plain field value would create. Legal counsel should advise on whether the underlying field should be gated or removed as well.

3. **HIGH — Gate `accessibility_requirements`, `service_animal`, `support_animal`, `emotional_support_animal` behind authentication.** Disability is a clearly defined FHA protected class; these fields directly signal it.

4. **MEDIUM — Gate income and credit fields (`monthly_income`, `minimum_annual_net_income`, `credit_score_range`, `screening_concerns`) behind authentication.** Consumer financial data privacy risk. CCPA and state equivalents apply.

5. **MEDIUM — Gate `prior_eviction` behind agent/landlord authentication.** Lower urgency than criminal history given the more complex legal landscape, but should not remain open to anonymous visitors.

---

## 10. Fields Not in Scope

The following are confirmed **not rendered** in the public tenant view (`view.blade.php`) and require no policy action from this audit:

- `prior_felony_explanation` — not rendered in any section
- `eviction_explanation` — not rendered in any section
- `number_of_occupants` / `number_occupant` — explicitly suppressed with a code comment and excluded from the catch-all fallback (`$knownKeys` list, line 1397)

The landlord-facing and agent-facing private views (`resources/views/agent/offer-listing-view.blade.php`, the accepted bid summary views) are already appropriately gated behind authentication and role checks and are **out of scope** for this audit.

---

*End of audit report. No code changes were made during this audit.*

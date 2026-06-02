# Tenant Offer Listing — Public Visibility Policy

**Document Version:** 1.0
**Created:** June 2, 2026
**Basis:** Compliance audit `docs/audits/tenant-prescreening-public-field-audit.md`
**Scope:** `resources/views/offer-listing/tenant/view.blade.php`
**Status:** POLICY — No code changes. This document defines intended access rules for future implementation.

> **Legal disclaimer:** This document defines platform policy recommendations informed by the compliance audit. It does not constitute legal advice. All access-level decisions touching Fair Housing Act, CCPA, FCRA, or state housing law should be reviewed by qualified legal counsel before implementation.

---

## 1. Visibility Tiers

The following five access tiers are used throughout this policy:

| Tier | Label | Definition |
|------|-------|------------|
| **A** | Public Visitor | Any anonymous visitor — no account required. The current default for all fields. |
| **B** | Authenticated User | Any signed-in user with a confirmed account. No role or verification requirement. |
| **C** | Verified Landlord | Authenticated user whose account has been verified as a landlord or property owner. |
| **D** | Verified Agent | Authenticated user whose account has been verified as a licensed real estate agent. |
| **E** | Listing Owner | The user who created this specific tenant listing. Always has full access to their own data. |

**Reading the matrix:** A ✅ at a given tier means the field is visible at that tier and all tiers above it (i.e., Tier B ✅ means Authenticated User and above can see it). A ❌ means the field must be hidden at that tier.

---

## 2. Complete Field Visibility Matrix

### 2.1 Hero Section — Snapshot Card (No-Photo Fallback)

Rendered when no photos are uploaded. Draws from the same meta keys as the hero summary sidebar.

| Field | Label | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------|:---:|:---:|:---:|:---:|:---:|-------|
| `budget` / `desired_rental_amount` / `maximum_budget` | Rent Budget | ✅ | ✅ | ✅ | ✅ | ✅ | Core listing criterion |
| `state`, `cities`, `counties`, `zip_codes` | Location | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive search data |
| `property_type` | Property Type | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `bedrooms` | Min. Beds | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `bathrooms` | Min. Baths | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `minimum_heated_square` | Min. Sq Ft | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `move_in_date_earliest` | Move-In | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `credit_score_range` | Credit Range | ❌ | ✅ | ✅ | ✅ | ✅ | **Financial privacy** — see §5 |
| `monthly_income` | Mo. Income | ❌ | ✅ | ✅ | ✅ | ✅ | **Financial privacy** — see §5 |
| `desired_lease_length` / `tenant_desired_lease_length` | Lease Pref. | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| `listing_status` | Status | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

> **Current state:** All snapshot card fields including `credit_score_range` and `monthly_income` are rendered publicly. **Both financial fields must be hidden from Tier A.**

---

### 2.2 Hero Section — Standout Box Badges

Badges rendered as colored chips below the hero summary. Two or more "strong" badges trigger the "Why This Tenant Stands Out" prose box.

| Badge Label | Source Field | Strong | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------------|-------------|:------:|:---:|:---:|:---:|:---:|:---:|-------|
| Location Flexible | `cities` count > 0 | No | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Lease Option | `offered_financing` includes "Lease Option" | Yes | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Lease Purchase | `offered_financing` includes "Lease Purchase" | Yes | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Crypto OK | `offered_financing` includes "Cryptocurrency" | No | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Property type label | `property_type` | No | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| **No Evictions** | `prior_eviction === 'No'` | **Yes** | ❌ | ❌ | ❌ | ❌ | ✅ | **Recommended for complete removal** — see §3 |
| **No Prior Felony** | `prior_felony === 'No'` | **Yes** | ❌ | ❌ | ❌ | ❌ | ✅ | **Recommended for complete removal** — see §3 |
| Status label | `listing_status` | No | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

> **Current state:** All badges including "No Evictions" and "No Prior Felony" are rendered publicly as hero-section chips AND as promotional prose text in the Standout Box. **Both must be removed from all public tiers. See §3 for full rationale.**

---

### 2.3 Hero Summary Sidebar

Right-hand panel of the hero section, always rendered when the listing exists.

| Field | Label | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------|:---:|:---:|:---:|:---:|:---:|-------|
| Rent price display | Rent Budget | ✅ | ✅ | ✅ | ✅ | ✅ | Core listing data |
| Location text | Location | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Beds / Baths / Sq Ft / Property Type chips | Summary meta | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Status chip | `listing_status` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Listed / Updated dates | `listing_date`, `updated_at` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Badge row | See §2.2 | Per badge rules above | | | | | |
| Standout prose box | Derived from "strong" badges | ❌ (if only felony/eviction badges trigger it) | — | — | — | ✅ | Prose box must not appear if only sensitive badges qualify |
| Bidding Period timer | `auction_time` + `created_at` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive operational data |

---

### 2.4 Listing Overview Section (`#section-overview`)

| Field | Meta Key | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|----------|:---:|:---:|:---:|:---:|:---:|-------|
| Listing Title | `listing_title` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Listing Type | `auction_type` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Listing Status | `listing_status` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Listing Date | `listing_date` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Expiration Date | `expiration_date` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Auction Time | `auction_time` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Bidding Period countdown | Computed | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

---

### 2.5 Rental Criteria Section (`#section-rental`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| Rent Budget | `budget` / `desired_rental_amount` / `maximum_budget` | ✅ | ✅ | ✅ | ✅ | ✅ | Core search criterion |
| Desired Lease Length | `desired_lease_length` / `tenant_desired_lease_length` / `lease_length` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Move-In Funds Available | `move_in_funds_available` / `move_in_budget_upfront` | ✅ | ✅ | ✅ | ✅ | ✅ | General budget signal; non-sensitive |
| First Month Rent Available | `first_month_rent_available` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Last Month Rent Available | `last_month_rent_available` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Security Deposit Budget | `security_deposit_budget` | ✅ | ✅ | ✅ | ✅ | ✅ | General budget signal; non-sensitive |
| Earliest Move-In Date | `move_in_date_earliest` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Latest Move-In Date | `move_in_date_latest` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Terms of Lease | `terms_of_lease` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Tenant Pays | `tenant_pays` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Owner Pays | `owner_pays` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Rent Includes | `rent_includes` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Offered Financing | `offered_financing` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Lease Option Price | `lease_option_price` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive; tenant's stated preference |
| Lease Purchase Price | `lease_purchase_price` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Down Payment Amount | `down_payment_amount` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Interest Rate | `interest_rate` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Loan Duration | `loan_duration` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Cryptocurrency Type | `cryptocurrency_type` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

---

### 2.6 Location Preferences Section (`#section-location`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| State | `state` / `property_state` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Cities | `cities` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Counties | `counties` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| ZIP Codes | `zip_codes` / `property_zip` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive preference |
| Address | `address` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive (this is a desired location, not the tenant's residence) |

---

### 2.7 Desired Property Features Section (`#section-property`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| Property Type | `property_type` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Condition | `condition_prop_buyer` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Leasing Spaces | `leasing_spaces_tenant` / `leasing_spaces` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Bedrooms | `bedrooms` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Bathrooms | `bathrooms` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Minimum Heated Sq Ft | `minimum_heated_square` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Minimum Leaseable Sq Ft | `minimum_leaseable` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Min Acreage | `min_acreage` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Total Sq Ft | `total_square_feet` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Sq Ft Source | `sqft_heated_source` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Age-Restricted (55+) | `leasing_55_plus` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive; tenant preference |
| Pool Needed | `pool_needed` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Pool Type | `pool_type` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| View Preferences | `view_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Appliances Needed | `appliances` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Required Amenities | `non_negotiable_amenities` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Property Items / Features | `property_items` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

---

### 2.8 Pets & Occupancy Section (`#section-pets`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| Pets | `pets` / `type_of_pets` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Number of Pets | `number_of_pets` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Breed of Pets | `breed_of_pets` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Weight of Pets | `weight_of_pets` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Pet Information | `pet_information` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Number of Occupants | `number_of_occupants` / `number_occupant` | ❌ | ❌ | ✅ | ✅ | ✅ | **Already suppressed** in current code; familial status proxy (FHA) |
| **Service Animal** | `service_animal` | ❌ | ✅ | ✅ | ✅ | ✅ | **Disability indicator** — FHA protected class; see §4 |
| **Support / Emotional Support Animal** | `support_animal` / `emotional_support_animal` | ❌ | ✅ | ✅ | ✅ | ✅ | **Disability indicator** — FHA protected class; see §4 |
| Breed Restrictions | `has_breed_restrictions` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Breed Restriction Details | `breed_restrictions` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Parking Needed | `carport_needed` / `garage_needed` / `parking_needed` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

> **Note on Number of Occupants:** This field is already suppressed from the public view via an explicit code comment and `$knownKeys` exclusion. That suppression is correct and should be maintained. The familial status concern (FHA prohibits discrimination based on presence of children) is the underlying reason.

---

### 2.9 Parking & Amenities Section (`#section-parking`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| Parking Type / Details | `garage_parking_spaces` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Carport Spaces | `carport_spaces` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Garage Spaces | `garage_spaces` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Parking Features | `garage_parking_spaces_option` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Other Parking Details | `other_parking_space_wrapper` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

---

### 2.10 Pre-Screening / Tenant Details Section (`#section-prescreening`)

This section contains the highest concentration of legally sensitive fields on the page.

| Field | Meta Key | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Risk Category |
|-------|----------|:---:|:---:|:---:|:---:|:---:|---------------|
| **Prior Eviction** | `prior_eviction` | ❌ | ❌ | ✅ | ✅ | ✅ | **Fair Housing — disparate impact** |
| **Prior Felony** | `prior_felony` | ❌ | ❌ | ❌ | ❌ | ✅ | **Fair Housing — CRITICAL — see §3** |
| **Monthly Income** | `monthly_income` | ❌ | ✅ | ✅ | ✅ | ✅ | **Financial privacy — CCPA** |
| **Min Annual Net Income** | `minimum_annual_net_income` | ❌ | ✅ | ✅ | ✅ | ✅ | **Financial privacy — CCPA** |
| **Credit Score Range** | `credit_score_range` | ❌ | ✅ | ✅ | ✅ | ✅ | **Financial privacy — CCPA** |
| **Credit / Screening Concerns** | `screening_concerns` | ❌ | ❌ | ✅ | ✅ | ✅ | **Financial privacy + PII free-text** |
| Current Status | `current_status` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |
| Rental Purpose | `rental_purpose` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |
| Smoking Preference | `smoking_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |
| **Accessibility Requirements** | `accessibility_requirements` | ❌ | ✅ | ✅ | ✅ | ✅ | **Fair Housing — disability protected class** |
| Commute Destination ZIP | `commute_destination_zip` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |
| Max Commute (minutes) | `max_commute_minutes` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |
| Commute Mode | `commute_mode` | ✅ | ✅ | ✅ | ✅ | ✅ | Low risk |

> **Current state:** All 13 fields in this section are rendered to Tier A anonymous visitors with no authentication check. Six fields require access restriction. `prior_felony` is the most critical — recommended to be hidden from all public tiers including authenticated users, visible only to Tier E (owner).

---

### 2.11 Lease Preferences & Conditions Section (`#section-lease-prefs`)

| Field | Meta Key | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|----------|:---:|:---:|:---:|:---:|:---:|-------|
| Leasing For | `lease_for` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Utility Preference | `utility_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Maintenance Preference | `maintenance_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Renewal Option Requested | `renewal_option_requested` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Renewal Option Details | `renewal_option_details` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Occupancy Status | `occupancy_status` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Occupied Until | `occupied_until` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Tenant Requirements | `tenant_require` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Tenant Conditions | `tenant_conditions` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Additional Lease Terms | `additional_tenant_lease_terms` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Commercial Lease Type | `commercial_lease_type_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| CAM / NNN Preference | `cam_nnn_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Rent Escalation | `rent_escalation_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Buildout / Tenant Improvement | `buildout_tenant_improvement_request` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Intended Business Use | `intended_business_use` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Signage Request | `signage_request` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Commercial Parking Needs | `commercial_parking_access_needs` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Personal Guarantee | `personal_guarantee_preference` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |
| Commercial Approval Conditions | `commercial_approval_conditions` | ✅ | ✅ | ✅ | ✅ | ✅ | Non-sensitive |

---

### 2.12 Broker Compensation Section (`#section-broker-compensation`)

| Field | Meta Key(s) | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|-------------|:---:|:---:|:---:|:---:|:---:|-------|
| Tenant's Broker Commission Structure | `commission_structure` | ✅ | ✅ | ✅ | ✅ | ✅ | Market-facing professional data; non-sensitive |
| Tenant's Broker Lease Fee | Derived from `lease_fee_type` + fee sub-keys | ✅ | ✅ | ✅ | ✅ | ✅ | Market-facing professional data; non-sensitive |

---

### 2.13 Contact Information Section (`#section-contact`)

| Field | Meta Key | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|----------|:---:|:---:|:---:|:---:|:---:|-------|
| Name | `first_name` + `last_name` | ✅ | ✅ | ✅ | ✅ | ✅ | User has consented to display by submitting the listing |
| Email | `email` | ✅ | ✅ | ✅ | ✅ | ✅ | User has consented to display; enables Contact CTA |
| Phone | `phone_number` | ✅ | ✅ | ✅ | ✅ | ✅ | User has consented to display |
| Brokerage | `agent_brokerage` | ✅ | ✅ | ✅ | ✅ | ✅ | Professional credential; non-sensitive |
| License Number | `agent_license_number` | ✅ | ✅ | ✅ | ✅ | ✅ | Professional public record; non-sensitive |
| NAR Member ID | `agent_nar_member_id` | ✅ | ✅ | ✅ | ✅ | ✅ | Professional credential; non-sensitive |
| Video (uploaded) | `video` | ✅ | ✅ | ✅ | ✅ | ✅ | User-submitted media; non-sensitive |
| Video Link | `video_link` | ✅ | ✅ | ✅ | ✅ | ✅ | User-submitted link; non-sensitive |

---

### 2.14 Additional Details Section (`#section-additional`)

| Field | Meta Key | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|----------|:---:|:---:|:---:|:---:|:---:|-------|
| Additional Details | `additional_details` | ✅ | ✅ | ✅ | ✅ | ✅ | Free-text; user-supplied and consented to display. No special restriction unless content is screened. |

> **Implementation note:** Because `additional_details` is free-text, platform policy should consider whether the create/edit form warns tenants that this field is public before they submit it. No access restriction is required at this time, but a form-side disclosure notice is advisable.

---

### 2.15 Additional Information / Catch-All Section (`#section-remaining`)

The catch-all section renders any populated meta key that is not in the `$knownKeys` exclusion list (view lines 1331–1436). Because its contents are by definition unknown at policy-writing time, a conservative default rule applies:

| Field | Tier A Public | Tier B Auth | Tier C Landlord | Tier D Agent | Tier E Owner | Notes |
|-------|:---:|:---:|:---:|:---:|:---:|-------|
| Any unknown meta key | ✅ | ✅ | ✅ | ✅ | ✅ | Default — but see implementation note |

> **Implementation note:** Before any new meta key is added to the Tenant create/edit wizard, it must be evaluated against the sensitivity classification in §4 and §5. If it touches a protected class or financial PII, it must be added to `$knownKeys` and routed to a gated display section rather than falling through to the catch-all. The catch-all must not become a secondary leak path for sensitive fields.

---

## 3. Fields Recommended For Complete Public Removal

The following fields carry such high Fair Housing Act disparate-impact risk that they should not be displayed at Tier A, Tier B, Tier C, or Tier D. The recommended policy is removal from all non-owner public display tiers — including the hero, badges, and the Pre-Screening section.

### 3.1 "No Prior Felony" Badge + `prior_felony` Field

**Recommendation: Remove from all public display tiers (Tier A through D).**

**Basis:**
- HUD's April 2016 guidance establishes that criminal-history data carries the highest disparate-impact exposure of any commonly used screening criterion in housing decisions.
- The "No Prior Felony" Standout Box badge does not merely disclose a neutral data point. It actively promotes criminal-history absence as a desirable market attribute, which implicitly devalues listings from tenants with any felony record. This creates a filtering incentive structurally identical to posting "No Criminal Records Preferred" — a practice HUD guidance explicitly targets.
- Even displaying `prior_felony` to Verified Landlords (Tier C) is legally complex: no individualized assessment of rehabilitation, time elapsed, or offense type is provided alongside the Yes/No value, which is the minimum HUD guidance requires for any criminal-history use in housing.
- The field may be retained in the database and displayed only to the listing owner (Tier E) for their own records management. No other tier is recommended.

### 3.2 "No Evictions" Badge

**Recommendation: Remove from all public display tiers (Tier A through D).** The underlying `prior_eviction` field may be displayed to Tier C (Verified Landlord) and above, but the badge in the hero section promotes eviction history as a public merit attribute and should be removed from the badge array entirely.

**Basis:**
- The badge format amplifies risk beyond what the plain field disclosure would create. Placing "No Evictions" in the hero Standout Box and in the promotional prose ("Why This Tenant Stands Out") makes eviction-free status a headline selling point visible at first glance to any anonymous visitor, before they engage with the listing at all.
- Several state laws (New York City Human Rights Law § 8-107.1, Seattle Fair Chance Housing Ordinance, California AB 2925) restrict or prohibit the use of eviction records in housing decisions. Advertising eviction-free status as a badge is likely to encourage the exact filtering these laws prohibit.
- The badge should be removed from the badge source array. The field itself (`prior_eviction`) may be displayed in the Pre-Screening section to Tier C (Verified Landlord) only.

---

## 4. Fair Housing Sensitive Fields

The following fields touch protected class characteristics under the Fair Housing Act (42 U.S.C. §§ 3601–3619) or state fair housing equivalents. Any expansion, modification, or new display context for these fields requires compliance review before implementation.

| Field | Meta Key | Protected Class | FHA Section | Current State | Recommended State |
|-------|----------|----------------|-------------|--------------|-------------------|
| Prior Felony | `prior_felony` | Race / National Origin (disparate impact) | § 804(a), § 804(c) | Tier A public | Remove all public tiers |
| "No Prior Felony" badge | `prior_felony` | Race / National Origin (disparate impact) | § 804(a), § 804(c) | Tier A hero section | Remove entirely |
| Prior Eviction | `prior_eviction` | Race / National Origin (disparate impact) | § 804(a) | Tier A public | Tier C Verified Landlord+ |
| "No Evictions" badge | `prior_eviction` | Race / National Origin (disparate impact) | § 804(a) | Tier A hero section | Remove entirely |
| Accessibility Requirements | `accessibility_requirements` | Disability | § 804(f) | Tier A public | Tier B Authenticated+ |
| Service Animal | `service_animal` | Disability | § 804(f), § 3604(f)(3)(B) | Tier A public | Tier B Authenticated+ |
| Support / Emotional Support Animal | `support_animal` / `emotional_support_animal` | Disability | § 804(f), § 3604(f)(3)(B) | Tier A public | Tier B Authenticated+ |
| Number of Occupants | `number_of_occupants` / `number_occupant` | Familial Status | § 804(b) | **Already suppressed** | Maintain suppression; Tier C+ only |

> **FHA § 804(c) note:** Section 804(c) of the Fair Housing Act prohibits "making, printing, or publishing … any notice, statement, or advertisement … that indicates any preference, limitation, or discrimination" based on a protected class. Public display of criminal-history and eviction data — particularly in badge/promotional format — may independently trigger § 804(c) exposure for the platform, separate from any landlord's decision-making.

---

## 5. Financial Privacy Sensitive Fields

The following fields contain consumer financial information subject to state consumer privacy laws including the California Consumer Privacy Act (CCPA/CPRA) and equivalent statutes (Virginia VCDPA, Colorado CPA, and others). Publicly displaying these fields on an unauthenticated, indexable page creates data exposure risk independent of any housing discrimination concern.

| Field | Meta Key | Privacy Law Exposure | Current State | Recommended State |
|-------|----------|---------------------|--------------|-------------------|
| Monthly Income | `monthly_income` | CCPA "personal information" — financial | Tier A public | Tier B Authenticated+ |
| Min Annual Net Income | `minimum_annual_net_income` | CCPA "personal information" — financial | Tier A public | Tier B Authenticated+ |
| Credit Score Range | `credit_score_range` | CCPA "personal information" — financial; FCRA-adjacent | Tier A public | Tier B Authenticated+ |
| Credit / Screening Concerns | `screening_concerns` | CCPA free-text PII; may contain bankruptcy, criminal, or eviction narrative | Tier A public | Tier C Verified Landlord+ |
| Credit Score Range (snapshot card) | `credit_score_range` | Same as above | Tier A hero section | Tier B Authenticated+ |
| Monthly Income (snapshot card) | `monthly_income` | Same as above | Tier A hero section | Tier B Authenticated+ |

> **Consent gap:** The current platform has no disclosure to tenants at the time of listing creation that their income, credit range, or screening narrative will be publicly displayed on an unauthenticated URL. CCPA and equivalent laws require that consumers be informed of the purposes for which their personal information is used. A form-side disclosure or consent acknowledgment should be considered a prerequisite to any future restoration of these fields to public display, even with legal clearance.

> **Search engine indexing risk:** Anonymous public pages are indexed by search engines and cached in third-party archives. A tenant's income and credit data on a public listing URL may persist in search engine caches even after the listing is taken down. Gating these fields behind authentication eliminates this risk.

---

## 6. Recommended Future Verification Requirements

This section discusses possible future access-tier verification models. These are policy options — no implementation is required or implied.

### 6.1 Verified Landlord (Tier C)

To access Tier C fields (`prior_eviction`, `screening_concerns`), a user would need to demonstrate landlord status. Possible verification mechanisms:

- **Self-declaration with terms acknowledgment:** User checks "I am a landlord" and acknowledges fair housing compliance obligations. Low friction; minimal legal protection for the platform.
- **Document verification:** Upload of proof of property ownership (deed, tax record, property management license). Higher friction; stronger platform protection.
- **Subscription gating:** Only users on a paid landlord plan can view gated fields. Reduces anonymous access while creating revenue alignment.
- **Identity verification:** Third-party ID verification (e.g., Persona, Stripe Identity). Highest protection; highest friction.

**Recommendation:** At minimum, a self-declaration with an explicit fair housing compliance acknowledgment and terms of access. Document verification for platforms seeking stronger liability protection.

### 6.2 Verified Agent (Tier D)

To access Tier D fields (same as Tier C plus any agent-specific fields), a user would need to demonstrate licensed agent status. Possible verification mechanisms:

- **License number validation:** Cross-reference against ARELLO or state licensing databases.
- **NAR membership verification:** Via NAR API or manual review.
- **Brokerage affiliation:** Verified email domain from a known brokerage.

### 6.3 Signed NDA / Fair Housing Acknowledgment

For the highest-sensitivity fields (`prior_felony`, `screening_concerns`), a platform may require users to sign a digital acknowledgment confirming:
- They understand fair housing obligations.
- They will not use criminal or eviction history as a blanket disqualifier.
- They will evaluate each tenant individually in accordance with HUD guidance.

This acknowledgment creates a documented compliance checkpoint and may provide the platform a good-faith defense in the event of a fair housing complaint.

### 6.4 Listing Engagement Threshold

An alternative model for `prior_eviction` and financial fields: require the viewing user to have expressed genuine interest in the listing (e.g., submitted an inquiry, clicked "I'm interested") before the gated data is unlocked. This limits exposure to users who have a legitimate, transaction-related reason to see the data, reducing opportunistic scraping risk.

---

## 7. Final Recommendation — Recommended Production Visibility Policy

### 7.1 Recommended Minimum Production Policy

The following table summarizes the recommended minimum policy for production. This is a floor — the platform may choose to add more restriction for any field. Legal counsel must review before implementation.

| Risk Level | Fields | Minimum Recommended Access |
|-----------|--------|---------------------------|
| **CRITICAL** | `prior_felony`, "No Prior Felony" badge | Owner-only (Tier E). Remove from all public and gated tiers. |
| **HIGH** | "No Evictions" badge | Remove entirely from badge set; field may remain at Tier C. |
| **HIGH** | `service_animal`, `support_animal`, `emotional_support_animal`, `accessibility_requirements` | Tier B (Authenticated User) minimum. |
| **MEDIUM** | `prior_eviction`, `screening_concerns` | Tier C (Verified Landlord) minimum. |
| **MEDIUM** | `monthly_income`, `minimum_annual_net_income`, `credit_score_range` | Tier B (Authenticated User) minimum. Also must be removed from Snapshot Hero Card at Tier A. |
| **LOW** | All other fields in §2.4–2.15 | Tier A (Public Visitor) — no change required. |

### 7.2 Risks of Over-Exposure (Current State)

The current implementation exposes all fields to Tier A anonymous visitors. Key risks:

1. **FHA § 804(c) liability** from public display of criminal-history and eviction badges as promotional merit attributes.
2. **Disparate-impact claims** if landlords use the publicly-surfaced `prior_felony` or `prior_eviction` fields as categorical filters without individualized assessment.
3. **CCPA / state privacy law** exposure from publicly displaying income and credit data on indexable, unauthenticated URLs without consumer disclosure.
4. **Search engine caching** of sensitive financial and criminal-history data beyond the tenant's control, even after listing deletion.
5. **Platform reputational risk** if a tenant discovers their criminal record or financial data is publicly visible without clear disclosure.

### 7.3 Risks of Over-Restriction

Implementing excessive gating carries its own risks:

1. **Reduced landlord engagement.** If too many fields require Tier C verification, landlords may not bother signing up, reducing the platform's value proposition.
2. **Tenant discovery issues.** Fields that help tenants market themselves effectively (e.g., income, credit range as signals of financial strength) lose their market function if hidden from all landlords.
3. **Operational complexity.** Each additional tier requires verified role management, which adds authentication surface area and maintenance cost.
4. **False sense of security.** Tier B (authenticated user) gating does not prevent a determined bad actor from creating an account to view sensitive fields. Tier C verification is a meaningfully stronger control.

**Balancing recommendation:** The policy minimums in §7.1 represent the tightest access needed for the highest-risk fields, while leaving the majority of the listing openly visible. Income and credit range, while gated at Tier A, should be surfaced readily at Tier B without friction — creating an account is a low-friction action that still eliminates anonymous scraping.

---

## 8. Fields Currently Not Rendered (Confirmed Absent from Public View)

The following fields are in the database schema and may be collected in the create/edit wizard but are confirmed to not render in `tenant/view.blade.php`. No policy action is required for these fields at this time, but they must be classified before any future display is added.

| Field | Meta Key | Why Absent | Policy If Displayed |
|-------|----------|-----------|---------------------|
| Felony explanation | `prior_felony_explanation` | Not rendered in any section | Remove from all public tiers; same as `prior_felony` |
| Eviction explanation | `eviction_explanation` | Not rendered in any section | Tier C Verified Landlord minimum |
| Screening concerns explanation | `screening_concerns_explanation` | In `$knownKeys` suppression list | Tier C Verified Landlord minimum |
| Number of occupants | `number_of_occupants` / `number_occupant` | Suppressed by code comment + `$knownKeys` | Tier C minimum; familial status proxy |

---

*End of policy document. No code changes were made during the creation of this document.*

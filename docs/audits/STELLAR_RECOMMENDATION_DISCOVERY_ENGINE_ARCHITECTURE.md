# Stellar Recommendation & Discovery Engine Architecture

> Document type: Architecture design record
> Date: 2026-06-16
> Related documents:
>   - `docs/audits/STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`
>   - `docs/audits/STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md`
>   - `docs/audits/STELLAR_ALERT_SYSTEM_ARCHITECTURE.md`
>   - `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md`
> Scope: Documentation only — no code, no migrations, no schema changes, no UI changes

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Matching vs Recommendation](#2-matching-vs-recommendation)
3. [Recommendation Types](#3-recommendation-types)
4. [Input Signals](#4-input-signals)
5. [Scoring Model](#5-scoring-model)
6. [Recommendation Explanation Blueprint](#6-recommendation-explanation-blueprint)
7. [Buyer Recommendation Strategy](#7-buyer-recommendation-strategy)
8. [Tenant Recommendation Strategy](#8-tenant-recommendation-strategy)
9. [Performance Strategy](#9-performance-strategy)
10. [Compliance and Suppression](#10-compliance-and-suppression)
11. [Storage Strategy](#11-storage-strategy)
12. [Implementation Roadmap](#12-implementation-roadmap)
13. [Recommendation Feedback Loop](#13-recommendation-feedback-loop)

---

## 1. Executive Summary

### Purpose

The Stellar Recommendation & Discovery Engine is the layer of the platform that answers a fundamentally different question than the matching engine. Where the matching engine answers "which active listings satisfy my exact criteria?", the recommendation engine answers "what else should I be considering that I might not have thought of?"

These are complementary, not competing, systems. A buyer who specifies 3 bedrooms, 2 bathrooms, a pool, in Orlando under $450,000 will receive a ranked match list from the matching engine. The recommendation engine then enriches that experience: it surfaces a waterfront home in a nearby ZIP that is $20,000 under budget but lacks a private pool, flags a new construction community three miles away that is 5% above budget but includes all HOA amenities, and highlights a same-neighborhood listing with a community pool rather than a private one that comes in significantly cheaper. These are not matches — they are discoveries.

### Why Recommendations Are Needed

Real estate decisions are rarely optimized on a single dimension. Buyers and tenants frequently discover that their stated criteria do not reflect their latent preferences: a buyer who said they require a private pool may accept a waterfront home with a community pool if the tradeoff is explained clearly. A tenant priced out of their target ZIP may not know that a neighboring ZIP offers comparable walkability and school access at a meaningful price discount. The matching engine, by design, enforces hard filters and scores against strict criteria — it cannot surface these adjacent opportunities.

The recommendation engine removes that blind spot. It is not a replacement for match scores. It is an expansion layer that operates on the candidate set adjacent to, but not exclusively inside, the buyer's or tenant's stated criteria.

### Position in the Platform Architecture

```
User Criteria
     │
     ▼
┌─────────────────────────────────┐
│       Matching Engine           │  ← "Does this listing fit my criteria?"
│  Hard filters + scored ranking  │
└────────────────┬────────────────┘
                 │
                 ▼
        Matched Result Set
                 │
                 ▼
┌─────────────────────────────────┐
│    Recommendation Engine        │  ← "What else should I consider?"
│  Similarity + value + location  │
│  adjacency + amenity overlap    │
└────────────────┬────────────────┘
                 │
                 ▼
  Match Results + Recommendation Cards
  (each with natural-language explanation)
```

The recommendation engine runs after the matching engine has produced its ranked result set. It may also run independently as a discovery experience when the user has saved criteria but no match results have been generated yet (e.g., a new user who has just saved preferences but has not yet browsed listings).

---

## 2. Matching vs Recommendation

### Core Distinction

| Dimension | Matching Engine | Recommendation Engine |
|---|---|---|
| **Primary question** | "Does this listing fit my criteria?" | "What else should I consider?" |
| **Input basis** | Buyer/tenant stated criteria (explicit, structured) | Criteria + behavior signals + listing similarity features |
| **Filter behavior** | Hard filters eliminate ineligible listings before scoring | No hard eliminators; all candidates are surfaced with explanation |
| **Score meaning** | How closely this listing meets stated requirements | How relevant this listing is as an alternative worth considering |
| **Result framing** | "X% match — here is why" | "Similar because…", "Worth considering because…", "Tradeoff…" |
| **Universe** | Active listings that pass all hard filters | Active listings adjacent to the criteria or matched set |
| **Compliance** | Tier 6 fields excluded from scoring | Tier 6 fields excluded from all outputs, including explanations |
| **When it runs** | On criteria save, on search, on alert trigger | After matching engine; also as standalone discovery |

### The Two Systems Are Not Interchangeable

The matching engine cannot produce recommendations, because it operates exclusively on the pass/fail logic of stated criteria. A listing that fails a hard filter in the matching engine (e.g., it has 2 bedrooms when the buyer requires 3) is eliminated before any score is assigned — it is invisible to the match result layer. The recommendation engine may surface that same listing with the label "close alternative — one fewer bedroom, but $40,000 under your budget in the same ZIP." This is useful information that the matching engine is architecturally prohibited from providing.

Conversely, the recommendation engine should never replace the matching engine for the primary result set. Recommendations are secondary context, not primary results. The UI layer must preserve this distinction clearly: match results appear first with match scores; recommendation cards appear below or alongside with recommendation-type labels.

### Reference

The matching engine architecture for buyers is documented in full in `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`. The tenant matching engine architecture is in `STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md`. This document does not repeat those specifications; it references them as the upstream source of the candidate pool against which recommendations operate.

---

## 3. Recommendation Types

The recommendation engine defines nine distinct recommendation types. Each type has a specific triggering condition, an intended audience, and a natural-language label for the UI card.

### 3.1 — Similar Homes

**Description:** Listings that share the same property type, subtype, approximate size, bedroom/bathroom count, and price range as listings the buyer has viewed, saved, or matched against — but which differ in one or two secondary dimensions (different ZIP, different subdivision, different year built, or slightly different sqft).

**Triggering condition:** Similarity score ≥ 0.70 against at least one listing in the user's viewed or saved set, or against the top-ranked match result.

**Label:** "Similar to homes you've been looking at"

**Value proposition:** Expands the buyer's effective search area by surfacing comparable properties they may not have found through their direct search criteria alone.

---

### 3.2 — Nearby Alternatives

**Description:** Listings that fall outside the buyer's specified city or ZIP code but are within a configurable distance radius (default: 10 miles) of the buyer's target geography. These listings otherwise satisfy the buyer's hard criteria (price, type, beds, baths).

**Triggering condition:** Listing passes all buyer hard filters; distance from buyer's target centroid is greater than the buyer's stated search radius but less than the recommendation radius threshold (buyer radius + 10 miles, capped at 30 miles total).

**Label:** "Nearby alternative — [X] miles from your target area"

**Value proposition:** Addresses the scenario where the buyer's target geography is inventory-constrained. Buyers priced out of a preferred ZIP often discover comparable or superior value within a short drive.

---

### 3.3 — Budget-Stretch Options

**Description:** Listings priced between the buyer's maximum budget and 110% of that maximum (a configurable stretch ceiling). These listings otherwise match the buyer's criteria on property type, size, and location. The recommendation explains the dollar gap and contextualizes it against the listing's feature set.

**Triggering condition:** Listing passes all buyer hard filters except price ceiling; `list_price` is between `buyer.max_budget` and `buyer.max_budget * 1.10`.

**Label:** "Just above your budget — worth considering"

**Value proposition:** Some buyers set conservative budget ceilings and may be willing to stretch once they see what the additional dollars purchase. The recommendation must always display the specific dollar gap, never obscure the overage.

---

### 3.4 — Better-Value Options

**Description:** Listings priced meaningfully below the buyer's maximum budget that score comparably to or better than their highest-matched listing on the non-price dimensions (location, size, amenities). The recommendation surfaces the dollar savings and explains what the buyer is getting for less.

**Triggering condition:** Listing passes all buyer hard filters; `list_price` is ≤ 85% of the buyer's maximum budget; non-price composite score ≥ 0.65 of the top match result's non-price composite score.

**Label:** "Better value — [X]% below your budget with comparable features"

**Value proposition:** Prevents budget anchoring. Buyers who set a ceiling of $450,000 often overlook a $360,000 listing that scores nearly as well on the criteria dimensions they actually care about.

---

### 3.5 — Same-Neighborhood Alternatives

**Description:** Listings in the same subdivision, MLS sub-market area, or ZIP code as a listing the buyer viewed or saved. These alternatives may differ on price, sqft, or specific amenities but share the geographic and community context the buyer is interested in.

**Triggering condition:** Listing shares `subdivision_name` or `postal_code` or `mls_area_major` with any listing in the buyer's viewed or saved set; `standard_status = 'Active'`.

**Label:** "Also in [subdivision/neighborhood/ZIP]"

**Value proposition:** Buyers who are drawn to a specific community often want to see everything available there, including listings that fall slightly outside their stated criteria. Same-neighborhood alternatives let them evaluate the full neighborhood inventory in one place.

---

### 3.6 — Similar Amenities

**Description:** Listings that share the specific amenity combination the buyer has indicated as preferences (pool, waterfront, garage, water view, community features), even if they differ on location or price from the buyer's primary search.

**Triggering condition:** Listing shares ≥ 2 of the buyer's indicated amenity preferences (`pool_private_yn`, `waterfront_yn`, `garage_yn`, `water_view_yn`, or `CommunityFeatures` / `AssociationAmenities` overlap); passes property type hard filter; `standard_status = 'Active'`.

**Label:** "Matches your lifestyle preferences — [amenity list]"

**Value proposition:** Amenity preferences are often the deepest buyer preferences. A buyer who insists on waterfront and garage may accept a different city or higher price if the amenity combination is exactly right. This recommendation type surfaces those matches across geographic boundaries.

---

### 3.7 — New Construction Alternatives

**Description:** New construction listings (`new_construction_yn = true`) that are in the buyer's general target area and price range, even if they differ on size or specific amenities. New construction carries unique purchase process implications (builder contracts, construction timelines, model home selection) that the recommendation copy must flag.

**Triggering condition:** `new_construction_yn = true`; listing is within 20 miles of buyer's target centroid; `list_price` is within 120% of buyer's maximum budget; `standard_status = 'Active'`.

**Label:** "New construction available nearby"

**Value proposition:** Buyers who are searching resale inventory often overlook new construction options that offer warranties, customization, and modern finishes at comparable price points. The recommendation must note that builder contracts differ from standard resale purchase agreements.

---

### 3.8 — Rental Alternatives

**Description:** For buyers whose criteria reveal pricing pressure (e.g., they are searching at the upper limit of their budget in a high-cost market), this recommendation surfaces active rental listings that match their size and location preferences. This type is surfaced only when the rental feed is active and populated.

**Triggering condition:** Buyer's `list_price` ceiling is within 10% of area median for the property type; tenant matching engine is active; rental listings matching buyer's location and size criteria are available; `for_lease_yn = true OR lease_considered_yn = true`.

**Label:** "Renting is also an option in this area"

**Value proposition:** Not all buyers are ready or positioned to purchase. Surfacing rental alternatives for cost-pressured buyers provides genuine value and keeps them engaged with the platform even if they exit the purchase funnel.

**Implementation gate:** This recommendation type must not be shown until the Stellar For Lease feed is confirmed active and the tenant matching engine is operational. See `STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md` Section 2 for the rental feed gate conditions.

---

### 3.9 — Investment Alternatives

**Description:** Listings with non-zero investment financial metrics (`CapRate`, `GrossIncome`, `STELLAR_EstAnnualMarketIncome`) surfaced to buyers who have indicated investment intent in their criteria. This type is only relevant for buyers whose criteria include investment signals (e.g., they are browsing multi-family or income property subtypes).

**Triggering condition:** Buyer's criteria includes investment property types (multi-family, duplex, commercial); listing has non-null `cap_rate` or `gross_income` in `raw_json`; `standard_status = 'Active'`.

**Label:** "Investment alternative with rental income potential"

**Value proposition:** Investment buyers need to see yield-bearing alternatives alongside standard residential listings. The recommendation must surface cap rate and gross income where available and include a disclaimer that these figures are as reported by the listing agent and are not independently verified.

**Compliance note:** All Tier 6 fields remain excluded. Agent commission data, brokerage compensation, and any field classified as compliance-excluded in the field audit must never appear in investment alternative recommendations.

---

## 4. Input Signals

The recommendation engine reads from multiple signal categories to determine which listings are relevant for a given user. Signals are weighted by recency and strength of intent.

### 4.1 — Match Score Signal

The output of the matching engine is the primary input to the recommendation engine. The match score for each listing across the buyer's or tenant's criteria dimensions provides the similarity baseline. Listings that score between 45 and 74 in the matching engine (fair or weak match) are strong candidates for recommendation rather than primary match display — they passed enough hard filters to be relevant but diverge enough on soft preferences to be surfaced as alternatives rather than primary results.

| Match Score Range | Matching Engine Role | Recommendation Engine Role |
|---|---|---|
| 85–100 (Excellent) | Primary match result | Source listing for similarity comparisons |
| 70–84 (Good) | Primary match result | Secondary similarity source |
| 55–69 (Fair) | Primary match result (lower rank) | Candidate for "Similar Homes" recommendation type |
| 40–54 (Weak) | Primary match result (lowest rank) | Candidate for "Nearby Alternatives" or "Better Value" type |
| < 40 (Poor) | Excluded from primary results | Excluded from recommendations unless a specific type condition is met |

### 4.2 — Listing Fields

The following native `bridge_properties` columns are direct inputs to recommendation scoring. Native column status is required for the performance strategy described in Section 9.

**Location fields:** `city`, `postal_code`, `state_or_province`, `county_or_parish`, `latitude`, `longitude`, `subdivision_name`, `mls_area_major`

**Classification fields:** `property_type`, `property_sub_type`, `standard_status`, `new_construction_yn`

**Size fields:** `bedrooms_total`, `bathrooms_total_integer`, `living_area`, `lot_size_sqft`, `year_built`

**Amenity flags:** `pool_private_yn`, `waterfront_yn`, `water_view_yn`, `view_yn`, `garage_yn`, `garage_spaces`

**Financial fields:** `list_price`, `original_list_price`, `association_fee`, `association_yn`, `cdd_yn`, `tax_annual_amount`

**Rental fields (when active):** `monthly_rent`, `lease_considered_yn`, `for_lease_yn`, `furnished`, `pets_allowed`, `availability_date`, `lease_term`

### 4.3 — Buyer/Tenant Criteria

The structured criteria from the buyer's `buyer_agent_auction` or `buyer_criteria_auction` record, or the tenant's `tenant_criteria_auction` record, provide the preference vector against which listing similarity is measured. Fields used:

- Target geography (city, ZIP, county, radius)
- Budget (maximum and, if specified, target price)
- Minimum bedrooms, bathrooms, living area
- Amenity preferences (pool, waterfront, garage, water view)
- Property type and subtype
- HOA/CDD/tax fee tolerances
- New construction preference
- Pet-friendly requirement (buyers: community-level; tenants: rental policy)
- Furnished requirement (tenant only)
- Lease term preference (tenant only)
- Move-in date target (tenant only)

### 4.4 — Saved Listings

Listings that a user has explicitly saved (favorited) are the strongest behavioral signal available before a showing or offer. The recommendation engine uses saved listings to:

1. Calibrate the similarity score baseline — what features do the saved listings share?
2. Identify the "effective criteria" the user is expressing through saved behavior, which may differ from their stated criteria.
3. Surface same-neighborhood alternatives for each saved listing.
4. Identify the price band the user is gravitating toward within their stated budget.

### 4.5 — Viewed Listings

Listings that a user has viewed (clicked through to the detail page) are a moderate behavioral signal. View behavior informs:

1. Which amenity combinations are attracting attention (pool homes viewed more than non-pool homes suggests pool is a genuine preference).
2. Which price range within the budget ceiling the user is gravitating toward.
3. Which neighborhoods or subdivisions are being explored.

View signals decay over time. A listing viewed more than 30 days ago contributes half the signal weight of a listing viewed within the past 7 days.

### 4.6 — Ignored / Dismissed Listings

Listings that a user has explicitly dismissed or hidden are negative signals. The recommendation engine suppresses:

1. Any listing the user has explicitly hidden.
2. Any listing in the same subdivision as a hidden listing, unless the user subsequently views a listing in that same subdivision (which reverses the suppression signal).
3. Any listing with the same property subtype as multiple consecutively hidden listings (suggesting the subtype is not desired, even if the criteria record includes it).

### 4.7 — Location DNA

Location DNA profiles, managed by the `PropertyIntelligenceProfileService` (`App\Services\Dna`), provide neighborhood-level intelligence that extends beyond what Stellar field values alone can convey. Location DNA signals used by the recommendation engine include:

- Neighborhood character tags (walkable, family-oriented, waterfront community, golf community, etc.)
- Proximity to amenity clusters (beaches, downtown cores, school districts, employment centers)
- Comparative value signals (is this listing priced above or below the neighborhood's typical range for this property type?)

Location DNA signals are used in the location adjacency score sub-dimension (Section 5) and in the natural-language recommendation explanations (Section 6).

### 4.8 — Price Range Signal

The buyer's price range and the distribution of prices within the match result set together define the "effective price band" for recommendations. If a buyer's ceiling is $450,000 but all their matched and saved listings cluster between $360,000–$400,000, the recommendation engine should treat the effective band as $360,000–$420,000 for similarity purposes — not the full $0–$450,000 range. This prevents the engine from surfacing a $449,000 listing as a "similar home" when the user is behaviorally expressing a $380,000 preference.

### 4.9 — Amenity Preference Signal

Amenity overlap between listings the user has viewed, saved, or matched against constitutes an implicit amenity preference signal even when the user's formal criteria record does not mark a specific amenity as required. If 7 of the user's top 10 viewed listings have `pool_private_yn = true`, pool is treated as a de-facto preference signal for recommendation scoring even if the user's criteria record left pool preference unchecked.

### 4.10 — PublicRemarks and Context Fields

The `PublicRemarks` field (stored in `raw_json`) is not indexed or used in database-level recommendation queries. However, it is passed to the Ask AI context layer (Phase 4 of the implementation roadmap) so that AI-generated recommendation explanations can draw on listing-specific copy. Other context fields passed at explanation-generation time include `InteriorFeatures`, `ExteriorFeatures`, `CommunityFeatures`, and `AssociationAmenities`. These are not scoring inputs — they are explanation enrichment only.

---

## 5. Scoring Model

### 5.1 — Overview

The recommendation score is separate from the match score. A listing can have a low match score (it fails several soft criteria) but a high recommendation score (it is highly similar on the dimensions the user is demonstrably weighting in their behavior). The two scores serve different purposes and must never be conflated in the UI.

The recommendation score is a 100-point weighted composite across six sub-scores. The weights below total exactly **100 points**.

| # | Sub-Score | Weight | Rationale |
|---|---|---|---|
| 1 | Similarity Score | 30 | Core of recommendation quality — how much does this listing resemble listings the user has engaged with? |
| 2 | Location Adjacency Score | 25 | Geography is the primary non-negotiable dimension; nearby alternatives must be close enough to be actionable |
| 3 | Value Score | 20 | Price-to-feature ratio relative to the buyer's effective price band and the comparable listing set |
| 4 | Amenity Overlap Score | 15 | Overlap between the listing's feature set and the user's demonstrated amenity preferences |
| 5 | Tradeoff Score | 7 | How clearly and favorably the tradeoff can be expressed — a listing with one clear, well-explained tradeoff scores higher than one with many diffuse gaps |
| 6 | Freshness Score | 3 | Recency of the listing in the active inventory; newly listed or recently reduced listings score higher |
| **Total** | | **100** | |

### 5.2 — Similarity Score (30 points)

The similarity score measures structural resemblance between the candidate recommendation listing and the listings the user has engaged with (saved, viewed, or matched). It is computed as a weighted feature vector cosine similarity across the following dimensions:

| Dimension | Feature Input | Weight within Sub-score |
|---|---|---|
| Property type + subtype match | `property_type`, `property_sub_type` | 20% |
| Bedroom + bathroom count proximity | `bedrooms_total`, `bathrooms_total_integer` | 20% |
| Living area proximity | `living_area` (±15% tolerance = full credit) | 15% |
| Price proximity | `list_price` (within effective band = full credit) | 20% |
| Year built era proximity | `year_built` (same decade = full credit; ±10 years = half credit) | 10% |
| Amenity flag overlap | `pool_private_yn`, `waterfront_yn`, `garage_yn`, `water_view_yn` | 15% |

**Computation:** For each listing in the user's engaged set, compute the feature similarity vector against the candidate recommendation listing. Take the maximum similarity score across all engaged listings (a candidate only needs to resemble one engaged listing strongly to qualify). Multiply by 30 to get the sub-score.

**Minimum threshold:** Candidates with a similarity score below 0.45 (13.5 points) are excluded from recommendations entirely — they are not sufficiently similar to serve as useful discovery alternatives.

### 5.3 — Location Adjacency Score (25 points)

The location adjacency score measures how geographically actionable the recommendation is. A listing 45 miles away is not a meaningful recommendation; a listing 4 miles away in a neighboring ZIP is.

| Adjacency Level | Points | Condition |
|---|---|---|
| Same city + ZIP | 25 | Listing city and postal code match any of buyer's target cities/ZIPs |
| Same city, different ZIP | 20 | Listing city matches; ZIP differs |
| Same county, neighboring city | 15 | Listing is in buyer's target county but different city |
| Adjacent county, within radius | 10 | Listing is in a neighboring county; Haversine distance ≤ recommendation radius (buyer radius + 10 miles) |
| Same MLS sub-market | 8 | Listing shares `mls_area_major` with buyer's target; geography may overlap with above bands |
| Same subdivision | +5 bonus | Added to whichever band applies; same-subdivision recommendations receive a 5-point location bonus |
| Beyond recommendation radius | 0 | Excluded from recommendations regardless of similarity score |

**Location DNA integration (Phase 2+):** When Location DNA neighborhood profiles are available, listings in neighborhoods with a high character-tag overlap with the buyer's engaged listing neighborhoods receive a +3 bonus applied to the adjacency score (capped at the 25-point maximum for this sub-score).

### 5.4 — Value Score (20 points)

The value score assesses whether the candidate listing offers a compelling price-to-feature ratio relative to the user's effective price band and the matched result set.

| Scenario | Points | Computation |
|---|---|---|
| Significantly better value | 20 | Listing is ≤ 85% of buyer's effective price band ceiling AND non-price feature set is comparable (similarity ≥ 0.60) |
| Moderately better value | 15 | Listing is 86–93% of effective ceiling with comparable features |
| On-par value | 10 | Listing is within 5% of median match result price with similar feature set |
| Slight stretch | 5 | Listing is 101–110% of buyer's maximum budget with meaningfully stronger feature set |
| Poor value or excessive stretch | 0 | Listing is >110% of buyer's maximum budget or offers substantially fewer features at same price |
| Price-reduced listing bonus | +3 | Added when `original_list_price > list_price`; seller motivation signal; capped at 20-point maximum |

### 5.5 — Amenity Overlap Score (15 points)

The amenity overlap score measures how closely the candidate recommendation listing matches the user's demonstrated amenity preferences (from both stated criteria and behavioral signals).

Each amenity preference match contributes a proportional share of the 15 points. The total is normalized to 15 regardless of how many amenity preferences the user has.

| Amenity | Points (when user preference is confirmed) |
|---|---|
| Private pool match | 5 |
| Waterfront match | 4 |
| Garage match (≥ required count) | 3 |
| Water view match | 2 |
| Community features overlap (≥ 2 tags) | 1 |

**Normalization:** If the user has only one amenity preference, it scales to the full 15 points. If the user has no discernible amenity preferences (stated or behavioral), this sub-score defaults to 10 points (neutral) — not 15 and not 0.

### 5.6 — Tradeoff Score (7 points)

The tradeoff score rewards candidates where the deviation from the user's stated criteria is small, singular, and clearly expressible. A listing with one specific, named tradeoff ("no private pool, but $35,000 below your budget") is more useful as a recommendation than a listing with five diffuse gaps.

| Tradeoff Quality | Points | Condition |
|---|---|---|
| Single, named, favorable tradeoff | 7 | Exactly one dimension deviates from criteria; the deviation is offset by a measurable benefit (price savings, superior location, better amenities) |
| Single, named, neutral tradeoff | 5 | Exactly one dimension deviates; the offset benefit is modest or uncertain |
| Two named tradeoffs | 3 | Two dimensions deviate; both can be named and explained |
| Three or more tradeoffs | 1 | Three or more deviations; a recommendation can still be surfaced but the explanation complexity is high |
| Unexplainable gap pattern | 0 | Deviations cannot be mapped to a clear explanation template; listing is excluded from recommendations |

### 5.7 — Freshness Score (3 points)

The freshness score rewards listings that are newly added to inventory or have recently had a price reduction. Stale listings (long days-on-market, no recent activity) receive lower freshness scores.

| Freshness Condition | Points |
|---|---|
| Listed within the last 7 days | 3 |
| Listed 8–30 days ago | 2 |
| Listed 31–90 days ago | 1 |
| Listed > 90 days ago | 0 |
| Price reduced within the last 14 days | +1 bonus (added to date-based score, capped at 3) |

**Data source:** `modification_timestamp` (already a native column) is used as the freshness proxy. When `original_list_price > list_price` (native after Phase 1 promotions), a recent price reduction adds the +1 bonus.

### 5.8 — Total Score and Interpretation

| Score | Label | Meaning |
|---|---|---|
| 80–100 | Strong recommendation | Highly similar; actionable geography; clear explanation; surface prominently |
| 65–79 | Good recommendation | Solid similarity; worth surfacing with clear explanation |
| 50–64 | Fair recommendation | Relevant but requires clear tradeoff framing; surface with explanation |
| 35–49 | Weak recommendation | Surface only when the matched result set is thin (< 5 results) |
| < 35 | Not recommended | Exclude from recommendation output |

---

## 6. Recommendation Explanation Blueprint

Every recommendation card returns four structured explanation blocks. These blocks are consumed by the UI display layer and, in Phase 4, by the Ask AI natural-language generation layer. The block structure is consistent across all nine recommendation types; the copy templates below vary by type.

### 6.1 — "Similar Because…" Template

Used for: Similar Homes, Same-Neighborhood Alternatives, Similar Amenities

```
{
  "explanation_type": "similar_because",
  "headline": "Similar to homes you've been looking at",
  "reasons": [
    {
      "dimension": "size",
      "copy": "3 beds / 2 baths — same layout as homes you've saved"
    },
    {
      "dimension": "amenities",
      "copy": "Private pool — matches your top amenity preference"
    },
    {
      "dimension": "price",
      "copy": "Listed at $372,000 — within your budget range"
    }
  ],
  "fields_used": ["bedrooms_total", "bathrooms_total_integer", "pool_private_yn", "list_price"]
}
```

**Rules:**
- Include 2–4 reasons; never more than 4.
- Each reason maps to exactly one scoring dimension.
- Do not expose Stellar field names to the user — use plain English dimension labels.
- `fields_used` is internal metadata for debugging and Phase 4 prompt construction only.

### 6.2 — "Worth Considering Because…" Template

Used for: Nearby Alternatives, New Construction Alternatives, Investment Alternatives

```
{
  "explanation_type": "worth_considering",
  "headline": "Worth considering — [X] miles from your target area",
  "context": "Outside your search area, but comparable on the criteria that matter most.",
  "reasons": [
    {
      "dimension": "location",
      "copy": "4.2 miles from Orlando — nearby in Kissimmee"
    },
    {
      "dimension": "value",
      "copy": "Listed at $398,000 — $52,000 below your budget"
    },
    {
      "dimension": "amenities",
      "copy": "Waterfront — matches your top preference"
    }
  ],
  "fields_used": ["city", "postal_code", "list_price", "waterfront_yn"]
}
```

### 6.3 — "Tradeoff…" Template

Used for: Budget-Stretch Options, Better-Value Options, any recommendation where a single primary tradeoff exists

```
{
  "explanation_type": "tradeoff",
  "headline": "One tradeoff — here is what you are getting and what you are giving up",
  "tradeoff": {
    "giving_up": "Private pool — this listing does not have one",
    "getting": "$38,000 below your budget in the same ZIP code",
    "net_assessment": "Consider whether the price savings offset the pool preference"
  },
  "fields_used": ["pool_private_yn", "list_price", "postal_code"]
}
```

**Rules:**
- The "tradeoff" block must always name both the giving-up and the getting dimension.
- The net_assessment sentence must be neutral — it must not tell the buyer what to decide. It frames the choice; it does not make it.
- If the tradeoff score (Section 5.6) produced a rating of "two named tradeoffs", the block includes two `tradeoff` objects in an array.

### 6.4 — "Better Value Because…" Template

Used for: Better-Value Options

```
{
  "explanation_type": "better_value",
  "headline": "Better value — comparable features at a lower price",
  "savings": {
    "amount_below_budget": "$67,000",
    "percent_below_budget": "16%",
    "compared_to": "your viewed listings in this area"
  },
  "feature_parity": [
    "Same bedroom and bathroom count",
    "Similar square footage (within 8%)",
    "Garage included"
  ],
  "fields_used": ["list_price", "bedrooms_total", "bathrooms_total_integer", "living_area", "garage_yn"]
}
```

### 6.5 — "Nearby Alternative…" Template

Used for: Nearby Alternatives, Rental Alternatives

```
{
  "explanation_type": "nearby_alternative",
  "headline": "Nearby alternative in [city/area name]",
  "distance": "6.8 miles from your target area",
  "key_differences": [
    "Different city — [City Name] instead of [Target City]",
    "Slightly larger — 2,150 sqft vs. your typical 1,900 sqft range",
    "Listed at $315,000 — 15% below your budget"
  ],
  "fields_used": ["city", "postal_code", "living_area", "list_price"]
}
```

---

## 7. Buyer Recommendation Strategy

This section defines concrete strategy examples for how the recommendation engine applies the nine recommendation types to real buyer scenarios in the Florida residential market.

### 7.1 — Buyer Wants Waterfront, But Exact Matches Are Limited

**Scenario:** A buyer with a maximum budget of $550,000 is searching for waterfront single-family homes in Sarasota County. The matching engine returns only 3 active waterfront matches.

**Recommendation strategy:**
1. **Similar Amenities (Section 3.6):** Surface active listings with `water_view_yn = true` (but `waterfront_yn = false`) in the same county. The recommendation copy frames these as "water view — not direct waterfront, but comparable views at a lower price point."
2. **Nearby Alternatives (Section 3.2):** Expand the search to Manatee County (adjacent county) where waterfront inventory at this price point may be deeper. The adjacency score will be 10–15 (same recommendation radius band); the explanation labels the county and the driving distance.
3. **Better-Value Options (Section 3.4):** If any inland listings in Sarasota County with `pool_private_yn = true` score ≥ 0.65 on non-location dimensions, surface them as better-value alternatives with the explicit tradeoff: "No waterfront — but private pool, $120,000 below your budget, and 5 minutes from a public beach."
4. **Budget-Stretch Options (Section 3.3):** If the buyer's $550,000 ceiling is below the median waterfront price for the county, surface 1–2 listings at $580,000–$605,000 (within the 110% stretch ceiling) to show what the additional dollars buy.

**What is not recommended:** Do not surface non-waterfront, non-water-view listings from a different county as "similar homes" — the similarity score will be too low and the tradeoff is too diffuse to explain usefully.

---

### 7.2 — Buyer Wants Pool, But Nearby Non-Pool Homes Are Better Value

**Scenario:** A buyer searching for pool homes in Lake Nona (Orlando) at $420,000 receives 8 pool-home matches, but the pool homes in the area are concentrated at the upper end of their budget ($395,000–$420,000), while comparable non-pool homes are priced at $340,000–$370,000.

**Recommendation strategy:**
1. **Better-Value Options (Section 3.4):** Surface the top 3 non-pool comparables in the same ZIP code. Value score will be high (16–19% below effective ceiling). The tradeoff block explicitly names: "No private pool — but community pool is 0.3 miles away, and you save $60,000–$80,000 at this price range."
2. **Same-Neighborhood Alternatives (Section 3.5):** Surface any listing in the same subdivision with `pool_private_yn = false` but `CommunityFeatures` containing "Pool" — the community pool signal mitigates the private pool gap and the explanation copy should say so.
3. **Suppress budget-stretch options** in this scenario — the buyer's issue is value distribution, not budget constraint. Surfacing $460,000 pool homes would not serve their needs.

---

### 7.3 — Buyer Priced Out of Preferred ZIP

**Scenario:** A buyer searching for 3-bedroom homes in ZIP 34236 (Sarasota, FL) at a $400,000 ceiling finds zero active matches — all active inventory in that ZIP is above their ceiling.

**Recommendation strategy:**
1. **Nearby Alternatives (Section 3.2):** Immediately expand to neighboring ZIPs within 8 miles (34237, 34238, 34231, 34229). Surface the top 5 by recommendation score with the label "Nearby alternative — [X] miles from your preferred ZIP."
2. **Better-Value Options (Section 3.4):** If the expanded set includes any listings at ≤ 88% of the buyer's ceiling that score ≥ 0.65 on non-price dimensions, surface them as better-value highlights.
3. **Budget-Stretch Options (Section 3.3):** Surface 1–2 listings in the target ZIP at $410,000–$440,000 with the framing "Just above your budget in your preferred neighborhood." The buyer may be willing to stretch once they see there are no matches at their ceiling in the target ZIP.
4. **Do not surface zero recommendations:** If the match result set is empty, the recommendation engine must activate immediately and lead the results page rather than showing an empty state.

---

### 7.4 — Buyer Open to Neighboring Cities

**Scenario:** A buyer's criteria includes three preferred cities but they have also checked "open to nearby areas" or left their search radius at 20 miles. The matching engine returns 15 results; 12 are in City A, 2 in City B, and 1 in City C.

**Recommendation strategy:**
1. **Nearby Alternatives (Section 3.2):** Surface high-scoring candidates from cities within the radius but not in the buyer's explicitly stated preferred city list. These appear in a dedicated "Also worth looking at in [City Name]" section.
2. **Similar Homes (Section 3.1):** Within the expanded radius, surface listings that are structurally similar to the buyer's top-saved matches even if they are in unfamiliar cities.
3. **Location DNA integration:** Use neighborhood character tags to surface cities with similar lifestyle profiles to the buyer's preferred city even if they are further from the centroid. Example: "Lakewood Ranch has a similar family-oriented, golf-community character to [preferred city] — 14 miles away."

---

## 8. Tenant Recommendation Strategy

This section is subject to the rental feed implementation gate documented in `STELLAR_TENANT_MATCHING_ENGINE_ARCHITECTURE.md` Section 2. No tenant recommendation strategy is implementable until the Stellar For Lease feed is active and the tenant matching engine is operational.

### 8.1 — Pet-Friendly Alternatives

**Scenario:** A tenant with a dog has a maximum monthly rent of $2,000 and a target ZIP in Kissimmee. The matching engine returns only 2 active pet-friendly rentals under $2,000 in that ZIP.

**Recommendation strategy:**
1. **Nearby Alternatives (Section 3.2):** Expand to neighboring ZIPs within 8 miles where `pets_allowed` includes "Dogs OK" or "Yes" and `monthly_rent ≤ $2,000`. Surface with the label "Pet-friendly alternative — [X] miles away."
2. **Budget-Stretch Options (Section 3.3):** If pet-friendly inventory is thin at $2,000, surface 1–2 listings at $2,050–$2,200 (within the 110% stretch ceiling) where pet policy is unambiguously permissive. Note the pet deposit and monthly pet fee from `STELLAR_PetDepositFee` and `STELLAR_PetMonthlyFee` in the explanation.
3. **Conditional pet policy warning:** For any listing where `PetsAllowed` contains "Call", "Negotiable", or a conditional value, the recommendation card must include a warning: "Pet policy requires confirmation — contact the landlord before applying." These listings must not be surfaced as "pet-friendly" without this warning.

---

### 8.2 — Furnished Alternatives

**Scenario:** A tenant requires a furnished unit for a 6-month stay (corporate relocation). The matching engine finds 4 furnished matches in the target area; 2 are above the budget ceiling.

**Recommendation strategy:**
1. **Similar Homes (Section 3.1):** Surface the 2 in-budget furnished matches and flag them as primary results. For the 2 above-budget options, apply the Budget-Stretch template with the specific dollar overage.
2. **Better-Value Options (Section 3.4):** If any unfurnished listing in the same area is ≤ 80% of the tenant's ceiling with a note that "short-term furnished rentals may be available for this unit type — contact the landlord," surface it as a better-value alternative with explicit framing that furnished status would need to be confirmed.
3. **Nearby Alternatives (Section 3.2):** Expand the geographic search to neighboring ZIPs for furnished units. Furnished rentals are less geographically constrained for corporate relocations — the tenant is often more flexible on location than on furnished status.

---

### 8.3 — Move-In Date Alternatives

**Scenario:** A tenant's target move-in date is August 1. The matching engine finds 6 matches, but only 2 have `availability_date ≤ August 1`. The other 4 are available August 15 – September 1.

**Recommendation strategy:**
1. **Surface the 2 on-time matches first** as primary results.
2. **Apply the availability scoring from the tenant matching engine** (Section 5, Bucket 3 in tenant document) to rank the 4 late-availability options by proximity to August 1.
3. **Recommendation copy for late-availability options:** "Available [date] — [N] days after your target move-in. A short-term bridge arrangement may close this gap." This is the Move-In Timing Notes template from the tenant matching engine explanation blueprint, adapted for the recommendation layer.
4. **Nearby Alternatives (Section 3.2):** If expanding to nearby ZIPs yields listings available before August 1, surface them with the label "Available sooner — [date] — in [City Name], [X] miles away."

---

### 8.4 — Lower-Rent Nearby Options

**Scenario:** A tenant is searching in a high-cost ZIP at the upper limit of their $1,800/month ceiling. The matching engine returns 3 matches, all priced between $1,740 and $1,800.

**Recommendation strategy:**
1. **Better-Value Options (Section 3.4):** Surface listings in neighboring ZIPs where `monthly_rent` is ≤ $1,500 and the size, bedroom count, and pet/furnished status are comparable. Value score will be high.
2. **Explanation copy:** "In [Nearby City] — comparable size and features at $300/month less. [X] miles further from your target area."
3. **Include total monthly cost context:** Where `RentIncludes` and `TenantPays` data is available, surface the "effective monthly cost" (rent + estimated utilities responsibility) in the recommendation card rather than rent alone.

---

### 8.5 — Lease-Term Alternatives

**Scenario:** A tenant prefers a 12-month lease. The matching engine returns 5 results, but 3 of them have `minimum_lease` values of 6 months (shorter than preferred) and 1 has a `LeaseTerm` of 24 months (longer than preferred). Only 1 exactly matches.

**Recommendation strategy:**
1. **Nearby Alternatives (Section 3.2):** Expand to neighboring ZIPs or subdivisions where the 12-month term is the dominant listing pattern.
2. **Tradeoff template for lease mismatch:** "Minimum lease is 6 months — you prefer 12. This may allow month-to-month renewal after the initial term; confirm with the landlord before applying."
3. **Tradeoff template for over-length lease:** "24-month lease — longer than your preference. Consider whether this timeline works for your situation."
4. **Do not apply a hard filter for lease term in the recommendation layer** — the recommendation engine must surface options outside the preferred term with clear framing, unlike the matching engine which may score them lower.

---

## 9. Performance Strategy

### 9.1 — Native Columns First

All recommendation scoring that involves WHERE clauses, range comparisons, or ORDER BY operations against `bridge_properties` must use native indexed columns. The recommendation engine inherits the same performance constraint as the matching engine: `raw_json` field extraction is O(1) acceptable for per-record context loading but is not acceptable for cross-table comparison queries.

The Phase 1 native column promotions documented in `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 8 are prerequisites for the recommendation engine's scoring layer. The recommendation engine must not be implemented before these columns are promoted and indexed.

**Required native columns for recommendation scoring:**

| Column | Use in Recommendation Engine |
|---|---|
| `latitude`, `longitude` | Location adjacency score (Haversine distance) |
| `city`, `postal_code`, `county_or_parish` | Adjacency band classification |
| `subdivision_name`, `mls_area_major` | Same-neighborhood and same sub-market detection |
| `list_price`, `original_list_price` | Value score, price-reduction freshness bonus |
| `bedrooms_total`, `bathrooms_total_integer`, `living_area` | Similarity score vector |
| `property_type`, `property_sub_type` | Type match in similarity score |
| `pool_private_yn`, `waterfront_yn`, `water_view_yn`, `garage_yn` | Amenity overlap score |
| `new_construction_yn` | New construction recommendation type gate |
| `year_built` | Era-proximity in similarity score |
| `association_fee`, `association_yn`, `cdd_yn`, `tax_annual_amount` | Total ownership cost in value score |
| `modification_timestamp` | Freshness score baseline |
| `standard_status` | Active listing gate — all queries |

### 9.2 — Candidate Pool Strategy

The recommendation engine does not scan the full `bridge_properties` table for every user. It builds a candidate pool in two stages:

**Stage 1 — Geography-gated pre-filter (SQL WHERE on native columns):**
```sql
WHERE standard_status = 'Active'
  AND property_type = :buyer_property_type
  AND (
    city = ANY(:target_cities)
    OR postal_code = ANY(:target_zips)
    OR (
      latitude BETWEEN :lat_min AND :lat_max
      AND longitude BETWEEN :lng_min AND :lng_max
    )
  )
  AND list_price BETWEEN :price_floor AND :price_ceiling_with_stretch
  AND bedrooms_total >= :min_bedrooms - 1  -- allow one bedroom below for recommendations
  AND senior_community_yn = :buyer_senior_eligible
```

This pre-filter uses the bounding box approximation for the expanded radius (buyer radius + 10 miles) before the precise Haversine calculation is applied. It reduces the candidate pool to a few hundred records before any scoring begins.

**Stage 2 — Scoring on candidate pool:**
Scoring (Sections 5.2–5.7) runs on the candidate pool. All six sub-scores are computed per candidate using only native column values. No `raw_json` extraction occurs at scoring time.

**Stage 3 — Context enrichment from `raw_json` (per record, after ranking):**
After scoring and ranking, the top N recommendation candidates (configurable; default: 20) are loaded with their full `raw_json` payload for explanation generation. This is O(1) per record and is acceptable. `PublicRemarks`, `CommunityFeatures`, `AssociationAmenities`, and similar context fields are extracted at this stage.

### 9.3 — Cached Recommendation Results

Recommendation results are computationally more expensive than match results because they require the similarity vector comparison across the user's engaged listing set. To avoid recomputing on every page load, results are cached with the following invalidation rules:

**Cache key:** `recommendations:{user_id}:{criteria_record_id}:{criteria_hash}`

The `criteria_hash` is a hash of the user's criteria record fields plus their behavioral signal set (saved listing IDs, last-viewed listing IDs). Any change to these inputs invalidates the cache.

**Refresh triggers:**
1. Any change to the user's criteria record (new save, edited preferences).
2. Any listing in the user's saved or viewed set changes `standard_status` (it left the market; the similarity baseline must be recomputed).
3. The user dismisses or hides a recommendation (feedback signal processed; cache invalidated for next load).
4. `modification_timestamp` advances on any listing in the candidate pool that was previously scored. New inventory or price changes may produce better recommendations.
5. Time-based expiry: recommendation cache expires after 4 hours regardless of invalidation signals, to ensure freshness without recomputing on every visit.

### 9.4 — `modification_timestamp` as the Freshness Gate

`modification_timestamp` (native column, already indexed) is the canonical trigger for stale cache detection. The recommendation engine computes a `max(modification_timestamp)` over the candidate pool as part of each scoring run and stores it alongside the cached result. On cache read, the system checks whether any record in the candidate pool has a `modification_timestamp` newer than the stored max. If so, the cache is invalidated and recommendations are recomputed.

This pattern avoids full-table scans on cache validation: the query `SELECT MAX(modification_timestamp) FROM bridge_properties WHERE [geography pre-filter]` runs on the same native indexed columns as the Stage 1 pre-filter and is fast.

---

## 10. Compliance and Suppression

### 10.1 — Tier 6 Field Exclusion

No Tier 6 (Compliance/Excluded) field may appear in any recommendation score, recommendation explanation, tradeoff copy, or natural-language output. This boundary is identical to the matching engine boundary established in `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md` Section 1.

The 223 fields classified as Tier 6 in the readiness audit include all agent PII, license numbers, brokerage data, lockbox details, compensation fields, and showing instruction fields. These are hard-excluded at the data layer. The recommendation engine must not read these fields from `raw_json` for any purpose, including explanation enrichment.

### 10.2 — Non-IDX Listings

Listings that are not IDX-eligible must not appear in recommendations. IDX eligibility is determined by the `STELLAR_IDXEntryDate` and `STELLAR_IDXParticipationYN` flags in `raw_json`. Until a native column is promoted for IDX eligibility, the recommendation engine must extract and check this value from `raw_json` at Stage 3 (context enrichment) and suppress any non-IDX listing before it is returned as a recommendation card, even if it passed the scoring stage.

### 10.3 — Inactive, Expired, and Deleted Listings

All recommendation queries are gated on `standard_status = 'Active'` as a hard WHERE clause (Stage 1 pre-filter). No Pending, Closed, Withdrawn, Expired, or Cancelled listing may appear in recommendations under any circumstances.

Additionally, any listing in the user's viewed, saved, or engaged set that has left Active status must be removed from the similarity baseline before the similarity score is computed. A listing the user saved 3 months ago that is now Closed is not a valid similarity anchor.

### 10.4 — Age-Restricted Communities (HOPA Compliance)

Listings with `senior_community_yn = true` are subject to the same HOPA compliance gate as in the matching engine. When the buyer or tenant is not 55+ eligible, `senior_community_yn = true` listings are suppressed from all recommendation types. This is enforced in Stage 1 via the native `senior_community_yn` column.

This is not a soft-scoring penalty — it is a hard suppression. The recommendation engine must never surface a senior community listing to a non-eligible user, even as a "worth considering" alternative.

### 10.5 — Private Agent and Brokerage Data

Agent compensation fields, listing agent contact details, brokerage co-op terms, and commission splits must never appear in recommendation cards, explanation copy, or any user-facing output from the recommendation engine. These fields are structurally excluded by the Tier 6 boundary (Section 10.1) but are called out explicitly because they are the most legally sensitive category.

The recommendation card may include:
- Listing address
- Price
- Beds / baths / sqft
- Key amenity flags (pool, waterfront, garage)
- Year built
- HOA fee (when `association_fee` is native and populated)
- Recommendation type label and explanation copy

The recommendation card must never include:
- Listing agent name, phone, or email
- Brokerage name or logo in a context that implies endorsement or compensation
- MLS lock-box instructions or showing contact details
- Any Tier 6 field value

---

## 11. Storage Strategy

The recommendation engine's storage layer requires three future tables. No migrations are created by this document — these are specifications for future implementation.

### 11.1 — `listing_recommendations` Table

**Purpose:** Stores the scored recommendation results for a given user's criteria record at a point in time. Acts as the cache backing store for recommendation results.

**Column sketch:**

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT` (PK, auto-increment) | |
| `user_id` | `BIGINT` (FK → users) | Recommendation recipient |
| `criteria_record_type` | `VARCHAR(50)` | `buyer_agent_auction`, `buyer_criteria_auction`, `tenant_criteria_auction` |
| `criteria_record_id` | `BIGINT` | ID of the source criteria record |
| `criteria_hash` | `VARCHAR(64)` | SHA-256 of the criteria + behavioral signal set at generation time |
| `listing_key` | `VARCHAR(50)` | FK → bridge_properties.listing_key |
| `recommendation_type` | `VARCHAR(50)` | One of the nine types from Section 3 |
| `recommendation_score` | `SMALLINT` | 0–100; composite score from Section 5 |
| `similarity_score` | `SMALLINT` | Sub-score: 0–30 |
| `location_adjacency_score` | `SMALLINT` | Sub-score: 0–25 |
| `value_score` | `SMALLINT` | Sub-score: 0–20 |
| `amenity_overlap_score` | `SMALLINT` | Sub-score: 0–15 |
| `tradeoff_score` | `SMALLINT` | Sub-score: 0–7 |
| `freshness_score` | `SMALLINT` | Sub-score: 0–3 |
| `explanation_payload` | `JSONB` | Serialized explanation block from Section 6 |
| `candidate_pool_max_timestamp` | `TIMESTAMP` | Max modification_timestamp over candidate pool at generation time (used for cache invalidation) |
| `generated_at` | `TIMESTAMP` | When this recommendation run was computed |
| `expires_at` | `TIMESTAMP` | Cache expiry (default: generated_at + 4 hours) |

**Indexes:**
- `(user_id, criteria_record_id, expires_at)` — primary cache lookup
- `(listing_key)` — join for listing status checks
- `(criteria_hash)` — criteria change detection

**Rationale:** Storing scored results with their sub-scores enables debugging, A/B testing of scoring weights, and future feedback loop integration (Section 13) without recomputing scores from scratch on every feedback event.

---

### 11.2 — `user_recommendation_feedback` Table

**Purpose:** Records explicit and implicit user actions on recommendation cards. Feeds the feedback loop (Section 13) and informs future recommendation quality improvements.

**Column sketch:**

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT` (PK, auto-increment) | |
| `user_id` | `BIGINT` (FK → users) | |
| `listing_key` | `VARCHAR(50)` | FK → bridge_properties.listing_key |
| `recommendation_id` | `BIGINT` (FK → listing_recommendations) | Which recommendation record this feedback is for |
| `action_type` | `VARCHAR(50)` | `saved`, `viewed`, `dismissed`, `hidden`, `scheduled_showing`, `offer_submitted` |
| `action_timestamp` | `TIMESTAMP` | When the action occurred |
| `recommendation_score_at_action` | `SMALLINT` | The recommendation score at the time of the action (for learning) |
| `recommendation_type_at_action` | `VARCHAR(50)` | Which recommendation type surfaced this listing |

**Indexes:**
- `(user_id, action_type, action_timestamp)` — user feedback history queries
- `(listing_key, action_type)` — listing-level feedback aggregation

**Rationale:** The feedback table does not drive real-time scoring changes in the initial implementation. It accumulates signal for Phase 3 (behavior-based recommendations) and Phase 4 (AI explanation personalization). The separation of feedback from the recommendation cache allows the cache to be invalidated on feedback without losing the feedback record.

---

### 11.3 — `recommendation_runs` Table

**Purpose:** Audit log of recommendation generation jobs. Records when each run was triggered, how long it took, how many candidates were evaluated, and how many results were stored. Used for performance monitoring and debugging.

**Column sketch:**

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT` (PK, auto-increment) | |
| `user_id` | `BIGINT` (FK → users) | Null for batch/scheduled runs |
| `criteria_record_type` | `VARCHAR(50)` | Source criteria type |
| `criteria_record_id` | `BIGINT` | Source criteria ID |
| `trigger_reason` | `VARCHAR(100)` | `criteria_saved`, `cache_expired`, `feedback_event`, `listing_imported`, `manual` |
| `candidate_pool_size` | `INTEGER` | How many listings passed Stage 1 pre-filter |
| `scored_count` | `INTEGER` | How many candidates were fully scored |
| `results_stored` | `INTEGER` | How many recommendation results were written to `listing_recommendations` |
| `duration_ms` | `INTEGER` | Wall-clock duration of the run in milliseconds |
| `started_at` | `TIMESTAMP` | |
| `completed_at` | `TIMESTAMP` | |

**Rationale:** Without a run audit log, it is impossible to detect scoring regressions, identify performance degradation as the `bridge_properties` table grows, or confirm that cache invalidation is triggering runs at the expected frequency.

---

## 12. Implementation Roadmap

The recommendation engine is implemented in four phases. Each phase has a named gate condition that must be verified before the phase begins. No phase begins until the gate condition is satisfied.

### Phase 1 — Similar Listings from Native MLS Fields

**Gate condition:** All Phase 1 native column promotions from `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` Section 8 are in production and indexed. The buyer matching engine (Phase 1 of `STELLAR_BUYER_MATCHING_ENGINE_ARCHITECTURE.md`) is operational and producing scored match results.

**Scope:**
- Implement the Stage 1 geography-gated pre-filter using native columns.
- Implement Similarity Score (30 points) using the feature vector approach from Section 5.2, limited to native column dimensions only.
- Implement Location Adjacency Score (25 points) using Haversine distance and the adjacency band table from Section 5.3.
- Implement Value Score (20 points) based on `list_price` and `original_list_price` native columns.
- Implement the `listing_recommendations` and `recommendation_runs` tables.
- Surface three recommendation types: Similar Homes, Nearby Alternatives, Better-Value Options.
- Implement the "Similar Because…", "Nearby Alternative…", and "Better Value Because…" explanation templates as structured JSON (plain-language string assembly, no AI).
- Implement basic cache with 4-hour expiry and `modification_timestamp` invalidation.

**Recommendation types available in Phase 1:** Similar Homes, Nearby Alternatives, Better-Value Options

**Not available in Phase 1:** Amenity overlap score (requires behavioral signal), tradeoff score, freshness score, Location DNA integration, AI explanations, any tenant recommendation type.

---

### Phase 2 — Location DNA Alternatives

**Gate condition:** Phase 1 is complete and operating in production. Location DNA neighborhood profiles (`PropertyIntelligenceProfileService`) are populated for the primary geographic market. Phase 2 native column promotions (subdivision_name, mls_area_major, and others per the expansion strategy) are complete.

**Scope:**
- Integrate Location DNA signals into the Location Adjacency Score (+3 neighborhood character bonus from Section 5.3).
- Implement Amenity Overlap Score (15 points) using native amenity flag columns (`pool_private_yn`, `waterfront_yn`, `water_view_yn`, `garage_yn`) and behavioral signal accumulation from the `user_recommendation_feedback` table.
- Implement Tradeoff Score (7 points) and the "Tradeoff…" explanation template from Section 6.3.
- Implement Freshness Score (3 points) using `modification_timestamp` and `original_list_price` comparison.
- Add Same-Neighborhood Alternatives recommendation type using `subdivision_name` and `mls_area_major` native columns.
- Add Similar Amenities recommendation type using amenity flag overlap.
- Add New Construction Alternatives recommendation type using `new_construction_yn` native column.
- Implement the `user_recommendation_feedback` table and begin recording dismissed and saved actions.
- Implement the "Worth Considering Because…" and "Better Value Because…" explanation templates.

**Recommendation types available after Phase 2:** Similar Homes, Nearby Alternatives, Better-Value Options, Same-Neighborhood Alternatives, Similar Amenities, New Construction Alternatives

---

### Phase 3 — Behavior-Based Recommendations

**Gate condition:** Phase 2 is complete. The `user_recommendation_feedback` table has accumulated at least 90 days of user action data. The tenant matching engine is operational (required for Rental Alternatives recommendation type).

**Scope:**
- Implement the behavioral signal layer: saved listing similarity calibration, view-frequency amenity inference, dismissed listing suppression (Section 4.4–4.6).
- Use accumulated `user_recommendation_feedback` to adjust the effective price band and effective amenity preference vector per user.
- Implement Budget-Stretch Options recommendation type with per-user stretch ceiling calibration.
- Implement Rental Alternatives recommendation type (tenant feed gate must be confirmed active).
- Implement Investment Alternatives recommendation type for buyers with investment property criteria.
- Implement A/B scoring weight experimentation using `recommendation_runs` audit log for analysis.
- Introduce cache invalidation on feedback events (Section 9.3, trigger #3).

**Recommendation types available after Phase 3:** All nine types from Section 3

---

### Phase 4 — AI-Generated Recommendation Explanations

**Gate condition:** Phase 3 is complete. The Ask AI infrastructure (including the `AskAiContextBuilderService` and `AskAiPromptBuilderService` pipeline) is stable and in production. Phase 4 Ask AI integration patterns are documented.

**Scope:**
- Replace string-assembled explanation templates (Phases 1–3) with AI-generated natural-language explanation copy.
- Pass the structured explanation payload (Section 6) plus the listing's `raw_json` context fields (`PublicRemarks`, `InteriorFeatures`, `CommunityFeatures`, `AssociationAmenities`) to the prompt builder.
- Generate personalized recommendation copy that references the specific listing's text — e.g., "The listing description highlights mature oak trees and a private screened pool, which match the outdoor lifestyle you've been exploring."
- Implement the feedback loop signal integration (Section 13) to personalize AI explanation tone and emphasis based on user action history.
- AI explanations must remain compliant with the Tier 6 exclusion boundary (Section 10.1): the prompt must explicitly instruct the model not to reference agent, brokerage, compensation, or lockbox data.

---

## 13. Recommendation Feedback Loop

This section documents how future user actions on recommendation cards may influence recommendation quality. All items in this section are documentation of intended future behavior. No implementation work is introduced here — this is a design record for the feedback signal architecture that Phases 3 and 4 will build against.

### 13.1 — Signal Types and Intended Influence

The feedback loop translates user actions into adjustments to the recommendation scoring inputs. Each action type has a defined influence on future recommendations for the same user.

| Action | Signal Strength | Intended Influence on Future Recommendations |
|---|---|---|
| **Saved listing** | Strong positive | The saved listing becomes part of the similarity baseline. Its feature vector is added to the user's engaged listing set. Its amenity flags increment the behavioral amenity preference counters. Its price becomes an anchor point for the effective price band. |
| **Viewed listing (detail page)** | Moderate positive | The viewed listing is added to the user's engaged listing set with a 50% weight (vs. 100% for saved listings). View count and recency affect decay (Section 4.5). |
| **Hidden recommendation** | Strong negative | The listing is suppressed from all future recommendation runs for this user. Its subdivision and subdivision-type are noted as negative signals. If 3+ listings from the same subdivision are hidden, the subdivision is temporarily suppressed. |
| **Dismissed recommendation** | Moderate negative | The listing is not shown again in the current session. It may be surfaced in a future session if the recommendation score increases (e.g., price reduction). The dismissal is recorded in `user_recommendation_feedback` for analysis. |
| **Scheduled showing** | Very strong positive | A showing request on a recommendation card is the highest-intent behavioral signal. The listing's full feature vector is weighted at 200% in the similarity baseline. Its recommendation type is flagged as high-conversion for future A/B testing. |
| **Submitted offer** | Very strong positive (terminal) | An offer on a recommendation card closes the recommendation loop for that listing. The listing is removed from future recommendations (it is now under offer). All listings in the same subdivision with a similar feature profile receive a temporary 5-point amenity overlap score boost — the offer revealed a strong preference signal. |

### 13.2 — Signal Decay

Behavioral signals are not permanent. Older signals decay to prevent historical preferences from dominating current recommendations.

| Signal Age | Signal Weight |
|---|---|
| 0–7 days | 100% |
| 8–30 days | 75% |
| 31–90 days | 50% |
| 91–180 days | 25% |
| > 180 days | Signal is archived; excluded from active scoring |

Signal decay ensures that a saved listing from 6 months ago (which may no longer reflect the user's current preferences) does not anchor the recommendation engine to outdated criteria.

### 13.3 — Feedback Loop Integrity Constraints

The feedback loop must not create feedback that could encode, amplify, or surface fair-housing-sensitive patterns. Specifically:

- The feedback loop must operate exclusively on the non-Tier-6 feature dimensions: price, size, amenities, property type, and geography.
- Feedback signals must never adjust scoring on the basis of neighborhood demographic composition, school district quality (as a proxy for demographics), or any signal that could function as a Fair Housing Act proxy.
- The `SeniorCommunityYN` hard suppression (Section 10.4) must remain a hard gate regardless of feedback signals — a user's "saved listing" action on a senior community listing must not remove the eligibility suppression for subsequent runs.
- All feedback records in `user_recommendation_feedback` are per-user and must not be aggregated across users in any way that would allow demographic inference.

### 13.4 — Feedback Loop and Cache Invalidation

When a feedback event is recorded (any action in the table from Section 13.1), the recommendation cache for that user is invalidated on the next page load. This ensures the user sees updated recommendations that reflect their action immediately rather than waiting for the 4-hour TTL to expire. The `recommendation_runs` table records the trigger reason as `feedback_event` when this invalidation path fires.

### 13.5 — Feedback Loop Is Documentation Only

The feedback loop architecture described in this section is a design record. No tables, services, event listeners, or cache invalidation hooks implementing this behavior are created by this document. The feedback loop is the Phase 3 deliverable (Section 12, Phase 3) and the Phase 4 AI personalization input (Section 12, Phase 4). Implementation teams should treat this section as the specification they build against, not as code that already exists.

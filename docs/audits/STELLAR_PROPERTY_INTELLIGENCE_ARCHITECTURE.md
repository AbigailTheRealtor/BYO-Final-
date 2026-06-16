# Stellar Property Intelligence Architecture

> Document type: Architecture design record  
> Date: 2026-06-16  
> Source audits: `docs/audits/STELLAR_BRIDGE_FIELD_AUDIT.md` · `docs/audits/STELLAR_MATCHING_READINESS_AUDIT.md` · `docs/audits/STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` · `docs/audits/STELLAR_ALERT_SYSTEM_ARCHITECTURE.md`  
> Scope: Documentation only — no code changes, no migrations, no API integrations

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Data Sources](#2-data-sources)
3. [Intelligence Signals](#3-intelligence-signals)
4. [Field Mapping Table](#4-field-mapping-table)
5. [Buyer Use Cases](#5-buyer-use-cases)
6. [Seller and Agent Use Cases](#6-seller-and-agent-use-cases)
7. [Matching Integration](#7-matching-integration)
8. [Compliance and Privacy](#8-compliance-and-privacy)
9. [Storage Strategy](#9-storage-strategy)
10. [Implementation Roadmap](#10-implementation-roadmap)

---

## 1. Executive Summary

### Purpose

The Stellar Property Intelligence Layer is a planned data enrichment system that sits above the existing Stellar MLS feed to give buyers, tenants, agents, and the platform itself a richer, more actionable view of any property. Where the current MLS feed provides listing attributes — price, beds, baths, square footage, amenities — the intelligence layer adds context that the MLS cannot: who owns the property, how long they have owned it, how much equity they hold, whether they are likely to sell, whether they are in financial distress, and what signals suggest the property would be a strong match for an investor, an owner-occupant, or a specific buyer persona.

The intelligence layer is not a replacement for the MLS feed. It is an enrichment overlay that combines MLS data, public county records, deed and sale history, mortgage and tax data, flood and FEMA risk data, and eventually brokerage partnership APIs into a unified property intelligence profile. Each profile surfaces actionable signals — structured scores and flags — that the matching engine, the Ask AI system, and human agents can use to prioritize, explain, and close transactions.

### Strategic Value

| Value Driver | Description |
|---|---|
| Buyer personalization | Investors can filter for high-equity absentee owners; relocation buyers can identify move-in-ready homes; land buyers can assess redevelopment potential |
| Seller lead generation | Agents can identify off-market owners who are likely to sell based on ownership duration, equity, and life-event signals |
| CMA enrichment | Pricing conversations are grounded in actual sale history and tax assessed values, not just comparable list prices |
| Platform differentiation | Buyers and agents on this platform receive intelligence that public portals do not surface — creating a retention moat and a premium positioning argument |
| Matching quality | The matching engine gains additional dimensions that go beyond MLS attributes: ownership type, financial distress signals, and investor-income potential become match score factors |

### Scope of This Document

This document defines the architecture, data sources, signal definitions, field mapping, use cases, compliance rules, storage recommendations, and phased implementation roadmap for the property intelligence layer. No code is written, no migrations are run, and no API integrations are built as part of this document. This is the design specification that will guide future implementation work.

### Relationship to Existing Architecture

The intelligence layer builds directly on the `bridge_properties` table and the Stellar Bridge API field inventory documented in `STELLAR_BRIDGE_FIELD_AUDIT.md`. The matching readiness gaps identified in `STELLAR_MATCHING_READINESS_AUDIT.md` — school district quality, flood zone risk, ownership type, investor income potential — are the primary gaps that the intelligence layer is designed to close. The native column promotion strategy in `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` defines the MLS-side foundation; this document defines the public-records and derived-signal layer on top of it.

---

## 2. Data Sources

The intelligence layer draws from five distinct source categories. Each category provides a different class of signals. No single source is sufficient on its own — the value of the layer comes from combining signals across sources into a unified property profile.

### 2.1 — Stellar MLS Fields (Bridge API)

**What it provides:** Active and historical listing data including price, property characteristics, listing status, agent information, and a subset of investment and rental fields.

**Current availability:** 553 unique fields documented in `STELLAR_BRIDGE_FIELD_AUDIT.md`. 217 are reliably populated (≥50% of records). 66 are classified as Tier 1 Core Matching fields.

**Intelligence-layer contribution:**
- List price and price history (`ListPrice`, `OriginalListPrice`, `PreviousListPrice`, `ClosePrice`) establish market value and negotiation range
- Ownership type (`Ownership` field: "Fee Simple", "Condominium", "Cooperative") identifies holding structure
- Financial signals (`TaxAnnualAmount`, `AssociationFee`, `STELLAR_CDDYN`, `CapRate`, `GrossScheduledIncome`) contribute to cost-of-ownership and investment yield calculations
- Listing history signals (`DaysOnMarket`, `CumulativeDaysOnMarket`, `MlsStatus`, `PreviousListPrice`) reveal seller motivation
- Flood zone code (`STELLAR_FloodZoneCode`) provides raw FEMA zone data that must be translated to a risk tier

**Limitations:** The MLS does not expose ownership identity, equity position, or mortgage debt. It does not reveal whether the owner is the occupant or a remote investor. It does not provide deed history or tax delinquency data. These gaps require public-records enrichment.

---

### 2.2 — Bridge Interactive Public Records / Property Records API

**What it provides:** Bridge Interactive's property records product supplements the MLS feed with ownership data, parcel details, and historical transactions sourced from county assessor and recorder databases. It is the most natural enrichment source given that the platform already uses the Bridge API for the Stellar MLS feed.

**Data categories available:**
- **Owner name and mailing address:** Legal owner of record and the address where property tax bills and legal notices are sent. Owner mailing address that differs from the property address is a primary signal for absentee ownership.
- **Owner type classification:** Individual, LLC, corporation, trust, government. Owner type is the first-order signal for investor vs. owner-occupant distinction.
- **Parcel identification:** Parcel number, legal description, lot dimensions, zoning classification, and land use code — data that the MLS only partially exposes.
- **Assessment history:** County assessed value and assessed value history. Assessed value diverging from list price is a tax-burden signal.
- **Property characteristics supplement:** Roof type, foundation type, exterior construction, heating/cooling system age — property condition details that the MLS omits.

**Integration pattern:** Query by parcel number (already available in the MLS as `ParcelNumber`) or by geocoded address. Returns structured JSON with a consistent field schema. Rate-limited; designed for batch enrichment, not real-time per-request lookups.

**Limitations:** Bridge public records coverage varies by county. Florida counties with strong assessor digitization (Orange, Hillsborough, Pinellas, Miami-Dade) have good coverage. Rural counties and recently annexed parcels may have stale or incomplete data.

---

### 2.3 — County Tax Records

**What it provides:** Annual tax assessment data maintained by county property appraisers. This is authoritative for assessed value, millage rate, tax amount due, tax exemptions, and CDD/special assessment amounts.

**Data categories available:**
- **Assessed value (just value):** The county's current year estimate of the property's fair market value. Divergence from list price reveals over-/under-assessment and tax appeal risk.
- **Land value vs. improvement value split:** Critical for investment and redevelopment analysis — high land-to-improvement ratio signals teardown or development potential.
- **Homestead exemption status:** `STELLAR_HomesteadYN` is already in the MLS (25/25 populated in current sample). County records provide the exact exemption amount and status.
- **Other exemptions:** Veteran, senior, disability, and agricultural exemptions reduce taxable value. Buyers need to know whether the seller's effective tax burden is transferable.
- **CDD/special assessment amounts:** `STELLAR_CDDYN` flags the existence of a Community Development District but does not surface the annual assessment amount. County records provide this amount, which is critical for Florida buyers.
- **Tax delinquency status:** Whether the property has unpaid taxes or is in the tax deed sale pipeline. A critical distress signal.
- **Millage rate:** `STELLAR_MillageRate` is 19/25 in the current MLS sample. County records are authoritative for the exact rate and its breakdown by taxing authority.

**Integration pattern:** Most Florida counties expose their property appraiser data via public APIs or bulk download files. Orange County, Hillsborough County, and Miami-Dade County all offer REST endpoints. A batch import job runs nightly, keyed by parcel number.

**Limitations:** Assessed values lag market values — Florida's Save Our Homes cap limits annual assessment increases on homesteaded properties to 3% or the CPI increase, whichever is lower. A long-tenured homestead owner may have an assessed value far below market. This creates a positive equity signal (large hidden equity) but also means the tax burden will reset at market value for a new buyer — an important disclosure for buyers purchasing from long-tenured sellers.

---

### 2.4 — Deed and Sale History

**What it provides:** A chronological record of every ownership transfer, mortgage origination, and encumbrance on a parcel. Sourced from county recorder/clerk offices via Bridge public records or direct county API.

**Data categories available:**
- **Last sale date and price:** When the property last transferred and for how much. Enables years-owned calculation and estimated equity derivation.
- **Sale history timeline:** All prior sales. Multiple transactions in short succession suggest a flip; long holding periods suggest an owner with significant accrued equity.
- **Deed type:** Warranty deed, quitclaim deed, trustee's deed, sheriff's deed. Quitclaim and sheriff's deeds are distress-flag signals.
- **Mortgage origination:** Date, amount, lender, and loan type (conventional, FHA, VA, reverse mortgage) at time of purchase and for any subsequent refinances.
- **Current mortgage balance (estimated):** Original loan amount minus estimated principal payments based on standard amortization for the origination date, rate environment, and loan term. This is an estimate, not a verified figure — should be labeled as such.
- **Liens and encumbrances:** Recorded mechanic's liens, HOA liens, lis pendens filings. Each is a distress or complexity signal.

**Integration pattern:** Bridge public records deed endpoint, keyed by parcel number or property address. May require supplemental lookup from county clerk recording systems for recent transactions not yet indexed by Bridge.

**Limitations:** Private-label loans and seller-financed transactions may not appear in public records. Mortgage balance is an estimate — actual payoff amount varies based on prepayments, escrow adjustments, and lender-specific terms. Lis pendens data is not consistently indexed across Florida counties.

---

### 2.5 — Owner Mailing Address (Absentee Signal Source)

**What it provides:** The mailing address on file with the county property appraiser for the property's current owner. Comparing the mailing address to the property address is the primary method for identifying absentee owners.

**Signal derivation:**
- If the mailing address matches the property address: presumed owner-occupant
- If the mailing address is a different Florida address: local investor or accidental landlord (may respond to outreach)
- If the mailing address is out of state: absentee investor; higher likelihood of willingness to sell; higher likelihood of professional property management
- If the mailing address is a PO Box, LLC address, or management company address: institutional investor or portfolio landlord

**Data source:** County property appraiser records. Usually bundled with the assessed value and ownership data in the same API call. Requires address normalization before comparison — the county may store "19323 FALLGLO DR" while the MLS stores "19323 Fallglo Drive"; both must normalize to a canonical form before the comparison is made.

**Privacy note:** Owner mailing addresses are part of the public record in Florida and are legally accessible. However, the platform must not expose individual owner PII in any buyer-facing, tenant-facing, or public-facing interface. The mailing address is used internally to derive the absentee flag; the flag is what is surfaced, not the address itself. See Section 8 for full compliance rules.

---

### 2.6 — Mortgage and Equity Signals

**What it provides:** Derived equity estimates combining deed/sale history, recorded mortgage data, and current assessed or market value. No direct API provides real-time mortgage balance — all equity figures are estimated from public records.

**Derivation approach:**
1. Obtain last sale price and date from deed records
2. Obtain recorded mortgage amount(s) from county recorder
3. Apply standard amortization to estimate current principal balance based on origination date and prevailing rates for that vintage
4. Subtract estimated current balance from current assessed value (or current list price if listed) to produce estimated equity
5. Flag if estimated loan-to-value ratio exceeds 90% (potential underwater scenario) or is below 30% (high-equity target)

**Data sources feeding this signal:** Deed records (Section 2.4), county tax records (Section 2.3), and optionally the MLS `ClosePrice` and `CloseDate` fields which provide verified prior sale data for properties that last transacted while MLS-listed.

**Limitations:** Refinances, HELOCs, reverse mortgages, and private-lender seconds may not appear in county records promptly. Estimated equity should always be presented with an uncertainty label. The platform should never present an equity estimate as a verified figure.

---

### 2.7 — Flood and FEMA Data

**What it provides:** FEMA National Flood Insurance Program (NFIP) flood zone classification and the associated risk level for each parcel.

**MLS availability:** `STELLAR_FloodZoneCode` is populated for 17/25 records in the current Stellar sample, using FEMA zone codes (X, AE, VE, etc.). `STELLAR_FloodZoneDate` and `STELLAR_FloodZonePanel` exist in the schema but are 0/25 in the current sample. The raw FEMA zone code is not human-readable without translation.

**Intelligence layer contribution:**
- **Zone-to-risk-tier translation:** FEMA zone codes must be translated to a standardized risk tier before they can be used in matching or surfaced in Ask AI. The standard translation:
  - Zone X (or X500): Minimal / low risk
  - Zone X (shaded / AH / AO): Moderate risk
  - Zone A (without base flood elevation), Zone AE, Zone A1-30: High risk — in the Special Flood Hazard Area (SFHA); flood insurance required for federally-backed mortgages
  - Zone V, VE, V1-30: Very high risk — coastal, wave action; highest insurance premiums
- **Flood insurance cost signal:** High and very-high risk zones carry mandatory flood insurance requirements and significantly elevated premiums. The insurance cost is a hidden monthly ownership expense that buyers must factor into true cost of ownership.
- **FEMA map panel and date:** Identifies the FIRM (Flood Insurance Rate Map) version in effect. Properties near zone boundaries may benefit from an elevation certificate or LOMA (Letter of Map Amendment) to reclassify to a lower risk zone.

**External source required:** The FEMA Flood Map Service Center provides the National Flood Hazard Layer (NFHL) as a free REST API. For properties where `STELLAR_FloodZoneCode` is missing (8/25 in current sample), the FEMA API can derive the flood zone from latitude/longitude coordinates.

---

## 3. Intelligence Signals

The intelligence layer derives twelve primary signals from the data sources defined in Section 2. Each signal is a structured, actionable value — a flag, a tier, a score, or a date — that the matching engine, Ask AI, and agent tools can consume directly without processing raw source data.

### Signal 1: Ownership Type

**Definition:** The legal classification of who owns the property. Distinguishes between owner-occupants, individual investors, LLCs, corporations, trusts, and government entities.

**Values:** `owner_occupant` · `individual_investor` · `llc` · `corporate` · `trust` · `government` · `unknown`

**Derivation:**
1. Pull owner name from county property appraiser records
2. Apply pattern matching: names containing "LLC", "INC", "CORP", "TRUST", "HOLDINGS", "PROPERTIES", or similar suffixes map to the corresponding entity type
3. If the owner name appears to be an individual (two words, no entity suffix) and the mailing address matches the property address: `owner_occupant`
4. If the owner name appears to be an individual but the mailing address differs: `individual_investor`
5. If the homestead exemption is active on the parcel: strong corroborating signal for `owner_occupant` (homestead is only available to primary residents)

**Use:** Hard filter for investor buyer matching (investors want non-owner-occupant sellers). Seller propensity scoring (LLC and corporate owners are more likely to be motivated to sell to institutional or large-scale buyers).

---

### Signal 2: Absentee Owner Flag

**Definition:** A boolean flag indicating that the property's owner of record does not appear to live at the property.

**Values:** `true` (absentee) · `false` (presumed occupant) · `null` (unable to determine)

**Derivation:** Compare the owner mailing address from county records against the property address. A match (after normalization) sets the flag to `false`. A mismatch sets it to `true`. If the owner mailing address is unavailable, the flag is `null`.

**Use:** Absentee owners are a primary target segment for off-market seller outreach. They are more likely to be motivated sellers (landlords tired of managing a property, out-of-state heirs, accidental landlords who would rather sell). The flag is a key input for the Seller Propensity Score (Signal 8).

---

### Signal 3: Years Owned

**Definition:** The number of years since the current owner acquired the property, calculated from the last deed transfer date.

**Values:** A floating-point number of years (e.g., 8.3). Null if last deed date is unavailable.

**Derivation:** `(current_date - last_deed_date) / 365.25`

**Use:**
- Long tenure (8+ years in a rising market) is a strong equity accumulation signal
- Very long tenure (15+ years) combined with absentee ownership suggests an estate or senior owner with high propensity to sell
- Short tenure (< 2 years) combined with significant price increase indicates a flip or opportunistic resale
- Median years owned by market area provides neighborhood stability context for buyers

---

### Signal 4: Last Sale Date and Price

**Definition:** The date and price of the most recent ownership transfer for the property.

**Values:** A date and a dollar amount. Sourced from deed records. May also be available from MLS `CloseDate` and `ClosePrice` for properties that transacted while MLS-listed.

**Derivation:** Pull from deed records keyed by parcel number. MLS `CloseDate`/`ClosePrice` can supplement where deed records lag. Always prefer deed records as authoritative.

**Use:**
- Establishes the cost basis for equity estimation
- Recency of sale identifies recently flipped properties (potential buyer caution re: inflated basis)
- CMA context for agents: "This home last sold for $330,000 in June 2014" grounds pricing conversations in objective data

---

### Signal 5: Estimated Equity

**Definition:** The estimated difference between the current market value (or list price) and the outstanding mortgage balance. Expressed as a dollar amount and as a loan-to-value (LTV) ratio.

**Values:** Dollar amount (integer) and LTV percentage (float). Both marked as estimated.

**Derivation:** See Section 2.6 for full methodology. In brief: subtract estimated current principal balance (derived from amortization on recorded mortgage data) from current assessed value or list price.

**Equity tier classification:**
- **Equity-rich:** LTV < 30% — owner can sell at market and walk away with significant cash; motivated seller candidate
- **Standard equity:** LTV 30–70% — normal transaction, standard closing flexibility
- **Low equity:** LTV 70–90% — minimal seller flexibility; may be constrained on price reduction
- **At-risk:** LTV > 90% — potential underwater scenario; short sale or lender negotiation may be involved

**Use:** Core signal for investor buyer matching (high-equity properties offer room for negotiation). Seller propensity input (equity-rich absentee owners are prime outreach targets). Pricing conversation grounding for agents.

---

### Signal 6: Annual Tax Burden

**Definition:** The total annual property tax obligation for the parcel, including base millage, CDD special assessments, and any other annual fees levied against the parcel.

**Values:** Dollar amount (integer). Broken down into components where available: `base_tax`, `cdd_amount`, `other_assessments`.

**Derivation:** Pull from county tax records. `TaxAnnualAmount` is available in the MLS (Tier 1, promoted to native column in Phase 1) for records where populated. County records are authoritative and may include CDD amounts that the MLS does not separately itemize.

**Use:**
- True cost of ownership calculation for buyers: monthly tax equivalent is a hidden recurring cost
- Investment yield calculation: tax burden is the largest fixed cost component for rental properties
- Pricing negotiation: high tax burden relative to assessed value signals possible over-assessment and tax appeal opportunity

---

### Signal 7: Investor-Owned Likelihood Score

**Definition:** A 0–100 score estimating the probability that the property is investor-owned rather than owner-occupied, based on a combination of available signals.

**Scoring inputs:**
| Signal | Weight | Direction |
|---|---|---|
| Ownership type = LLC, corporate, or trust | +40 | Strong investor indicator |
| Absentee owner flag = true | +25 | Moderate investor indicator |
| No homestead exemption on file | +15 | Occupancy corroboration |
| Multiple properties under same owner name/entity (county records) | +10 | Portfolio investor |
| MLS `OccupantType` = "Tenant" | +10 | Confirmed investment property |
| MLS `STELLAR_ForLeaseYN` = true (current or historical) | +5 | Used as rental |
| Homestead exemption active | −30 | Strong owner-occupant indicator |

**Use:** Buyer matching (investor buyers specifically seek investor-owned properties for portfolio acquisition). Seller propensity outreach (high-score properties owned by absentee LLCs are the highest-value cold outreach targets). Listing agents can use the score to frame their listing pitch for investor-facing marketing.

---

### Signal 8: Seller Propensity Score

**Definition:** A 0–100 score estimating the probability that the current owner would be receptive to selling in the next 12 months, based on ownership signals and life-event proxies.

**Scoring inputs:**
| Signal | Weight | Direction |
|---|---|---|
| Absentee owner, out-of-state | +30 | High motivation signal |
| Years owned ≥ 10 in rising market | +20 | Equity maturity + life-stage signal |
| Equity-rich tier (LTV < 30%) | +20 | Financial readiness to transact |
| LLC or corporate owner (not institutional REIT) | +15 | Portfolio optimization motivation |
| Property has not been listed for 5+ years | +10 | Pent-up inventory signal |
| Tax delinquency flag | +15 | Financial distress motivation |
| Any recorded lien | +10 | Complexity motivation to exit |
| Very recent acquisition (< 18 months) | −20 | Holding period investment intent |
| Homestead exemption active | −15 | Primary residence reduces likelihood |

**Use:** The seller propensity score is the primary input for the off-market outreach workflow. Agents can filter properties by score to identify the highest-ROI targets for direct mail, phone, or digital outreach campaigns. Not exposed to buyers directly — this is an agent-facing and platform-facing signal.

---

### Signal 9: Distressed Property Indicators

**Definition:** A set of boolean flags indicating specific conditions that suggest the property owner may be under financial or legal stress.

**Individual flags:**
- `tax_delinquent`: Unpaid property taxes on file with the county tax collector
- `lis_pendens_active`: An active lis pendens filing (notice of pending legal action, typically a foreclosure filing) recorded against the parcel
- `hoa_lien_active`: An active HOA lien filed for unpaid assessments
- `mechanic_lien_active`: An active mechanic's lien filed by a contractor
- `sheriff_deed_in_history`: A sheriff's deed appears in the property's ownership history (indicates prior forced sale)

**Derivation:** Pull from county recorder/clerk records via parcel number. May require separate API calls to the county circuit court clerk for lis pendens data.

**Use:**
- Investors seeking distressed properties can filter by these flags
- Buyer agents must disclose known distress conditions that materially affect title
- Platform compliance: the existence of these flags must not be used in marketing copy to individual owners (see Section 8). They are internal intelligence signals only.

---

### Signal 10: Rental and Income Potential

**Definition:** A structured estimate of the gross annual rental income the property could generate if leased at market rates, plus a gross rent multiplier (GRM) signal.

**Components:**
- `estimated_monthly_rent`: Estimated market rent for the property based on comparable active rental listings (from the Stellar rental feed, once active) matched by beds/baths/zip/property type
- `estimated_gross_annual_income`: `estimated_monthly_rent × 12`
- `gross_rent_multiplier`: `list_price / estimated_gross_annual_income` — a quick-and-dirty yield proxy; lower GRM = better yield
- `cap_rate_estimate`: `(estimated_gross_annual_income × 0.6) / list_price` — using a 40% expense ratio as a conservative default; overridden by actual expense data from MLS if `STELLAR_AnnualExpenses` is populated

**Data sources:** Stellar MLS rental feed (once active) for comparable rent data. MLS `CapRate`, `GrossScheduledIncome`, and `STELLAR_AnnualExpenses` for investment-listed properties where populated.

**Use:** Investor buyer matching (buyers can filter for cap rate range and GRM threshold). Landlord agent CMA tool (compare a property's income potential to comparable rentals). Seller/listing agent pitch: "This property could generate $X/month in rental income if held as an investment."

---

### Signal 11: Flood Risk Tier

**Definition:** A standardized human-readable risk tier derived from the FEMA flood zone code.

**Values:** `minimal` · `moderate` · `high` · `very_high` · `unknown`

**Derivation:** See Section 2.7 for the zone-code-to-tier mapping. Zone code sourced from `STELLAR_FloodZoneCode` (17/25 in current MLS sample) or from the FEMA NFHL API using property latitude/longitude for records where the MLS code is absent.

**Use:** Buyer filtering (many buyers explicitly exclude high and very-high risk flood zones). Insurance cost signal in the Ask AI true-cost-of-ownership answer chain. Matching engine hard filter option for buyers who have indicated flood zone sensitivity.

---

### Signal 12: Redevelopment Potential

**Definition:** A boolean flag and supporting context indicating whether the property's land characteristics suggest value as a teardown, infill, or redevelopment opportunity.

**Derivation:**
- Land-to-improvement value ratio > 60% from county assessment records (land is worth more than the structure)
- Zoning classification allows multi-family, commercial, or mixed-use development
- Lot size exceeds the typical minimum for the current zoning for a density increase
- Existing structure age > 50 years combined with condition flags indicating deferred maintenance

**Corroborating MLS signals:** `Zoning`, `ZoningDescription`, `LotSizeSquareFeet`, `YearBuilt`, `PropertyCondition`, `CurrentUse`, `STELLAR_FutureLandUse`

**Use:** Land buyer matching. Commercial and mixed-use investor matching. Agent pitch for sellers whose highest-and-best-use is not the existing residential structure.

---

## 4. Field Mapping Table

This table maps each of the twelve intelligence signals to its primary data source, the specific field or computation involved, and the fallback if the primary source is unavailable.

| Signal | Primary Source | Primary Field(s) | External API Required? | Fallback | Notes |
|---|---|---|---|---|---|
| **Ownership Type** | County property appraiser | Owner name (Bridge public records) | No — Bridge property records | `unknown` if owner name unavailable | Apply entity-suffix pattern matching |
| **Absentee Owner Flag** | County property appraiser | Owner mailing address vs. property address | No — Bridge property records | `null` if mailing address unavailable | Requires address normalization |
| **Years Owned** | County deed records | Last deed transfer date (Bridge public records) | No — Bridge property records | `null` if no deed date | `(today - last_deed_date) / 365.25` |
| **Last Sale Date** | County deed records | Last deed transfer date and price | No — Bridge public records | MLS `CloseDate` for MLS-listed transactions | Deed records authoritative over MLS |
| **Last Sale Price** | County deed records | Last deed transfer price | No — Bridge public records | MLS `ClosePrice` | Deed records authoritative |
| **Estimated Equity (amount)** | Computed | Assessed value − estimated mortgage balance | No | `null` if deed mortgage data missing | Estimate — must be labeled as such |
| **Estimated LTV** | Computed | Estimated mortgage balance / assessed value | No | `null` if insufficient data | Estimate — must be labeled as such |
| **Annual Tax Burden** | County tax records | Assessed amount + CDD + special assessments | No — county tax API | MLS `TaxAnnualAmount` (native column in Phase 1) | County records more complete than MLS |
| **Investor-Owned Score** | Computed | Composite of ownership type, absentee flag, exemption, occupant type | No | Partial score from available inputs | 0–100 scale |
| **Seller Propensity Score** | Computed | Composite of absentee flag, years owned, equity tier, distress flags | No | Partial score from available inputs | 0–100 scale; agent-facing only |
| **Tax Delinquent Flag** | County tax collector records | Delinquent tax roll lookup | No — county tax API | `null` if data unavailable | Boolean |
| **Lis Pendens Flag** | County circuit court clerk | Lis pendens recording index | Yes — county clerk API (not Bridge) | `null` if data unavailable | Boolean; requires parcel/owner lookup |
| **HOA Lien Flag** | County recorder | Recorded lien index | No — Bridge property records | `null` if data unavailable | Boolean |
| **Mechanic Lien Flag** | County recorder | Recorded lien index | No — Bridge property records | `null` if data unavailable | Boolean |
| **Estimated Monthly Rent** | Stellar MLS rental feed | Comparable active rental listings | No — same Bridge API | `null` until rental feed is active | Requires Phase 2R rental feed |
| **Gross Rent Multiplier** | Computed | List price / estimated gross annual rent | No | `null` if monthly rent estimate unavailable | Approximation |
| **Cap Rate Estimate** | Computed | (Annual rent × 0.6) / list price; overridden by MLS `CapRate` | No | `null` if rental estimate unavailable | 40% expense ratio default |
| **Flood Risk Tier** | STELLAR_FloodZoneCode (MLS) | `STELLAR_FloodZoneCode` translated to tier | Yes — FEMA NFHL API for missing MLS records | `unknown` if zone code absent and coordinates unavailable | FEMA API uses lat/long |
| **Redevelopment Potential Flag** | County assessment + MLS | Land-to-improvement ratio, zoning, lot size, structure age | No | Partial signal from available inputs | Boolean + supporting context |

---

## 5. Buyer Use Cases

### 5.1 — Investor Buyers

**Profile:** Buyers seeking properties to hold as rentals, flip, or add to a portfolio. Motivated by yield, cash flow, and appreciation potential. May be targeting specific ownership structures (e.g., wanting to buy from an LLC seller for ease of negotiation) or geographic concentrations.

**Intelligence layer benefits:**

**Cap rate and rental yield pre-filtering:** Once the Stellar rental feed is active, investor buyers can filter their criteria not just by list price and beds/baths but by estimated cap rate range. A buyer who requires a minimum 5% cap rate sees only properties that meet that threshold — a capability not available on any public portal.

**High-equity absentee owner identification:** The Investor-Owned Likelihood Score and Estimated Equity signal together identify properties where the seller is likely an unmotivated long-distance landlord with significant financial flexibility. Investor buyers benefit from knowing that the seller can negotiate without being constrained by a short payoff.

**Distress flags for opportunistic acquisition:** Investor buyers who specifically target distressed properties can filter by `tax_delinquent`, `lis_pendens_active`, and `hoa_lien_active` flags to find properties where the owner may need to move quickly.

**Portfolio concentration maps:** If an investor buyer is already acquiring in a specific neighborhood or zip code, the platform can use the Investor-Owned Likelihood Score to identify which other properties in that area are also investor-owned — suggesting potential portfolio acquisition targets or competition context.

**GRM benchmarking:** The Gross Rent Multiplier provides a fast comparison metric across markets. An investor comparing properties across different Florida markets can use GRM to normalize yield expectations before doing a full underwriting analysis.

---

### 5.2 — Relocation Buyers

**Profile:** Buyers moving from another state or metro area who are unfamiliar with local market dynamics, school districts, neighborhood stability, and true cost of ownership. Highly reliant on platform guidance because they cannot do drive-by research easily.

**Intelligence layer benefits:**

**True cost of ownership transparency:** Relocation buyers consistently underestimate Florida-specific costs: property taxes, HOA fees, CDD assessments, and flood insurance. The intelligence layer surfaces all of these costs in a unified monthly cost estimate alongside the mortgage payment. A buyer comparing a $400,000 home in Zone AE with a $4,000 CDD assessment and a $3,200/year flood insurance requirement against a comparable property in Zone X with no CDD makes a fundamentally different financial decision.

**School district quality context:** The intelligence layer will eventually incorporate a geocode-to-school-district lookup to supplement the inconsistently-normalized MLS school name fields. Until that integration is built, the raw `ElementarySchool`, `MiddleOrJuniorSchool`, and `HighSchool` fields are surfaced as Ask AI context.

**Neighborhood stability signals:** Years-owned distribution within a neighborhood (median tenure of neighboring property owners, available via batch intelligence layer queries across parcels in the same zip code) provides a stability signal. High median tenure suggests a stable, established neighborhood. Rapid turnover suggests a transitional area.

**Flood and disaster risk visualization:** Relocation buyers from non-coastal states often have no intuition for flood zone risk. The Flood Risk Tier signal translates FEMA zone codes into plain-language risk labels and surfaces the associated flood insurance cost estimate in matching results and Ask AI answers.

---

### 5.3 — Waterfront Buyers

**Profile:** Buyers specifically seeking water access, water views, or waterfront property. A high-premium segment in Florida where the difference between "waterfront" (direct access) and "water view" (visible but no access) represents a significant price gap.

**Intelligence layer benefits:**

**Water characteristic disambiguation:** The MLS provides `WaterfrontYN` (boolean), `STELLAR_WaterViewYN` (boolean), and `STELLAR_WaterView` (array: Pond, Lake, Bay, Gulf, Ocean, Canal, Intracoastal). The intelligence layer enriches this with dock details from `STELLAR_DockYN`, `STELLAR_DockDescrip`, `STELLAR_DockDimensions`, and `STELLAR_DockLiftCap` (currently 0/25 in the sale sample but populated in coastal and waterfront feeds). These fields allow waterfront buyers to filter not just for "waterfront" but for specific access types: navigable water with boat lift, gulf-front with beach access, canal with fixed bridge height, etc.

**Flood risk correlation:** Waterfront properties inherently carry higher flood risk. The intelligence layer pairs the waterfront flag with the Flood Risk Tier to give buyers who want waterfront an immediate picture of the insurance cost implications. "Waterfront" + Zone VE is a very different proposition from "Waterfront" + Zone X.

**Historical waterfront premium analysis:** Using deed sale history, the intelligence layer can contextualize whether the current list price premium for waterfront is consistent with historical transacted premiums in the same area. If waterfront properties in the zip code historically sell at 40% premium and this listing is priced at 70% premium, that is a negotiation signal.

---

### 5.4 — Land Buyers

**Profile:** Buyers seeking vacant land or teardown opportunities for new construction, agricultural use, or investment holding. The matching engine must handle these buyers separately because MLS fields like `LivingArea` and `BedroomsTotal` are irrelevant — the relevant dimensions are lot size, zoning, utility availability, access, and development potential.

**Intelligence layer benefits:**

**Redevelopment Potential flag:** The Redevelopment Potential signal (Signal 12) explicitly identifies parcels where the land value exceeds the improvement value and where zoning or lot characteristics support a higher-density use. Land buyers who want to build rather than buy existing structures can filter directly on this signal.

**Zoning and future land use context:** `STELLAR_FutureLandUse` (22/25 populated) and `Zoning`/`ZoningDescription` provide current and planned use classification. The intelligence layer enriches this with county land use codes from public records to give a more complete picture of development constraints.

**Agricultural and equestrian features:** For rural land buyers, the MLS fields `HorseAmenities` and `STELLAR_NumberOfPaddocksPastures` are relevant but sparsely populated. County land use codes often identify agricultural classification, which affects both tax treatment and development options.

**Seller propensity for long-held vacant land:** Absentee owners of vacant land parcels who have held for 10+ years with no improvement activity are high-probability candidates for off-market acquisition. The Seller Propensity Score is particularly effective for this segment.

---

### 5.5 — Commercial and Income Property Buyers

**Profile:** Buyers seeking multi-family, mixed-use, or commercial properties for income production. This segment requires investment financial data (cap rate, gross income, net operating income, expense ratio) that the MLS provides inconsistently and that the intelligence layer must supplement.

**Intelligence layer benefits:**

**Investment financial signals:** The MLS `CapRate`, `GrossScheduledIncome`, `GrossIncome`, `STELLAR_AnnualNetIncome`, and `STELLAR_AnnualExpenses` fields are all 0/25 in the current residential-for-sale sample but will populate in the commercial and multi-family feed once enabled. The intelligence layer normalizes these into consistent computed signals (cap rate, GRM, NOI) regardless of which subset of fields the specific listing agent populated.

**Tenant-in-place signals:** `STELLAR_ExistLseTenantYN` (12/25 in current sample) indicates whether a tenant is currently occupying the property. For buyers who want turnkey income properties, an existing tenant with a lease in place is a premium feature. For buyers who want to redevelop or owner-occupy, an existing tenant is a complexity signal.

**Owner entity type for acquisition structure:** LLC-owned income properties are often more straightforward for entity-to-entity acquisition (asset purchase or LLC membership transfer) than individually-owned properties. The Ownership Type signal helps commercial buyers identify properties where the acquisition structure can be simplified.

---

## 6. Seller and Agent Use Cases

### 6.1 — Listing Opportunity Identification

**Use case:** An agent wants to identify potential sellers in their target market before those sellers have listed with any broker — capturing the listing relationship at the moment of motivation rather than competing in a listing presentation after the decision to sell has already been made.

**Intelligence layer support:**

The Seller Propensity Score (Signal 8) is the primary tool for this use case. The agent filters properties in their target market by:
- Score ≥ 70 (high propensity)
- Absentee owner flag = true
- Years owned ≥ 8
- No active MLS listing (not currently for sale)

The resulting set is the agent's prospecting list. Each property in the list has an estimated equity range and an ownership entity type that suggests how to structure the outreach (individual outreach vs. LLC-to-agent communication).

The platform does not expose individual owner PII to agents directly. Instead, the agent receives a property address and a set of aggregate intelligence signals. They then use the property address to look up the owner through publicly available county records on their own initiative. The platform facilitates the insight that the property is a promising outreach target; the agent executes the outreach using public information.

---

### 6.2 — Seller Lead Qualification

**Use case:** An agent receives an inbound inquiry from a potential seller who wants to know if "now is a good time to sell." The agent needs to quickly assess whether the seller's financial position supports a transaction at current market conditions.

**Intelligence layer support:**

The Estimated Equity signal (Signal 5) gives the agent immediate context: if the seller's estimated LTV is below 30%, they have substantial flexibility on price and terms. If the estimated LTV is above 85%, the agent needs to have a more careful conversation about net proceeds and closing costs.

The Annual Tax Burden signal (Signal 6) reveals the homestead tax reset implication: a seller who has owned for 15 years under Florida's Save Our Homes cap is paying a lower assessed value than market value. The buyer will pay the market-rate tax bill going forward — a data point the agent can use to counsel the buyer's agent on pricing expectations.

The Last Sale Price and Years Owned signals contextualize the seller's return: "You paid $185,000 in 2009 and could list at $520,000 today" is a compelling anchoring statement that is only possible with deed history data.

---

### 6.3 — CMA Context Enrichment

**Use case:** An agent is preparing a Comparative Market Analysis (CMA) for a listing presentation or a buyer offer. They want comparable sales data that goes beyond the MLS's 90-day active window.

**Intelligence layer support:**

The Last Sale Date and Last Sale Price fields for every property in the target neighborhood (not just MLS-listed properties) give the agent access to off-market transactions, builder closes, and estate sales that would not appear in a standard MLS CMA pull.

The Annual Tax Burden signal for each comparable property gives context for how the subject property's tax position compares to its peers — relevant when the subject property is significantly under-assessed or carries an unusual CDD obligation.

The Estimated Equity signal for comparable properties that are currently listed (not yet sold) helps the agent identify which active competitors have pricing flexibility (high equity, can negotiate) versus which are constrained (low equity, cannot reduce below a certain floor).

---

### 6.4 — Pricing Conversation Support

**Use case:** A seller wants to price their home above what the agent believes the market will support. The agent needs objective third-party data to ground the conversation without damaging the client relationship.

**Intelligence layer support:**

The historical sale price for the subject property from deed records is an objective fact, not an agent opinion. "This home last sold for $215,000 in 2016, and comparable homes in this zip code have appreciated 42% over that period — which suggests a market value in the $305,000 range. Pricing above $340,000 has limited support from the transaction data."

The Flood Risk Tier signal provides an objective basis for discussing price adjustments between Zone X and Zone AE properties that are otherwise comparable — flood insurance cost differentials are a legitimate value adjustment factor.

The Days on Market history for the subject property (from the MLS, accessible via `CumulativeDaysOnMarket` and `DaysOnMarket`) combined with prior list price history shows the seller what happened when a similar property sat on the market at an inflated price.

---

### 6.5 — Off-Market Outreach Campaigns

**Use case:** An agent or the platform's outreach system wants to send targeted, personalized communication to property owners who have not listed but who exhibit high seller propensity.

**Intelligence layer support:**

The Seller Propensity Score (Signal 8) provides the targeting filter. The Absentee Owner flag and Ownership Type signal determine the outreach channel and tone: an out-of-state LLC owner receives a different message than a local individual investor.

The Estimated Equity signal provides the outreach hook: "Your property at [address] has appreciated significantly since you acquired it in [year]. If you are considering options, we can discuss what a sale would net you at today's market values."

**Critical compliance rule:** The platform generates the targeted list internally using the intelligence signals. It does not expose owner PII (name, mailing address) in any agent-facing UI. The agent sees: property address, estimated equity tier, years owned, propensity score. The agent looks up the owner independently through public county records. The platform provides the intelligence; the agent provides the outreach. See Section 8 for full compliance rules.

---

## 7. Matching Integration

The intelligence signals defined in Section 3 interact with the matching engine across four categories: hard filters, soft scoring weights, recommendation explanations, and alerts. This section defines which signals belong in each category and why.

### 7.1 — Hard Filters

Hard filters are binary pass/fail gates. A property that fails a hard filter is excluded from the result set entirely, regardless of how well it scores on other dimensions. Hard filters should only be applied for signals that represent a true disqualifying condition — not a preference.

| Signal | Hard Filter Condition | Buyer Segment |
|---|---|---|
| Flood Risk Tier | User selects "Exclude high and very high flood risk" | Buyers who explicitly opt out of high-risk flood zones |
| Senior Community (`SeniorCommunityYN`) | User does not meet 55+ age requirement | Legal requirement — must exclude under-55 buyers from 55+ communities |
| Ownership Type = `government` | Buyer cannot purchase government-owned property through platform | All buyer segments |
| `lis_pendens_active = true` | Optional hard exclude for buyers who cannot wait for foreclosure resolution | Conservative buyers, financed buyers |
| `tax_delinquent = true` | Optional hard exclude for buyers who need clean title | Buyers who require lender financing (lenders will require clear tax certificate) |

---

### 7.2 — Soft Scoring Weights

Soft scoring weights adjust the match score up or down based on the degree to which an intelligence signal aligns with a buyer's stated or inferred preferences. They do not exclude properties — they rank them.

| Signal | Scoring Direction | Buyer Segment | Rationale |
|---|---|---|---|
| Cap Rate Estimate | Higher cap rate = higher score | Investor buyers | Core investment matching dimension |
| Gross Rent Multiplier | Lower GRM = higher score | Investor buyers | Yield indicator |
| Estimated Equity tier | Equity-rich = slight score boost | Investor buyers | Signals negotiation room |
| Flood Risk Tier | Higher risk = lower score | All buyers | Implied preference, adjustable by user |
| Investor-Owned Likelihood Score | High score = score boost | Investor buyers who prefer non-owner-occupant sellers | Enables targeted portfolio acquisition |
| Seller Propensity Score | High score = score boost | Agent's prospect lists only; not buyer-facing | Prioritizes listings where seller motivation is high |
| Redevelopment Potential flag | Positive flag = score boost | Land buyers, commercial buyers | Signals highest-and-best-use opportunity |
| Years Owned | 8+ years = slight score boost for investors | Investor buyers | Equity accumulation signal |
| Absentee Owner | `true` = score boost | Investor buyers | Seller availability and motivation |
| Distress flags (any) | Active flag = score boost | Distress-focused investors only | User must explicitly opt in to this weight |

---

### 7.3 — Recommendation Explanations

Recommendation explanations are the human-readable reasons displayed to a buyer explaining why a specific property was surfaced. They are critical for user trust — a matching engine that produces results without explanation feels opaque and generates disengagement.

Intelligence signals that can produce recommendation explanations:

| Signal | Explanation Template |
|---|---|
| Cap Rate | "Estimated cap rate of {X}% based on comparable rental listings in this zip code" |
| Flood Risk Tier | "Located in a {minimal/moderate/high} flood risk zone; {estimated flood insurance cost}" |
| Estimated Equity | "Seller estimated to hold significant equity — may have pricing flexibility" |
| Absentee Owner | "Owner does not appear to reside at this property — may be a motivated seller" |
| Seller Propensity Score (high) | "Signals suggest this owner may be open to an offer" (agent-facing view only) |
| Redevelopment Potential | "Lot characteristics and zoning may support higher-density development" |
| Tax Delinquency | "Property has unpaid taxes on record — consult your agent about title implications" |
| Years Owned (long) + High Equity | "Long-tenured ownership with significant estimated equity" |

**Compliance rule for explanations:** Explanations must describe property characteristics, not owner characteristics. Saying "This property may have a motivated seller" is acceptable. Saying "The LLC that owns this property is based in New York and has not visited in years" is not.

---

### 7.4 — Alerts

Intelligence signals generate a new class of alerts that goes beyond the MLS-event-driven alerts defined in `STELLAR_ALERT_SYSTEM_ARCHITECTURE.md`.

| Alert Type | Trigger Signal | Recipient |
|---|---|---|
| **High propensity property alert** | Seller Propensity Score crosses threshold (e.g., score rises from 55 to 75 due to a tax delinquency filing) | Agent who has saved this property to a watchlist |
| **New distress signal** | `tax_delinquent`, `lis_pendens_active`, or `hoa_lien_active` changes from `false` to `true` on a watchlisted property | Agent or investor buyer with saved criteria |
| **Equity milestone** | Estimated LTV crosses from standard equity to equity-rich tier (e.g., after a significant price reduction or reassessment) | Investor buyers who have expressed interest in high-equity targets |
| **Flood zone reclassification** | FEMA issues a new FIRM and the property's flood zone code changes | All buyers with the property saved or matched |
| **Owner entity change** | Ownership transfers to a new entity type (e.g., individual dies and property transfers to an LLC or trust through an estate) | Agents monitoring high-propensity properties |

---

## 8. Compliance and Privacy

The intelligence layer processes and derives sensitive information about property owners who are private individuals, trusts, and business entities. The following rules govern what the platform may and may not expose, and to whom.

### 8.1 — What Must Never Be Exposed (Absolute Prohibitions)

**Owner PII in any user-facing interface:**
- Owner legal name (even though publicly available in county records)
- Owner mailing address
- Owner phone number
- Owner email address (if obtained from any source)

**Rationale:** Even though Florida property appraiser records are public, aggregating and displaying individual owner contact information at scale transforms a public lookup into a commercial data product that triggers CAN-SPAM, TCPA, and CCPA/Florida privacy framework considerations. The platform derives intelligence from this data but does not redistribute it.

**Protected-class proxies:**
Any signal that could serve as a proxy for a protected class under the Fair Housing Act must not be used in buyer or tenant matching, recommendation explanations, or alert targeting. Specific prohibitions:
- **Racial or ethnic composition of neighborhood** — must not appear in any signal, recommendation, or explanation
- **School rating scores from third-party sources that correlate with race/income** — raw school name is permissible; quality ranking scores must not be used as a match weight
- **Crime statistics** — must not be incorporated as a match dimension or recommendation explanation
- **Language used by neighborhood residents** — must not be a signal source

**Private and non-IDX MLS data:**
The following Stellar MLS fields are classified as Compliance/Restricted in the Bridge field audit and must never appear in any intelligence signal, match score, Ask AI prompt, recommendation explanation, or public-facing interface:
`ListAgentEmail`, `ListAgentPreferredPhone`, `ListOfficePhone`, `ListAgentStateLicense`, `BuyerAgentStateLicense`, `CoListAgentStateLicense`, `CoBuyerAgentStateLicense`, `License1`, `License2`, `License3`, `STELLAR_BuilderLicenseNumber`, `LockBoxLocation`, `LockBoxSerialNumber`, `LockBoxType`, `STELLAR_ShowingRequirements`, `STELLAR_ShowingConsiderations`, `STELLAR_CallCenterPhoneNumber`, `STELLAR_EscrowAgentEmail`, `STELLAR_EscrowAgentPhone`, `STELLAR_ListOfficeContactPreferred`, `STELLAR_PropertyManagerPhone`, `STELLAR_TenantPhone`, `STELLAR_TenantName`, `STELLAR_RealtorInfoConfidential`, `STELLAR_SoldRemarks`

**Sensitive distress language:**
Alert copy, recommendation explanations, and Ask AI answers must not use language that characterizes a property owner as financially distressed in buyer-facing or public-facing contexts. The following framings are prohibited:
- "This owner appears to be in foreclosure"
- "The owner cannot afford to pay their taxes"
- "This property is at risk of being seized"

Permissible equivalents:
- "Tax records indicate unpaid taxes — your agent can advise on title implications"
- "A notice of legal action (lis pendens) is recorded on this property — consult your agent"

---

### 8.2 — What May Be Exposed and To Whom

| Signal | Buyer-Facing | Tenant-Facing | Agent-Facing | Platform Internal |
|---|---|---|---|---|
| Flood Risk Tier | Yes | Yes | Yes | Yes |
| Annual Tax Burden (amount) | Yes | Yes | Yes | Yes |
| Estimated Equity tier (not dollar amount) | No — too specific | No | Yes | Yes |
| Estimated Equity dollar amount | No | No | Yes (with "estimated" label) | Yes |
| Investor-Owned Likelihood Score | No | No | Yes | Yes |
| Seller Propensity Score | No | No | Yes | Yes |
| Absentee Owner flag | No | No | Yes | Yes |
| Owner name | No | No | No | No |
| Owner mailing address | No | No | No | Internal computation only |
| Tax Delinquent flag | Yes (with softened language) | No | Yes | Yes |
| Lis Pendens flag | Yes (with softened language) | No | Yes | Yes |
| HOA Lien flag | Yes (with softened language) | No | Yes | Yes |
| Redevelopment Potential flag | Yes (investor/land buyer only) | No | Yes | Yes |
| Cap Rate Estimate | Yes (investor buyer persona) | No | Yes | Yes |
| Gross Rent Multiplier | Yes (investor buyer persona) | No | Yes | Yes |
| Estimated Monthly Rent | Yes | Yes | Yes | Yes |
| Ownership Type | No (too identifying) | No | Yes | Yes |
| Years Owned | No | No | Yes | Yes |

---

### 8.3 — Data Retention and Access Controls

- Intelligence profiles stored in `bridge_property_intelligence` (see Section 9) must be accessible only to authenticated users with the appropriate role
- Raw source data (owner mailing addresses, deed records, court filings) must be stored in a separate, access-controlled table (`property_owner_records`) that is not readable by any Blade view, API endpoint, or Livewire component serving buyer or tenant users
- All intelligence API responses that touch owner data must be logged with the requesting user ID for audit purposes
- Intelligence data sourced from county records must carry a freshness timestamp; stale intelligence (> 90 days without refresh) must be clearly labeled or suppressed from display

---

### 8.4 — Seller Propensity Outreach — Specific Rules

The Seller Propensity Score is used to identify off-market outreach targets. The following rules govern its use:

1. The platform may surface a prioritized property list to an agent based on the score
2. The platform may not send automated outreach communications to property owners on the agent's behalf without the agent's explicit review and approval of each communication
3. Any outreach communication generated by or facilitated by the platform must identify the agent and their brokerage — not the platform — as the sender
4. The platform must not infer that an owner wants to sell based solely on the Seller Propensity Score. The score indicates statistical likelihood, not intent. Communications must be framed as informational, not presumptuous.
5. Do-not-contact preferences expressed by an owner (if collected through any channel) must be honored and must suppress that owner from all platform-facilitated outreach lists immediately

---

## 9. Storage Strategy

The intelligence layer requires four new tables to store its data. No migrations are produced by this document. The table structures below are design recommendations for the implementation phase.

### Table: `bridge_property_intelligence`

Stores the derived intelligence signals for each property keyed by `listing_key`. One row per property. Updated on each intelligence refresh cycle.

| Column | SQL Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `listing_key` | `varchar(255)` | Foreign key → `bridge_properties.listing_key`; unique indexed |
| `parcel_number` | `varchar(50)` | County parcel ID; join key to owner and tax tables |
| `ownership_type` | `varchar(50)` | Enum: owner_occupant / individual_investor / llc / corporate / trust / government / unknown |
| `absentee_owner` | `boolean` (nullable) | True if mailing address differs from property address |
| `years_owned` | `decimal(5,2)` (nullable) | (today - last_deed_date) / 365.25 |
| `estimated_equity_amount` | `integer` (nullable) | Dollar estimate; labeled as estimated in all UI |
| `estimated_ltv_pct` | `decimal(5,2)` (nullable) | Loan-to-value percentage estimate |
| `equity_tier` | `varchar(20)` (nullable) | equity_rich / standard / low_equity / at_risk |
| `annual_tax_amount` | `integer` (nullable) | Total annual tax obligation in dollars |
| `cdd_annual_amount` | `integer` (nullable) | CDD special assessment component |
| `investor_owned_score` | `tinyint unsigned` (nullable) | 0–100 |
| `seller_propensity_score` | `tinyint unsigned` (nullable) | 0–100; not exposed to buyer/tenant users |
| `tax_delinquent` | `boolean` (nullable) | True if unpaid taxes on record |
| `lis_pendens_active` | `boolean` (nullable) | True if active lis pendens filed |
| `hoa_lien_active` | `boolean` (nullable) | True if active HOA lien recorded |
| `mechanic_lien_active` | `boolean` (nullable) | True if active mechanic's lien recorded |
| `flood_zone_code` | `varchar(20)` (nullable) | Raw FEMA zone code from MLS or FEMA API |
| `flood_risk_tier` | `varchar(20)` (nullable) | minimal / moderate / high / very_high / unknown |
| `estimated_monthly_rent` | `integer` (nullable) | Dollar estimate based on comparable rentals |
| `estimated_cap_rate` | `decimal(5,2)` (nullable) | Percentage; labeled as estimated |
| `gross_rent_multiplier` | `decimal(6,2)` (nullable) | List price / annual rent estimate |
| `redevelopment_potential` | `boolean` (nullable) | True if land/zoning signals suggest development opportunity |
| `redevelopment_context` | `jsonb` (nullable) | Supporting detail: land ratio, zoning, lot size, structure age |
| `signals_version` | `tinyint unsigned` | Schema version for the signals computation; bump when signal definitions change |
| `source_refreshed_at` | `timestamp` | When the underlying source data was last fetched |
| `computed_at` | `timestamp` | When signals were last computed from source data |
| `created_at` | `timestamp` | Row creation timestamp |
| `updated_at` | `timestamp` | Last update timestamp |

**Index recommendations:**
- Unique index on `listing_key`
- Index on `parcel_number`
- Composite index on `(seller_propensity_score, absentee_owner)` for agent prospect queries
- Index on `(flood_risk_tier)` for flood-filter queries
- Index on `(investor_owned_score)` for investor matching queries
- Index on `(equity_tier)` for equity-filtered queries

**Column rationale:**
- Keeping all derived signals in a single row per property avoids costly joins in matching queries
- `signals_version` allows safe re-computation when signal definitions change without invalidating all existing rows simultaneously
- `source_refreshed_at` vs. `computed_at` separation allows the system to know whether stale signals reflect stale source data or just a delayed computation job

---

### Table: `property_owner_records`

Stores raw ownership data from county property appraiser records. This table is access-controlled and must not be readable by any buyer-facing, tenant-facing, or public-facing component.

| Column | SQL Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `parcel_number` | `varchar(50)` | County parcel ID; unique indexed |
| `owner_name_1` | `varchar(200)` | Primary owner name as recorded by county |
| `owner_name_2` | `varchar(200)` (nullable) | Co-owner name (joint tenancy, spouse) |
| `owner_entity_type` | `varchar(50)` | individual / llc / corporation / trust / government / unknown |
| `owner_mailing_address` | `varchar(300)` | Owner's mailing address as recorded by county |
| `owner_mailing_city` | `varchar(100)` | |
| `owner_mailing_state` | `varchar(2)` | Two-letter state code |
| `owner_mailing_zip` | `varchar(10)` | |
| `mailing_matches_property` | `boolean` (nullable) | Computed: true if mailing address normalizes to property address |
| `homestead_exemption` | `boolean` | True if homestead exemption is active |
| `homestead_exemption_amount` | `integer` (nullable) | Dollar value of exemption if available |
| `other_exemptions` | `jsonb` (nullable) | Array of other exemption types and amounts |
| `acquisition_date` | `date` (nullable) | Date of most recent deed transfer |
| `acquisition_price` | `integer` (nullable) | Price at most recent deed transfer |
| `source` | `varchar(50)` | Data source: bridge_property_records / county_api / manual |
| `source_refreshed_at` | `timestamp` | When this row was last fetched from source |
| `created_at` | `timestamp` | |
| `updated_at` | `timestamp` | |

**Access control rationale:** This table contains owner PII (name, mailing address). It must be readable only by the intelligence computation jobs that derive signals, and by privileged internal admin roles. No Livewire component, API controller, or Blade view serving buyer or tenant users may query this table. The signals derived from this data live in `bridge_property_intelligence`, where the PII has already been stripped.

---

### Table: `property_tax_records`

Stores county tax assessment data per parcel. Refreshed annually when county assessment rolls are published (typically July–November in Florida).

| Column | SQL Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `parcel_number` | `varchar(50)` | County parcel ID; indexed |
| `assessment_year` | `smallint unsigned` | Tax year for this record |
| `just_value` | `integer` (nullable) | County just/market value estimate |
| `assessed_value` | `integer` (nullable) | Assessed value after Save Our Homes cap and exemptions |
| `taxable_value` | `integer` (nullable) | Assessed value minus all exemptions |
| `land_value` | `integer` (nullable) | Value of land component only |
| `improvement_value` | `integer` (nullable) | Value of structure component only |
| `land_to_improvement_ratio` | `decimal(5,2)` (nullable) | Computed: land_value / improvement_value; high ratio = redevelopment signal |
| `millage_rate` | `decimal(8,4)` (nullable) | Total millage rate for the parcel |
| `base_tax_amount` | `integer` (nullable) | Base property tax (taxable_value × millage_rate / 1000) |
| `cdd_assessment_amount` | `integer` (nullable) | Annual CDD special assessment |
| `other_assessment_amount` | `integer` (nullable) | Other special assessments |
| `total_tax_amount` | `integer` (nullable) | Sum of all tax components |
| `tax_paid` | `boolean` (nullable) | Whether taxes for this year have been paid |
| `delinquent_amount` | `integer` (nullable) | Dollar amount of delinquent taxes if any |
| `county` | `varchar(100)` | County name |
| `source_refreshed_at` | `timestamp` | When fetched from county records |
| `created_at` | `timestamp` | |

**Uniqueness:** Composite unique index on `(parcel_number, assessment_year)` — one row per parcel per tax year. Historical rows are retained for trend analysis (assessed value trajectory is a useful signal for both buyer cost modeling and redevelopment potential assessment).

---

### Table: `property_sales_history`

Stores the chronological deed transfer and recorded mortgage history for each parcel.

| Column | SQL Type | Notes |
|---|---|---|
| `id` | `bigint unsigned` (auto-increment) | Primary key |
| `parcel_number` | `varchar(50)` | County parcel ID; indexed |
| `transaction_type` | `varchar(50)` | sale / refinance / lien / lien_release / lis_pendens / lis_pendens_release / other |
| `transaction_date` | `date` | Date of recording |
| `sale_price` | `integer` (nullable) | Transfer price (null for non-sale transactions) |
| `deed_type` | `varchar(100)` (nullable) | Warranty Deed / Quitclaim Deed / Sheriff's Deed / Trustee's Deed / etc. |
| `grantor` | `varchar(200)` (nullable) | Seller / transferor name as recorded |
| `grantee` | `varchar(200)` (nullable) | Buyer / transferee name as recorded |
| `mortgage_amount` | `integer` (nullable) | Original loan amount if a mortgage was recorded |
| `mortgage_lender` | `varchar(200)` (nullable) | Lender name |
| `mortgage_type` | `varchar(50)` (nullable) | Conventional / FHA / VA / Reverse / HELOC / Private |
| `document_number` | `varchar(100)` (nullable) | County recorder document reference |
| `is_arms_length` | `boolean` (nullable) | True if the transaction appears to be an arm's-length market transaction |
| `source` | `varchar(50)` | bridge_property_records / county_recorder / mls_close |
| `source_refreshed_at` | `timestamp` | |
| `created_at` | `timestamp` | |

**Index recommendations:**
- Index on `parcel_number`
- Composite index on `(parcel_number, transaction_date)` for chronological deed history retrieval
- Index on `(transaction_type, transaction_date)` for distress signal queries (find all active lis_pendens records)

**Distress flag derivation from this table:** The `property_sales_history` rows with `transaction_type = 'lis_pendens'` that do not have a corresponding `lis_pendens_release` row with a later date constitute the active lis pendens set. A scheduled job queries this table to update the `lis_pendens_active` flag in `bridge_property_intelligence`.

---

## 10. Implementation Roadmap

The intelligence layer is built in four phases. Each phase is independently deployable and delivers incremental value. No phase requires the completion of subsequent phases.

### Phase 1: MLS-Only Intelligence (Derivable Without Public Records)

**Prerequisite:** Phase 1 native column promotions from `STELLAR_NATIVE_COLUMN_EXPANSION_STRATEGY.md` must be complete. Specifically: `latitude`, `longitude`, `county_or_parish`, `year_built`, `association_fee`, `tax_annual_amount`, `lot_size_sqft`, `new_construction_yn`, `pool_private_yn`, `waterfront_yn`, `senior_community_yn`, `mls_status`, `cdd_yn`.

**What is built:**

A simplified version of `bridge_property_intelligence` is populated using only MLS data already in `bridge_properties.raw_json`. No external API calls are required.

Signals available in Phase 1 (MLS sources only):
- **Flood Risk Tier** — derived from `STELLAR_FloodZoneCode` (MLS); records where this field is absent receive `unknown` until Phase 2 FEMA API integration
- **Annual Tax Burden (partial)** — `TaxAnnualAmount` (MLS native column after Phase 1 promotion); does not yet include CDD amounts that require county records
- **Estimated Monthly Rent** — computed from comparable active Stellar rental listings once the For Lease feed is active (requires Phase 2R rental feed)
- **Cap Rate (MLS-reported)** — `CapRate` from raw_json for investment-listed properties; investment feed only
- **Days on Market trend** — derived from `DaysOnMarket` and `CumulativeDaysOnMarket` MLS fields
- **Price reduction signal** — derived from `ListPrice` vs. `OriginalListPrice` MLS fields; indicates seller motivation
- **Occupant type** — `OccupantType` MLS field (Vacant / Owner / Tenant) as a partial proxy for absentee ownership; not as reliable as county records but available immediately

**Phase 1 deliverables:**
- `bridge_property_intelligence` table created with all columns, initially populated only for columns derivable from MLS data
- Signal computation job: reads `bridge_properties.raw_json`, computes Phase 1 signals, writes to `bridge_property_intelligence`
- Ask AI integration: flood risk tier and annual tax burden surfaced in cost-of-ownership answers
- Matching engine: flood risk tier available as a hard filter; MLS-only signals available as soft weights

**Estimated value:** Flood zone risk filtering alone meaningfully improves buyer match quality for Florida coastal listings. Tax burden as a true monthly cost input improves Ask AI answer quality for cost-of-ownership questions.

---

### Phase 2: Public Records Enrichment

**Prerequisite:** Bridge Interactive public records API integration approved and credentialed. County tax API integrations for top-5 Florida counties (Orange, Hillsborough, Pinellas, Broward, Miami-Dade) established. `property_owner_records` and `property_tax_records` tables created.

**What is built:**

A batch enrichment pipeline that queries Bridge public records and county tax APIs for every property in `bridge_properties`, keyed by `ParcelNumber`. Results are stored in `property_owner_records` and `property_tax_records`. The intelligence computation job is extended to read from these tables and update the remaining `bridge_property_intelligence` columns.

Signals added in Phase 2:
- **Ownership Type** — from `property_owner_records.owner_entity_type`
- **Absentee Owner Flag** — from `property_owner_records.mailing_matches_property`
- **Years Owned** — from `property_owner_records.acquisition_date`
- **Estimated Equity Amount and LTV** — from `property_owner_records.acquisition_price` + `property_tax_records.just_value` + amortization estimate
- **Equity Tier** — derived from LTV percentage
- **Annual Tax Burden (complete)** — from `property_tax_records.total_tax_amount` including CDD component
- **Tax Delinquent Flag** — from `property_tax_records.tax_paid` / `delinquent_amount`
- **Investor-Owned Likelihood Score** — full composite score now that ownership type and absentee flag are available
- **Seller Propensity Score** — available for agent-facing tools; not exposed to buyers
- **Redevelopment Potential Flag** — from `property_tax_records.land_to_improvement_ratio` + MLS zoning fields

**Phase 2 deliverables:**
- Batch enrichment pipeline running nightly for all `bridge_properties` records with a `ParcelNumber`
- Agent-facing prospect list feature: filter by seller propensity score, ownership type, equity tier
- Matching engine: ownership type and investor-owned score available as soft weights for investor buyer matching
- Ask AI: annual tax burden (complete), equity tier context, and absentee owner flag available in agent-facing Ask AI responses

**Estimated value:** Seller propensity scoring unlocks off-market outreach for agents — a significant differentiation from competitor platforms. Equity-filtered investor matching is a new capability unavailable on public portals.

---

### Phase 3: Seller Propensity and Investor Scoring

**Prerequisite:** Phase 2 public records data stable and refreshing reliably. `property_sales_history` table created and populated from Bridge deed records API.

**What is built:**

Deed history enrichment pipeline. Lien and lis pendens detection. Seller propensity score refinement using deed history signals. Rental income potential computation using the Stellar For Lease feed (Phase 2R).

Signals added or refined in Phase 3:
- **Last Sale Date and Price** — from `property_sales_history`
- **Lis Pendens Flag** — from `property_sales_history` records with `transaction_type = 'lis_pendens'`
- **HOA Lien Flag** — from `property_sales_history` records with `transaction_type = 'lien'` and lien type = HOA
- **Mechanic Lien Flag** — from `property_sales_history` records with `transaction_type = 'lien'` and lien type = mechanic
- **Seller Propensity Score (v2)** — refined with deed history signals (sheriff deed in history, multiple rapid flips, estate-transfer deed types)
- **Estimated Monthly Rent (if Phase 2R complete)** — using Stellar rental feed comparable matching
- **Cap Rate Estimate (computed)** — using estimated monthly rent and tax burden
- **Gross Rent Multiplier** — computed from cap rate estimate and list price

**Phase 3 deliverables:**
- Distress signal alerts: agents receive alerts when a watchlisted property has a new lis pendens or HOA lien filing
- Investor matching: cap rate and GRM available as buyer criteria dimensions for investor buyer auctions
- Agent CMA tool: deed history surfaced in the agent-facing listing intelligence panel
- Seller propensity v2 scoring: higher accuracy leads to better off-market outreach ROI

---

### Phase 4: MLS and Brokerage Partnership API

**Prerequisite:** Phases 1–3 stable. Platform has established direct data-sharing relationships with Stellar MLS or participating brokerages that allow access to non-IDX data, private remarks, listing history, and transaction data beyond what the Bridge API public feed provides.

**What is built:**

Integration of MLS partnership-level data feeds. This phase is the most speculative — it requires business relationships and legal agreements, not just technical integration. The technical architecture is the same as Phases 1–3 (additional data sources enriching `bridge_property_intelligence`), but the data quality and signal fidelity improve significantly.

Capabilities unlocked in Phase 4:
- **Private remarks** — MLS-internal remarks often contain seller motivation, pricing history context, and terms that the public listing does not. Available only to licensed agents and authorized platforms under MLS data agreements.
- **Agent-to-agent showing data** — Properties with high showing counts and no offers are a motivation signal. Properties with low showing counts may be overpriced or have a presentation problem. Showing data is not available in the public Bridge feed.
- **Transaction-side data** — Access to buyer-side transaction data (what buyers are actually offering, not just what sellers are asking) from participating brokerages enables real-time market intelligence on the bid-to-ask spread by property type and location.
- **MLS historical database** — Expired and cancelled listing history reveals properties that have previously failed to sell — a price-sensitivity and motivation signal not in the active-listings-only Bridge feed.
- **Brokerage proprietary data** — Some large brokerages maintain CRM data on seller leads, past clients, and pre-market opportunities. Partnership agreements can make this data available for off-market intelligence in exchange for referral or platform access.

**Phase 4 deliverables:**
- Private remarks available in agent-facing Ask AI responses (not public-facing)
- Showing-count signal in seller propensity scoring
- Expired/cancelled listing history in CMA tool
- Bid-to-ask spread data as market context in pricing conversation support

**Phase 4 prerequisite summary:** This phase requires executed data licensing agreements with Stellar MLS and/or participating brokerages. No implementation begins until legal review of the agreement terms is complete and the compliance rules for the new data categories are fully defined.

---

*End of document. No code changes, migrations, or API integrations have been made as part of this architecture document.*

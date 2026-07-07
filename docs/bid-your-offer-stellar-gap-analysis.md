# Bid Your Offer ↔ Stellar MLS — Gap Analysis & Data-Model Roadmap

**Type:** Documentation-only audit. No code, migrations, or config were changed. Nothing committed.
**Date:** 2026-07-01
**Sources of truth:**
- Current implementation → `docs/bid-your-offer-field-audit.md` (the BYO Field Audit).
- Target reference → the seven Stellar MLS data-entry forms in `attached_assets/` (all pages read): Residential (14pp), Income (12pp), Commercial Sale (10pp), Business Opportunity (10pp), Commercial Lease (9pp), Rental (13pp), Vacant Land (9pp).

**Purpose:** Decide which Stellar fields to adopt into Bid Your Offer (BYO) to strengthen Property DNA, Buyer DNA, Tenant DNA, Location DNA, lifestyle metadata, matching, Ask AI, search, marketing, and target-audience intelligence — and, just as importantly, which Stellar fields to deliberately **not** adopt.

> **Guiding rule (applied throughout):** A Stellar field is only recommended if it meaningfully improves BYO's AI, matching, DNA, search, marketing, or UX. MLS-administrative/internal/agent-workflow fields are explicitly excluded (Section: "Do NOT add").

---

## How the comparison was done

Stellar's forms describe a **property/listing from the seller/landlord side**. BYO mirrors each attribute twice: once as a **listing** (Seller sale / Landlord lease) and once as **criteria** (Buyer / Tenant). So each Stellar form is compared against the two BYO flows it maps to:

| Stellar form | BYO listing flow | BYO criteria flow |
|---|---|---|
| Residential | Seller — Residential | Buyer — Residential |
| Income | Seller — Income | Buyer — Income |
| Commercial Sale | Seller — Commercial | Buyer — Commercial |
| Business Opportunity | Seller — Business | Buyer — Business |
| Vacant Land | Seller — Vacant Land | Buyer — Vacant Land |
| Rental | Landlord — Residential Rental | Tenant — Residential Rental |
| Commercial Lease | Landlord — Commercial Lease | Tenant — Commercial Lease |

Each field is classified as: **Implemented** (present in BYO, same meaning) · **Partial** (present but weaker — free-text vs structured, listing-side only, or missing sub-fields) · **Missing** · **Covered under a different name** (BYO name ≠ Stellar name) · **Duplicate** (BYO-internal duplication to clean up) · **Do NOT add** (MLS-only/admin/internal).

**Missing-field table columns:** `Field` · `Property Type(s)` · `Priority` (Critical / High / Medium / Low) · `Why add` · `Search?` (should be a search filter) · `Public?` (display on public listing) · `Ask AI?` · `DNA / lifestyle tags`.

Priority definitions:
- **Critical** — needed for a credible launch of that property type (a buyer/seller would consider the listing incomplete without it) or unblocks matching/search.
- **High** — strong, differentiated DNA / lifestyle / target-audience value; adopt in the first enhancement wave.
- **Medium** — useful structured data that improves search/AI/marketing but not launch-blocking.
- **Low** — nice-to-have; niche or long-tail.

---

## Executive summary — the big picture

1. **BYO is already strongly aligned with Stellar on core physical attributes.** The BYO option lists for property style, condition, waterfront (access/view/frontage/feet), interior features, appliances, view, roof, exterior construction, foundation, heating/AC/water/sewer/utilities, pool, garage/carport, HOA/association (fee, frequency, includes, amenities, approval, leasing restrictions), flood zone, CDD, special assessments, pets, financing types, and sale provisions are clearly derived from Stellar and match option-for-option. **Most Stellar *consumer* fields are already Implemented or Covered-under-a-different-name.** The gap is not breadth of physical description — it is a specific set of **high-signal lifestyle / DNA / target-audience fields Stellar has that BYO omitted**, plus **structured commercial/income economics**.

2. **BYO is genuinely *ahead* of Stellar in several areas** — do not regress these: consumer-side **commute** (zip/minutes/mode), **Location DNA search-areas + Important Places**, **Buyer/Tenant criteria capture**, **structured financing sub-forms** (assumable, seller-financing, lease-option/purchase, crypto, NFT, exchange), **business-opportunity economics** (SDE/EBITDA, inventory value, FF&E value, reason for sale, employee count, NDA, financial-statements-available — Stellar only has NOI/gross), and **tenant pre-screening self-disclosure**.

3. **The most valuable net-new Stellar fields for BYO's AI/DNA are a small, high-leverage set** (all currently Missing): **Schools**, **Front Exposure (sun orientation)**, **Architectural Style**, **Accessibility Features**, **Community Features** (gated/golf/dog-park/etc. as structured lifestyle tags), **In-Law Suite/ADU + Additional Rooms (home office, media, Florida room)**, **Disaster Mitigation** (hurricane shutters/windows, above flood plain — FL-critical for insurance and marketing), **Solar / Green Energy Generation + Solar Panel Ownership** (owned/leased/financed — materially affects the deal), **Fireplace**, and **exact Lot Size (sqft/acres) + Lot Features (topography/wetlands)**.

4. **Commercial needs structured economics, not more physical fields.** BYO already has zoning, ceiling height, building features, electrical service, space class. The commercial gaps are **Lease Price Unit ($/sqft vs monthly)**, **loading/dock bays + door dimensions**, **structured pass-through/CAM** (currently free-text), **structured TI allowance / rent-escalation** (currently free-text), **vacancy rate / gross scheduled income / number of tenants / anchor tenant** on the sale side.

5. **Do NOT port ~40% of every Stellar form.** Office Exclusive, Listing Type/Service Type, Seller Representation, all legal survey identifiers (Section/Township/Range/Plat Book/Block/Lot/Alt Key/Census), GeoLocation (BYO geocodes itself), Agent/Office IDs, Seller's Preferred Closing Agent, Showing Instructions/lockbox/ShowingTime, Listing Distribution/IDX/VOW/syndication, Realtor-Only/Confidential remarks, signatures, and municipal Use/Land-Use codes are MLS-workflow artifacts with no BYO value.

---
## 1. RESIDENTIAL  (Stellar Residential ↔ Seller-Residential + Buyer-Residential)

### Already Implemented (present in BYO with equivalent meaning)
List Price/asking price; Special Sale Provision; address block; Property Style (`property_items`); Property Condition (`condition_prop`/`condition_prop_buyer`); Beds/Full Baths/Half Baths; SqFt Heated (`minimum_heated_square`); SqFt Total (`total_square_feet`); SqFt Heated Source (`sqft_heated_source`); Year Built; Zoning; Total Acreage range (`total_acreage`); Lot Dimensions; Private Pool + Pool Features/type; Spa (partial); Garage/Carport + spaces + Garage/Parking Features; Waterfront/Water Access/Water View/Water Extras/Water Frontage + footage + Water Name; Dock (full block); View (`view_preference`); Interior Features; Appliances Included; Roof; Exterior Construction; Foundation; Heating and Fuel; Air Conditioning; Water; Sewer; Utilities; Floor Covering (`floor_covering`, Landlord side); Laundry Features (Landlord); Security Features (Landlord); Flood Zone Code/Date/Panel; CDD + Annual CDD Fee; Special Assessments; HOA/Association full block (fee, requirement, frequency, includes, amenities, approval, application fee, master assoc, leasing restrictions, min lease period, max leases/yr); Pets Allowed + number/size/weight/restrictions; Home Warranty; Ownership occupant status; Existing lease/occupant (`occupant_status`/`occupant_tenant`); Financing Available (`offered_financing`); Public Remarks (`additional_details`); Virtual Tour / Web links; Possession preference.

### Covered under a different name
- Stellar **Property Style** = BYO `property_items` · **Housing for Older Persons** = `leasing_55_plus` · **SqFt Heated** = `minimum_heated_square` · **Furnishings** = `tenant_require` · **Heating and Fuel** = `heating_and_fuel`/`heating_fuel` · **Total Acreage** (range) = `total_acreage` · **Fee Includes** = `association_fee_includes` · **Association Amenities** = `association_amenities` · **Special Sale Provision** = `sale_provision` · **Financing Available** = `offered_financing` · **Public Remarks** = `additional_details`.

### Partial (present but weaker)
- **Lot Size Square Footage / Lot Size Acres** — BYO captures the `total_acreage` *range* + `lot_dimensions` text, but not the exact numeric sqft/acres Stellar requires. → tighten for search/valuation.
- **Spa / Pool Features** — pool is strong; Spa Features (`Swim Spa`, etc.) not fully modeled.
- **Fencing / Security Features / Floor Covering** — exist on the Landlord side but **not on Seller/Buyer Residential**. → extend to sale side.
- **Existing Lease detail** — BYO has occupant status; Stellar adds monthly rent, lease end date, notice-to-tenant. → add for tenant-occupied sales (investor buyers).

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| R1 | **Schools** (Elementary / Middle / High) | Residential (Seller list + Buyer criteria) | **Critical** | #1 residential decision driver; enables family/target-audience matching, school-zone search, and Ask AI "what schools serve this home?" | Yes | Yes | Yes | Location DNA: `school_zone`, `school_rated`; Buyer DNA: `family_oriented`, `school_priority` |
| R2 | **Front Exposure** (N/S/E/W + intercardinal) | Residential, all sale types | **High** | Sun orientation = lifestyle + energy; cheap to collect, strong DNA signal ("morning sun lanai", "north-facing cool") | Yes | Yes | Yes | Property DNA: `exposure_south`, `natural_light`; lifestyle `sunrise_view`/`sunset_view` |
| R3 | **Architectural Style** (Coastal, Key West, Mediterranean, Colonial, Craftsman, Mid-Century, Ranch, Victorian, Contemporary…) | Residential | **High** | Marketing + Property DNA personality + style-based search; BYO `property_items` is *structure* type only, not architectural character | Yes | Yes | Yes | Property DNA: `architectural_style:*`; marketing tone |
| R4 | **Accessibility Features** (24-value list) | Residential (+ Rental, Commercial) | **High** | Underserved target audience (aging-in-place, mobility); ADA/senior matching; strong Ask AI intent | Yes | Yes | Yes | Buyer/Tenant DNA: `accessibility_needed`; lifestyle `single_level`, `wheelchair_accessible` |
| R5 | **In-Law Suite / ADU** (Y/N + description: attached/detached, kitchen, private entrance, beds/baths, sqft) | Residential | **High** | Multigenerational + rental-income + remote-work; high buyer intent; BYO only has ADU as a landlord *leasing-space* option | Yes | Yes | Yes | Property DNA: `has_adu`, `multigenerational`, `income_potential`; Buyer DNA `multigen_need` |
| R6 | **Additional Rooms** (Den/Office, Bonus Room, Media Room, Great Room, Florida Room, Loft, Inside Utility) | Residential | **High** | Remote-work (home office) + lifestyle rooms are top post-2020 buyer intents; enables "home office" search + DNA | Yes | Yes | Yes | Property DNA: `home_office`, `media_room`, `flex_space`; Buyer DNA `remote_work` |
| R7 | **Fireplace** (Y/N) + **Fireplace Description** (Gas/Wood/Electric/location) | Residential | **Medium** | Common buyer filter + marketing; distinct from generic interior features | Yes | Yes | Yes | lifestyle `fireplace`, `cozy` |
| R8 | **Community Features** (Gated w/ or w/o Guard, Golf Community, Dog Park, Playground, Fitness Center, Clubhouse, Sidewalks, Tennis/Pickleball, Deed Restrictions, Golf Carts OK) | Residential (+ all) | **High** | These are prime **lifestyle/target-audience tags** and top search filters; BYO only partially captures via `association_amenities` | Yes | Yes | Yes | Lifestyle: `gated`, `golf_community`, `dog_friendly`, `family_amenities`, `active_adult`, `walkable` |
| R9 | **Disaster Mitigation** (Hurricane Shutters/Windows, Above Flood Plain, Safe Room, Fire-Resistant Exterior, Hurricane-Insurance-Deduction Qualified) | Residential (+ all FL types) | **High** | FL-critical: insurability + resilience is a real buyer concern and marketing edge; ties to Location DNA flood data | Yes | Yes | Yes | Property DNA: `hurricane_hardened`, `insurance_friendly`; Location DNA flood synergy |
| R10 | **Green Energy Generation** (Y/N + Solar/Wind) + **Solar Panel Ownership** (Owned/Leased/Financed/Utility) + **Solar Lease/Finance Terms** (Assumable/Seller-paid) | Residential (+ all) | **High** | Solar ownership materially changes the transaction & financing; owned-solar is a marketing/DNA asset, leased-solar a diligence item | Yes | Yes | Yes | Property DNA: `solar_owned`, `energy_efficient`, `low_utility_cost` |
| R11 | **Exact Lot Size** (Lot Size SqFt + Lot Size Acres, numeric) | Residential (+ all land-bearing) | **Medium** | Precise search/valuation vs. current range bucket; supports "≥ X sqft lot" filters | Yes | Yes | Yes | Property DNA `large_lot`, `oversized_lot` |
| R12 | **Ownership Type** (Fee Simple / Condominium / Co-op / Leasehold / Fractional) + **Land Lease** (Y/N + fee) | Residential (+ all) | **Medium** | Affects financing & fees; leasehold/land-lease are material diligence and lending constraints | Yes | Yes | Yes | `fee_simple`, `leasehold`, `land_lease` |
| R13 | **Patio & Porch Features** (Screened, Covered, Front Porch, Lanai, Wrap-Around) | Residential | **Medium** | FL outdoor-living lifestyle marketing + search | Yes | Yes | Yes | lifestyle `screened_lanai`, `outdoor_living` |
| R14 | **Architectural / Property Description** (Corner Unit, End Unit, Penthouse, High/Mid Rise, Stilt Home, Walk-Up) | Residential (condo/attached) | **Medium** | Condo/townhome buyer filters; floor position drives value | Yes | Yes | Yes | `penthouse`, `corner_unit`, `top_floor` |
| R15 | **Building / Floor info for condos** (Floor Number, Total Floors, Building Elevator) | Residential (condo) | **Medium** | Elevator + floor level are decisive for condo buyers (accessibility, views) | Yes | Yes | Yes | `elevator`, `high_floor`, `top_floor` |
| R16 | **Window Features** (Impact/Storm, Double-Pane, ENERGY STAR, Tinted) | Residential (+ all) | **Low/Medium** | Impact windows = insurance + storm resilience (FL); energy signal | Yes | Yes | Yes | `impact_windows`, `energy_efficient` |
| R17 | **Green Building Certifications** (LEED, ENERGY STAR, HERS Index, FGBC) | Residential (+ all) | **Low** | Niche but strong marketing for eco-conscious audience | No | Yes | Yes | `green_certified`, `energy_star` |
| R18 | **Indoor Air Quality** (MERV filters, No/Low-VOC, Whole-house vacuum) | Residential | **Low** | Health-conscious/allergy target audience; long-tail | No | Yes | Yes | `healthy_home`, `low_voc` |
| R19 | **Room dimensions grid** (Room Type + L×W + level) | Residential | **Low** | Nice for marketing/AI but heavy to collect on a bidding platform; defer | No | Yes | Partial | — |

### Duplicate / cleanup (BYO-internal, surfaced by the audit — not Stellar-driven)
- `condition_prop` vs `condition_prop_buyer` (two condition fields); `pet_policy` meta always empty while `pets` is the live field; `video_tour_url` / `virtual_tour_url` label inversion. → consolidate when touching these areas.

### Do NOT add (MLS-only / admin / internal)
Office Exclusive (+Temporary Exclusion); Listing Contract Date; Listing Type; Listing Service Type; Seller Representation (BYO handles agency via broker-compensation); GeoLocation (BYO geocodes); Permit #/Builder License #; all legal survey IDs (Tax ID beyond BYO's `parcel_id`, Section/Township/Range/Plat Book/Block/Lot #/Alt Key/Legal Subdivision/Census Tract/Census Block); SqFt Total Source (keep only Heated Source); Owner/Tenant name & phone; Association manager contact/email/website; Realtor Information & Realtor Information Confidential; Documents-and-Disclosures grid (BYO has its own docs rows); List Agent/Office IDs & Team; Seller's Preferred Closing Agent block; Call Center/ShowingTime/Occupant Type/Showing Instructions/Showing Considerations; Internet/Delayed Distribution/Third Party/Show Address/IDX-VOW/AVM/Distribute-To; Create-Auto-Virtual-Tour; Realtor Only Remarks; Driving Directions; signatures/dates.

## 2. INCOME  (Stellar Income ↔ Seller-Income + Buyer-Income)

### Already Implemented
Total # of Buildings (`unit_buildings`); Total # of Units (`unit_number`); **Unit-mix / rent-roll grid** (BYO `unit_type_configurations[]` with per-row unit type, beds/baths, spaces, # units, # occupied, expected rent, sqft — matches Stellar's Units table); Annual Gross Income (`gross_annual_income`); Annual Net Income / NOI (`minimum_annual_net_income`); Annual Expenses (`annual_operating_expenses`); Cap Rate (`minimum_cap_rate`); Rent Roll Available / Operating Statement Available; Number Water Meters / Number Electric Meters; all Residential physical attributes (Property Style Duplex/Triplex/Quad, construction systems, pool, pets, etc.); Financing; HOA; Flood/CDD.

### Covered under a different name
Stellar **Property Style** (Duplex/Triplex/Quadruplex) = BYO `property_items`; **Annual Net Income** = `minimum_annual_net_income`; **Number Water/Electric Meters** = same in BYO.

### Partial
- **Individually Metered** (Stellar checkbox: Cable/Electric/Gas/Internet/Sewer/Trash/Water) — BYO has meter *counts* but not the per-utility "which utilities are individually metered" flag. → add for investor DNA.
- **Terms of Lease / Tenant Pays** (income) — BYO Income has these partially via Seller terms; Stellar has explicit Gross/Net/Pass-Throughs + Tenant-Pays expense responsibility. → structure.
- **Est Annual Market Income / Gross Scheduled Income / Total Monthly Rent & Expenses** — BYO has gross/net/expenses but not the market-vs-actual and gross-scheduled distinctions. → add for underwriting AI.

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| I1 | **Individually Metered utilities** (per-utility multi-select) | Income | **High** | Separately-metered units are a core investor value driver (tenant-paid utilities); enables investor matching | Yes | Yes | Yes | Investor DNA: `separately_metered`, `low_owner_expense` |
| I2 | **Gross Scheduled Income** + **Est. Market (potential) Income** | Income | **Medium** | Distinguishes actual vs. pro-forma; feeds Ask AI underwriting and value-add DNA | No | Yes | Yes | Investor DNA: `value_add`, `upside_potential` |
| I3 | **Financial Source** (Accountant / Broker / Owner / Tax Return) | Income | **Medium** | Credibility signal for the financials; Ask AI can qualify confidence | No | Yes | Yes | `financials_verified` |
| I4 | **Total Monthly Rent** & **Total Monthly Expenses** (aggregate) | Income | **Medium** | Quick DSCR/cash-flow signal without opening the rent roll | Yes | Yes | Yes | `cash_flowing` |
| I5 | **Terms of Lease / Tenant Pays** (structured, income) | Income | **Medium** | Expense-responsibility structure drives NOI; today it's implicit | No | Yes | Yes | `tenant_pays_utilities` |
| I6 | **GRM (Gross Rent Multiplier)** — derived display | Income | **Low** | Cheap derived metric; not a stored field (compute from price ÷ gross income) | Yes | Yes | Yes | `grm` |

*(Vacancy is already captured via per-unit "# Occupied" in the BYO unit grid — no separate field needed.)*

### Do NOT add
Same MLS-admin exclusions as Residential (listing agreement, legal survey IDs, agent/office, showing, distribution, signatures). Stellar Income adds no admin fields BYO should adopt.

---

## 3. COMMERCIAL SALE  (Stellar Commercial Sale ↔ Seller-Commercial + Buyer-Commercial)

### Already Implemented
Property Style / use type (`property_items`); Business Type (`business_type`); Zoning; Year Built; Total # of Buildings; Total Units on Property; Ceiling Height; Building Features; Electrical Service (incl. 3-phase); Air Conditioning/HVAC; Water/Sewer/Utilities; Foundation; Exterior Construction; Roof; Road Frontage (incl. Rail) + Road Surface; Cap Rate; NOI (`minimum_annual_net_income`); Price per SqFt (`price_per_sqft`); Existing Lease Type + Lease Expiration + Lease Assignable; Business Assets (`business_assets`); Office/Retail Space SqFt; Flex Space SqFt (via commercial block); Front Footage / Road Frontage Feet; Flood Zone; Total Acreage; Lot Size; Waterfront/Dock; Financing (incl. SBA-adjacent via `offered_financing`).

### Covered under a different name
Stellar **SqFt Total** (building) = BYO `total_square_feet`/`minimum_heated_square`; **Lot Size SqFt** (land) = BYO lot fields; **Property Style** = `property_items`; **Ceiling Height** = `ceiling_height`.

### Partial
- **Space Classification (A/B/C/D)** & **Space Type (Gray/Vanilla Shell, Re-Let)** — present on the BYO **Landlord** commercial side but **not confirmed on Seller-Commercial**. → extend to sale.
- **Electrical Service / Building Features** — present but verify parity of option lists with Stellar (3-phase, generator, high bays, clear span, columns).
- **Existing lease economics** — BYO has lease type/expiration/assignable; missing tenancy economics below.

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| CS1 | **Loading / Dock configuration** (# Bays Grade-Level, # Bays Dock-High, # Bays Dock-Well, Door Height/Width, Truck Doors/Well, High Bays, Clear Span, Columns) | Commercial Sale + Lease | **High** | Decisive for industrial/warehouse matching & search; today unmodeled | Yes | Yes | Yes | Commercial DNA: `dock_high`, `drive_in`, `clear_span`, `industrial_ready` |
| CS2 | **Number of Tenants / Tenant Count** (Multi/Single/Vacant) + **Anchor or Co-Tenant** | Commercial Sale | **High** | Core to investment-grade commercial matching (single-tenant NNN vs multi-tenant); anchor drives value | Yes | Yes | Yes | Investor DNA: `single_tenant_nnn`, `multi_tenant`, `anchored` |
| CS3 | **Vacancy Rate** + **Gross Scheduled Income** + **Income Includes** (Rent/Parking/Storage/Laundry) | Commercial Sale | **Medium** | Completes the underwriting picture for Ask AI / investor DNA | Yes | Yes | Yes | `stabilized`, `value_add` |
| CS4 | **NOI Type / Income Type** (Actual vs Projected) | Commercial Sale | **Medium** | Distinguishes in-place vs pro-forma NOI — material for cap-rate trust | No | Yes | Yes | `actual_noi`, `proforma` |
| CS5 | **Total Parking Spaces** (+ parking ratio) | Commercial Sale + Lease | **Medium** | Retail/office viability filter (spaces per 1,000 sqft) | Yes | Yes | Yes | `ample_parking`, `parking_ratio` |
| CS6 | **Signage** (Pole Sign / On Building / Directory / Street) | Commercial Sale + Lease | **Medium** | Retail visibility is a real value/marketing driver | Yes | Yes | Yes | `pole_sign`, `high_visibility` |
| CS7 | **Adjoining Property / Adjacent Use** | Commercial Sale + Lease | **Medium** | Context for use compatibility & Ask AI ("what's next door?") | No | Yes | Yes | `commercial_corridor`, `anchor_adjacent` |
| CS8 | **# of Restrooms / Offices / Conference Rooms / Hotel-Motel Rooms** | Commercial Sale + Lease | **Medium** | Office/hospitality fit-out search; BYO has some on lease side | Yes | Yes | Yes | `turnkey_office`, `built_out` |
| CS9 | **Freezer Space / Freestanding / Converted Residence** | Commercial Sale | **Low** | Niche use-type flags (restaurant/retail) | No | Yes | Yes | `restaurant_ready`, `freestanding` |
| CS10 | **Management Type** (Owner/Professional On-Off site) | Commercial Sale + Income | **Low** | Operational context for investors | No | Yes | Partial | `professionally_managed` |

### Do NOT add
Use Code / State-County Land-Use codes; all legal survey IDs; agent/office/closing-agent; showing/distribution/signatures; Realtor Information Confidential.

---

## 4. COMMERCIAL LEASE  (Stellar Commercial Lease ↔ Landlord-Commercial-Lease + Tenant-Commercial-Lease)

### Already Implemented
Lease Term; Terms of Lease / commercial lease type (NNN/Gross/Modified Gross/Absolute Net/Percentage — BYO `commercial_lease_type` + `terms_of_lease`); Owner Pays / Tenant Pays (`owner_pays`/`tenant_pays`); Net Leasable SqFt (`minimum_leaseable`); Office/Retail & Flex SqFt; Ceiling Height; Space Classification (A/B/C/D) + Space Type; Zoning; Property Style/use; Building Features; Electrical Service; HVAC; Signage (`signage_rights`/`signage_request`); Permitted use / approval conditions; Personal Guarantee; CAM/NNN charges (free-text `cam_nnn_additional_rent_charges`); Rent escalation (free-text); TI/buildout (free-text); # restrooms/offices/conference rooms (Landlord).

### Covered under a different name
Stellar **Terms of Lease** = BYO `terms_of_lease` / `commercial_lease_type` (+ `commercial_lease_type_preference` on Tenant); **Net Leasable SqFt** = `minimum_leaseable`; **Owner/Tenant Pays** = same.

### Partial → should become structured
- **CAM / Pass-Through Expenses** — Stellar structures it (Pass-Through Expense Includes checklist + Initial Pass-Through Expenses amount + type Flat-Monthly vs Annual $/SqFt). BYO stores it as **free text** (`cam_nnn_additional_rent_charges`, `cam_nnn_preference`). → structure for matching/AI.
- **Rent Escalation / TI Allowance** — Stellar's **Commercial Transaction Terms** (Annual Rate Increase, Improvement Allowance, Pre-Leasing, Sub-Leasing) is structured; BYO is free-text (`rent_escalation_terms`/`rent_escalation_preference`, `tenant_improvement_buildout_terms`/`buildout_tenant_improvement_request`). → structure.

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| CL1 | **Lease Price Unit** ($ Total Monthly vs Per Square Foot) + **Lease Amount Frequency** (Annual/Monthly) | Commercial Lease (Landlord + Tenant) | **Critical** | Without a rate *unit*, commercial rents can't be normalized or compared/searched ($18/sqft/yr vs $2,400/mo). Blocks credible commercial-lease matching & search | Yes | Yes | Yes | `rate_per_sqft`, `nnn_rate` |
| CL2 | **Pass-Through Expense Includes** (structured checklist) + **Initial Pass-Through Expenses** ($ + Flat-Monthly / Annual $-per-SqFt) | Commercial Lease | **High** | Converts free-text CAM into structured, comparable data for total-occupancy-cost matching & Ask AI | Yes | Yes | Yes | `low_cam`, `nnn_transparent` |
| CL3 | **Commercial Transaction Terms** (Annual Rate Increase / Improvement Allowance / Pre-Leasing / Sub-Leasing) — structured | Commercial Lease | **High** | Structures escalations, TI allowance, build-to-suit, and sublease availability for matching | Yes | Yes | Yes | `ti_allowance`, `build_to_suit`, `sublease_ok`, `escalation` |
| CL4 | **Loading / Dock config** (see CS1) | Commercial Lease | **High** | Same industrial/warehouse driver as sale side | Yes | Yes | Yes | `dock_high`, `drive_in`, `clear_span` |
| CL5 | **Number of Tenants / Tenant Count** | Commercial Lease | **Medium** | Multi-tenant context / co-tenancy | Yes | Yes | Yes | `multi_tenant` |
| CL6 | **Total Parking Spaces / ratio**, **Signage**, **Adjoining Property**, **Door dimensions** | Commercial Lease | **Medium** | Retail/office fit filters (mirror CS5–CS7) | Yes | Yes | Yes | `parking_ratio`, `high_visibility` |
| CL7 | **Freezer Space / Freestanding / Converted Residence** | Commercial Lease | **Low** | Restaurant/retail niche | No | Yes | Yes | `restaurant_ready` |

### Do NOT add
Use Code / municipal codes; legal survey IDs; agent/office; showing/distribution; Realtor Only/Confidential; signatures; Property Manager contact.

## 5. BUSINESS OPPORTUNITY  (Stellar Business Opportunity ↔ Seller-Business + Buyer-Business)

**BYO is ahead of Stellar here.** BYO already captures SDE/EBITDA, gross profit, inventory value, FF&E value, reason for sale, employee count, NDA-required, financial-statements-available, tax-returns-available, and business-location-leased sub-terms — none of which exist as discrete Stellar fields (Stellar pushes them into Remarks/Documents). Keep and promote these as differentiators.

### Already Implemented
Business Name; Year Established; Business Type (`business_type`, 50-category list); Real Estate Included (`real_estate_purchase`); Sale Includes (`sale_includes`); Licenses (Beer/Wine, Liquor, On/Off-site); Annual Gross Income / NOI / Annual Expenses / Cap Rate; Anchor/Co-Tenant context; Financing incl. SBA/Owner/Lease-Back; plus the BYO-only extras above (SDE/EBITDA, inventory, FF&E, reason for sale, employees, NDA, statements available).

### Covered under a different name
Stellar **Sale Includes** = BYO `sale_includes`; **Business Type** = `business_type`; **Confidentiality Letter Req** (NDA) = BYO `nda_required`; **Real Estate Included** = `real_estate_purchase`.

### Partial
- **Ownership legal structure** — Stellar Ownership adds *Franchise / Sole Proprietor / LLC / Corporation / Partnership / Leasehold*. BYO doesn't capture the entity/franchise structure. → add (franchise resale is a distinct buyer intent).

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| B1 | **Ownership / Entity Structure** (Franchise / Sole Proprietor / LLC / Corporation / Partnership / Leasehold) | Business | **High** | Franchise vs independent is a primary buyer filter; entity form affects the deal | Yes | Yes | Yes | Business DNA: `franchise`, `independent`, `absentee_owner` |
| B2 | **Number of Tenants / Anchor or Co-Tenant** | Business (retail/strip) | **Medium** | For strip/retail business sales with sub-tenants | Yes | Yes | Yes | `anchored`, `multi_tenant` |
| B3 | **NOI Type / Income Type** (Actual vs Projected) | Business | **Medium** | Trust signal on the stated financials | No | Yes | Yes | `actual_financials` |
| B4 | **Hours / Days of Operation** | Business | **Low** | Operational profile for buyers (absentee-friendly?) | No | Yes | Yes | `absentee_run`, `limited_hours` |
| B5 | **Non-Compete** (Y/N + term) & **Seller Training** (Y/N + period) | Business | **Low/Medium** | Common terms buyers screen on; currently only free-text | No | Yes | Yes | `training_provided`, `non_compete` |
| B6 | **Freezer Space / Converted Residence / Freestanding** | Business (restaurant/retail) | **Low** | Use-fit flags | No | Yes | Yes | `restaurant_ready` |

### Do NOT add
Use Code; legal survey IDs; agent/office/closing; showing/distribution; Realtor Confidential; signatures. (Reason for Sale / Employees / NDA are already BYO fields — do not re-add.)

---

## 6. VACANT LAND  (Stellar Vacant Land ↔ Seller-Vacant Land + Buyer-Vacant Land)

**BYO Seller-Vacant-Land is strongly aligned.** It already has current use, adjacent use, zoning, buildable, utility-availability (water/sewer/electric/gas/telecom), road frontage/surface, wells/septics, fences, vegetation, easements, front footage, lot dimensions, and acreage.

### Already Implemented
Current Use (`current_use`); Current Adjacent Use (`current_adjacent_use`); Zoning; Buildable (`buildable`); Water/Sewer/Electric/Gas/Telecom Available; # Wells / # Septics; Road Frontage; Road Surface; Fences; Vegetation; Easements (+ water rights via list); Front Footage; Lot Dimensions; Total Acreage (+ `min_acreage`); Flood Zone; Waterfront/Dock; Financing; HOA.

### Covered under a different name
Stellar **Current Use** (Property Style) = BYO `current_use`; **Utilities** (available) = BYO `*_available` flags; **Front Footage** = same.

### Partial
- **Exact Lot Size SqFt / Acres** — BYO has acreage range + dimensions; add numeric sqft/acres (Buyer criteria especially).
- **Buyer-Vacant-Land is sparse** — the BYO Buyer flow only captures style/acreage/view structurally; **land buyers cannot specify zoning / utilities / topography / buildability as structured criteria** (free-text KB only). → this is the biggest land gap (see VL-buyer items).

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| VL1 | **Structured land criteria on the Buyer side** (Zoning intent, Utilities-required, Buildable, Min acreage, Road access, Current-use intent) | Vacant Land — **Buyer** | **High** | Land buyers today can't express structured wants; blocks land matching & search entirely on the criteria side | Yes | n/a (criteria) | Yes | Buyer DNA: `land_use_intent`, `utilities_required`, `buildable_only` |
| VL2 | **Lot Features** (Topography: Hilly/Level/Sloped/Rolling; Wetlands; Flood Plain; Conservation Area; Cleared/Wooded; Filled/May-Need-Fill; Compact Soil; Brownfield) | Vacant Land | **High** | Topography/wetlands/soil drive buildability & cost — top land-valuation signals; currently unmodeled | Yes | Yes | Yes | Land DNA: `level_lot`, `wetlands`, `cleared`, `wooded`, `buildable` |
| VL3 | **Future Land Use** designation + **Zoning Compatible** (Y/N) | Vacant Land | **Medium** | Development potential; comp/entitlement AI | Yes | Yes | Yes | `development_potential`, `rezoning_candidate` |
| VL4 | **Farm Type** + **AG Exemption** (Y/N) | Vacant Land (agricultural) | **Medium** | Ag classification affects taxes & use; farm-buyer target audience | Yes | Yes | Yes | `agricultural`, `ag_exemption`, `equestrian` |
| VL5 | **Exact Lot Size** (SqFt + Acres numeric) | Vacant Land | **Medium** | Precise acreage search | Yes | Yes | Yes | `large_acreage` |
| VL6 | **Planned Unit Development (PUD)** (Y/N) + **Additional Parcels / Total # Parcels** | Vacant Land | **Low/Medium** | Assemblage & subdivision potential | Yes | Yes | Yes | `assemblage`, `subdividable` |
| VL7 | **Horse/Barn amenities** (already partly in BYO?) + **# Paddocks/Stalls** | Vacant Land (equestrian) | **Low** | Equestrian target audience | Yes | Yes | Yes | `equestrian`, `horses_allowed` |

### Do NOT add
Legal survey IDs; State/County Land-Use & Property-Use codes; Designated Builder; agent/office/closing; showing/distribution; signatures.

---

## 7. RESIDENTIAL RENTAL  (Stellar Rental ↔ Landlord-Residential-Rental + Tenant-Residential-Rental)

### Already Implemented
Rent Price + Lease Amount Frequency; Lease Term / desired lease length; Minimum Lease; Date Available; Security Deposit (`security_deposit_amount` / tenant `security_deposit_budget`); Rent Includes; Furnishings; Pets Allowed + pet deposit/monthly-fee/fee/rent + restrictions (BYO ahead here); Housing for Older Persons (`leasing_55_plus`); Property Style; all interior/exterior physical attributes; Association info; Occupant Type; applicant pre-screening (Landlord: credit/income/employment/eviction/bankruptcy/criminal/reference/verification — BYO ahead); tenant pre-screening self-disclosure (Tenant: income, credit range, screening concerns, smoking, occupants — BYO ahead).

### Covered under a different name
Stellar **Furnishings** = `tenant_require`; **Housing for Older Persons** = `leasing_55_plus`; **Rent Includes** = `rent_includes`; **Lease Term/Minimum Lease** = `desired_lease_length`.

### Partial
- **Association Fees for Tenants** (Application/Approval, Security Deposit, Parking, Other + frequencies) — BYO has generic HOA + applicant reqs; Stellar structures tenant-borne association fees. → add for true move-in cost.
- **Tenant Pays** (utilities the tenant covers) — BYO Landlord has `est_*` utility *estimates*; add explicit tenant-pays responsibility list.

### MISSING — recommended additions

| # | Field | Property Type(s) | Priority | Why add | Search? | Public? | Ask AI? | DNA / lifestyle tags |
|---|---|---|---|---|---|---|---|---|
| RN1 | **Application Fee** + **Additional Applicant Fee** | Rental (Landlord) | **High** | Standard, expected rental cost; missing today; affects true move-in cost & tenant matching | Yes | Yes | Yes | `application_fee` |
| RN2 | **Short-Term / Seasonal block** (Long-Term Y/N, Seasonal Rent, Off-Season Rent, Weeks/Months Available calendar) | Rental (Landlord + Tenant) | **High** | FL seasonal/vacation rentals are a huge segment BYO can't currently express (seasonal pricing + availability windows) | Yes | Yes | Yes | Rental DNA: `seasonal`, `snowbird`, `vacation_rental`, `annual` |
| RN3 | **Association Fees for Tenants** (Approval Fee, Security Deposit, Parking Fee, Other + frequency) | Rental (Landlord) | **Medium** | Real move-in cost transparency; association-approval friction is a tenant concern | Yes | Yes | Yes | `hoa_approval_required`, `move_in_cost` |
| RN4 | **Association Approval Required** (Y/N) + **Approval Process/Timeframe** | Rental (Landlord) | **Medium** | Approval delay is decisive for move-in timing; Ask AI intent | Yes | Yes | Yes | `association_approval` |
| RN5 | **Tenant Pays** (utilities responsibility list) | Rental (Landlord + Tenant) | **Medium** | Clarifies total cost of occupancy; complements Rent Includes | Yes | Yes | Yes | `tenant_pays_utilities` |
| RN6 | **Primary Bed Size** (King/Queen/etc.) | Rental (furnished/seasonal) | **Low** | Relevant for furnished/seasonal listings | No | Yes | Partial | `furnished_detail` |
| RN7 | **Pet Restrictions Source** (Association vs Landlord) | Rental | **Low** | Clarifies who sets the pet rule (negotiability signal for AI) | No | Yes | Yes | `pet_rule_source` |

*(RentSpree screening integration = Do NOT add — BYO has its own pre-screening. Accessibility Features (R4) and Community Features (R8) from the Residential section apply equally to rentals — do not duplicate the recommendation.)*

### Do NOT add
Long-Term flag as an *admin* toggle (adopt only as part of the seasonal block RN2), Office Exclusive, listing agreement/service type, legal survey IDs, agent/office, RentSpree, showing/distribution/IDX/VOW, signatures, Owner/Tenant contact.

---

# MASTER FINDINGS & ROADMAP

## 1. Critical fields missing before launch

These block a credible launch of the affected property type (listing feels incomplete, or matching/search can't function):

1. **Schools (Elementary / Middle / High)** — Residential (Seller + Buyer). The single biggest residential decision factor; absence is conspicuous.
2. **Lease Price Unit ($/sqft vs monthly) + Lease Amount Frequency** — Commercial Lease (Landlord + Tenant). Commercial rents are meaningless without a rate unit; blocks commercial-lease comparison/search/matching.
3. **Structured land criteria on the Buyer side** — Vacant Land (Buyer). Land buyers currently cannot state zoning/utilities/buildability wants; land matching is impossible without it.
4. **Application Fee + move-in cost fields** — Residential Rental. Expected on every rental; needed for true cost-of-occupancy.
5. **Exact Lot Size (SqFt/Acres)** — where BYO only stores acreage *ranges* today and a numeric filter is expected (Residential, Vacant Land).
6. **Ownership / Entity Structure (Franchise vs Independent)** — Business Opportunity. Primary buyer filter for business listings.

*(Everything else below is enhancement, not launch-blocking. Note: several "launch" gaps are narrow — BYO's physical-attribute coverage is otherwise launch-ready.)*

## 2. High-value lifestyle / DNA metadata opportunities

The highest ROI net-new data for BYO's AI/DNA (all currently Missing), because they turn into rich, searchable, marketable tags:

- **Front Exposure** → sun orientation (`exposure_south`, `natural_light`, `sunset_view`).
- **Architectural Style** → property personality (`key_west`, `mediterranean`, `mid_century`, `coastal`).
- **Community Features** → lifestyle/target-audience (`gated`, `golf_community`, `dog_friendly`, `active_adult`, `walkable`, `family_amenities`).
- **Accessibility Features** → underserved audience (`single_level`, `wheelchair_accessible`, `aging_in_place`).
- **In-Law Suite / ADU + Additional Rooms** → (`multigenerational`, `home_office`, `income_potential`, `media_room`, `flex_space`).
- **Disaster Mitigation** → FL insurability/resilience (`hurricane_hardened`, `impact_windows`, `above_flood_plain`, `insurance_friendly`).
- **Solar / Green Energy + Ownership** → (`solar_owned`, `energy_efficient`, `low_utility_cost`).
- **Patio/Porch, Fireplace, Pool detail** → cozy/outdoor-living lifestyle (`screened_lanai`, `outdoor_living`, `fireplace`, `heated_pool`).
- **Purchase-purpose / rental-purpose** (BYO already has these) → tie into Buyer/Tenant DNA (`investor`, `snowbird`, `primary_residence`).

These pair with BYO's existing **Location DNA** (schools, commute, flood, POIs) to produce a genuinely differentiated buyer/tenant DNA profile.

## 3. Commercial-specific gaps (Sale + Lease)

- **Rate normalization:** Lease Price Unit ($/sqft vs monthly) + frequency — *Critical* for lease.
- **Structured CAM / pass-throughs** (checklist + initial amount + flat vs $/sqft) — replaces BYO free-text.
- **Structured Commercial Transaction Terms** (annual escalation, TI allowance, pre-leasing/build-to-suit, sub-leasing) — replaces BYO free-text.
- **Loading/dock configuration** (dock-high/grade/well bays, door dims, truck doors, high bays, clear span, columns) — industrial matching.
- **Tenancy economics (sale):** number of tenants (single/multi), anchor/co-tenant, vacancy rate, gross scheduled income, NOI actual-vs-projected, income-includes.
- **Parking count/ratio, signage, adjoining property, restrooms/offices/conference-room counts** — retail/office fit.
- **Space Classification (A–D) on the sale side** (already on lease side).

## 4. Income-property gaps

- **Individually-metered utilities** (per-utility flags) — investor value driver.
- **Gross Scheduled Income + Est. Market Income** (actual vs pro-forma) and **Total Monthly Rent/Expenses** aggregates — underwriting AI.
- **Financial Source** (accountant/tax-return/owner) — credibility signal.
- **Structured Terms-of-Lease / Tenant-Pays** for income units.
- (BYO already leads on unit-mix/rent-roll grid, NOI, cap rate, meter counts — keep.)

## 5. Rental-specific gaps

- **Application Fee + Additional Applicant Fee** — *High*, expected on every rental.
- **Short-Term / Seasonal block** (long-term flag, seasonal & off-season rent, weeks/months-available calendar) — *High*, FL seasonal market.
- **Association Fees for Tenants** (approval/parking/security + frequency) & **Association Approval Required + process/timeframe** — real move-in cost & timing.
- **Tenant Pays** utilities responsibility.
- (BYO already leads on pet economics + pre-screening — keep.)

## 6. Vacant-land gaps

- **Structured Buyer-side land criteria** — *High*, currently absent.
- **Lot Features** (topography, wetlands, flood plain, conservation, cleared/wooded, soil, buildable) — top valuation drivers.
- **Future Land Use + Zoning Compatible** — development potential.
- **Farm Type + AG Exemption** — agricultural/equestrian audience.
- **Exact Lot Size (sqft/acres)**, **PUD**, **additional parcels/assemblage**.

## 7. Business-opportunity gaps

- **Ownership / Entity Structure** (Franchise/Sole-Prop/LLC/Corp/Partnership) — *High*.
- **NOI Actual-vs-Projected**, **Number of Tenants / Anchor** (retail).
- **Hours/Days of Operation**, **Non-Compete + term**, **Seller Training + period** (structure BYO's free-text).
- (BYO already leads on SDE/EBITDA, inventory, FF&E, reason-for-sale, employees, NDA — keep and market these.)

## 8. Top 100 recommended field additions

Ordered by priority tier, then domain. Flags: **S**=should be searchable, **P**=display publicly, **AI**=Ask AI should use it. All are net-new to BYO (or a structured replacement of a BYO free-text field). This list intentionally **excludes** MLS-admin fields.

### Tier: CRITICAL (1–15)
1. Elementary School — Residential (Seller+Buyer) — S/P/AI
2. Middle School — Residential — S/P/AI
3. High School — Residential — S/P/AI
4. School Zone (verified, Location-DNA derived) — Residential — S/P/AI
5. Lease Price Unit ($/SqFt vs Total Monthly) — Commercial Lease — S/P/AI
6. Lease Amount Frequency (Annual/Monthly) — Commercial Lease — S/P/AI
7. Buyer land criteria — Zoning intent — Vacant Land (Buyer) — S/AI
8. Buyer land criteria — Utilities required — Vacant Land (Buyer) — S/AI
9. Buyer land criteria — Buildable required — Vacant Land (Buyer) — S/AI
10. Buyer land criteria — Minimum acreage — Vacant Land (Buyer) — S/AI
11. Application Fee — Residential Rental (Landlord) — S/P/AI
12. Additional Applicant Fee — Residential Rental — S/P/AI
13. Lot Size — exact SqFt (numeric) — Residential + Land — S/P/AI
14. Lot Size — exact Acres (numeric) — Residential + Land — S/P/AI
15. Ownership / Entity Structure (Franchise/Sole-Prop/LLC/Corp/Partnership) — Business — S/P/AI

### Tier: HIGH (16–67)
16. Front Exposure (compass orientation) — Residential + all — S/P/AI
17. Architectural Style (Coastal/Key West/Mediterranean/Colonial/…) — Residential — S/P/AI
18. Accessibility Features (24-value set) — Residential/Rental/Commercial — S/P/AI
19. In-Law Suite / ADU (Y/N) — Residential — S/P/AI
20. In-Law Suite description (attached/detached/kitchen/private-entrance) — Residential — S/P/AI
21. In-Law Suite sqft (under-air + total) — Residential — P/AI
22. Additional Room — Home Office / Den — Residential — S/P/AI
23. Additional Room — Bonus Room — Residential — S/P/AI
24. Additional Room — Media Room — Residential — S/P/AI
25. Additional Room — Florida Room / Sunroom — Residential — S/P/AI
26. Additional Room — Great Room — Residential — S/P/AI
27. Additional Room — Loft — Residential — S/P/AI
28. Community Feature — Gated (Guard / No Guard) — Residential + all — S/P/AI
29. Community Feature — Golf Community — Residential — S/P/AI
30. Community Feature — Dog Park — Residential — S/P/AI
31. Community Feature — Playground — Residential — S/P/AI
32. Community Feature — Fitness Center — Residential — S/P/AI
33. Community Feature — Clubhouse — Residential — S/P/AI
34. Community Feature — Tennis / Pickleball — Residential — S/P/AI
35. Community Feature — Sidewalks / Walkable — Residential — S/P/AI
36. Community Feature — Deed Restrictions — Residential — S/P/AI
37. Disaster Mitigation — Hurricane Shutters / Impact Windows — Residential + all — S/P/AI
38. Disaster Mitigation — Above Flood Plain — Residential + all — S/P/AI
39. Disaster Mitigation — Safe Room — Residential — P/AI
40. Disaster Mitigation — Hurricane-Insurance-Deduction Qualified — Residential — P/AI
41. Green Energy Generation (Y/N + Solar/Wind/Hydro) — Residential + all — S/P/AI
42. Solar Panel Ownership (Owned/Leased/Financed/Utility) — Residential + all — S/P/AI
43. Solar Lease/Finance Terms (Assumable/Seller-paid-at-closing) — Residential — P/AI
44. Individually-Metered utilities (per-utility flags) — Income — S/P/AI
45. Loading — # Bays Dock-High — Commercial Sale + Lease — S/P/AI
46. Loading — # Bays Grade-Level — Commercial Sale + Lease — S/P/AI
47. Loading — # Bays Dock-Well — Commercial Sale + Lease — S/P/AI
48. Loading — Door Height / Width — Commercial — S/P/AI
49. Building — High Bays / Clear Span / Columns — Commercial — S/P/AI
50. Number of Tenants (Single / Multi / Vacant) — Commercial Sale + Lease — S/P/AI
51. Anchor or Co-Tenant — Commercial Sale — P/AI
52. Pass-Through Expense Includes (structured CAM checklist) — Commercial Lease — S/P/AI
53. Initial Pass-Through Expenses ($ + Flat-Monthly/Annual-$per-SqFt) — Commercial Lease — S/P/AI
54. Commercial Transaction Term — Annual Rate Increase / Escalation — Commercial Lease — S/P/AI
55. Commercial Transaction Term — Improvement (TI) Allowance — Commercial Lease — S/P/AI
56. Commercial Transaction Term — Pre-Leasing / Build-to-Suit — Commercial Lease — S/P/AI
57. Commercial Transaction Term — Sub-Leasing available — Commercial Lease — S/P/AI
58. Seasonal — Long-Term (Y/N) indicator — Rental — S/AI
59. Seasonal — Seasonal Rent — Rental — S/P/AI
60. Seasonal — Off-Season Rent — Rental — S/P/AI
61. Seasonal — Weeks / Months Available calendar — Rental — S/P/AI
62. Lot Feature — Topography (Level/Sloped/Hilly/Rolling) — Vacant Land + Residential — S/P/AI
63. Lot Feature — Wetlands — Vacant Land — S/P/AI
64. Lot Feature — Flood Plain — Vacant Land — S/P/AI
65. Lot Feature — Conservation Area — Vacant Land — S/P/AI
66. Lot Feature — Cleared / Wooded — Vacant Land — S/P/AI
67. Fireplace (Y/N) — Residential — S/P/AI

### Tier: MEDIUM (68–95)
68. Fireplace Description (Gas/Wood/Electric + location) — Residential — P/AI
69. Ownership Type (Fee Simple/Condo/Co-op/Leasehold/Fractional) — Residential + all — S/P/AI
70. Land Lease (Y/N + annual fee) — Residential + all — S/P/AI
71. Patio & Porch Features (Screened/Covered/Lanai/Wrap-Around) — Residential — S/P/AI
72. Property Position (Corner/End/Penthouse/High-Rise/Mid-Rise/Stilt) — Residential (condo) — S/P/AI
73. Floor Number — Residential (condo) — S/P/AI
74. Total # of Floors — Residential (condo) — P/AI
75. Building Elevator (Y/N) — Residential (condo) — S/P/AI
76. Window Features (Impact/Double-Pane/ENERGY STAR/Tinted) — Residential + all — S/P/AI
77. Fencing (extend to Seller/Buyer Residential) — Residential — S/P/AI
78. Security Features (extend to Seller/Buyer Residential) — Residential — S/P/AI
79. Floor Covering (extend to Seller/Buyer Residential) — Residential — S/P/AI
80. Spa + Spa Features — Residential — S/P/AI
81. Existing-Lease detail (monthly rent + lease end + notice) — Residential/Income sales — P/AI
82. Gross Scheduled Income — Income + Commercial Sale — P/AI
83. Est. Market (potential) Income — Income — P/AI
84. Financial Source (Accountant/Tax-Return/Broker/Owner) — Income + Business — P/AI
85. Total Monthly Rent + Total Monthly Expenses (aggregate) — Income — S/P/AI
86. Terms of Lease / Tenant Pays (structured) — Income — P/AI
87. NOI Type (Actual vs Projected) — Commercial Sale + Income + Business — P/AI
88. Vacancy Rate — Commercial Sale — S/P/AI
89. Income Includes (Rent/Parking/Storage/Laundry) — Commercial Sale — P/AI
90. Total Parking Spaces / ratio — Commercial Sale + Lease — S/P/AI
91. Signage (Pole/On-Building/Directory/Street) — Commercial Sale + Lease — S/P/AI
92. Adjoining Property / Adjacent Use — Commercial Sale + Lease — P/AI
93. # of Restrooms / Offices / Conference Rooms (extend to sale) — Commercial — S/P/AI
94. Space Classification (A/B/C/D) on Sale side — Commercial Sale — S/P/AI
95. Association Fees for Tenants (approval/parking/security + freq) — Rental — S/P/AI

### Tier: MEDIUM / LOW (96–100)
96. Association Approval Required + Process/Timeframe — Rental — S/P/AI
97. Tenant Pays (utilities responsibility list) — Rental + Income — S/P/AI
98. Future Land Use + Zoning Compatible (Y/N) — Vacant Land — S/P/AI
99. Farm Type + AG Exemption (Y/N) — Vacant Land — S/P/AI
100. Non-Compete (+term) & Seller Training (+period), structured — Business — P/AI

**Low-priority backlog (beyond the Top 100, adopt opportunistically):** Green Building Certifications (LEED/ENERGY STAR/HERS); Indoor Air Quality (MERV/low-VOC); Room-dimensions grid; Primary Bed Size (furnished/seasonal); Pet-Restriction Source (Association vs Landlord); PUD (Y/N); Additional Parcels / assemblage; GRM (derived); Hours/Days of Operation (Business); Freezer Space / Freestanding / Converted Residence (commercial/business); Barn/Horse amenities + paddocks/stalls (equestrian land).

## 9. Top 100 recommended metadata / lifestyle tags

Tag slugs for Property DNA / Buyer DNA / Tenant DNA / Location DNA / lifestyle / target-audience layers. Most are **derivable** from the fields in Section 8 plus BYO's existing data — they are the searchable/marketable output of the new fields, not new inputs. Grouped by DNA layer.

**Location DNA (1–12):** 1 `school_zone_rated` · 2 `top_school_district` · 3 `walkable` · 4 `near_public_transit` · 5 `golf_community` · 6 `gated_community` · 7 `waterfront` · 8 `water_view` · 9 `beach_proximity` · 10 `downtown_proximity` · 11 `short_commute` · 12 `low_hoa`

**Property DNA — style/character (13–24):** 13 `architectural_coastal` · 14 `architectural_key_west` · 15 `architectural_mediterranean` · 16 `architectural_modern` · 17 `architectural_traditional` · 18 `new_construction` · 19 `move_in_ready` · 20 `fixer_upper` · 21 `luxury` · 22 `historic` · 23 `single_level` · 24 `multi_story`

**Property DNA — features (25–45):** 25 `pool_home` · 26 `heated_pool` · 27 `spa_hot_tub` · 28 `screened_lanai` · 29 `outdoor_living` · 30 `fireplace` · 31 `gourmet_kitchen` · 32 `open_floorplan` · 33 `high_ceilings` · 34 `smart_home` · 35 `home_office` · 36 `media_room` · 37 `flex_space` · 38 `florida_room` · 39 `bonus_room` · 40 `oversized_garage` · 41 `rv_boat_parking` · 42 `ev_charging` · 43 `large_lot` · 44 `corner_lot` · 45 `cul_de_sac`

**Lifestyle / exposure (46–55):** 46 `exposure_south` · 47 `natural_light` · 48 `sunset_view` · 49 `sunrise_view` · 50 `private_backyard` · 51 `fenced_yard` · 52 `dog_friendly` · 53 `equestrian` · 54 `boater_lifestyle` · 55 `golf_cart_community`

**Target audience (56–68):** 56 `family_oriented` · 57 `active_adult_55plus` · 58 `multigenerational` · 59 `remote_worker_ready` · 60 `investor_ready` · 61 `snowbird_seasonal` · 62 `first_time_buyer` · 63 `downsizer` · 64 `accessibility_ready` · 65 `aging_in_place` · 66 `wheelchair_accessible` · 67 `pet_owner_friendly` · 68 `eco_conscious`

**Resilience / green / cost (69–80):** 69 `hurricane_hardened` · 70 `impact_windows` · 71 `above_flood_plain` · 72 `insurance_friendly` · 73 `flood_zone_low_risk` · 74 `solar_owned` · 75 `energy_efficient` · 76 `low_utility_cost` · 77 `green_certified` · 78 `no_cdd` · 79 `low_property_tax` · 80 `assumable_financing`

**Financial / deal (81–88):** 81 `cash_flowing` · 82 `value_add` · 83 `high_cap_rate` · 84 `seller_financing_available` · 85 `lease_option_available` · 86 `turnkey_investment` · 87 `tenant_occupied` · 88 `below_market_rent`

**Commercial (89–95):** 89 `nnn_investment` · 90 `single_tenant_net_lease` · 91 `multi_tenant` · 92 `anchored_center` · 93 `industrial_dock_high` · 94 `high_visibility_retail` · 95 `build_to_suit`

**Land (96–98):** 96 `buildable_lot` · 97 `agricultural_zoned` · 98 `development_potential`

**Rental (99–100):** 99 `furnished_rental` · 100 `annual_lease`

> **Tagging principle:** tags should be **derived** at save/DNA-generation time from structured fields (Section 8) + existing BYO data, not hand-entered — this keeps them consistent for matching, search facets, and Ask AI retrieval. Store them on the listing's DNA profile and expose as search facets.

## 10. Recommended implementation order

Sequenced to unblock launch first, then maximize DNA/marketing value, then long-tail. Each wave is a coherent migration + Livewire + Blade + display + Ask-AI-registry + DNA-tag unit of work.

**Wave 0 — Launch blockers (Critical).**
- Schools (Elementary/Middle/High) on Residential Seller + Buyer (+ wire into Location DNA and Ask AI).
- Commercial-Lease rate normalization: Lease Price Unit + Lease Amount Frequency (Landlord + Tenant).
- Structured Buyer-side Vacant-Land criteria (zoning/utilities/buildable/min-acreage).
- Rental Application Fee + Additional Applicant Fee.
- Exact Lot Size (SqFt/Acres) numeric on Residential + Land.
- Business Ownership/Entity Structure.

**Wave 1 — High-value residential DNA & lifestyle.**
- Front Exposure; Architectural Style; Accessibility Features; In-Law Suite/ADU + Additional Rooms; Community Features; Fireplace.
- Wire each into the DNA tag deriver + search facets + Ask AI registry.

**Wave 2 — Florida resilience & energy (marketing/insurance edge).**
- Disaster Mitigation (hurricane shutters/impact windows/above-flood-plain/safe-room/insurance-qualified).
- Green Energy Generation + Solar Panel Ownership + Solar Finance Terms.
- Window Features (impact); Patio/Porch; Spa.

**Wave 3 — Commercial economics & industrial matching.**
- Loading/dock configuration; structured Pass-Through/CAM; structured Commercial Transaction Terms (escalation/TI/build-to-suit/sublease); Number of Tenants + Anchor; Parking count/ratio; Signage; Space Class on sale side; restrooms/offices/conference counts.

**Wave 4 — Income & investor intelligence.**
- Individually-metered utilities; Gross Scheduled + Market Income; Financial Source; Total Monthly Rent/Expenses; NOI Type; Vacancy Rate; structured Terms-of-Lease/Tenant-Pays.

**Wave 5 — Rental depth & seasonal market.**
- Short-Term/Seasonal block (seasonal/off-season rent + availability calendar); Association Fees for Tenants + Approval Required/process; Tenant Pays.

**Wave 6 — Land depth.**
- Lot Features (topography/wetlands/flood-plain/conservation/cleared-wooded); Future Land Use + Zoning Compatible; Farm Type + AG Exemption; PUD/assemblage.

**Wave 7 — Ownership/legal + condo depth + backlog.**
- Ownership Type + Land Lease; condo Floor/Elevator/Position; extend Fencing/Security/Floor-Covering to sale side; Existing-Lease detail; then the Low-priority backlog list.

**Cross-cutting (every wave):** for each field added, also (a) add its option list to config, (b) mirror listing↔criteria (Seller/Landlord ↔ Buyer/Tenant), (c) register it in `AskAiFieldQuestionRegistryService`, (d) add derived DNA/lifestyle tags, (e) add search facets where flagged **S**, (f) respect the "Do NOT add" exclusions. Also fold in the BYO-internal duplicate cleanups noted per section (e.g., `condition_prop` vs `condition_prop_buyer`, empty `pet_policy`, tour-URL label inversion).

---

## Files reviewed

- **Source of truth:** `docs/bid-your-offer-field-audit.md` (BYO Field Audit — full in context).
- **Stellar forms (all pages):** `attached_assets/{Residential,Income,Commercial_Sale,Business_Opportunity,Commercial_Lease,Rental,Vacant_Land}_Data_Entry_Form_1782925021820.pdf`.
- **Extracted Stellar inventories (working artifacts):** `scratchpad/stellar-{residential,income,commercial-sale,business,commercial-lease,rental,vacant-land}.md` (exhaustive per-form field inventories, one section per form section, ~1,000+ fields total across forms).

Field counts inventoried from Stellar (consumer + admin): Residential ~205 · Income ~197 · Commercial Sale 146 · Business Opportunity 226 · Commercial Lease 132 · Rental ~155 · Vacant Land 133.

## Recommended next step

Turn this roadmap into an executable backlog: create a ticket per **Wave 0** item (schools, commercial rate unit, buyer land criteria, rental application fee, exact lot size, business entity), each specifying the migration, Livewire props, Blade tab, public-display binding, Ask-AI registry entry, and derived DNA tags — then proceed wave by wave. Because BYO's physical-attribute coverage already matches Stellar, the effort concentrates on the ~15 launch items and the high-value lifestyle/DNA layer (Waves 1–2), not on wholesale field porting.
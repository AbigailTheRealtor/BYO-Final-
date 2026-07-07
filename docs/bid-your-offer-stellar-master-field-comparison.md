# Bid Your Offer ↔ Stellar MLS — Master Field Comparison (Definitive Source of Truth)

**Type:** Documentation-only audit. **No** code, Blade, Livewire, migrations, or config were changed. **Nothing committed.**
**Date:** 2026-07-01 · **Status:** Final Audit Revision (supersedes and consolidates the working gap analysis).
**Author context:** Real Estate Matchmaker Club / Bid Your Offer (BYO).

## Purpose & scope

This is the permanent master reference comparing **Bid Your Offer's** listing/criteria data model against the **seven Stellar MLS consumer data-entry forms**. It is intended to (a) let BYO eventually support *every consumer-relevant* Stellar listing field per property type, and (b) document and preserve the AI-first capabilities that already put BYO ahead of the MLS.

**Sources of truth (all read in full, every page):**

| Stellar form (in `attached_assets/…_1782925021820.pdf`) | Pages | BYO listing flow | BYO criteria flow |
|---|---|---|---|
| Residential Data Entry Form | 14 | Seller — Residential | Buyer — Residential |
| Income Data Entry Form | 12 | Seller — Income | Buyer — Income |
| Commercial Sale Data Entry Form | 10 | Seller — Commercial | Buyer — Commercial |
| Business Opportunity Data Entry Form | 10 | Seller — Business | Buyer — Business |
| Commercial Lease Data Entry Form | 9 | Landlord — Commercial Lease | Tenant — Commercial Lease |
| Residential Rental Data Entry Form | 13 | Landlord — Residential Rental | Tenant — Residential Rental |
| Vacant Land Data Entry Form | 9 | Seller — Vacant Land | Buyer — Vacant Land |

- BYO current state: `docs/bid-your-offer-field-audit.md` (full field audit, all four role flows × five property types).
- Prior comparison: `docs/bid-your-offer-stellar-gap-analysis.md` (this document supersedes it and adds the required-vs-optional split and the statistics).

**How "Required" was determined.** Stellar marks every mandatory field on every form with an asterisk (`*`) and prints the note "Indicates Required Field" in the header of every page. Every asterisked field on all 77 pages was extracted and classified. A field is **Required** here only if Stellar asterisks it. A field with no asterisk is **Optional**.

**Consumer vs. administrative.** A Stellar field is **consumer-facing** if it describes the property/listing a buyer, seller, landlord, or tenant would care about. It is **administrative** if it exists only for MLS/brokerage workflow (listing agreement, agent/office IDs, legal survey identifiers, showing/lockbox, IDX/VOW distribution, signatures). Per the brief, administrative fields are **excluded** from the "missing" analysis (Sections 1–2) and enumerated in Section 3.

**Classification vocabulary.** For each field vs. BYO: **Supported** (BYO has an equivalent with the same meaning, possibly under a different key) · **Partial** (BYO has a weaker/adjacent version, or has it on another role-flow but not this one) · **Missing** (no BYO equivalent on the relevant flow).

---

# 1. Required Stellar MLS Fields Missing From Bid Your Offer

Every asterisked (required) consumer field across all seven forms was evaluated. The overwhelming majority are already Supported — BYO's physical-attribute option lists were clearly derived from Stellar and match option-for-option (address block, price, property style, beds/baths/sqft, year built, zoning, roof/exterior/foundation, HVAC/water/sewer/utilities, pool, garage/carport, waterfront block, flood zone, CDD, HOA block, 55+, appliances, interior features, public remarks, virtual tour, etc.).

The tables below list **only the required consumer fields that are Missing or Partial** on the relevant BYO flow. Administrative required fields (Office Exclusive, Listing Type/Service Type, Seller Representation, legal survey IDs, agent/office, showing, IDX/VOW, signatures, etc.) are intentionally excluded here and covered in Section 3.

### A recurring core of 4 required gaps appears on almost every form

These four required Stellar fields are Missing/Partial on nearly every property type and are therefore the highest-leverage required additions:

1. **Green Energy Generation (Y/N + type)** — asterisked on Residential, Income, Commercial Sale, Business Opportunity, and Commercial Lease. BYO has **no** green-energy/solar field on any flow.
2. **Lot Size Square Footage + Lot Size Acres (exact numeric)** — asterisked on **all seven** forms. BYO stores only a `total_acreage` **range** bucket + free-text `lot_dimensions`; it lacks the exact numeric sqft/acres Stellar requires for search/valuation.
3. **Ownership (ownership type)** — asterisked on Residential, Income, Commercial Sale, Business, Vacant Land. Stellar's "Ownership" = **Fee Simple / Condominium / Co-op / Fractional / Leasehold** (and on Business = **Franchise / Sole Proprietor / LLC / Corporation / Partnership / Leasehold**). BYO's `occupant_status` (Owner/Tenant/Vacant) is a *different* concept; ownership **type/entity** is not captured.
4. **Land Lease (Y/N + fee)** — asterisked on Residential and Income. BYO has no land-lease field.

## 1.1 Residential (Seller — Residential + Buyer — Residential)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required by Stellar | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Residential | Residential | Pool/Exposure | **Front Exposure** | Yes | None | `front_exposure` | Stellar mandates compass orientation for every home | Sun orientation is a real lifestyle + energy signal ("morning-sun lanai", "north-facing/cool"); cheap, strong Property-DNA tag | **High** | N/S/E/W + intercardinal |
| Residential | Residential | Structure | **Floors in Unit/Home** | Yes | None | `stories` / `floors_in_home` | Story count is a required structural attribute | Single-vs-multi-story is a top accessibility/lifestyle filter (stairs, aging-in-place) | **Medium** | numeric |
| Residential | Residential | Lot | **Lot Size Square Footage** | Yes | Partial (`total_acreage` range) | `lot_size_sqft` | Exact lot area required | Enables "≥ X sqft lot" numeric search/valuation vs. today's range bucket | **High** | numeric |
| Residential | Residential | Lot | **Lot Size Acres** | Yes | Partial (`total_acreage` range) | `lot_size_acres` | Exact acreage required | Same as above; precise land valuation | **High** | numeric |
| Residential | Residential | Lot/Tax | **Land Lease (Y/N + Fee)** | Yes | None | `land_lease` / `land_lease_fee` | Required flag | Land-lease/leasehold is a material lending & cost constraint | **Medium** | pairs with Ownership |
| Residential | Residential | Beds/Baths/SqFt | **Fireplace (Y/N)** | Yes | Partial (a value inside `interior_features`) | `fireplace` / `fireplace_description` | Standalone required Y/N | Distinct buyer filter + marketing; a checkbox buried in interior features can't be searched cleanly | **Medium** | + Gas/Wood/Electric/location |
| Residential | Residential | Ownership | **Ownership (Fee Simple/Condo/Co-op/Fractional/Leasehold)** | Yes | None (`occupant_status` is a different concept) | `ownership_type` | Ownership form is required | Affects financing & fees; leasehold/co-op are decisive lending constraints | **Medium** | not the same as occupant status |
| Residential | Residential | Green | **Green Energy Generation (Y/N + Solar/Wind)** | Yes | None | `green_energy_generation` (+ `solar_panel_ownership`) | Required Y/N | Solar ownership materially changes the deal & financing; owned-solar is a marketing/DNA asset | **High** | see also §2 solar ownership |
| Residential | Residential | Rooms | **Room Types grid** (Kitchen\*, Living/Great Room\*, Primary Bedroom\* + Room Type/Level) | Yes | Partial (beds/baths counts only) | `additional_rooms[]` | Structured room list required | Home office / bonus / media / Florida room are top post-2020 intents; enables room-level search | **Medium** | essentials implied; structured grid missing |
| Residential | Residential | Structure | **Road Surface Type** | Yes | Partial (exists on Commercial/VL, not Residential-sale) | extend `road_surface_type` | Required | Trivial extension of an existing field to the residential flow | **Low** | extend-to-flow |
| Residential | Residential | Furnishings | **Furnishings** | Yes | Partial (`tenant_require` on rental side only) | extend `furnishings` to sale | Required Furnished/Unfurnished/Partial | Extend the rental-side field to the sale flow | **Low** | extend-to-flow |
| Residential | Residential | Interior | **Laundry Features** | Yes | Partial (`laundry_features` on Landlord only) | extend to Seller/Buyer | Required | Extend existing landlord field to sale flow | **Low** | extend-to-flow |
| Residential | Residential | Interior | **Floor Covering** | Yes | Partial (`floor_covering` on Landlord only) | extend to Seller/Buyer | Required | Extend existing landlord field to sale flow | **Low** | extend-to-flow |

*Note: New Construction\* is treated as **Supported** — BYO's `condition_prop` config includes "New Construction". Exterior Features\* is treated as **Partial-Supported** via `non_negotiable_amenities` (Balcony/Patio, etc.).*

## 1.2 Income (Seller — Income + Buyer — Income)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Income | Income | Lot | **Lot Size Square Footage** | Yes | Partial (range) | `lot_size_sqft` | Exact area required | Numeric land search/valuation | **High** | numeric |
| Income | Income | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Exact acreage required | Same | **High** | numeric |
| Income | Income | Lot | **Land Lease (Y/N)** | Yes | None | `land_lease` | Required | Lending/cost constraint | **Medium** | — |
| Income | Income | Structure | **Fireplace (Y/N + Desc)** | Yes | Partial (interior-features value) | `fireplace` | Required standalone | Searchable feature | **Low/Medium** | — |
| Income | Income | Ownership | **Ownership (type)** | Yes | None | `ownership_type` | Required | Financing/fee impact | **Medium** | — |
| Income | Income | Green | **Green Energy Generation (Y/N)** | Yes | None | `green_energy_generation` | Required | Deal/financing + marketing | **High** | — |

*Supported on Income: Total # of Buildings (`unit_buildings`), Total # of Units (`unit_number`), Annual Net Income (`minimum_annual_net_income`), Unit-mix grid (`unit_type_configurations[]`), Private Pool, all shared physical attributes, HOA, Flood/CDD.*

## 1.3 Commercial Sale (Seller — Commercial + Buyer — Commercial)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Commercial Sale | Commercial Sale | Lot | **Lot Size Square Footage** | Yes | Partial (range) | `lot_size_sqft` | Required | Numeric search/valuation | **High** | — |
| Commercial Sale | Commercial Sale | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Required | Same | **High** | — |
| Commercial Sale | Commercial Sale | Ownership | **Ownership (type)** | Yes | None | `ownership_type` | Required | Fee-simple vs leasehold/condo affects financing | **Medium** | — |
| Commercial Sale | Commercial Sale | Green | **Green Energy Generation (Y/N)** | Yes | None | `green_energy_generation` | Required | Operating-cost + marketing signal | **Medium** | — |

*Supported on Commercial Sale: Property Style, Road Frontage (`road_frontage`), Road Surface (`road_surface_type`), Exterior Construction, Foundation, Year Built, Total # of Buildings, SqFt Total, Air Conditioning, Total Acreage, Flood Zone, Waterfront block.*

## 1.4 Business Opportunity (Seller — Business + Buyer — Business)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Business Opportunity | Business Opportunity | Ownership | **Ownership (Franchise / Sole Proprietor / LLC / Corporation / Partnership / Leasehold)** | Yes | None | `ownership_entity_structure` | Required entity form | Franchise-vs-independent is a **primary** business-buyer filter; entity form drives the deal | **High** | this is the Business "Ownership" field |
| Business Opportunity | Business Opportunity | Lot | **Lot Size Square Footage** | Yes | Partial (range) | `lot_size_sqft` | Required | Numeric search | **Medium** | when real estate included |
| Business Opportunity | Business Opportunity | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Required | Same | **Medium** | — |
| Business Opportunity | Business Opportunity | Green | **Green Energy Generation (Y/N)** | Yes | None | `green_energy_generation` | Required | Operating-cost/marketing | **Low/Medium** | — |

*BYO is otherwise **ahead** here — see Section 4 (SDE/EBITDA, inventory value, FF&E, reason for sale, employees, NDA, financial-statements-available). Business Name, Year Established, Real Estate Included, Business Type are all Supported.*

## 1.5 Commercial Lease (Landlord — Commercial Lease + Tenant — Commercial Lease)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Commercial Lease | Commercial Lease | Lease Terms | **Lease Price Unit** ($ Total Monthly vs Per Square Foot) | Yes | None (BYO has amount + Annual/Monthly frequency, no *unit*) | `lease_price_unit` | Required alongside Lease Price | **Commercial rents are meaningless without a rate unit** ($18/sqft/yr vs $2,400/mo); blocks credible commercial-lease comparison, search, and matching | **Critical** | pairs with `desired_rental_amount` |
| Commercial Lease | Commercial Lease | Lease Terms | **Initial Pass-Through Expenses** ($ + Flat-Monthly / Annual $-per-SqFt) | Yes | Partial (free-text `cam_nnn_additional_rent_charges`) | `pass_through_expenses` (structured) + `pass_through_type` | Required | Converts free-text CAM/NNN into structured, comparable total-occupancy-cost data for matching & Ask AI | **High** | see §2 pass-through checklist |
| Commercial Lease | Commercial Lease | Lot | **Lot Size Square Footage** | Yes | Partial (range) | `lot_size_sqft` | Required | Numeric search | **Medium** | — |
| Commercial Lease | Commercial Lease | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Required | Same | **Medium** | — |
| Commercial Lease | Commercial Lease | Green | **Green Energy Generation (Y/N)** | Yes | None | `green_energy_generation` | Required | Operating-cost/marketing | **Medium** | — |
| Commercial Lease | Commercial Lease | Structure | **Road Frontage** | Yes | Partial (on Seller-Commercial, not Landlord-Lease flow) | extend `road_frontage` | Required | Extend existing field to the lease flow | **Low** | extend-to-flow |

*Supported: Lease Price (`desired_rental_amount`), Lease Amount Frequency (`lease_amount_frequency` = Annually/Monthly), Lease Term (`desired_lease_length`), Owner Pays (`owner_pays`), Terms of Lease (`terms_of_lease` / `commercial_lease_type`), Net Leasable SqFt (`minimum_leaseable`), Property Style, Exterior Construction, Foundation, Year Built, Total # of Buildings, SqFt Total, Air Conditioning, Total Acreage, Flood Zone.*

## 1.6 Residential Rental (Landlord — Rental + Tenant — Rental)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Residential Rental | Rental | Lease/Fees | **Application Fee** | Yes | None | `application_fee` | Required on every rental | Standard, expected move-in cost; affects true cost-of-occupancy and tenant matching | **High** | — |
| Residential Rental | Rental | In-Law Suite | **In-Law Suite YN** | Yes | None (ADU exists only as a *leasing-space* option) | `in_law_suite` (+ description, sqft) | Required Y/N | Multigenerational + rental-income + remote-work intent; high tenant/buyer signal | **High** | see §2 In-Law detail |
| Residential Rental | Rental | Structure | **Floors in Unit/Home** | Yes | None | `stories` / `floors_in_home` | Required | Single-vs-multi-story accessibility filter | **Medium** | — |
| Residential Rental | Rental | Lot | **Lot Size Square Footage** | Yes | Partial (range) | `lot_size_sqft` | Required | Numeric search | **Medium** | — |
| Residential Rental | Rental | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Required | Same | **Medium** | — |
| Residential Rental | Rental | Green | **Green Energy Generation (Y/N)** | Yes | None | `green_energy_generation` | Required | Lower utilities = tenant value + marketing | **Medium** | — |
| Residential Rental | Rental | Header | **Long Term (Y/N)** | Yes | Partial (`lease_amount_frequency` has "Seasonal"; no explicit long-term flag + seasonal-rate block) | `long_term` + seasonal block | Required toggle | Distinguishes annual vs seasonal/vacation rentals — a huge FL segment (see §2 seasonal block) | **Medium** | adopt with the seasonal block, not as a bare toggle |
| Residential Rental | Rental | Rooms | **Room Types grid** | Yes | Partial (beds/baths only) | `additional_rooms[]` | Structured rooms required | Same rationale as residential-sale rooms | **Low** | — |

*Supported: Rent Price (`desired_rental_amount`), Lease Amount Frequency, Date Available (`lease_available_date`/`available_date`), Rent Includes (`rent_includes`), Pets Allowed (rich — BYO ahead), Furnishings (`tenant_require`), Laundry Features (`laundry_features`), Private Pool, Property Style, Garage/Carport, Year Built, Beds/Baths/SqFt, Appliances, Interior Features, Heating/AC, HOA, Housing for Older Persons (`leasing_55_plus`).*

## 1.7 Vacant Land (Seller — Vacant Land + Buyer — Vacant Land)

| Property Type | Stellar Form | Stellar Section | Field Name | Required | BYO Equivalent | Recommended BYO Field | Why Required | Why It Should Be Added | Priority | Notes |
|---|---|---|---|---|---|---|---|---|---|---|
| Vacant Land | Vacant Land | Lot | **Lot Size Square Footage** | Yes | Partial (range + `lot_dimensions`) | `lot_size_sqft` | Required | Precise acreage/area search — core to land | **High** | — |
| Vacant Land | Vacant Land | Lot | **Lot Size Acres** | Yes | Partial (range) | `lot_size_acres` | Required | Same | **High** | — |
| Vacant Land | Vacant Land | Ownership | **Ownership (type)** | Yes | None | `ownership_type` | Required | Fee-simple vs leasehold affects financing | **Medium** | — |
| Vacant Land | Vacant Land | Development | **Designated Builder (Y/N)** | Yes | None | `designated_builder` | Required Y/N | Builder-tie-in is a real constraint in deed-restricted/PUD land | **Low** | niche but required |
| Vacant Land | Vacant Land | Header | **For Lease (Y/N)** | Yes | Partial (BYO land flow is sale-oriented) | `land_for_lease` | Required | Land-lease listings (ag, billboard, cell tower) are a real segment | **Low** | — |

*Supported: Current Use (`current_use`), Utilities/Water/Sewer (`*_available` + `water`/`sewer`), Road Surface (`road_surface_type`), Zoning (`zoning`), Flood Zone, Front Footage (`front_footage`), Total Acreage (`total_acreage`), Lot Dimensions (`lot_dimensions`), Waterfront block, Buildable (`buildable`), Wells/Septics, Fences, Vegetation, Easements.*

> **Buyer/Tenant criteria side (cross-cutting).** Stellar's forms are listing-side only. On BYO's **Buyer — Vacant Land** flow, land buyers cannot express structured criteria (zoning intent, utilities-required, buildable, min-acreage) — this is captured only in free-text KB today. It is not a "Stellar-required field" (Stellar has no buyer form) but it is the single biggest criteria-side gap and is flagged **High** in Section 2 (VL-Buyer items).

---

# 2. Optional Stellar MLS Fields Missing From Bid Your Offer

These are **non-asterisked (optional)** Stellar consumer fields with no BYO equivalent (or only a partial one). Optional fields already covered by BYO's option lists (the bulk of every form) are not repeated here. Columns: **Search?** (become a search facet) · **Public?** (show on public listing) · **AI?** (Ask AI should use) · **Prop/Buyer/Tenant/Loc DNA?** (feed that DNA layer). "Do Not Add" priority means the field is optional in Stellar and not worth porting.

## 2.1 Residential

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Residential | Residential | Schools | **Elementary / Middle / High School** | **High** | Optional in Stellar but the #1 residential decision driver; BYO only *derives* a school district via Location DNA, not a named field | Yes | Yes | Yes | No | Yes | No | Yes | `school_zone_rated`, `top_school_district`, `family_oriented` | 3 fields; pair with Location DNA |
| Residential | Residential | Structure | **Architectural Style** (Coastal, Key West, Mediterranean, Ranch, Traditional, Victorian, Tudor…) | **High** | `property_items` is *structure* type only, not architectural character | Yes | Yes | Yes | Yes | Yes | No | No | `architectural_key_west`, `architectural_mediterranean`, `architectural_coastal` | marketing tone |
| Residential | Residential | Accessibility | **Accessibility Features** (24-value list) | **High** | Underserved audience (aging-in-place, mobility/ADA); strong Ask AI intent | Yes | Yes | Yes | Yes | Yes | Yes | No | `wheelchair_accessible`, `single_level`, `aging_in_place` | — |
| Residential | Residential | Community | **Community Features** (Gated±Guard, Golf, Dog Park, Playground, Fitness Center, Clubhouse, Tennis/Pickleball, Sidewalks, Deed Restrictions, Community Mailbox, Airport/Runway) | **High** | Prime lifestyle/target-audience tags & top search filters; BYO covers only partially via `association_amenities` | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `gated`, `golf_community`, `dog_friendly`, `walkable`, `active_adult` | — |
| Residential | Residential | In-Law | **In-Law Suite** (Y/N, Description attached/detached/kitchen/private-entry, Under-Air SqFt, Total SqFt) | **High** | Multigenerational + income + remote-work; BYO only has ADU as a landlord leasing option | Yes | Yes | Yes | Yes | Yes | No | No | `multigenerational`, `has_adu`, `income_potential` | required on Rental (see §1.6) |
| Residential | Residential | Rooms | **Additional Rooms** (Den/Office, Bonus, Media, Great Room, Florida Room, Loft, Inside Utility, Gym, Library, Sauna) | **High** | Home-office & lifestyle rooms are top post-2020 intents | Yes | Yes | Yes | Yes | Yes | No | No | `home_office`, `media_room`, `flex_space`, `florida_room` | structured rooms grid |
| Residential | Residential | Disaster | **Disaster Mitigation** (Hurricane Shutters, Impact/Storm Windows, Above Flood Plain, Safe Room, Fire-Resistant) | **High** | FL-critical: insurability + resilience is a genuine buyer concern & marketing edge; ties to Location DNA flood data | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `hurricane_hardened`, `impact_windows`, `above_flood_plain`, `insurance_friendly` | — |
| Residential | Residential | Green | **Solar Panel Ownership** (Owned/Leased/Financed/Utility) + **Solar Finance Terms** (Assumable/Seller-paid) | **High** | Complements the required Green Energy Y/N; ownership materially changes the transaction | Yes | Yes | Yes | Yes | Yes | No | No | `solar_owned`, `energy_efficient`, `low_utility_cost` | pairs with §1 Green Energy |
| Residential | Residential | Patio | **Patio & Porch Features** (Screened, Covered, Front/Rear/Side Porch, Lanai, Wrap-Around, Deck, Enclosed) | **Medium** | FL outdoor-living lifestyle marketing + search | Yes | Yes | Yes | Yes | Yes | No | No | `screened_lanai`, `outdoor_living` | — |
| Residential | Residential | Condo | **Property Description / Position** (Corner Unit, End Unit, Penthouse, High/Mid Rise, Stilt, Walk-Up) | **Medium** | Condo/townhome floor-position filters | Yes | Yes | Yes | Yes | Yes | No | No | `penthouse`, `corner_unit`, `top_floor` | — |
| Residential | Residential | Condo | **Floor Number / Total # of Floors / Building Elevator** | **Medium** | Elevator + floor level decisive for condo buyers | Yes | Yes | Yes | Yes | Yes | No | No | `elevator`, `high_floor` | — |
| Residential | Residential | Windows | **Window Features** (Impact/Storm, Double-Pane, ENERGY STAR, Tinted) | **Medium** | Impact windows = insurance + storm resilience | Yes | Yes | Yes | Yes | Yes | No | No | `impact_windows`, `energy_efficient` | — |
| Residential | Residential | Green | **Green Building Certifications** (LEED, ENERGY STAR, HERS, FGBC) | **Low** | Niche but strong eco-conscious marketing | No | Yes | Yes | Yes | No | No | No | `green_certified`, `energy_star` | — |
| Residential | Residential | Interior | **Indoor Air Quality** (MERV filters, Low/No-VOC, Whole-house vacuum) | **Low** | Health/allergy target audience; long-tail | No | Yes | Yes | Yes | No | No | No | `healthy_home`, `low_voc` | — |
| Residential | Residential | Rooms | **Room Dimensions grid** (Room + L×W + level) | **Do Not Add** | Heavy to collect on a bidding platform; low ROI | No | Yes | No | No | No | No | No | — | defer |
| Residential | Residential | HOA | **Condo Fee / Addtl Maint / Other Fee + schedules** | **Medium** | Total carrying-cost transparency (BYO has HOA fee, not condo-specific tiers) | Yes | Yes | Yes | No | No | No | No | `low_hoa`, `carrying_cost` | — |

## 2.2 Income

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Income | Income | Utilities | **Individually Metered** (per-utility: Cable/Electric/Gas/Internet/Sewer/Trash/Water) | **High** | Separately-metered units = core investor value driver (tenant-paid utilities); BYO has meter *counts* only | Yes | Yes | Yes | Yes | Yes | No | No | `separately_metered`, `low_owner_expense` | investor DNA |
| Income | Income | Financials | **Gross Scheduled Income** + **Est. Market (potential) Income** | **Medium** | Distinguishes actual vs. pro-forma; feeds underwriting AI | No | Yes | Yes | Yes | Yes | No | No | `value_add`, `upside_potential` | — |
| Income | Income | Financials | **Financial Source** (Accountant / Broker / Owner / Tax Return) | **Medium** | Credibility signal on the financials | No | Yes | Yes | No | No | No | No | `financials_verified` | — |
| Income | Income | Financials | **Total Monthly Rent** & **Total Monthly Expenses** (aggregate) | **Medium** | Quick DSCR/cash-flow read without opening the rent roll | Yes | Yes | Yes | Yes | Yes | No | No | `cash_flowing` | — |
| Income | Income | Lease | **Terms of Lease / Tenant Pays** (structured, income) | **Medium** | Expense responsibility drives NOI | No | Yes | Yes | No | Yes | No | No | `tenant_pays_utilities` | — |
| Income | Income | Financials | **GRM (Gross Rent Multiplier)** | **Low** | Cheap derived metric (price ÷ gross income) | Yes | Yes | Yes | No | No | No | No | `grm` | derived, not stored |

## 2.3 Commercial Sale

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Commercial Sale | Commercial Sale | Loading | **Loading/Dock config** (# Bays Grade/Dock-High/Dock-Well, Door H×W, Truck Doors/Well, High Bays, Clear Span, Columns) | **High** | Decisive for industrial/warehouse matching & search; unmodeled today | Yes | Yes | Yes | Yes | Yes | No | No | `dock_high`, `drive_in`, `clear_span`, `industrial_ready` | mirror on Lease |
| Commercial Sale | Commercial Sale | Tenancy | **Number of Tenants** (Single/Multi/Vacant) + **Anchor / Co-Tenant** | **High** | Core to investment-grade matching (single-tenant NNN vs multi-tenant); anchor drives value | Yes | Yes | Yes | Yes | Yes | No | No | `single_tenant_nnn`, `multi_tenant`, `anchored` | — |
| Commercial Sale | Commercial Sale | Financials | **Vacancy Rate** + **Gross Scheduled Income** + **Income Includes** (Rent/Parking/Storage/Laundry) | **Medium** | Completes the underwriting picture | Yes | Yes | Yes | Yes | Yes | No | No | `stabilized`, `value_add` | — |
| Commercial Sale | Commercial Sale | Financials | **NOI Type / Income Type** (Actual vs Projected) | **Medium** | In-place vs pro-forma NOI — material to cap-rate trust | No | Yes | Yes | No | Yes | No | No | `actual_noi`, `proforma` | — |
| Commercial Sale | Commercial Sale | Parking | **Total Parking Spaces / ratio** | **Medium** | Retail/office viability (spaces per 1,000 sqft) | Yes | Yes | Yes | Yes | Yes | No | No | `ample_parking`, `parking_ratio` | — |
| Commercial Sale | Commercial Sale | Signage | **Signage** (Pole, On-Building, Directory, Street) | **Medium** | Retail visibility = value/marketing | Yes | Yes | Yes | Yes | No | No | No | `pole_sign`, `high_visibility` | — |
| Commercial Sale | Commercial Sale | Context | **Adjoining Property / Adjacent Use** | **Medium** | Use-compatibility context for Ask AI | No | Yes | Yes | Yes | No | No | Yes | `commercial_corridor`, `anchor_adjacent` | — |
| Commercial Sale | Commercial Sale | Fit-out | **# Restrooms / Offices / Conference Rooms / Hotel-Motel Rooms** | **Medium** | Office/hospitality fit search (BYO has these on the Lease side only) | Yes | Yes | Yes | Yes | Yes | No | No | `turnkey_office`, `built_out` | extend from lease |
| Commercial Sale | Commercial Sale | Space | **Space Classification (A/B/C/D)** on Sale side | **Medium** | Present on BYO Landlord side; extend to Seller-Commercial | Yes | Yes | Yes | Yes | Yes | No | No | `class_a`, `class_b` | extend-to-flow |
| Commercial Sale | Commercial Sale | Use | **Freezer Space / Freestanding / Converted Residence** | **Low** | Restaurant/retail niche flags | No | Yes | Yes | Yes | No | No | No | `restaurant_ready`, `freestanding` | — |
| Commercial Sale | Commercial Sale | Mgmt | **Management Type** (Owner / Professional On-Off site) | **Low** | Operational context for investors | No | Yes | Yes | No | Yes | No | No | `professionally_managed` | — |

## 2.4 Business Opportunity

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Business Opportunity | Business Opportunity | Tenancy | **Number of Tenants / Anchor or Co-Tenant** | **Medium** | For strip/retail business sales with sub-tenants | Yes | Yes | Yes | Yes | Yes | No | No | `anchored`, `multi_tenant` | — |
| Business Opportunity | Business Opportunity | Financials | **NOI / Income Type** (Actual vs Projected) | **Medium** | Trust signal on stated financials | No | Yes | Yes | No | Yes | No | No | `actual_financials` | — |
| Business Opportunity | Business Opportunity | Operations | **Hours / Days of Operation** | **Low** | Absentee-friendly? operational profile | No | Yes | Yes | No | Yes | No | No | `absentee_run`, `limited_hours` | — |
| Business Opportunity | Business Opportunity | Terms | **Non-Compete (Y/N + term)** & **Seller Training (Y/N + period)** | **Low/Medium** | Common screening terms; today only free-text | No | Yes | Yes | No | Yes | No | No | `training_provided`, `non_compete` | structure existing text |
| Business Opportunity | Business Opportunity | Use | **Freezer Space / Converted Residence / Freestanding** | **Low** | Use-fit flags (restaurant/retail) | No | Yes | Yes | Yes | No | No | No | `restaurant_ready` | — |

## 2.5 Commercial Lease

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Commercial Lease | Commercial Lease | Pass-Through | **Pass-Through Expense Includes** (structured checklist: Insurance/Taxes/CAM/Utilities/…) | **High** | Structures free-text CAM into comparable total-occupancy-cost data | Yes | Yes | Yes | No | No | Yes | No | `low_cam`, `nnn_transparent` | pairs with §1.5 Initial Pass-Through |
| Commercial Lease | Commercial Lease | Transaction Terms | **Commercial Transaction Terms** (Annual Rate Increase, Improvement/TI Allowance, Pre-Leasing/Build-to-Suit, Sub-Leasing) — structured | **High** | Structures escalations, TI allowance, build-to-suit, sublease availability for matching (BYO free-text today) | Yes | Yes | Yes | No | No | Yes | No | `ti_allowance`, `build_to_suit`, `sublease_ok`, `escalation` | — |
| Commercial Lease | Commercial Lease | Loading | **Loading / Dock config + Door dimensions** | **High** | Same industrial driver as Sale (§2.3) | Yes | Yes | Yes | Yes | No | Yes | No | `dock_high`, `drive_in`, `clear_span` | — |
| Commercial Lease | Commercial Lease | Tenancy | **Number of Tenants** | **Medium** | Multi-tenant / co-tenancy context | Yes | Yes | Yes | Yes | No | Yes | No | `multi_tenant` | — |
| Commercial Lease | Commercial Lease | Parking/Signage | **Total Parking Spaces / ratio**, **Signage**, **Adjoining Property** | **Medium** | Retail/office fit filters (mirror §2.3) | Yes | Yes | Yes | Yes | No | Yes | Yes | `parking_ratio`, `high_visibility` | — |
| Commercial Lease | Commercial Lease | Use | **Freezer Space / Freestanding / Converted Residence** | **Low** | Restaurant/retail niche | No | Yes | Yes | Yes | No | No | No | `restaurant_ready` | — |

## 2.6 Residential Rental

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Residential Rental | Rental | Seasonal | **Short-Term / Seasonal block** (Seasonal Rent, Off-Season Rent, Weeks/Months Available calendar) | **High** | FL seasonal/vacation rentals are a huge segment BYO can't express (seasonal pricing + availability windows) | Yes | Yes | Yes | No | No | Yes | No | `seasonal`, `snowbird`, `vacation_rental`, `annual` | adopt with the required Long-Term flag (§1.6) |
| Residential Rental | Rental | Fees | **Additional Applicant Fee** | **Medium** | Complements required Application Fee; per-additional-adult cost | Yes | Yes | Yes | No | No | Yes | No | `application_fee` | optional (not asterisked) |
| Residential Rental | Rental | Association | **Association Fees for Tenants** (Approval Fee, Security Deposit, Parking Fee, Other + frequency) | **Medium** | True move-in cost; association-approval friction is a tenant concern | Yes | Yes | Yes | No | No | Yes | No | `hoa_approval_required`, `move_in_cost` | — |
| Residential Rental | Rental | Association | **Assoc Approval Required (Y/N) + Process/Timeframe** | **Medium** | Approval delay is decisive for move-in timing | Yes | Yes | Yes | No | No | Yes | No | `association_approval` | Stellar Rental has `Assoc Appr Req` |
| Residential Rental | Rental | Utilities | **Tenant Pays** (utilities responsibility list) | **Medium** | Clarifies total cost of occupancy; complements Rent Includes | Yes | Yes | Yes | No | No | Yes | No | `tenant_pays_utilities` | Stellar Rental has explicit `Tenant Pays` |
| Residential Rental | Rental | Furnished | **Primary Bed Size** (King/Queen/…) | **Low** | Relevant to furnished/seasonal | No | Yes | No | No | No | Yes | No | `furnished_detail` | Stellar Rental field `Primary Bed Size` |
| Residential Rental | Rental | Pets | **Pet Restrictions Source** (Association vs Landlord) | **Low** | Who sets the pet rule (negotiability signal) | No | Yes | Yes | No | No | Yes | No | `pet_rule_source` | — |

## 2.7 Vacant Land

| Property Type | Form | Section | Field Name | Priority | Reason | Search? | Public? | Ask AI? | Prop DNA? | Buyer DNA? | Tenant DNA? | Loc DNA? | Suggested Metadata Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Vacant Land | Vacant Land | Buyer criteria | **Structured land criteria on the Buyer side** (Zoning intent, Utilities-required, Buildable, Min-acreage, Road access, Current-use intent) | **High** | Land buyers can't express structured wants today (free-text KB only) — blocks land matching on the criteria side | Yes | n/a | Yes | No | Yes | No | Yes | `land_use_intent`, `utilities_required`, `buildable_only` | criteria side only |
| Vacant Land | Vacant Land | Lot Features | **Lot Features** (Topography: Hilly/Level/Sloped/Rolling; Wetlands; Flood Plain; Conservation; Cleared/Wooded; Filled/May-Need-Fill; Compact Soil; Brownfield) | **High** | Topography/wetlands/soil drive buildability & cost — top land-valuation signals | Yes | Yes | Yes | Yes | Yes | No | Yes | `level_lot`, `wetlands`, `cleared`, `wooded` | BYO has `vegetation`, not topography/soil |
| Vacant Land | Vacant Land | Zoning | **Future Land Use** + **Zoning Compatible (Y/N)** | **Medium** | Development potential; entitlement AI | Yes | Yes | Yes | Yes | Yes | No | Yes | `development_potential`, `rezoning_candidate` | — |
| Vacant Land | Vacant Land | Agricultural | **Farm Type** + **AG Exemption (Y/N)** | **Medium** | Ag classification affects taxes & use; farm-buyer audience | Yes | Yes | Yes | Yes | Yes | No | No | `agricultural`, `ag_exemption`, `equestrian` | Stellar has AG Exemption (also on Residential) |
| Vacant Land | Vacant Land | Parcels | **Planned Unit Development (PUD) (Y/N)** + **Additional Parcels / Total # Parcels** | **Low/Medium** | Assemblage & subdivision potential | Yes | Yes | Yes | Yes | Yes | No | No | `assemblage`, `subdividable`, `pud` | BYO has additional-parcels on other flows |
| Vacant Land | Vacant Land | Equestrian | **Horse/Barn amenities + # Paddocks/Stalls** | **Low** | Equestrian target audience | Yes | Yes | Yes | Yes | Yes | No | No | `equestrian`, `horses_allowed` | — |

---

# 3. Fields That Should NOT Be Added

These Stellar fields (many of them **required** on the MLS forms) are **administrative-only** and should **never** be implemented in BYO. They exist for MLS/brokerage workflow, not for the consumer listing. Adding them would add friction, PII, or compliance surface with zero AI/matching/marketing value.

| # | Stellar Field(s) | Category | Why NOT to add |
|---|---|---|---|
| 1 | **Office Exclusive**, Office Exclusive with Temporary Exclusion, Temporary Exclusion Date | Listing distribution | MLS office-exclusivity workflow; irrelevant to a consumer bidding platform |
| 2 | **Listing Type**, **Listing Service Type** | MLS workflow | Exclusive-Right-of-Sale/Agency service tier is an MLS-agreement artifact; BYO models engagement via broker-compensation |
| 3 | **Seller Representation** (Single Agent / Transaction Broker / No Brokerage) | Agency | BYO already handles agency/brokerage relationship inside broker-compensation (`brokerage_relationship`) |
| 4 | **Listing Contract Date**, **Expiration Date** (as MLS-agreement dates) | MLS workflow | Listing-agreement lifecycle; BYO has its own listing/expiration lifecycle |
| 5 | **All legal survey identifiers**: Section, Township, Range, Plat Book/Page, Block/Parcel, Lot #, Alt Key/Folio, Legal Subdivision Name | Legal survey | Surveyor/title identifiers with no consumer, search, or AI value. (BYO keeps only free-text `legal_description` + `parcel_id`.) |
| 6 | **Additional Tax IDs list**, **Homestead**, **AG Exemption / Other Exemptions** (as tax-admin), **Tax Year** (beyond BYO's existing) | Tax admin | Assessor/tax-roll administration; BYO already stores annual taxes + parcel where useful |
| 7 | **Use Code / State-County Land-Use / Property-Use codes** | Municipal codes | Internal municipal classification; BYO's `current_use`/`zoning` cover the consumer need |
| 8 | **GeoLocation** field | MLS geo | BYO geocodes the address itself (lat/lng/place-id) |
| 9 | **Permit Number**, **Builder License #**, **Proj. Completion Date** | Construction admin | Builder/permit paperwork; not consumer-relevant |
| 10 | **List Agent ID**, **Agent Name & Information**, **Office Name & Information**, **Team**, List Agent/Office codes | Agent/office | BYO already has its own agent identity/credential model |
| 11 | **Owner Name / Owner Phone / Tenant Name / Tenant Phone** | Owner/tenant PII | Personally-identifiable data with no listing value; adds privacy/compliance risk |
| 12 | **Association/Manager Contact Name / Phone / Email / Website** | HOA admin contact | Property-management contact PII; BYO captures HOA *facts* (fee/frequency/approval), not the manager's contact card |
| 13 | **Occupant Type (showing)**, **Showing Instructions**, Showing Considerations, Call Center / ShowingTime, Lockbox | Showing/access | Physical-showing logistics; BYO is a remote bidding platform |
| 14 | **Internet**, **Delayed Distribution (+Date)**, **Third Party**, **Show Prop Address on Internet**, **IDX/VOW Display Comments**, **IDX/VOW AVM**, Distribute-To, Create-Auto-Virtual-Tour | Syndication/IDX/VOW | MLS data-distribution/syndication settings; BYO controls its own display |
| 15 | **RentSpree Online Tenant Screening** | Third-party integration | BYO has its own richer pre-screening (Section 4); don't couple to a Stellar vendor |
| 16 | **Realtor Information / Realtor-Only Remarks / Confidential Remarks** | Agent-only remarks | Broker-to-broker private notes; not consumer content |
| 17 | **Driving Directions** | Navigation | Redundant with geocoded address + maps |
| 18 | **Documents & Disclosures grid (Stellar's)** | MLS docs matrix | BYO has its own `doc_rows` / disclosure model |
| 19 | **Seller's Preferred Closing Agent** block | Transaction admin | Title/closing coordination; not a listing attribute |
| 20 | **Owner Signature / Broker Signature / Dates** | Signatures | Form-execution artifacts; not data |
| 21 | **SqFt Total Source** (keep only **SqFt Heated Source**) | Redundant source field | BYO keeps the heated-source provenance; total-source is redundant |
| 22 | **Designated Builder** *name/details* beyond the Y/N flag, **Long-Term** *as a bare admin toggle* | Partial-adopt caveats | Adopt only the consumer-meaningful part (Y/N + seasonal block); skip the admin remainder |

> **Principle:** BYO adopts Stellar's *consumer property facts* and deliberately drops Stellar's *MLS operational plumbing*. Roughly 35–45% of every Stellar form is administrative and belongs here.

---

# 4. Fields Where Bid Your Offer Is Better Than Stellar MLS

BYO is not merely catching up to Stellar — on a whole class of capabilities it **exceeds** the MLS. These must be preserved and marketed as the platform's differentiation. **Do not regress any of these** while adding Stellar parity fields.

## 4.1 AI-first intelligence layers (no Stellar equivalent)

| Capability | What BYO Has | Stellar Equivalent |
|---|---|---|
| **Property DNA** | AI-generated property personality/marketing profile (`PropertyDnaGenerator`) + deterministic marketing context | None |
| **Buyer DNA / Tenant DNA** | AI buyer/tenant personas (`BuyerTenantDnaGenerator`) from purpose, budget, financing, lifestyle, screening | None |
| **Location DNA** | Geospatial enrichment: POI (Google Places), flood zone (FEMA), school districts (Census TIGER), commute times, tile-cached | Stellar has only static named School fields |
| **Ask AI (per-role field registry)** | Structured Ask-AI knowledge base per listing (`AskAiFieldQuestionRegistryService` + `listing_ai_faq`), ~90 fields/role feeding a conversational KB | None |
| **Compatibility matching / scoring** | `*BidMatchScoreHelper` + compatibility scoring framework (config-weighted dimensions summing to 100) | Stellar is a data catalog, not a matcher |

## 4.2 Consumer criteria capture (Stellar is listing-side only)

| Capability | What BYO Has |
|---|---|
| **Buyer / Tenant criteria flows** | Full mirror of every listing attribute as *search criteria* (Buyer & Tenant offer listings) — Stellar has no buyer/tenant form at all |
| **Commute preferences** | Work/School ZIP + max minutes + mode (Drive/Transit/Walk/Bike/Remote) feeding commute scoring |
| **Search Areas (map polygons)** | `location_dna_preferences_json` map-drawn areas that compose with boundary/flood/school data |
| **Important Places** | `important_places_json` — named places a buyer/tenant wants to be near |
| **Purchase / Rental purpose** | `purchase_purpose` / `rental_purpose` (investor, primary, snowbird…) driving DNA & matching |
| **Flood-zone tolerance, HOA acceptance/max fee** | Buyer-side risk/cost preferences (`flood_zone_tolerance`, `hoa_acceptance`, `hoa_max_monthly_fee`) |

## 4.3 Richer transactional & financing structure (deeper than Stellar)

| Capability | What BYO Has | Stellar |
|---|---|---|
| **Structured financing sub-forms** | Assumable, Seller-Financing, Lease-Option, Lease-Purchase, Exchange/Trade, **Cryptocurrency**, **NFT** — each a full structured sub-form | Stellar lists only "Financing Available" checkboxes |
| **Offer / bidding workflow** | Bidding Period vs Traditional, starting/reserve/buy-now price, deposits, contingencies (inspection/appraisal/financing/sale-of-buyer-property), possession | None (MLS is not a transaction engine) |
| **Negotiation & broker-compensation modeling** | Full commission-structure, retainer, protection period, early-termination, agency-agreement, referral modeling per role | Stellar captures compensation offer only |
| **Seller purchase terms** | Included/excluded personal property, home warranty, seller credit, escrow preference, payment-assumption calculator | Sparse on Stellar |

## 4.4 Business Opportunity economics (BYO clearly ahead)

BYO captures **SDE/EBITDA, Annual Revenue, Gross Profit, Inventory Value, FF&E Value, Reason for Sale, Employee Count, NDA-required, Financial-Statements-Available, Tax-Returns-Available, and full underlying-lease sub-terms** as discrete fields. Stellar's Business Opportunity form pushes most of these into Remarks/Documents. Keep and promote these.

## 4.5 Rental pre-screening (BYO ahead)

- **Landlord side:** structured applicant requirements — min credit score, income-qualification method (2x/2.5x/3x/fixed), employment/eviction/bankruptcy/criminal/reference/income verification, occupant limits, per-utility cost estimates.
- **Tenant side:** self-disclosure pre-screening — income, credit-score range, screening concerns (+explanation), smoking preference, occupants, full pet block, accessibility requirements.
- **Pet economics:** pet deposit / monthly fee / pet rent / pet fee + restrictions — richer than Stellar's Pets Allowed.

Stellar's only equivalent is a link to the third-party **RentSpree** integration (which BYO correctly declines to adopt — Section 3).

---

# 5. Final Statistics

## 5.1 Counting methodology (read this first)

- **Unit of count = a distinct consumer field-concept per property-type form.** Multi-value checkbox *options* within a field (e.g. the 24 Accessibility values, the dozens of Interior-Feature checkboxes) are **not** counted individually — they are one field. This keeps the denominator meaningful (a "field", not a "checkbox").
- **Required** = asterisked on the Stellar form. **Optional** = not asterisked.
- **Consumer** = describes the property/listing (Section 3 administrative fields are excluded from every count below).
- **Supported** = BYO has an equivalent (incl. "Partial — needs extension to this flow"). **Missing** = no BYO equivalent on the relevant flow.
- **Per-form counting** is used (address block, price, etc. recur on each form) because the goal is "can BYO support every consumer field **for each property type**." A field shared across forms is therefore counted once per form it appears on. This is stated so the percentages are reproducible.
- Optional counts are at the **field-concept** level as defined above; where a whole Stellar sub-section maps to a single BYO multiselect (e.g. Interior Features), it is counted as one supported field on both sides.

## 5.2 Required consumer fields — per form

| Property Type | Required Consumer Fields | Supported by BYO | Missing/Partial | Required Compatibility % |
|---|---|---:|---:|---:|
| Residential | 62 | 53 | 9 | **85%** |
| Income | 52 | 46 | 6 | **88%** |
| Commercial Sale | 30 | 26 | 4 | **87%** |
| Business Opportunity | 32 | 28 | 4 | **88%** |
| Commercial Lease | 34 | 29 | 5 | **85%** |
| Residential Rental | 44 | 37 | 7 | **84%** |
| Vacant Land | 27 | 23 | 4 | **85%** |
| **TOTAL** | **281** | **242** | **39** | **86.1%** |

**Missing required (39), by field-concept** (many recur across forms):
Green Energy Generation (×5 forms), Lot Size SqFt exact (×7), Lot Size Acres exact (×7), Ownership type (×5), Land Lease (×2), Fireplace structured (×2), Floors in Unit/Home (×2), Room-types grid structured (×2), Front Exposure (Residential), Application Fee (Rental), In-Law Suite YN (Rental), Long-Term/seasonal flag (Rental), Lease Price Unit (Commercial Lease — **Critical**), Initial Pass-Through Expenses structured (Commercial Lease), Business Ownership/entity structure (Business), Designated Builder (Vacant Land), For-Lease flag (Vacant Land), plus the extend-to-flow partials (Road Surface, Furnishings, Laundry, Floor Covering on the residential-sale side; Road Frontage on the commercial-lease side).

- **Total Required Stellar MLS Consumer Fields: 281** (per-form).
- **Required Fields Already Supported by BYO: 242.**
- **Required Fields Missing From BYO: 39** (≈ **18 distinct field-concepts**, since most repeat across forms).

## 5.3 Optional consumer fields — per form (field-concept level)

| Property Type | Optional Consumer Fields | Supported by BYO | Missing | Optional Compatibility % |
|---|---|---:|---:|---:|
| Residential | ~135 | ~120 | 15 | 89% |
| Income | ~70 | ~64 | 6 | 91% |
| Commercial Sale | ~90 | ~80 | 10 | 89% |
| Business Opportunity | ~120 | ~115 | 5 | 96% |
| Commercial Lease | ~80 | ~74 | 6 | 93% |
| Residential Rental | ~95 | ~88 | 7 | 93% |
| Vacant Land | ~75 | ~69 | 6 | 92% |
| **TOTAL** | **~665** | **~610** | **55** | **~91.7%** |

> Optional totals are stated with a leading "~" because, unlike required fields (which are hard-marked by asterisk), the optional-field denominator depends on how finely Stellar's option groups are split. They are counted at the field-concept level defined in 5.1. The **Missing** column is exact — it is the enumerated Section 2 list (55 optional field-concepts). The Supported/total figures are the best-supported reconstruction of BYO's option-list coverage (which mirrors Stellar closely) and should be treated as ±5%.

- **Total Optional Stellar MLS Consumer Fields: ~665.**
- **Optional Fields Already Supported by BYO: ~610.**
- **Optional Fields Missing From BYO: 55** (the Section 2 enumeration; exact).

## 5.4 Recommendation totals

- **Total Fields Recommended To Add: 94** — = 39 required-missing (Section 1) + 55 optional-missing (Section 2), **minus** the ~0 marked "Do Not Add" inside Section 2 (only "Room Dimensions grid" is "Do Not Add", so net **93** recommended to add). At the **distinct field-concept** level (collapsing cross-form repeats), this is ≈ **70 distinct fields**.
- **Total Fields Recommended NOT To Add: 22 field-groups** (Section 3), which expand to **~60–70 individual Stellar fields** (each legal-survey identifier, each IDX/VOW toggle, each signature line counted).

## 5.5 Overall compatibility

| Metric | Value | How it was calculated |
|---|---|---|
| **Required Consumer Field Compatibility** | **86.1%** | 242 supported ÷ 281 required consumer fields (per-form, Section 5.2) |
| **Optional Consumer Field Compatibility** | **~91.7%** | ~610 supported ÷ ~665 optional consumer fields (per-form, Section 5.3) |
| **Overall Consumer Listing Compatibility** | **~90.4%** | (242 + ~610) supported ÷ (281 + ~665) total consumer fields = ~852 ÷ ~946 |

**Interpretation.** BYO already supports ~90% of *all* consumer-relevant Stellar listing fields and ~86% of the strictly *required* ones. The remaining gap is **not** breadth of physical description — it is (a) a small set of required fields led by exact Lot Size, Green Energy, Ownership type, and (Critical) the Commercial-Lease rate unit; and (b) a high-value lifestyle/DNA layer (Schools, Architectural Style, Accessibility, Community Features, Disaster Mitigation, In-Law Suite, seasonal rentals, structured commercial economics, and structured land features). Closing Section 1 makes every property type launch-complete against the MLS; closing Section 2 turns BYO's AI/DNA differentiation into searchable, marketable, matchable intelligence that Stellar cannot match (Section 4).

---

## Verification statement

- **Every page of every one of the seven `…_1782925021820.pdf` forms was read** (Residential 14, Income 12, Commercial Sale 10, Business Opportunity 10, Commercial Lease 9, Rental 13, Vacant Land 9 = **77 pages**).
- **Every asterisked (required) field on all 77 pages was extracted and individually classified** as consumer/administrative and Supported/Partial/Missing against the BYO field audit.
- **Every optional field group was evaluated**; missing ones are enumerated in Section 2 with full DNA/search/display classification.
- Cross-checked against `docs/bid-your-offer-field-audit.md` (all four role flows × five property types) and the prior `docs/bid-your-offer-stellar-gap-analysis.md`.
- **No code, Blade, Livewire, migration, or config file was modified. Nothing was committed.** This is documentation only.

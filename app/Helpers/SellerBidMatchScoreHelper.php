<?php

namespace App\Helpers;

use App\Traits\AgentMatchSubScorer;

/**
 * SellerBidMatchScoreHelper
 *
 * Baseline-driven match score for "Hire a Seller's Agent" bid comparisons.
 * Mirrors TenantBidMatchScoreHelper / LandlordBidMatchScoreHelper architecture
 * exactly — only Seller-specific field names, field groups, and service
 * catalogs differ.
 *
 * SCORING RULES (identical to Tenant/Landlord)
 * ============================================
 * Services:
 *   baseline_total = count(baseline services filtered to valid Seller catalog)
 *   matched        = baseline items also in compared
 *   missing        = baseline items not in compared
 *   extra          = compared items not in baseline  (NOT in denominator)
 *   services_match_percent = matched / baseline_total * 100
 *
 * Terms (Logical Field Groups):
 *   Each group = ONE logical decision, regardless of sub-inputs.
 *   baseline_total = groups with a filled baseline composite value
 *   matched        = groups where baseline composite = compared composite
 *   changed        = groups where composites differ
 *   added_by_agent = groups where baseline blank but compared filled
 *
 *   Cascade deactivation: if the BID's parent field is 'No', child groups
 *   are excluded entirely from the denominator.
 *
 * Overall = 50% Services + 50% Terms (only non-zero components averaged)
 */
class SellerBidMatchScoreHelper
{
    use AgentMatchSubScorer;

    /**
     * Logical field groups — Seller-specific fields.
     * Same structure as TenantBidMatchScoreHelper::LOGICAL_FIELD_GROUPS.
     */
    const LOGICAL_FIELD_GROUPS = [
        // 1. Seller's Broker Purchase Fee — type + sub-values = ONE logical decision
        [
            'key'    => 'purchase_fee_type',
            'fields' => [
                'purchase_fee_type',
                'purchase_fee_flat',
                'purchase_fee_percentage',
                'purchase_fee_flat_combo',
                'purchase_fee_percentage_combo',
                'purchase_fee_other',
            ],
        ],

        // 2. Nominal Consideration Fee
        [
            'key'    => 'nominal',
            'fields' => ['nominal'],
        ],

        // 3. Buyer's Broker Commission Structure
        [
            'key'    => 'commission_structure',
            'fields' => ['commission_structure'],
        ],

        // 4. Buyer's Broker Commission Structure Type (sub-field when structure has a type)
        [
            'key'    => 'commission_structure_type',
            'fields' => ['commission_structure_type'],
        ],

        // 5. Interested in Offering a Lease Agreement (parent)
        [
            'key'    => 'interested_purchase_fee_type',
            'fields' => ['interested_purchase_fee_type'],
        ],

        // 5a. Seller's Broker Leasing Fee — only active when parent = Yes in BOTH
        [
            'key'    => 'seller_leasing_fee_type',
            'fields' => [
                'seller_leasing_fee_type',
                'seller_leasing_gross_purchase_fee_flat_amount',
                'seller_leasing_gross',
                'seller_leasing_gross_rental',
                'seller_leasing_gross_month_rent',
                'seller_leasing_gross_no_of_months',
                'seller_leasing_gross_other',
                'seller_leasing_gross_percentage',
                'seller_leasing_gross_ross_percentage_rent',
                'seller_leasing_gross_flat_combo',
                'seller_leasing_gross_percentage_combo',
                'seller_leasing_gross_flat_net_combo',
                'seller_leasing_gross_percentage_net_combo',
                'seller_leasing_gross_purchase_fee_other',
            ],
            'baseline_active_when' => ['interested_purchase_fee_type' => 'Yes'],
            'bid_active_when'      => ['interested_purchase_fee_type' => 'Yes'],
        ],

        // 6. Interested in a Lease-Option Agreement (parent)
        [
            'key'    => 'interested_lease_option_agreement',
            'fields' => ['interested_lease_option_agreement'],
        ],

        // 6a. Lease-Option creation fee — only active when parent = Yes in BOTH
        [
            'key'    => 'lease_type',
            'fields' => ['lease_type', 'lease_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 6b. Purchase option exercise fee — only active when parent = Yes in BOTH
        [
            'key'    => 'purchase_type',
            'fields' => ['purchase_type', 'purchase_value'],
            'baseline_active_when' => ['interested_lease_option_agreement' => 'Yes'],
            'bid_active_when'      => ['interested_lease_option_agreement' => 'Yes'],
        ],

        // 7. Early Termination Fee (Yes/No parent)
        [
            'key'    => 'early_termination_fee_option',
            'fields' => ['early_termination_fee_option'],
        ],

        // 8. Termination Fee Amount — only when parent = Yes in BOTH
        [
            'key'    => 'early_termination_fee_amount',
            'fields' => ['early_termination_fee_amount'],
            'baseline_active_when' => ['early_termination_fee_option' => 'Yes'],
            'bid_active_when'      => ['early_termination_fee_option' => 'Yes'],
        ],

        // 9. Retainer Fee (Yes/No parent)
        [
            'key'    => 'retainer_fee_option',
            'fields' => ['retainer_fee_option'],
        ],

        // 10. Retainer Fee Amount — only when parent = Yes in BOTH
        [
            'key'    => 'retainer_fee_amount',
            'fields' => ['retainer_fee_amount'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 11. Retainer Fee Application — only when parent = Yes in BOTH
        [
            'key'    => 'retainer_fee_application',
            'fields' => ['retainer_fee_application'],
            'baseline_active_when' => ['retainer_fee_option' => 'Yes'],
            'bid_active_when'      => ['retainer_fee_option' => 'Yes'],
        ],

        // 12. Seller's Broker's Share of Retained Deposits
        [
            'key'    => 'retained_deposits',
            'fields' => ['retained_deposits'],
        ],

        // 13. Protection Period Timeframe
        [
            'key'    => 'protection_period',
            'fields' => ['protection_period'],
        ],

        // 14. Seller Agency Agreement Timeframe
        [
            'key'    => 'agency_agreement_timeframe',
            'fields' => ['agency_agreement_timeframe'],
        ],

        // 15. Acceptable Brokerage Relationship
        [
            'key'    => 'brokerage_relationship',
            'fields' => ['brokerage_relationship'],
        ],

        // 16. Referral Fee (%) — only present on agent-created listings
        [
            'key'    => 'referral_fee_percent',
            'fields' => ['referral_fee_percent'],
        ],
    ];

    /**
     * Legacy flat field list — for backward compat only.
     */
    const BROKER_FIELDS = [
        'purchase_fee_type',
        'purchase_fee_flat',
        'purchase_fee_percentage',
        'purchase_fee_flat_combo',
        'purchase_fee_percentage_combo',
        'purchase_fee_other',
        'nominal',
        'commission_structure',
        'commission_structure_type',
        'interested_purchase_fee_type',
        'seller_leasing_fee_type',
        'interested_lease_option_agreement',
        'lease_type',
        'lease_value',
        'purchase_type',
        'purchase_value',
        'early_termination_fee_option',
        'early_termination_fee_amount',
        'retainer_fee_option',
        'retainer_fee_amount',
        'retainer_fee_application',
        'retained_deposits',
        'protection_period',
        'agency_agreement_timeframe',
        'brokerage_relationship',
        'referral_fee_percent',
    ];

    /**
     * Residential Seller services catalog.
     * Source: resources/views/hire_seller_agent/view.blade.php ($residentialCategories)
     * All strings lowercased and unicode-normalized for comparison.
     */
    const RESIDENTIAL_SERVICES_CATALOG = [
        // Property Marketing & Listing Promotion
        "list the property on the local multiple listing service (mls)",
        "syndicate the listing to third-party platforms (e.g., zillow.com, realtor.com, trulia.com, homes.com)",
        "create a branded flyer featuring the property's key highlights",
        "post the property on facebook marketplace",
        "post the property on craigslist under the \"homes for sale\" category",
        "share the listing on nextdoor in neighborhood or community groups",
        "promote the listing on facebook in real estate or community groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in professional or real estate groups",
        "upload a tiktok video walkthrough of the property",
        "upload a youtube video walkthrough of the property",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Preparation & Presentation
        "conduct a property walkthrough and provide recommendations for listing readiness",
        "provide a custom listing preparation checklist",
        "collect property details and prepare mls remarks and a public listing description",
        "provide a visual consultation for interior layout, cleanliness, and presentation",
        "provide a curb appeal consultation focused on exterior presentation",
        "provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). vendor fees billed separately. referrals only — no endorsement or warranty is made",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "ensure proper notice is provided if the property is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective buyers",
        "coordinate showings with buyer's agents",
        "collect and relay showing feedback to the seller",
        // Offer & Contract Management
        "present all offers to the seller and summarize key terms, pricing, and contingencies",
        "provide the seller with the necessary disclosure forms required by state or local law",
        "negotiate price, terms, and contingencies with the buyer's agent or buyer",
        "manage communications with the buyer's agent or buyer",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with inspection-related negotiations and buyer requests for repairs",
        "monitor contract milestones, contingency periods, and financing deadlines",
        "provide referrals to attorneys, title companies, and escrow professionals (referrals only — no endorsement or warranty is made)",
        // Closing Coordination & Transaction Management
        "coordinate scheduling for inspections, appraisals, and other requested evaluations",
        "coordinate with the buyer's agent, lender, title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement and coordinate with all parties if corrections are needed",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        // Selling Strategy & Guidance
        "provide a comparative market analysis (cma) with pricing recommendations based on comparable sales, neighborhood trends, and current market conditions",
        "provide general insight on local market trends, seasonal timing, and pricing thresholds",
        "recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
        "provide general guidance on seller obligations, required disclosures, and listing preparation",
    ];

    /**
     * Commercial Seller services catalog.
     * Source: resources/views/hire_seller_agent/view.blade.php ($commercialCategories)
     */
    const COMMERCIAL_SERVICES_CATALOG = [
        // Property Marketing & Listing Promotion
        "list the property on the local multiple listing service (mls)",
        "list the property on crexi.com",
        "list the property on loopnet.com",
        "create a branded flyer summarizing the property's investment highlights and key selling points",
        "post the property on craigslist under the \"commercial for sale\" category",
        "promote the listing on facebook in commercial or investor real estate groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in professional, real estate, or commercial investment groups",
        "upload a tiktok video walkthrough of the property",
        "upload a youtube video walkthrough of the property",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Preparation & Asset Presentation
        "conduct a property walkthrough and provide recommendations for listing readiness",
        "provide a visual consultation on interior layout, cleanliness, and overall presentation",
        "provide a curb appeal consultation focused on exterior appearance and first impressions",
        "provide referrals to third-party vendors such as cleaners, handypeople, electricians, and landscapers (vendor fees billed separately; referrals only — no endorsement or warranty is made)",
        "compile essential marketing materials such as rent rolls, lease summaries, financial statements, and operating data (as available)",
        "organize zoning documentation, surveys, and public record reports (as available)",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "respond to buyer inquiries and screen for general qualifications",
        "provide non-disclosure agreement (nda) templates for access to confidential documents or showings",
        "ensure proper notice is provided if the property is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective buyers",
        "coordinate showings with buyer's agents",
        "collect and relay showing feedback to the seller",
        // Offer & Contract Management
        "present all offers to the seller and summarize key terms, pricing, and contingencies",
        "provide the seller with the necessary disclosure forms required by state or local law",
        "coordinate letter of intent (loi) submissions, counteroffers, and contract revisions",
        "negotiate deal terms such as pricing, deposit structure, closing timelines, and due diligence periods",
        "manage communication with the buyer's agent or buyer",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with inspection-related negotiations and buyer requests for repairs or credits",
        "monitor contract contingencies, including financing, estoppel review, lease audits, and environmental reports",
        "provide referrals to attorneys, title companies, escrow officers, or 1031 exchange professionals (referrals only — no endorsement or warranty is made)",
        // Closing Coordination & Transaction Management
        "coordinate inspections, appraisals, and estoppel certificate delivery with the buyer's agent or buyer, as applicable",
        "provide due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
        "coordinate with the buyer's agent, lender, title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement and coordinate with all parties if corrections are needed",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        // Selling Strategy & Guidance
        "provide a comparative market analysis (cma) with pricing insights based on recent commercial property sales, rental income trends, market cap rates, and investor activity",
        "assist in estimating capitalization rate (cap rate), price per square foot, or gross rent multiplier (grm) based on listing details and commercial comparables",
        "provide general insight on likely buyer types (e.g., owner-user, investor, 1031 exchange buyer), common value drivers, and investment strategies",
        "recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
        "provide general guidance on lease structures, expense ratios, and tenant impacts",
    ];

    /**
     * Income Property (multi-family) Seller services catalog.
     * Source: resources/views/hire_seller_agent/view.blade.php ($incomeCategories)
     */
    const INCOME_SERVICES_CATALOG = [
        // Property Marketing & Listing Promotion
        "list the property on the local multiple listing service (mls)",
        "syndicate the listing to third-party platforms (e.g., zillow.com, realtor.com, trulia.com, homes.com)",
        "list the property on crexi.com",
        "list the property on loopnet.com",
        "create a branded flyer with key rental data (e.g., unit mix, gross income, occupancy)",
        "post the property on craigslist under the \"multi-family for sale\" category",
        "share the listing on nextdoor in neighborhood or community groups",
        "promote the listing on facebook in real estate investor or multi-family buyer groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in investment or real estate groups",
        "upload a tiktok video walkthrough of the property",
        "upload a youtube video walkthrough of the property",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Preparation & Investment Packaging
        "conduct a property walkthrough and provide recommendations for listing readiness",
        "provide a custom listing preparation checklist",
        "assist with assembling an income property packet, including rent roll, lease copies, and an income/expense summary (as available)",
        "provide a visual consultation focused on interior layout, cleanliness, and unit presentation",
        "provide a curb appeal consultation focused on exterior maintenance and first impressions",
        "provide referrals to third-party vendors (e.g., cleaners, handypeople, electricians, landscapers). vendor fees billed separately. referrals only — no endorsement or warranty is made",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "respond to buyer inquiries and screen for general qualifications",
        "provide non-disclosure agreement (nda) templates for confidential showings or document access",
        "ensure proper notice is provided if the property is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective buyers",
        "coordinate showings with buyer's agents",
        "collect and relay showing feedback to the seller",
        // Offer & Contract Management
        "present all offers to the seller and summarize key terms, pricing, and contingencies",
        "provide the seller with the necessary disclosure forms required by state or local law",
        "negotiate deal structure, deposits, due diligence timelines, and buyer contingencies",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "manage communication with the buyer's agent or buyers",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "assist with inspection-related negotiations and buyer requests for repairs",
        "monitor contract contingencies, including financing, lease audits, estoppel review, insurance, inspections, and environmental reports",
        "provide referrals to attorneys, title companies, escrow officers, or 1031 exchange professionals. referrals only — no endorsement or warranty is made",
        // Closing Coordination & Transaction Management
        "review and organize due diligence documentation such as lease agreements, estoppel certificates, rent rolls, utility summaries, and operating expense breakdowns (as available)",
        "coordinate with the buyer's agent, buyer's lender, title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement for accuracy and coordinate with relevant parties if corrections are needed",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        // Selling Strategy & Guidance
        "provide a comparative market analysis (cma) with pricing insights based on recent income property sales, rental income trends, unit mix, and local investor activity",
        "assist in estimating gross rent multiplier (grm), capitalization rate (cap rate), or price per unit based on listing details and income property comparables",
        "provide general insight on likely investor buyer behavior, common value drivers, and investment strategies",
        "recommend adjustments to pricing or marketing strategy if the property is not receiving sufficient interest",
        "provide general guidance on lease transfers, rent proration, security deposits, and possession timelines",
    ];

    /**
     * Business Opportunity Seller services catalog.
     * Source: resources/views/hire_seller_agent/view.blade.php ($businessCategories)
     */
    const BUSINESS_SERVICES_CATALOG = [
        // Business Marketing & Listing Promotion
        "list the business opportunity on the local multiple listing service (mls)",
        "list the business opportunity on crexi.com",
        "list the business opportunity on loopnet.com",
        "list the business opportunity on bizbuysell.com",
        "list the business opportunity on bizquest.com",
        "list the business opportunity on businessesforsale.com",
        "create a branded flyer summarizing the business's key features (e.g., industry, cash flow, assets)",
        "post the business opportunity on craigslist under the \"business for sale\" category",
        "promote the listing on facebook in business buyer, franchise, or investor groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in business acquisition, startup, or investor groups",
        "upload a tiktok video summarizing the business opportunity",
        "upload a youtube video summarizing the business opportunity",
        "launch a mass email campaign promoting the listing",
        "distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Preparation & Confidential Marketing
        "conduct a preliminary seller consultation to gather details about the business's operations, assets, and goals",
        "provide a business sale checklist to collect financials, licenses, lease terms, and key operational details",
        "assist with preparing a non-confidential teaser or executive summary for marketing purposes",
        "organize internal documentation such as profit and loss statements, balance sheets, ff&e summaries, inventory lists, and staffing overviews (as available)",
        "refer third-party professionals such as valuation experts, accountants, or financial consultants, if requested (referrals only — no endorsement or warranty is made)",
        "compile essential marketing materials including business overviews, location descriptions, asset lists, and financial summaries",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video walkthrough tour",
        "provide a 3d virtual tour",
        "provide virtual staging (digital enhancements only; no physical staging)",
        "provide digital photo enhancements",
        "create a basic schematic floor plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "respond to buyer inquiries and screen for general qualifications",
        "provide non-disclosure agreement (nda) templates for confidential showings or document access",
        "ensure proper notice is provided if the property or business premises is occupied",
        "install a real estate sign on the property",
        "install a lockbox for agent access",
        "schedule and attend showings with prospective buyers",
        "coordinate showings with buyer's agents",
        "coordinate directly with tenant(s) or business staff to arrange access for showings",
        "collect and relay showing feedback to the seller",
        // Offer & Contract Management
        "present all letters of intent (lois) or formal offers to the seller and summarize key deal terms",
        "provide the seller with the necessary disclosure forms required by state or local law",
        "negotiate deal terms such as purchase price, deposit structure, contingencies, transition period, and asset allocation",
        "coordinate revisions, counteroffers, and ongoing communication with the buyer or their representatives",
        "manage communication with the buyer's broker or buyer",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "monitor contract contingencies and organize delivery of due diligence materials such as leases, vendor contracts, tax filings, and financial statements",
        "refer the seller to legal counsel for formal contract drafting and execution (referrals only — no legal advice provided)",
        "provide referrals to business attorneys, escrow officers, or business transfer specialists (referrals only — no endorsement or warranty is made)",
        // Closing Coordination & Transaction Management
        "coordinate buyer inspections, management interviews, and site visits as applicable",
        "provide a transaction checklist and track key deadlines throughout the escrow period",
        "coordinate with the buyer's attorney, escrow officer, or designated closing facilitator",
        "review the settlement statement and coordinate corrections with relevant parties",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        // Selling Strategy & Guidance
        "provide a business market overview with insights from recent comparable listings",
        "identify likely buyer types (e.g., owner-operator, investor, franchisee) and discuss common deal structures (e.g., asset sale, stock sale)",
        "provide general insight on common value drivers such as cash flow, recurring revenue, transferable licenses, and key staff retention",
        "provide general guidance on operational transition timelines, staff notifications, lease assignments, and post-sale training periods",
        "provide referrals to business valuation, accounting, or legal professionals (referrals only — no endorsement or warranty is made)",
    ];

    /**
     * Vacant Land Seller services catalog.
     * Source: resources/views/hire_seller_agent/view.blade.php ($vacantLandCategories)
     */
    const VACANT_LAND_SERVICES_CATALOG = [
        // Property Marketing & Listing Promotion
        "list the property in the local multiple listing service (mls)",
        "syndicate the listing to third-party platforms (e.g., zillow.com, realtor.com, trulia.com, homes.com)",
        "list the property on landwatch.com",
        "list the property on land.com",
        "list the property on landandfarm.com",
        "create a branded flyer highlighting lot features, zoning, and potential use",
        "post the listing on facebook marketplace",
        "post the listing on craigslist under the \"land for sale\" category",
        "share the listing on nextdoor in neighborhood or rural groups",
        "promote the listing on facebook in land buyers, developers, or homesteader groups",
        "share the listing on instagram using posts, stories, or reels",
        "promote the listing on linkedin in land acquisition or investment groups",
        "upload a tiktok video summarizing the land opportunity",
        "upload a youtube video summarizing the land opportunity (e.g., drone tour, narrated overview)",
        "launch a mass email campaign promoting the listing distribute printed flyers or postcards in target geographic areas",
        "launch hyperlocal or interest-based digital ad campaigns promoting the listing",
        // Listing Preparation & Research
        "provide a checklist to gather parcel data (e.g., apn, lot size, zoning, utilities, and access)",
        "assist with collecting public records, flood zone data, and land use information (as available)",
        "provide referrals to surveyors, soil testers, or land service professionals (referrals only — no endorsement or warranty is made)",
        // Photography, Video & Virtual Media
        "provide professional property photography",
        "provide aerial (drone) photography (subject to faa part 107 compliance)",
        "provide a video overview or narrated walkthrough",
        "provide a 3d virtual tour (if applicable)",
        "provide digital enhancements to media assets",
        "provide a parcel map, topographical image, or plot plan (non-certified; for marketing purposes only)",
        // Showings & Access Coordination
        "install a real estate sign on the property",
        "schedule and attend showings with prospective buyers",
        "coordinate showings with buyer's agents",
        "collect and relay showing feedback to the seller",
        // Offer & Contract Management
        "present all offers to the seller and summarize key terms, pricing, and contingencies",
        "provide the seller with the necessary disclosure forms required by state or local law",
        "negotiate price, due diligence timelines, and closing terms",
        "draft and deliver counteroffers and manage revisions to the purchase agreement",
        "manage communication with the buyer's agent or buyer",
        "assist with in-person or electronic contract signing, including e-signature setup and secure delivery of executed purchase agreements, addenda, and disclosures to all parties",
        "monitor contract contingencies, including survey, zoning verification, financing, and environmental reviews",
        "provide referrals to attorneys, title companies, escrow officers, or land use professionals (referrals only — no endorsement or warranty is made)",
        // Closing Coordination & Transaction Management
        "coordinate surveys, site visits, or environmental access with the buyer or buyer's agent, as applicable",
        "coordinate with title, escrow, and/or attorney to prepare for closing",
        "review the settlement statement and coordinate with all parties if corrections are needed",
        "confirm delivery of final executed documents, wire instructions, and closing paperwork to all relevant parties",
        "schedule and confirm the final walkthrough",
        "schedule and confirm the closing appointment",
        // Selling Strategy & Guidance
        "provide a comparative market analysis (cma) with pricing recommendations based on recent land sales, zoning categories, and location-based trends",
        "provide general insight on permitted uses, utility access, parcel features, and buyer demand in the area",
        "recommend adjustments to pricing or marketing strategy if the land is not receiving sufficient interest",
        "provide general guidance on seller obligations, disclosure requirements, and listing preparation",
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Main entry point — returns the full score array.
     *
     * @param array       $baselineData   Seller listing (or counter) data as array.
     * @param array       $comparedData   Agent bid data as array.
     * @param array|null  $brokerFields   Ignored (kept for API parity).
     * @param string|null $propertyType   'Residential Property' | 'Commercial Property' | etc.
     */
    /**
     * Calculate the match score between a baseline listing and an agent's bid.
     *
     * @param  array       $baselineData     Listing/criteria data (the client's requirements).
     * @param  array       $comparedData     Agent bid data being evaluated.
     * @param  array|null  $brokerFields     Reserved for future use (unused).
     * @param  string|null $propertyType     Activates property-type-specific service catalog.
     * @param  array|null  $agentProfileData Agent's profile_data from AgentDefaultProfile.
     *                                       When null, new dimension sub-scores return neutral
     *                                       values and do not affect the overall score
     *                                       (safe for all legacy call sites).
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null,
        ?string $propertyType = null,
        ?array $agentProfileData = null
    ): array {
        $catalog = ($propertyType !== null) ? self::getCatalog($propertyType) : null;

        // ── TERMS — Logical Group Scoring ─────────────────────────────────
        $matchedTerms      = [];
        $changedTerms      = [];
        $addedByAgentTerms = [];
        $groupMatchCount   = 0;
        $groupChangedCount = 0;
        $groupAddedCount   = 0;
        $groupTotalCount   = 0;

        foreach (self::LOGICAL_FIELD_GROUPS as $group) {
            $key    = $group['key'];
            $fields = $group['fields'];

            // 1. Baseline condition check
            if (isset($group['baseline_active_when'])) {
                if (!self::checkGroupCondition($group['baseline_active_when'], $baselineData)) {
                    continue;
                }
            }

            // 2. Build composite values
            $bComposite = self::buildCompositeValue($baselineData, $fields);
            $cComposite = self::buildCompositeValue($comparedData, $fields);

            // 3. Determine denominator membership
            if (!self::isFilled($bComposite)) {
                if (self::isFilled($cComposite)) {
                    $groupAddedCount++;
                    $addedByAgentTerms[$key] = ['baseline' => null, 'compared' => $cComposite];
                    foreach ($fields as $f) {
                        if ($f !== $key) {
                            $cv = $comparedData[$f] ?? null;
                            if (self::isFilled($cv)) {
                                $addedByAgentTerms[$f] = ['baseline' => $baselineData[$f] ?? null, 'compared' => $cv];
                            }
                        }
                    }
                }
                continue;
            }

            // 4. Bid cascade deactivation
            if (isset($group['bid_active_when'])) {
                if (!self::checkGroupCondition($group['bid_active_when'], $comparedData)) {
                    continue;
                }
            }

            // 5. Compare and classify
            $groupTotalCount++;

            if (self::normalizeForMatch($cComposite) === self::normalizeForMatch($bComposite)) {
                $groupMatchCount++;
                $matchedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $bv = $baselineData[$f] ?? null;
                        $cv = $comparedData[$f] ?? null;
                        if (self::isFilled($bv)) {
                            $matchedTerms[$f] = ['baseline' => $bv, 'compared' => $cv];
                        }
                    }
                }
            } else {
                $groupChangedCount++;
                $changedTerms[$key] = ['baseline' => $bComposite, 'compared' => $cComposite];
                foreach ($fields as $f) {
                    if ($f !== $key) {
                        $bv = $baselineData[$f] ?? null;
                        $cv = $comparedData[$f] ?? null;
                        if (self::isFilled($bv) || self::isFilled($cv)) {
                            $changedTerms[$f] = ['baseline' => $bv, 'compared' => $cv];
                        }
                    }
                }
            }
        }

        $termsBaselineTotal = $groupTotalCount;
        $termsMatchedCount  = $groupMatchCount;
        $termsChangedCount  = $groupChangedCount;
        $termsAddedCount    = $groupAddedCount;
        $termsMatchPercent  = $termsBaselineTotal > 0
            ? (int) round($termsMatchedCount / $termsBaselineTotal * 100)
            : 100;

        // ── SERVICES — Catalog-Filtered Scoring ──────────────────────────
        $rawBaseline = $baselineData['services'] ?? [];
        if (is_string($rawBaseline)) $rawBaseline = json_decode($rawBaseline, true) ?? [];
        $rawBaseline = is_array($rawBaseline) ? array_values(array_filter($rawBaseline)) : [];

        $rawCompared = $comparedData['services'] ?? [];
        if (is_string($rawCompared)) $rawCompared = json_decode($rawCompared, true) ?? [];
        $rawCompared = is_array($rawCompared) ? array_values(array_filter($rawCompared)) : [];

        $otherBaseline = $baselineData['other_services'] ?? [];
        if (is_string($otherBaseline)) $otherBaseline = json_decode($otherBaseline, true) ?? [];
        $otherBaseline = is_array($otherBaseline) ? array_values(array_filter($otherBaseline, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        $otherCompared = $comparedData['other_services'] ?? [];
        if (is_string($otherCompared)) $otherCompared = json_decode($otherCompared, true) ?? [];
        $otherCompared = is_array($otherCompared) ? array_values(array_filter($otherCompared, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        $normB = array_map(fn($s) => self::normalizeService((string) $s), $rawBaseline);
        $normC = array_map(fn($s) => self::normalizeService((string) $s), $rawCompared);

        if ($catalog !== null) {
            $normB = array_values(array_filter($normB, fn($s) => in_array($s, $catalog, true)));
            $normC = array_values(array_filter($normC, fn($s) => in_array($s, $catalog, true)));
        }

        $normOtherB = array_map(fn($s) => self::normalizeService((string) $s), $otherBaseline);
        $normOtherC = array_map(fn($s) => self::normalizeService((string) $s), $otherCompared);

        // Photo enhancement sub-options (Seller-specific) — each selected sub-option = +1
        // Applies to all Seller property types including Vacant Land (where the parent item
        // is named "Provide digital enhancements to media assets" but the sub-options are
        // stored identically in photo_enhancements[]).
        $photoEnhB = $baselineData['photo_enhancements'] ?? [];
        if (is_string($photoEnhB)) $photoEnhB = json_decode($photoEnhB, true) ?? [];
        $photoEnhB = is_array($photoEnhB) ? array_values(array_filter($photoEnhB, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        $photoEnhC = $comparedData['photo_enhancements'] ?? [];
        if (is_string($photoEnhC)) $photoEnhC = json_decode($photoEnhC, true) ?? [];
        $photoEnhC = is_array($photoEnhC) ? array_values(array_filter($photoEnhC, fn($s) => is_string($s) && !empty(trim($s)))) : [];

        $normPhotoEnhB = array_map(fn($s) => self::normalizeService((string) $s), $photoEnhB);
        $normPhotoEnhC = array_map(fn($s) => self::normalizeService((string) $s), $photoEnhC);

        $allNormB = array_merge($normB, $normOtherB, $normPhotoEnhB);
        $allNormC = array_merge($normC, $normOtherC, $normPhotoEnhC);

        $matchedServices = array_values(array_intersect($allNormB, $allNormC));
        $missingServices = array_values(array_diff($allNormB, $allNormC));
        $extraServices   = array_values(array_diff($allNormC, $allNormB));

        $servicesBaselineTotal = count($allNormB);
        $servicesMatchedCount  = count($matchedServices);
        $servicesMissingCount  = count($missingServices);
        $servicesExtraCount    = count($extraServices);
        $servicesMatchPercent  = $servicesBaselineTotal > 0
            ? (int) round($servicesMatchedCount / $servicesBaselineTotal * 100)
            : 100;

        // ── BUILD 4 / PHASE 1 SUB-SCORES ─────────────────────────────────
        // New dimensions are currently disabled in config (enabled => false).
        // Sub-scores are computed when agentProfileData is available and are
        // included in the return array for transparency and future activation.
        // When agentProfileData is null all new dimensions return neutral values
        // and the overall formula produces the same result as the legacy 50/50 split.
        $neutralSa  = (int) config('match_scoring.service_area.no_client_location_default_score', 50);
        $neutralAvl = (int) config('match_scoring.availability.agent_any_score', 80);
        $saScore     = ($agentProfileData !== null)
            ? self::scoreServiceArea($baselineData, $agentProfileData, 'seller')
            : $neutralSa;
        $expScore    = ($agentProfileData !== null)
            ? self::scoreExperience($agentProfileData)
            : 0;
        $availScore  = ($agentProfileData !== null)
            ? self::scoreAvailability($baselineData, $agentProfileData)
            : $neutralAvl;
        $compatScore = ($agentProfileData !== null)
            ? self::scoreCompatibility($baselineData, $agentProfileData)
            : 0;

        // ── OVERALL (config-driven weighted average) ───────────────────────
        $hasTerms    = $termsBaselineTotal > 0;
        $hasServices = $servicesBaselineTotal > 0;

        $overallPercent = self::computeWeightedOverall(
            $servicesMatchPercent, $termsMatchPercent,
            $hasServices, $hasTerms,
            $saScore, $expScore, $availScore, $compatScore
        );

        return [
            'overall_percent'         => $overallPercent,
            'terms_baseline_total'    => $termsBaselineTotal,
            'terms_matched_count'     => $termsMatchedCount,
            'terms_changed_count'     => $termsChangedCount,
            'terms_added_count'       => $termsAddedCount,
            'terms_match_percent'     => $termsMatchPercent,
            'matched_terms'           => $matchedTerms,
            'changed_terms'           => $changedTerms,
            'added_terms'             => $addedByAgentTerms,
            'services_baseline_total' => $servicesBaselineTotal,
            'services_matched_count'  => $servicesMatchedCount,
            'services_missing_count'  => $servicesMissingCount,
            'services_extra_count'    => $servicesExtraCount,
            'services_match_percent'  => $servicesMatchPercent,
            'matched_services'        => $matchedServices,
            'missing_services'        => $missingServices,
            'extra_services'          => $extraServices,
            // Build 4 / Phase 1 — new dimension sub-scores
            'service_area_score'      => $saScore,
            'experience_score'        => $expScore,
            'availability_score'      => $availScore,
            'compatibility_score'     => $compatScore,
            // Legacy-compat aliases
            'broker_comp_percent'     => $termsMatchPercent,
            'broker_comp_matched'     => $termsMatchedCount,
            'broker_comp_total'       => $termsBaselineTotal,
            'services_percent'        => $servicesMatchPercent,
            'services_matched'        => $servicesMatchedCount,
            'services_total'          => $servicesBaselineTotal,
        ];
    }

    /**
     * Return CSS color for a score percentage.
     */
    public static function scoreColor(int $score): string
    {
        if ($score >= 80) return '#28a745';
        if ($score >= 50) return '#ffc107';
        return '#dc3545';
    }

    /**
     * Return the normalized catalog for the given property type.
     * Supports: Residential Property, Commercial Property, Income Property,
     *           Business Opportunity, Vacant Land.
     */
    public static function getCatalog(string $propertyType): array
    {
        $pt = strtolower(trim($propertyType));
        if (str_contains($pt, 'commercial')) {
            return self::COMMERCIAL_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'income')) {
            return self::INCOME_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'business')) {
            return self::BUSINESS_SERVICES_CATALOG;
        }
        if (str_contains($pt, 'vacant') || str_contains($pt, 'land')) {
            return self::VACANT_LAND_SERVICES_CATALOG;
        }
        // Default: Residential Property (also catches 'residential property')
        return self::RESIDENTIAL_SERVICES_CATALOG;
    }

    /**
     * Normalize a service string for catalog comparison.
     */
    public static function normalizeService(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ["'", "'", '"', '"'], $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    public static function buildCompositeValue(array $data, array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            $v = $data[$f] ?? null;
            if (self::isFilled($v)) {
                $parts[] = self::normalizeForMatch($v);
            }
        }
        return implode('|', $parts);
    }

    private static function checkGroupCondition(array $conditionMap, array $data): bool
    {
        foreach ($conditionMap as $condField => $condValue) {
            if (strtolower((string) ($data[$condField] ?? '')) !== strtolower((string) $condValue)) {
                return false;
            }
        }
        return true;
    }

    private static function isFilled($v): bool
    {
        if ($v === null || $v === '' || $v === [] || $v === false) return false;
        if (is_string($v)) return trim($v) !== '';
        if (is_array($v)) return count(array_filter($v, fn($i) => $i !== null && $i !== '')) > 0;
        return true;
    }

    private static function normalizeForMatch($v): string
    {
        if (is_array($v)) {
            $parts = array_map(fn($i) => self::normalizeForMatch($i), $v);
            sort($parts);
            return implode(',', $parts);
        }
        $s = mb_strtolower(trim((string) $v));
        $s = str_replace(["\u{2019}", "\u{2018}"], "'", $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }
}

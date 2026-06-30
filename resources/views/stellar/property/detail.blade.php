@extends('layouts.main')

@section('title', ($property['address'] ?? 'Property Detail') . ' — Stellar MLS')

@section('content')
<div class="container-fluid py-3" style="max-width:1140px;">

    {{-- Back navigation --}}
    <div class="mb-3">
        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Results
        </a>
    </div>

    {{-- =======================================================================
         SECTION 1 — MLS Listing Information
    ======================================================================= --}}

    {{-- Photos --}}
    <div class="mb-4">
        <x-stellar.property-photo-carousel
            :photos="$property['photos']"
            :address="$property['address']"
        />
    </div>

    {{-- Price / address / specs / CTAs --}}
    <x-stellar.property-header :property="$property" />

    {{-- Two-column layout --}}
    <div class="row g-4 mb-2">

        {{-- ===== LEFT: description + feature cards ===== --}}
        <div class="col-12 col-lg-8">

            <x-stellar.property-description :remarks="$property['public_remarks']" />

            <x-stellar.property-interior
                :interior-features="$property['interior_features']"
                :appliances="$property['appliances']"
                :flooring="$property['flooring']"
                :cooling="$property['cooling']"
                :heating="$property['heating']"
                :laundry="$property['laundry']"
                :fireplace-features="$property['fireplace_features']"
                :window-features="$property['window_features']"
                :security="$property['security']"
                :accessibility="$property['accessibility']"
                :fireplace="$property['fireplace']"
            />

            <x-stellar.property-exterior
                :exterior-features="$property['exterior_features']"
                :construction-materials="$property['construction_materials']"
                :roof="$property['roof']"
                :foundation="$property['foundation']"
                :patio-and-porch="$property['patio_porch']"
                :other-structures="$property['other_structures']"
                :parking-features="$property['parking_features']"
                :pool-features="$property['pool_features']"
                :spa-features="$property['spa_features']"
                :view="$property['view']"
                :waterfront-features="$property['waterfront_features']"
                :pool="$property['pool']"
                :spa="$property['spa']"
                :waterfront="$property['waterfront']"
                :view-yn="$property['view_yn']"
                :garage="$property['garage']"
                :garage-spaces="$property['garage_spaces']"
                :carport-spaces="$property['carport_spaces']"
            />

            <x-stellar.property-community
                :community-features="$property['community_features']"
                :pets-allowed="$property['pets_allowed']"
            />

            <x-stellar.property-utilities
                :utilities="$property['utilities']"
                :sewer="$property['sewer']"
                :water-source="$property['water_source']"
            />

        </div>

        {{-- ===== RIGHT: key facts sidebar ===== --}}
        <div class="col-12 col-lg-4">

            <x-stellar.property-key-facts :property="$property" />

            <x-stellar.property-hoa-taxes
                :hoa="$property['hoa']"
                :hoa-fee-display="$property['hoa_fee_display']"
                :hoa-frequency="$property['hoa_frequency']"
                :hoa-name="$property['hoa_name']"
                :hoa-amenities="$property['hoa_amenities']"
                :tax-annual="$property['tax_annual']"
                :cdd="$property['cdd']"
            />

            <x-stellar.property-schools
                :elementary="$property['school_elementary']"
                :middle="$property['school_middle']"
                :high="$property['school_high']"
            />

            <x-stellar.property-office :office-name="$property['list_office_name']" />

        </div>
    </div>

    {{-- Map --}}
    <x-stellar.property-map
        :latitude="$property['latitude']"
        :longitude="$property['longitude']"
        :address="$property['address']"
    />

    {{-- =======================================================================
         SECTION SEPARATOR — BidYourOffer Matchmaker
    ======================================================================= --}}
    <div class="position-relative my-5">
        <hr class="border-0" style="border-top:2px solid #e5e7eb !important;">
        <div class="position-absolute top-50 start-50 translate-middle px-3" style="background:#f9fafb;">
            <div class="text-center py-1">
                <div class="fw-bold" style="font-size:1.1rem;color:#1f2937;">
                    <i class="fas fa-magnifying-glass-chart me-2" style="color:#8b5cf6;"></i>Why This Property Matches You
                </div>
                <div class="text-muted" style="font-size:.78rem;letter-spacing:.04em;text-transform:uppercase;">
                    Powered by BidYourOffer Matchmaker
                </div>
            </div>
        </div>
    </div>

    {{-- =======================================================================
         SECTION 2 — BidYourOffer Matchmaker Intelligence
    ======================================================================= --}}

    @if(!$criteriaId)
        <div class="alert alert-info d-flex align-items-start gap-3 mb-5" role="alert">
            <i class="fas fa-circle-info fa-lg mt-1 text-info flex-shrink-0"></i>
            <div>
                <strong>Match analysis is available from your search results.</strong><br>
                <a href="{{ route('stellar.buyer.results') }}" class="alert-link">Go to Matched Listings</a>
                and click View Details to see a personalized match score for this property.
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">

        {{-- ===== LEFT: match scores + analysis ===== --}}
        <div class="col-12 col-lg-8">

            @if($matchContext)
                <x-stellar.matchmaker-score
                    :total-score="$matchContext['total_score']"
                    :score-display="$matchContext['score_display']"
                />
                <x-stellar.matchmaker-category-bars :bars="$matchContext['category_bars']" />
                <x-stellar.matchmaker-why :items="$matchContext['why_this_matches']" />
                <x-stellar.matchmaker-tradeoffs :items="$matchContext['tradeoffs']" />
                <x-stellar.matchmaker-caution :items="$matchContext['caution_flags']" />
                <x-stellar.matchmaker-missing :items="$matchContext['missing_data']" />
            @endif

            <x-stellar.matchmaker-nearby :location-summary="$locationSummary" />

            <x-stellar.matchmaker-flood-zone :location-summary="$locationSummary" />

            <x-stellar.matchmaker-commute />

            <x-stellar.matchmaker-walkability
                :walk-score="$personality['walk_score'] ?? null"
                :transit-score="$personality['transit_score'] ?? null"
                :bike-score="$personality['bike_score'] ?? null"
            />

            <x-stellar.matchmaker-personality :personality="$personality" />

            <x-stellar.matchmaker-target-audience
                :archetype-tags="$personality['archetype_tags'] ?? []"
            />

            <x-stellar.matchmaker-appreciation />

        </div>

        {{-- ===== RIGHT: Ask AI ===== --}}
        <div class="col-12 col-lg-4">
            <x-stellar.matchmaker-ask-ai
                :listing-key="$property['listing_key']"
                :criteria-id="$askAiCriteriaId ?? null"
                :criteria-type="$criteriaType"
            />
        </div>

    </div>

</div>
@endsection

@extends('layouts.main')

{{-- Combined Fee Display Helper Functions (display-only, no storage changes) --}}
@php
  $fmtMoney = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    return '$' . number_format((float)$raw, 0);
  };

  $fmtPercent = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    $num = (float)$raw;
    return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
  };

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
@endphp

@push('styles')
  <!-- //Listing Description css  -->
  <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
  <!-- Toastr CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
  <style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Firefox */
    input[type=number] {
      -moz-appearance: textfield;
    }

    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
    .card-header h4,
    .section-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        color: #0f1a24;
    }

    /* SECTION HEADER BAR — shorter + true vertical centering */
    .card-header.section-header {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start;
        padding: 12px 18px !important;
        min-height: 0 !important;
        margin-top: 1.25rem;
    }

    /* SECTION TITLE TEXT — remove default heading spacing */
    .section-header .section-title {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1 !important;
        display: block;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #0f1a24;
    }

    /* Services section - extra breathing room before header */
    .services-section-header {
        margin-top: 0.75rem !important;
    }

    hr {
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }

    /* Field row styling - improved line-height for scan-readability */
    .col-md-12.col-12.pt-2.fw-bold {
        line-height: 1.6;
        padding-top: 0.6rem !important;
        padding-bottom: 0.2rem;
    }

    /* Broker Compensation subsection headers - breathing room */
    h5.mt-3.mb-2 {
        padding-top: 0.75rem;
        margin-top: 1rem !important;
    }

    /* Services section - Tighter spacing and indentation */
    ul.services {
        list-style: none !important;
        padding-left: 1.2em;
        margin-top: 0.35rem;
        margin-bottom: 0.5rem;
    }

    ul.services li {
        padding: 0.15rem 0;
        color: #34465c;
        position: relative;
        padding-left: 0;
        list-style: none !important;
        line-height: 1.4;
    }

    ul.services li::marker {
        content: none !important;
    }

    ul.services li::before {
        content: "\f101";
        font-family: FontAwesome;
        font-size: 1em;
        position: absolute;
        left: -1.2em;
        color: #11b7cf;
    }

    table .small {
      font-size: 14px;
    }

    .image {
      width: 200px;
    }

    .url {
      margin-left: 2px;
    }

    .removeBold {
      font-weight: normal;
    }
    .imgBox {
      width: 300px;
      height: 300px;
      display: flex;
      justify-content: center;
      align-items: flex-end;
      margin: auto;
  }
  .imgBox img{
    width:100%;
    height: 100%;
  }
    .videoBox {
      width: 300px;
      height: 300px;
      display: flex;
      justify-content: center;
      align-items: flex-end;
      margin: auto;
  }
  .videoBox video{
    width:100%;
    height: 100%;
  }
  </style>
@endpush

@section('content')
<!-- Modal -->
@if (@$auction->get->photo)
<div class="modal fade" id="lightbox" role="dialog" tabindex="-1" aria-labelledby="exampleModalLabel"
  aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <button type="button" class="close text-right p-2" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <div id="indicators" class="carousel slide" data-interval="false">
        <ol class="carousel-indicators">
          @php
            $photo = json_decode($auction->get->photo);
          @endphp
          @foreach ($photo as $image)
            <li data-target="#indicators" data-slide-to="{{ $loop->iteration }}" class="active">
            </li>
          @endforeach
        </ol>
        <div class="carousel-inner">
          @php
            $i = 1;
          @endphp
          @foreach ($photo as $image)
            <div class="carousel-item {{ $i++ == 1 ? 'active' : '' }}">
              <img class="d-block w-100" src="{{ asset($image) }}" alt="First slide">
            </div>
          @endforeach

        </div>
        <a class="carousel-control-prev" href="#indicators" role="button" data-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next" href="#indicators" role="button" data-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="sr-only">Next</span>
        </a>
      </div>

    </div>
  </div>
</div>
@endif
<!-- End  -->
  @php
    $auth_id = auth()->user() ? auth()->user()->id : 0;
    $meta = @$auction->meta;
  @endphp
  <!-- Gallery Start Here  -->
  <div class="container listingDescription">
    <div class="row">
      <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center justify-content-left">
            @if ($auction->is_approved == 1)
              <span class="badge bg-primary me-2">Active</span>
            @endif
            @if ($auction->is_approved == 0)
              <span class="badge bg-warning me-2">Pending</span>
            @endif
            @if ($auction->is_sold == 1)
              <span class="badge bg-success">Leased</span>
            @endif
          </div>
          @if ($auction->user_id == auth()->user()->id)
            <div class="d-flex justify-content-end align-content-center">
              <a href="{{route('agent.landlord.auction.edit', $auction->id)}}" class="btn btn-success btn-sm px-3 mb-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing</a>
              {{-- <a href="javascript:void(0)" class="btn btn-success btn-sm px-3 mb-3"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Auction Status</a> --}}
            </div>
          @endif
        </div>
        @php
            $_landlordAiFaq = json_decode($auction->info('listing_ai_faq') ?: '{}', true) ?? [];
            $_landlordIsOwner = auth()->check() && $auction->user_id == auth()->id();
        @endphp
        <x-listing-ai-knowledge-base
            listing-type="landlord"
            :listing-id="$auction->id"
            :is-owner="$_landlordIsOwner"
            :ai-faq="$_landlordAiFaq"
            :share-token="$auction->ai_share_token"
            :property-type="$auction->get->property_type ?? ''"
        />
        <!-- Description Box  -->
        <div class="card description">
          <div class="col-md-12 col-12 fw-bold image">
            @php
              $photos = json_decode(@$auction->info('photos'));
            @endphp
            @if (gettype($photos) == 'array')
              @foreach ($photos as $photo)
                <img src={{ asset($photo) }}>
              @endforeach
            @endif
          </div>
          <div class="card-body">
            <h4>Marketing Materials:</h4>
            <hr>
            <div class="row" style="flex-wrap: wrap;">
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1" data-toggle="modal" data-target="#lightbox">
                Property Photos:
                <!-- Main Video Baner  -->
                @php
                  $i_sr = 0;
                  $mediaImage = url('auction/images/noimage.png');
                  $photo = null;
                  if (isset($auction->get->photo)){
                    $photo = json_decode($auction->get->photo);
                    // dd($photo);
                  }
                  if (gettype($photo) == 'array') {
                      $photos = $photo;
                      $mediaImage = url(current($photos));
                  }
                @endphp
                <div class="col-sm-12 col-md-6 col-lg-8">
                  <img class="w-100" src="{{ asset(@$mediaImage) }}" data-target="#indicators"
                    data-slide-to="{{ $i_sr }}" alt="" />
                </div>
                <!-- Small Images  -->
                <div class="col-sm-12 col-md-4 col-lg-4">
                  <div class="row">
                    @if ($photo)
                      @foreach ($photo as $image)
                        <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                          <img class="w-100" src="{{ asset($image) }}" data-target="#indicators"
                            data-slide-to="{{ $i_sr++ }}" alt="" />
                        </div>
                      @endforeach
                    @else
                      <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                        <img class="w-100" src="{{ asset($mediaImage) }}" data-target="#indicators"
                          data-slide-to="{{ $i_sr++ }}" alt="" />
                      </div>
                    @endif
                  </div>
                </div>
              </div>
              @if (isset($auction->get->video_type) && $auction->get->video_type == 'video_upload')
                @if (isset($auction->get->video) && $auction->get->video !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <span class="removeBold">
                      <video src="{{ asset($auction->get->video) }}" style="width:100%;height:29vh;"
                          controls autoplay></video>
                    </span>
                  </div>
                @endif
              @elseif (isset($auction->get->video_type) && $auction->get->video_type == 'youtube_video')
                @if (isset($auction->get->youtube_video_link) && $auction->get->youtube_video_link !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <iframe width="560" height="315" src="{{$auction->get->youtube_video_link}}" 
                      title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                      referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>
                    </iframe>
                  </div>
                @endif
              @else 
                @if (isset($auction->get->vimeo_video_link) && $auction->get->vimeo_video_link !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <iframe src="{{$auction->get->vimeo_video_link}}" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>
                  </div>
                @endif
              @endif
              @if (isset($auction->get->note) && $auction->get->note !== null)
                <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                  Floor Plan:
                  @if (isset($auction->get->note) && $auction->get->note !== null && !is_array($auction->get->note))
                    <span class="removeBold">
                      <a href="{{ asset($auction->get->note) }}" class="d-block" download><img src="{{ asset($auction->get->note) }}" style="width:100px;height:130px;" /></a>
                    </span>
                  @endif
                </div>
              @endif
              @php
                if (isset($auction->get->disclosures)) {
                    $disclosure = json_decode($auction->get->disclosures) ?? null;
                } else {
                    $disclosure = null;
                }
              @endphp
              @if (isset($disclosure) && $disclosure !== null && is_array($disclosure))
                <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                  Addendums/Disclosure:
                  @if ($disclosure !== null && is_array($disclosure) && count($disclosure) > 0)
                  <div>
                    @foreach ($disclosure as $item)
                    <span class="removeBold">
                      <a href="{{ asset($item) }}" download><img src="{{ asset($item) }}" style="width:100px;height:130px;" /></a>
                    </span>
                    @endforeach
                  </div>
                  @endif
                </div>
              @endif
              @if (isset($auction->get->three_d_tour))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  3D Tour: <a href="{{ @$auction->get->three_d_tour }}" target="_blank"
                    rel="noopener noreferrer"><span class="removeBold"> {{ @$auction->get->three_d_tour }}</span></a>
                </div>
              @endif
            </div>
            <hr>

            <h4>Description:</h4>
            <p class="card-text">{{ @$auction->get->description }}</p>
            <hr>

            <h4>Price and Terms:</h4>
            <div class="row" style="flex-wrap: wrap;">
              @if(isset($auction->get->price) && $auction->get->price != null)
                <div class="col-md-12 col-12 fw-bold">Price:
                  <span class="removeBold">{{ @$auction->get->price }}</span>
                </div>
              @endif
              @if(isset($auction->get->list_price_per_sq) && $auction->get->list_price_per_sq != null)
                <div class="col-md-12 col-12 fw-bold">List Price Per Sqft:
                  <span class="removeBold">{{ @$auction->get->list_price_per_sq }}</span>
                </div>
              @endif
              @if(isset($auction->get->rentNow) && $auction->get->rentNow != null)
              <div class="col-md-12 col-12 fw-bold">Rent Now Price:
                <span class="removeBold">{{ @$auction->get->rentNow }}</span>
              </div>
              @endif
              @if(isset($auction->get->rentNowSqft) && $auction->get->rentNowSqft != null)
                <div class="col-md-12 col-12 fw-bold">Rent Now Price Per Sqft:
                  <span class="removeBold">{{ @$auction->get->rentNowSqft }}</span>
                </div>
              @endif
              @if(isset($auction->get->startingPrice) && $auction->get->startingPrice != null)
              <div class="col-md-12 col-12 fw-bold">Starting Price:
                <span class="removeBold">{{ @$auction->get->startingPrice }}</span>
              </div>
              @endif
              @if(isset($auction->get->leaseDate) && $auction->get->leaseDate != null)
                <div class="col-md-12 col-12 fw-bold">Lease Availability Date:
                  <span class="removeBold">{{ \Carbon\Carbon::parse($auction->get->leaseDate)->format('d-m-Y') }}</span>
                </div>
              @endif
              @if(isset($auction->get->leaseTime))
                @php
                  $leaseTime = json_decode($auction->get->leaseTime);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Acceptable Lease Duration:
                  @foreach ($leaseTime as $item)
                    <span class="badge bg-secondary removeBold">{{ $item !== 'Other' ? $item : $auction->get->other_lease_duration }}</span>
                  @endforeach
                </div>
              @endif
              @if(isset($auction->get->leaseTerms) && $auction->get->leaseTerms != 'null')
                @php
                  $leaseTerms = json_decode($auction->get->leaseTerms);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Terms of Lease: 
                  @foreach ($leaseTerms as $item)
                    <span class="badge bg-secondary removeBold">{{ $item !== 'Other' ? $item : $auction->get->other_lease_terms }}</span>
                  @endforeach
                </div>
              @endif
              @if(isset($auction->get->frequency) && $auction->get->frequency != null)
                @php
                  $frequency = json_decode($auction->get->frequency);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Lease Amount Frequency:
                  @foreach ($frequency as $item)
                    <span class="removeBold">{{ $item . ',' }}</span>
                  @endforeach
                </div>
              @endif
              @if(gettype(json_decode(@$auction->get->rent)) == 'array')
                  <div class="col-md-12 fw-bold">Rent Includes:
                      @foreach (json_decode(@$auction->get->rent) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->rentOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(isset($auction->get->tenant_pays) && $auction->get->tenant_pays != 'null')
                @php
                  $tenant_pays = json_decode($auction->get->tenant_pays);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Tenant Pays
                  @foreach ($tenant_pays as $item)
                    <span class="badge bg-secondary removeBold">{{ $item !== 'Other' ? $item : $auction->get->tenantPaysOther }}</span>
                  @endforeach
                </div>
              @endif

              @if(isset($auction->get->wnerPays))
                @php
                  $wnerPays = json_decode($auction->get->wnerPays);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Landlord Pays
                  @foreach ($wnerPays as $item)
                    <span class="badge bg-secondary removeBold">{{ $item !== 'Other' ? $item : $auction->get->landlordPaysOther }}</span>
                  @endforeach
                </div>
              @endif
              
              @if(isset($auction->get->required_at_move_in))
                <div class="col-md-12 col-12 fw-bold">Move-In Requirements:
                    <span class="removeBold">{{ $auction->get->required_at_move_in !== 'Other' ? $auction->get->required_at_move_in : $auction->get->leaseTermOther }}</span>
                </div>
              @endif
              @if(isset($auction->get->specialMoveOption) && $auction->get->specialMoveOption != null)
                <div class="col-md-12 col-12 fw-bold">Offered Move in Specials:
                    <span class="removeBold">{{ $auction->get->specialMoveOption }}</span>
                  @if ($auction->get->specialMoveOption == 'Yes')
                    <span class="d-inline-block removeBold badge bg-secondary">{{$auction->get->specialMove}}</span>
                  @endif
                </div>
              @endif
            </div>
            <hr>

            <h4>Landlord Pre-Screening Terms:</h4>
            <div class="row" style="flex-wrap: wrap;">
              @if(@$auction->get->petsOpt != null)
                <div class="col-md-12 col-12 fw-bold">Pets Allowed:
                  <span class="removeBold">{{ @$auction->get->petsOpt }}</span>
                </div>
              @endif
              @if(@$auction->get->petsNumber != null)
                <div class="col-md-12 col-12 fw-bold">Number of Pets Allowed:
                  <span class="removeBold">{{ @$auction->get->petsNumber }}</span>
                </div>
              @endif
              @if(@$auction->get->petsType != null)
                <div class="col-md-12 col-12 fw-bold">Acceptable Pet Types:
                  <span class="removeBold">{{ @$auction->get->petsType }}</span>
                </div>
              @endif
              @if(@$auction->get->petsWeight != null)
                <div class="col-md-12 col-12 fw-bold">Maximum Pet Weight:
                  <span class="removeBold">{{ @$auction->get->petsWeight }}</span>
                </div>
              @endif
              @if(@$auction->get->petsFee != null)
                <div class="col-md-12 col-12 fw-bold">One-Time Pet Deposit or Monthly Pet Fee:
                  <span class="removeBold">{{ @$auction->get->petsFee }}</span>
                </div>
              @endif
              @if(@$auction->get->petsAmount != null)
                <div class="col-md-12 col-12 fw-bold">Pet Fee Amount:
                  <span class="removeBold">{{ @$auction->get->petsAmount}}</span>
                </div>
              @endif
              @if(@$auction->get->petsFund != null)
                <div class="col-md-12 col-12 fw-bold">Pet Fee Type:
                  <span class="removeBold">{{ @$auction->get->petsFund}}</span>
                </div>
              @endif
              @if(@$auction->get->offer_allowed_occupants != null)
                <div class="col-md-12 col-12 fw-bold">Maximum Number of Occupants Allowed:
                  <span class="removeBold">{{ @$auction->get->offer_allowed_occupants !='Other'?@$auction->get->offer_allowed_occupants:@$auction->get->custom_occupants }}</span>
                </div>
              @endif  
              @if(@$auction->get->creditScore != null)
                <div class="col-md-12 col-12 fw-bold">Minimum Acceptable Credit Score:
                  <span class="removeBold">{{ @$auction->get->creditScore }}</span>
                </div>
              @endif
              @if(@$auction->get->offer_min_net_income != null)
                <div class="col-md-12 col-12 fw-bold">Minimum Monthly Household Net Income: 
                  <span class="removeBold">{{ @$auction->get->offer_min_net_income }}</span>
                </div>
              @endif
              @if(@$auction->get->eviction != null)
                <div class="col-md-12 col-12 fw-bold">Landlord Consideration of Tenants with Prior Evictions Within the Last 7 Years:
                  <span class="removeBold">{{ @$auction->get->eviction }}</span>
                </div>
              @endif
              @if(@$auction->get->offer_prior_felony != null)
                <div class="col-md-12 col-12 fw-bold">Landlord Consideration of Tenants with Prior Felonies Within the Last 7 Years:
                  <span class="removeBold">{{ @$auction->get->offer_prior_felony }}</span>
                </div>
              @endif
            </div>
            <hr>

            <h4>Listing Information:</h4>
              <div class="col-md-12 col-12 fw-bold">Address: 
                <span class="removeBold"> {{ @$auction->address }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">County: 
                <span class="removeBold">{{ @$auction->county }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">Listing Date:
                <span class="removeBold">{{ Carbon\Carbon::parse(@$auction->listing_date)->format('F j, Y') }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">Expiration Date:
                <span class="removeBold">{{ Carbon\Carbon::parse(@$auction->expiration_date)->format('F j, Y') }}</span>
              </div>
              @if(@$auction->get->representation != null)
              <div class="col-md-12 col-12 fw-bold">Representation:
                <span class="removeBold">{{ @$auction->get->representation }}</span>
              </div>
              @endif
              @if(@$auction->get->auction_type != null)
              <div class="col-md-12 col-12 fw-bold">Listing Type:
                <span class="removeBold">{{ @$auction->get->auction_type }}</span>
              </div>
              @endif
              @if(isset($auction->get->property_type))
              <div class="col-md-12 col-12 fw-bold">Property Style:
                <span class="removeBold">{{ @$auction->get->property_type }}</span>
                @if (isset($auction->get->property_items))
                  @php
                    $property_items = json_decode($auction->get->property_items);
                  @endphp
                  @foreach ($property_items as $item)
                    @if ($item)
                      <span class="d-inline-block removeBold badge bg-secondary">{{$item}}</span>
                    @endif
                  @endforeach
                @endif
              </div>
              @endif
              @if(isset($auction->get->leasePropOption) && @$auction->get->leasePropOption != null)
                <div class="col-md-12 col-12 fw-bold">Leasing Space:
                  <span class="removeBold">{{ @$auction->get->leasePropOption }}</span>
                </div>
                @if ($auction->get->leasePropOption == 'Single Room' && isset($auction->get->singleRoom))
                  @php
                    $singleRoom = json_decode($auction->get->singleRoom);
                  @endphp
                  @if(isset($singleRoom[0]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      Are there any shared amenities, such as conference rooms or parking facilities?
                    @else
                      What is the size of the room the landlord intends to lease?
                    @endif
                    <span class="removeBold">{{$singleRoom[0] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[1]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      Are there specific hours of operation for the building, and is 24/7 access available?
                    @else
                      Is there a private bathroom, or is it shared?
                    @endif
                    <span class="removeBold">{{ $singleRoom[1] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[2]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      Are there specific zoning restrictions or permitted uses for the space?
                    @else
                      How much storage space is available?
                    @endif
                    <span class="removeBold">{{ $singleRoom[2] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[3]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      How are maintenance issues and repairs handled for the commercial space?
                    @else
                      Can tenants use common areas like the kitchen, living room, or backyard?
                    @endif
                    <span class="removeBold">{{ $singleRoom[3] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[4]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      How are the utilities split?
                    @else
                      How is cleaning and maintenance of common areas managed?
                    @endif
                    <span class="removeBold">{{ $singleRoom[4] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[5]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      How is cleaning and maintenance of common areas managed?
                    @else
                      Are tenants allowed to have guests, and if so, are there any restrictions?
                    @endif
                    <span class="removeBold">{{ $singleRoom[5] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[6]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      How is the layout of the commercial space configured?
                    @else
                      How are maintenance issues handled?
                    @endif
                    <span class="removeBold">{{ $singleRoom[6] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[7]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      How much storage space is available?
                    @else
                      How are the utilities split?
                    @endif
                    <span class="removeBold">{{ $singleRoom[7] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[8]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      What is the size of the room the landlord intends to lease?
                    @endif
                    <span class="removeBold">{{ $singleRoom[8] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[9]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      What types of businesses are neighboring tenants in the building or surrounding area?
                    @endif
                    <span class="removeBold">{{ $singleRoom[9] }}</span>
                  </div>
                  @endif
                  @if(isset($singleRoom[10]))
                  <div class="col-md-12 col-12 fw-bold">
                    @if ($auction->get->property_type == 'Commercial Property')
                      Is there a designated reception area?
                    @endif
                    <span class="removeBold">{{ $singleRoom[10] }}</span>
                  </div>
                  @endif
                @endif
              @endif
              @if(isset($auction->get->propConditions))
                <div class="col-md-12 col-12 fw-bold">Property Condition:
                  <span class="removeBold">{{ $auction->get->propConditions !== 'Other' ? $auction->get->propConditions : $auction->get->propOther }}</span>
                </div>
              @endif
            <hr>

            <h4>Interior Features:</h4>
              @if (isset($auction->get->bedroom) && $auction->get->property_type == 'Residential Property')
              <div class="col-md-12 fw-bold">Bedrooms: 
                <span class="removeBold">{{ @$auction->get->bedroom !="Other" ? $auction->get->bedroom : $auction->get->other_bedrooms }}</span>
              </div>
              @endif
              @if (isset($auction->get->bathrooms))
                <div class="col-md-12 fw-bold">Bathrooms:
                  <span class="removeBold">{{ $auction->get->bathrooms != "Other" ? $auction->get->bathrooms : $auction->get->other_bathrooms }}</span>
                </div>
              @endif
              @if (@$auction->info('heated_sqft') != null)
                <div class="col-md-12 fw-bold">Heated Sqft:
                  <span class="removeBold">{{ @$auction->info('heated_sqft') }}</span>
                </div>
              @endif
              @if (isset($auction->get->net_leasable_sqft))
                <div class="col-md-12 fw-bold">Net Leasable Sqft: 
                  <span class="removeBold">{{ $auction->get->net_leasable_sqft }}</span>
                </div>
              @endif
              @if (@$auction->info('sqft_total') != null)
                <div class="col-md-12 fw-bold">Total Sqft:
                  <span class="removeBold">{{ @$auction->info('sqft_total') }}</span>
                </div>
              @endif
              @if (@$auction->get->heated_source != null)
                <div class="col-md-12 fw-bold">Heated SqFt Source:
                  <span class="removeBold">{{ @$auction->get->heated_source != 'Other' ? $auction->get->heated_source : $auction->get->otherSqft }}</span>
                </div>
              @endif
              @if(gettype(json_decode(@$auction->get->appliances)) == 'array')
                  <div class="col-md-12 fw-bold">Appliances:
                      @foreach (json_decode(@$auction->get->appliances) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->appliancesOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if (@$auction->get->firePlace != null)
                <div class="col-md-12 fw-bold">Fireplace:
                  <span class="removeBold">{{@$auction->get->firePlace }}</span>
                </div>
              @endif
              @if (@$auction->info('furnishings') != null)
                <div class="col-md-12 col-12 fw-bold">Furnishings:
                  <span class="removeBold"> {{ @$auction->info('furnishings') }}</span>
                </div>
              @endif
              @if ($auction->get->amenities)
                @php
                  $amenities = json_decode($auction->get->amenities);
                @endphp
                <div class="col-md-12 col-12 fw-bold">Amenities or Property Features:
                  @foreach ($amenities as $item)
                    <span class="badge bg-secondary removeBold">{{ $item !== 'Other' ? $item : ($auction->get->otherAmenities ?? '') }}</span>
                  @endforeach
                </div>
              @endif
              @if(gettype(json_decode(@$auction->get->features)) == 'array')
              <div class="col-md-12 fw-bold">Accessibility Features:
                @foreach (json_decode(@$auction->get->features) as $item)
                <span class="removeBold badge bg-secondary">
                  {{ $item }} 
                </span>
                      @endforeach
                  </div>
              @endif
              @if (@$auction->get->propFloors != null)
                <div class="col-md-12 fw-bold">Number of Floors in the Property:
                  <span class="removeBold">{{@$auction->get->propFloors }}</span>
                </div>
              @endif
              @if (@$auction->get->floorNumber != null)
                <div class="col-md-12 fw-bold">Floor Number:
                  <span class="removeBold">{{@$auction->get->floorNumber }}</span>
                </div>
              @endif
              @if (@$auction->get->totalFloors != null)
                <div class="col-md-12 fw-bold">Number of Floors in the Entire Building:
                  <span class="removeBold">{{@$auction->get->totalFloors }}</span>
                </div>
              @endif
              @if (isset($auction->get->totalBuildings))
                <div class="col-md-12 fw-bold">Total Number of Buildings:  
                  <span class="removeBold">{{@$auction->get->totalBuildings }}</span>
                </div>
              @endif
              @if (@$auction->get->building_elevator != null)
                <div class="col-md-12 fw-bold">Building Elevator:
                  <span class="removeBold">{{@$auction->get->building_elevator }}</span>
                </div>
              @endif
              @if(isset($auction->get->interiorFeatures) && is_Array($auction->get->interiorFeatures))
                  <div class="col-md-12 fw-bold">Interior Features:
                      @foreach (json_decode($auction->get->interiorFeatures) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->interiorFeatureOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(isset($auction->get->additional_rooms) && is_Array($auction->get->additional_rooms))
                  <div class="col-md-12 fw-bold">Additional Rooms:
                      @foreach (json_decode(@$auction->get->additional_rooms) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->roomOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->laundry)) == 'array')
                  <div class="col-md-12 fw-bold">Laundry Features:
                      @foreach (json_decode(@$auction->get->laundry) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->laundryOther }}</span>
                      @endforeach
                  </div>
              @endif
              
              @if(gettype(json_decode(@$auction->get->floor_covering)) == 'array')
                  <div class="col-md-12 fw-bold">Floor Covering:
                      @foreach (json_decode(@$auction->get->floor_covering) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->floorConvringOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->utilities)) == 'array')
                  <div class="col-md-12 fw-bold">Utilities:
                      @foreach (json_decode(@$auction->get->utilities) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->otherUtilities }}</span>
                      @endforeach
                  </div>
              @endif
              @if(isset($auction->get->sewer) && gettype(json_decode(@$auction->get->sewer)) == 'array')
                  <div class="col-md-12 fw-bold">Sewer:
                      @foreach (json_decode(@$auction->get->sewer) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : ($auction->get->otherSewer ?? '') }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->water)) == 'array')
                  <div class="col-md-12 fw-bold">Water:
                      @foreach (json_decode(@$auction->get->water) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->otherWater }}</span>
                      @endforeach
                  </div>
              @endif
              
              @if(gettype(json_decode(@$auction->get->airConditioning)) == 'array')
                  <div class="col-md-12 fw-bold">Air Conditioning:
                      @foreach (json_decode(@$auction->get->airConditioning) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->otherAirCondition }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->heatingFuel)) == 'array')
                  <div class="col-md-12 fw-bold">Heating and Fuel:
                      @foreach (json_decode(@$auction->get->heatingFuel) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->otherFuel }}</span>
                      @endforeach
                  </div>
              @endif
            <hr>

            @php
              $detailsOne = json_decode($auction->get->room_details_data, true);
              $details = json_decode($detailsOne, true);
            @endphp
            @if (isset($details) && is_Array($details))
              <h4>Room Details:</h4>
              @foreach ($details as $roomName => $roomData)
                <div class="col-md-12 fw-bold">Approximate Room Dimensions (Width x Length):
                  <span class="removeBold">{{$roomData['roomDimensions']}}</span>
                </div>
                <div class="col-md-12 fw-bold">Room Type:
                  <span class="removeBold badge bg-secondary">
                      {{ $roomName}} 
                  </span>
                </div>
                <div class="col-md-12 fw-bold">Room Level:
                  @foreach ($roomData['room_level'] as $item)
                    <span class="removeBold badge bg-secondary">
                        {{ $item }} 
                    </span>
                  @endforeach
                </div>
                <div class="col-md-12 fw-bold">Bedroom Closet Type:
                  <span class="removeBold badge bg-secondary">
                      {{ $roomData['bedroomCloset'] }} 
                  </span>
                </div>
                <div class="col-md-12 fw-bold">Room Primary Covering:
                  <span class="removeBold badge bg-secondary">
                    {{ $roomData['roomPrimary'] !== 'Other' ? $roomData['roomPrimary'] : $roomData['roomPrimaryOther'] }} 
                  </span>
                </div>
                <div class="col-md-12 fw-bold">Room Features:
                  <span class="removeBold badge bg-secondary">
                    {{ $roomData['room_feature'] !== 'Other' ? $roomData['room_feature'] : $roomData['room_featureOther'] }}
                  </span>
                </div>
                <br>
              @endforeach
            @endif
            <hr>
            
            <h4>Exterior Features:</h4>
              <div class="row" style="flex-wrap: wrap">
                @if (isset($auction->get->parking_feature_garage) && is_Array($auction->get->parking_feature_garage))
                  <div class="col-md-12 fw-bold">Garage/Parking Features: 
                    @foreach ($auction->get->parking_feature_garage as $item)
                      <span class="removeBold">{{ $item !== 'Other' ? $item : $auction->get->otherParking }}</span>
                    @endforeach
                  </div>
                @endif
                @if ($auction->get->front_exposure && $auction->get->front_exposure != null)
                  <div class="col-md-12 fw-bold">Front Exposure:
                    <span class="removeBold">{{@$auction->get->front_exposure }}</span>
                  </div>
                @endif
              </div>
              @if(gettype(json_decode(@$auction->get->foundation)) == 'array')
                  <div class="col-md-12 fw-bold">Foundation:
                      @foreach (json_decode(@$auction->get->foundation) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->foundationOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->exterior_construction)) == 'array')
                  <div class="col-md-12 fw-bold">Exterior Construction:
                      @foreach (json_decode(@$auction->get->exterior_construction) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->exteriorOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(isset($auction->get->buildingFeatures) && gettype(json_decode(@$auction->get->buildingFeatures)) == 'array')
                  <div class="col-md-12 fw-bold">Building Features:
                      @foreach (json_decode(@$auction->get->buildingFeatures) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->buildingFeaturesOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(isset($auction->get->road_frontage) && is_Array($auction->get->road_frontage))
                  <div class="col-md-12 fw-bold">Road Frontage:
                      @foreach (json_decode(@$auction->get->road_frontage) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->roadFrontageOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->exterior_feature)) == 'array')
                  <div class="col-md-12 fw-bold">Exterior Features:
                      @foreach (json_decode(@$auction->get->exterior_feature) as $item)
                      <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->exteriorFeatureOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->other_structures)) == 'array')
                  <div class="col-md-12 fw-bold">Other Structures:
                      @foreach (json_decode($auction->get->other_structures) as $item)
                      <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->structuresOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if(gettype(json_decode(@$auction->get->roof)) == 'array')
              <div class="col-md-12 fw-bold">Roof:
                @foreach (json_decode(@$auction->get->roof) as $item)
                  <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->roofCementOther }}</span>
                @endforeach
              </div>
              @endif

              @if(isset($auction->get->adjoining_property) && is_Array($auction->get->adjoining_property))
                  <div class="col-md-12 fw-bold">Other Structures:
                      @foreach (json_decode($auction->get->adjoining_property) as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                      @endforeach
                  </div>
              @endif

              @if(isset($auction->get->lot_features) && is_Array($auction->get->lot_features))
                  <div class="col-md-12 fw-bold">Lot Features:
                      @foreach (json_decode($auction->get->lot_features) as $item)
                        <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->otherFeatures }}</span>
                      @endforeach
                  </div>
              @endif

              @if(gettype(json_decode(@$auction->get->road_surface_type)) == 'array')
                  <div class="col-md-12 fw-bold">Road Surface Type:
                      @foreach (json_decode(@$auction->get->road_surface_type) as $item)
                          <span class="removeBold badge bg-secondary">{{ $item !== 'Other' ? $item : $auction->get->roadSurfaceOther }}</span>
                      @endforeach
                  </div>
              @endif
              @if (isset($auction->get->garage))
                <div class="col-md-12 col-12 fw-bold">Garage Spaces:
                  <span class="removeBold"> {{ @$auction->get->garage !== 'Yes' ?  $auction->get->garage : $auction->get->garage . '(' . $auction->get->garageOther . ')'}}</span>
                </div>
              @endif
              @if (isset($auction->get->carport))
                <div class="col-md-12 col-12 fw-bold">Carport Spaces:
                  <span class="removeBold"> {{ $auction->get->carport !== 'Yes' ? $auction->get->carport : $auction->get->carportOther }}</span>
                </div>
              @endif
              @if (isset($auction->get->poolOpt) && $auction->get->poolOpt != null)
                <div class="col-md-12 col-12 fw-bold">Pool:
                  <span class="removeBold"> {{ $auction->get->poolOpt !== 'Yes' ? $auction->get->poolOpt : $auction->get->poolOpt . '(' . $auction->get->pool . ')' }}</span>
                </div>
              @endif
              @if (isset($auction->get->viewOption) && $auction->get->viewOption != null)
                <div class="col-md-12 col-12 fw-bold">View:
                  @foreach (json_decode($auction->get->viewOption) as $item)
                    <span class="removeBold"> {{ $item}}</span>
                    @if ($item == 'Yes')
                      @if (isset($auction->get->view))
                        @foreach (json_decode($auction->get->view) as $item2)
                          @if ($item2 !== 'Other')
                            <span class="d-inline-block removeBold badge bg-secondary"> {{ $item2}}</span>
                          @else
                            @if (isset($auction->get->viewOther))
                              <span class="d-inline-block removeBold badge bg-secondary"> {{ $auction->get->viewOther }}</span>
                            @endif
                          @endif                          
                        @endforeach
                      @endif
                    @endif
                  @endforeach
                </div>
              @endif
            <hr>

            <h4>Water and Dock Information:</h4>
              <div class="row" style="flex-wrap: wrap">
                @if(isset($auction->get->waterAccessOpt))
                  <div class="col-md-12 fw-bold">Water Access:
                    @if ($auction->get->waterAccessOpt === 'Yes')
                      <span class="removeBold">Yes</span>
                      @if (isset($auction->get->water_access) && is_Array($auction->get->water_access))
                      @foreach ($auction->get->water_access as $item)
                        <span class="removeBold badge bg-secondary">
                          {{ $item }} 
                        </span>
                      @endforeach
                      @endif
                    @else
                    <span class="removeBold">No</span>
                    @endif
                  </div>
                @endif
                @if (isset($auction->get->has_water_view) && $auction->get->has_water_view != 'null')
                <div class="col-md-12 col-12 fw-bold">Water View:
                  @if ($auction->get->has_water_view === 'Yes')
                    <span class="removeBold">Yes</span>
                    @php
                      $water_view = json_decode($auction->get->water_view, true);
                    @endphp
                    @foreach ($water_view  as $item)
                      <span class="badge bg-secondary removeBold">{{ $item }}</span>
                    @endforeach
                  @else
                    <span class="removeBold">No</span>
                  @endif
                </div>
                @endif
                @if (isset($auction->get->has_water_extra))
                  <div class="col-md-12 col-12 fw-bold">Water Extras:
                    @if ($auction->get->has_water_extra === 'Yes')
                      <span class="removeBold">Yes</span>
                      @if (isset($auction->get->water_extras) && is_Array($auction->get->water_extras))
                        @foreach ($auction->get->water_extras as $item)
                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                        @endforeach
                      @endif
                    @else
                      <span class="removeBold">No</span>
                    @endif
                  </div>
                @endif
                @if (isset($auction->get->has_water_fontage))
                  <div class="col-md-12 col-12 fw-bold">Water Frontage:
                    @if ($auction->get->has_water_fontage === 'Yes')
                      <span class="removeBold">Yes</span>
                      @if (isset($auction->get->waterFrontageView) && is_Array($auction->get->waterFrontageView))
                        @foreach ($auction->get->waterFrontageView as $item)
                            <span class="badge bg-secondary removeBold">{{ $item }}</span>
                        @endforeach
                      @endif
                    @else
                      <span class="removeBold">No</span>
                    @endif
                  </div>
                @endif

                @if (isset($auction->get->has_dock) && $auction->get->has_dock != null)
                <div class="col-md-12 col-12 fw-bold">Dock:
                  <span class="removeBold">{{ $auction->get->has_dock }}</span>
                  @if ($auction->get->has_dock == 'Yes' && isset($auction->get->dock))
                    @foreach (json_decode($auction->get->dock) as $item)
                      <span class="removeBold badge bg-secondary">{{ $item }}</span>
                      @if ($item == 'Other' && isset($auction->get->dockDescription))
                        <span class="removeBold badge bg-secondary">{{ $auction->get->dockDescription }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
                @endif
                @if (isset($auction->get->dockLiftCapacity))
                <div class="col-md-12 col-12 fw-bold">Dock Lift Capacity: 
                  <span class="removeBold">{{ $auction->get->dockLiftCapacity }}</span>
                </div>
                @endif
                @if (isset($auction->get->dockYearBuilt))
                <div class="col-md-12 col-12 fw-bold">Dock Year Built:  
                  <span class="removeBold">{{ $auction->get->dockYearBuilt }}</span>
                </div>
                @endif
                @if (isset($auction->get->dockDimension))
                <div class="col-md-12 col-12 fw-bold">Dock Dimension:   
                  <span class="removeBold">{{ $auction->get->dockDimension }}</span>
                </div>
                @endif
                @if (isset($auction->get->dockMaintenanceFee))
                <div class="col-md-12 col-12 fw-bold">Dock Maintenance Fee:    
                  <span class="removeBold">{{ $auction->get->dockMaintenanceFee }}</span>
                </div>
                @endif
                @if (isset($auction->get->dockMaintenanceFeeFrequency) && is_Array($auction->get->dockMaintenanceFeeFrequency))
                <div class="col-md-12 col-12 fw-bold">Dock Maintenance Fee Frequency: 
                  @foreach ($auction->get->dockMaintenanceFeeFrequency as $item)
                    <span class="removeBold">{{ $item . ',' }}</span>
                  @endforeach
                </div>
                @endif
              </div>
            <hr>

            <h4>Land Information:</h4>
            <div class="row" style="flex-wrap: wrap">
              @if (@$auction->get->total_acreage != null)
                <div class="col-md-12 fw-bold">Total Acreage:
                  <span class="removeBold">{{@$auction->get->total_acreage }}</span>
                </div>
              @endif
              @if (@$auction->get->yearBuilt != null)
                <div class="col-md-12 fw-bold">Year Built:
                  <span class="removeBold">{{@$auction->get->yearBuilt }}</span>
                </div>
              @endif
              @if (@$auction->get->lotSize != null)
                <div class="col-md-12 fw-bold">Lot Size:
                  <span class="removeBold">{{@$auction->get->lotSize }}</span>
                </div>
              @endif
              @if (@$auction->get->legarName != null)
                <div class="col-md-12 fw-bold">Legal Subdivision Name:
                  <span class="removeBold">{{@$auction->get->legarName }}</span>
                </div>
              @endif
              @if (@$auction->get->taxId != null)
                <div class="col-md-12 fw-bold">Tax ID (Parcel Number):
                  <span class="removeBold">{{@$auction->get->taxId }}</span>
                </div>
              @endif
              @if (@$auction->get->zoneCode != null)
                <div class="col-md-12 fw-bold">Flood Zone Code:
                  <span class="removeBold">{{@$auction->get->zoneCode }}</span>
                </div>
              @endif

              @if (isset($auction->get->zoning))
                <div class="col-md-12 fw-bold">Zoning:
                  <span class="removeBold">{{@$auction->get->zoning }}</span>
                </div>
              @endif
              @if (isset($auction->get->tax_year))
                <div class="col-md-12 fw-bold">Tax Year: 
                  <span class="removeBold">{{@$auction->get->tax_year }}</span>
                </div>
              @endif
              @if (isset($auction->get->taxes_annual))
                <div class="col-md-12 fw-bold">Taxes (Annual Amount): 
                  <span class="removeBold">{{@$auction->get->taxes_annual }}</span>
                </div>
              @endif
              @if (isset($auction->get->legal_description))
                <div class="col-md-12 fw-bold">Legal Description: 
                  <span class="removeBold">{{@$auction->get->legal_description }}</span>
                </div>
              @endif
              @if (isset($auction->get->no_of_parcels))
                <div class="col-md-12 fw-bold">Total Number of Parcels: 
                  <span class="removeBold">{{@$auction->get->no_of_parcels }}</span>
                </div>
              @endif
              @if (isset($auction->get->additional_parcels))
                <div class="col-md-12 fw-bold">Additional Parcels:  
                  <span class="removeBold">{{@$auction->get->additional_parcels }}</span>
                </div>
              @endif
            </div>
            <hr>

            <h4>HOA and Condo Association Information:</h4>
              <div class="row" style="flex-wrap: wrap">
                @if (isset($auction->get->has_hoa))
                  <div class="col-md-12 fw-bold">Does the property have an HOA, condo association, master association, and/or
                    community fee?
                    <span class="removeBold">{{ $auction->get->has_hoa }}</span>
                  </div>
                  @if ($auction->get->has_hoa == 'Yes')
                    @if (isset($auction->get->assocRequired))
                    <div class="col-md-12 fw-bold">Association Approval Required:
                      <span class="removeBold">{{ $auction->get->assocRequired }}</span>
                    </div>
                    @endif       
                    @if (isset($auction->get->oldHouse))
                    <div class="col-md-12 fw-bold">Housing For Older Persons:
                      <span class="removeBold">{{ $auction->get->oldHouse }}</span>
                    </div>
                    @endif             
                    @if (isset($auction->get->hoa_fee_requirenment))
                    <div class="col-md-12 fw-bold">HOA Fee Requirement:
                      <span class="removeBold">{{ $auction->get->hoa_fee_requirenment }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->feeReq))
                    <div class="col-md-12 fw-bold">How much is the HOA Fee?
                      <span class="removeBold">{{ $auction->get->feeReq }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->paySchedule))
                    <div class="col-md-12 fw-bold">HOA Payment Schedule:
                      <span class="removeBold">{{ $auction->get->paySchedule }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->association_approval_fee))
                    <div class="col-md-12 fw-bold">Association Approval Fee for Tenants:
                      <span class="removeBold">{{ $auction->get->association_approval_fee }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->parking_fee_for_tenants))
                    <div class="col-md-12 fw-bold">Parking Fee For Tenants:
                      <span class="removeBold">{{ $auction->get->parking_fee_for_tenants }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->association_security_deposit))
                    <div class="col-md-12 fw-bold">Association Security Deposit Fee for Tenant:
                      <span class="removeBold">{{ $auction->get->association_security_deposit }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->other_association_fee))
                    <div class="col-md-12 fw-bold">Other Association Fees for Tenants:
                      <span class="removeBold">{{ $auction->get->other_association_fee }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->association_name))
                    <div class="col-md-12 fw-bold">Association/Manager Name:
                      <span class="removeBold">{{ $auction->get->association_name }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->association_phone))
                    <div class="col-md-12 fw-bold">Association/Manager Phone:
                      <span class="removeBold">{{ $auction->get->association_phone }}</span>
                    </div>
                    @endif  
                    @if (isset($auction->get->association_email))
                    <div class="col-md-12 fw-bold">Association/Manager Email:
                      <span class="removeBold">{{ $auction->get->association_email }}</span>
                    </div>
                    @endif  
                  @endif
                @endif
                @if(gettype(json_decode(@$auction->get->community_feature)) == 'array' && @$auction->get->community_feature != 'null' && @$auction->get->community_feature != null)
                  <div class="col-md-12 fw-bold">Community Features:
                      @foreach (json_decode(@$auction->get->community_feature) as $item)
                              @if($item !='Other')
                              <span class="removeBold badge bg-secondary">
                                {{ $item }} 
                              </span>
                              @endif
                              @if($item =='Other')
                              <span class="removeBold badge bg-secondary">
                                {{ $auction->get->communityFeatureOther }} 
                              </span>
                              @endif
                      @endforeach
                  </div>
                @endif
                @if(gettype(json_decode(@$auction->get->association_amenitie)) == 'array')
                  <div class="col-md-12 fw-bold">Association Amenities:
                      @foreach (json_decode(@$auction->get->association_amenitie) as $item)
                              @if($item !='Other')
                              <span class="removeBold badge bg-secondary">
                                {{ $item }} 
                              </span>
                              @endif
                              @if($item =='Other')
                              <span class="removeBold badge bg-secondary">
                                {{ $auction->get->otherAmenities }} 
                              </span>
                              @endif
                      @endforeach
                  </div>
              @endif
              </div>
            <hr>

            
            @if (@$auction->get->disclaimer != null)
            <h4>Legal Disclaimers:</h4>
            <div class="col-md-12 fw-bold">
              <span class="removeBold">{{ @$auction->get->disclaimer }}</span>
            </div>
            <hr>
            @endif
            

            @if (@$auction->get->driving_directions != null)
            <h4>Driving Directions:</h4>
            <div class="col-md-12 fw-bold">
                <span class="removeBold">{{ @$auction->get->driving_directions }}</span>
              </div>
              <hr>
            @endif

            <div class="card-header section-header">
              <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
            </div>

            <!-- Landlord's Broker Compensation Sub-section -->
            <h5 class="mt-3 mb-2"><strong>Landlord's Broker Compensation:</strong></h5>
            @if (isset($auction->get->compensation_structure))
              <div class="col-md-12 fw-bold">Landlord's Broker Commission Structure:
                <span class="removeBold">{{ $auction->get->compensation_structure }}</span>
              </div>
            @endif
            @if (isset($auction->get->compensation_structure_yes))
              <div class="col-md-12 fw-bold">Landlord's Broker Commission:
                <span class="removeBold">{{ $auction->get->compensation_structure_yes }}</span>
              </div>
            @endif
            <hr>


            @php
              $user = $auction->user()->first();
            @endphp

            @if ($user->user_type == 'agent')
              <h4>Listing Agent Information:</h4>
            @else
              <h4>Landlord’s Information:</h4>
            @endif

            <div class="row" style="flex-wrap: wrap;">
              <div class="col-md-12 col-12 fw-bold">First Name:
                <span class="removeBold">{{ @$auction->info('first_name') }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">Last Name:
                <span class="removeBold"> {{ @$auction->info('last_name') }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">Phone Number:
                <span class="removeBold">{{ @$auction->info('agent_phone') }}</span>
              </div>
              <div class="col-md-12 col-12 fw-bold">Email:
                <span class="removeBold">{{ @$auction->info('agent_email') }}</span>
              </div>
              @if($user->user_type === 'agent')
                <div class="col-md-12 col-12 fw-bold">Brokerage:
                  <span class="removeBold">{{ @$auction->info('agent_brokerage') }}</span>
                </div>
                <div class="col-md-12 col-12 fw-bold">Real Estate License #:
                  <span class="removeBold">{{ @$auction->info('agent_license_no') }}</span>
                </div>
                <div class="col-md-12 col-12 fw-bold">NAR Member ID (NRDS ID):
                  <span class="removeBold">{{ @$auction->info('agent_mls_id') }}</span>
                </div>
              @endif
            </div>            
          </div>
            <div class="row" style="flex-wrap: wrap;">

              @if (@$auction->info('public_private_contract_term') == 'Yes')
                <hr>
                <h4>If Landlord has an offer less than then Ideal price and terms what are the lowest
                  countered
                  terms Landlord will go (Lowest Terms Seller will accept):</h4>

                <div class="col-md-12 col-12 fw-bold">Rental Price:
                  ${{ @$auction->info('offer_rental_price') }} </div>
                <div class="col-md-12 col-12 fw-bold">Lease Terms:
                  {{ @$auction->info('special_offer_lease_term') }}
                </div>

                <div class="col-md-12 col-12 fw-bold">Security
                  Deposit:
                  ${{ @$auction->info('special_offer_security_deposit') }} </div>

                <div class="col-md-12 col-12 fw-bold">offered Move in
                  Date:
                  {{ @$auction->info('special_offer_move_date') }} </div>
              @endif

            </div>
          </div>
        
        <div class="card review">
          <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
              <img class="w-25" src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="">
              <div>
                <p class="mb-0"><a href="{{ route('author', [@$auction->user_id]) }}"><b>User
                      Details</b></a><span></span>
                  <span class="start opacity-50">
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                  </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">{{ @$auction->user->name }} • last online 5 days ago.</p>
              </div>
            </div>
            <div class="right text-center">
              <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">Message</button></a>
              <a href="{{ route('author', [@$auction->user_id]) }}"><button class="btn">View
                  Profile</button></a>

            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
        <h1>{{ @$auction->address }}</h1>
        <hr>
        @inject('carbon', 'Carbon\Carbon')
        @php
          $highest_bid_price = $auction->bids->max('price');
          $highest_bidder = @$auction->bids->where('price', $highest_bid_price)->first();
          $my_bid = ''; //@$auction->bids->where('user_id', $auth_id)->first();
        @endphp
        @if (@$auction->user_id != $auth_id)
          <a href="{{ route('auction-chat', ['landlord-property', $auction->id]) }}"
            class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message</a>
        @endif
        @if ($auth_id)
          @if (in_array(auth()->user()->user_type, ['seller', 'agent']))
            <button class="btn w-100"
              onclick="javascript:window.location='{{ route('agent.landlord.auction.bid', @$auction->id) }}';"
              {{ @$auction->user_id == $auth_id ? 'disabled' : '' }}>
              <span class="bid">Bid Now </span>
              <span class="badge bg-light float-end text-dark">${{ @$auction->get->price? @$auction->get->price:@$auction->get->startingPrice }}</span>
              @if (@$auction->sold)
                <span class="badge bg-danger">Sold</span>
              @endif
            </button>
          @endif
        @else
          <a href="{{ route('login') }}">
            <button class="btn w-100">
              <span class="bid">Login for Bid </span>
              <span class="badge bg-light float-end text-dark">${{ $lowest_bid_price }}</span>
            </button>
          </a>
        @endif
        <!-- Highest Bider -->
        <div class="card higestBider">
          @if($auction->user_id == auth()->user()->id && $bids->count() > 0)
            <div class="d-flex align-items-baseline justify-content-center">
              @if ($auction->display_bids == 0)
                <form action="{{ route('landlord.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"  method="post">
                  @csrf
                  <button class="btn bg-success btn-sm px-3 mb-3 mt-0">Show Bids</button>
                </form>
              @else
                <form action="{{ route('landlord.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}" method="post">
                  @csrf
                  <button class="btn bg-danger btn-sm px-3 mb-3 mt-0">Hide Bids</button>
                </form>
              @endif
            </div>
          @endif
          <div class="card-body">
            @if ($highest_bidder)
              <p><b>{{ $highest_bidder->user->name ?? '' }}</b> is the highest bidder.</p>
            @else
              <p>No one has bid on this auction.</p>
            @endif
            <div class="accordion" id="accordionExample">
              <div class="accordion-item border-0">
                @if ($auction->display_bids == 1 || $auction->user_id == auth()->user()->id)
                  @foreach ($bids as $bid)
                    <!-- Item loop -->
                    @if ($auction->get->auction_type === 'Auction Listing' && $bid->get->offered_price < $auction->get->reservePrice)
                      <span>The rental amount has not met the reserve price</span>
                    @endif
                    <span class=""></span>
                    <div class="accordion" type="button" data-bs-toggle="collapse"
                      data-bs-target="#item{{ $bid->id }}" aria-expanded="true"
                      aria-controls="item{{ $bid->id }}">
                      <div class="d-flex small accordion mr-0 text-center">
                        <div class="col-1">
                          <span class="badge">{{ $loop->iteration }}</span>
                        </div>
                        <div class="col-4">
                          {{ $bid->user->name }} </div>
                        <div class="col-4 text-right">
                          ${{ $bid->get->offered_price }} </div>
                        <div class="col-2">
                          Terms↓
                        </div>
                      </div>
                    </div>
                    <div id="item{{ $bid->id }}" class="accordion-collapse collapse" aria-labelledby="headingOne"
                      data-bs-parent="#accordionExample">
                      <div class="accordion-body">
                        <div id="bidding_history_data">
                          <div>
                            <div class="d-flex justify-content-center align-items-center w-100">
                              <a href="{{route('landlord.auction.bid.view', $bid->id)}}" style="color: blue">
                                  View <i class="fa fa-eye"></i>
                              </a>
                            </div>
                            <table class="table table-bordered">
                              <tbody>
                                <tr>
                                  <th class="small">First Name</th>
                                  <td class="small">{{ $bid->get->first_name }}</td>
                                </tr>
                                <tr>
                                  <th class="small">Offered Price:</th>
                                  <td class="small">${{ $bid->get->offered_price }}</td>
                                </tr>
                                <tr>
                                  <th class="small">Offered Lease Length:</th>
                                  @if (isset($bid->get->lease_terms))
                                    @php
                                      $data = json_decode($bid->get->lease_terms, true);
                                    @endphp
                                    @if (isset($data) && is_array($data) && count($data) > 0)
                                      @foreach ($data as $item)
                                        <td class="small">{{ $item !== 'Other' ? $item :  $bid->get->price}}</td>
                                      @endforeach
                                    @endif
                                  @endif
                                </tr>
                                <tr>
                                  <th class="small">Offered Lease Start Date:</th>
                                  <td class="small">{{ $bid->get->start_date }}</td>
                                </tr>
                                <tr>
                                  <th class="small">Offered Days Until the Lease Start Date:</th>
                                  <td class="small">{{ $bid->get->days_until_start_date }}</td>
                                </tr>
                                <tr>
                                  <th class="small">Acceptable Real Estate Agent Commission:</th>
                                  <td class="small">{{ $bid->get->tenant_requests_commission_amount !== 'Other' ? $bid->get->tenant_requests_commission_amount : $bid->get->tenant_requests_commission_amount_other }}</td>
                                </tr>
                                @if ($auction->get->auction_type == 'Traditional Listing')
                                  <tr>
                                    <th class="small">Offer Expires</th>
                                    <td class="small">{{ $bid->get->offer_expiry }}</td>
                                  </tr>
                                @endif
                                <tr>
                                  <th class="small">Additional Details or Countered Terms:</th>
                                  <td class="small">{{ $bid->get->additionalInfo}}</td>
                                </tr>
                              </tbody>
                            </table>

                            @if ($bid->accepted == 2)
                              <div class="d-flex justify-content-center align-items-center bg-white mb-4">
                                  <h4 class="text-danger">Rejected</h4>
                              </div>
                            @elseif ($bid->accepted != 2)
                              <div class="form-group d-flex justify-content-space gap-1">
                                @if ($auction->user_id == $auth_id && !$auction->is_sold)
                                    <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                      @csrf
                                      <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                      <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                      <div style="text-align: right;">
                                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                      </div>
                                    </form>
                                    <form action="{{ route('agent.landlord.auction.bid.reject', $bid->id) }}" id="deleteForm" method="post">
                                      @csrf
                                      <button type="button" style="background-color:#da2a43" class="btn btn-danger btn-sm" onclick="showToast()">Reject</button>
                                    </form>
                                @endif
                              </div>
                            @else
                              <div class="d-flex justify-content-center align-items-center bg-white mb-4">
                                  <h4 class="text-success">Accepted</h4>
                              </div>
                            @endif

                            @auth
                                  @if (auth()->user()->id == $bid->user->id || (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                    <div class="form-group biddingOperations">
                                      @if (!$auction->sold)
                                        <form action="{{ route('landlord.add.counter-bid', $bid->id) }}" method="get">
                                          <div class="d-flex gap-1">
                                            <button type="submit" class="btn btn-primary">Counter Bid</button>
                                          </div>
                                        </form>
                                      @endif
                                      @php
                                        $allBids = App\Models\LandlordAuctionBid::where('counter_id', $bid->id)->with('meta')
                                            ->orderByDesc('created_at')
                                            ->get();
                                      @endphp
                                      <div class="form-group">
                                        @foreach ($allBids as $key => $countBid)
                                          <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                          </form>
                                        @endforeach
                                      </div>
                                      <div class="form-group">
                                        @if (!$auction->sold)
                                          <h5>Counter Bids</h5>
                                          <hr>
                                          @if (isset($allBids) && count($allBids) > 0)
                                            @foreach ($allBids as $key => $countBid)
                                            <div class="d-flex justify-content-center align-items-center w-100">
                                              <a href="{{route('landlord.auction.bid.view', $countBid->id)}}" style="color: blue">
                                                  View <i class="fa fa-eye"></i>
                                              </a>
                                            </div>
                                              @if(isset($countBid->get->first_name))
                                                <p class="d-flex justify-content-between small fw-bold">First Name:
                                                  <span
                                                    class="removeBold">{{ $countBid->get->first_name }}</span>
                                                </p>
                                              @endif
                                              @if ($countBid->get->offered_price)
                                                <p class="d-flex justify-content-between small fw-bold">Offered Price:
                                                  <span class="removeBold">{{ $countBid->get->offered_price }}</span>
                                                </p>
                                              @endif

                                              @if (isset($countBid->get->lease_terms))
                                                @php
                                                  $data = json_decode($countBid->get->lease_terms, true);
                                                @endphp
                                                <p class="d-flex justify-content-between small fw-bold">Offered Lease Length:
                                                  @if (isset($data) && is_array($data) && count($data) > 0)
                                                    @foreach ($data as $item)
                                                      <span class="removeBold">{{ $item !== 'Other' ? $item :  $countBid->get->price }}</span>
                                                    @endforeach
                                                  @endif
                                                </p>
                                              @endif

                                              @if ($countBid->get->start_date)
                                              <p class="d-flex justify-content-between small fw-bold">Offered Lease Start Date:
                                                <span class="removeBold">{{ $countBid->get->start_date }}</span>
                                              </p>
                                              @endif

                                              @if(isset($countBid->get->days_until_start_date))
                                              <p class="d-flex justify-content-between small fw-bold">Offered Days Until the Lease Start Date:
                                                <span class="removeBold">{{ $countBid->get->days_until_start_date }}</span>
                                              </p>
                                              @endif
                                              @if (isset($countBid->get->tenant_requests_commission_amount))
                                                <p class="d-flex justify-content-between small fw-bold">Real Estate Commission Offered:
                                                  <span class="removeBold">{{ $countBid->get->tenant_requests_commission_amount !== 'Other' ? $countBid->get->tenant_requests_commission_amount : $countBid->get->tenant_requests_commission_amount_other }}</span>
                                                </p>
                                              @endif
                                              @if (isset($countBid->get->offer_expiry))
                                                <p class="d-flex justify-content-between small fw-bold">Offer Expires:
                                                  <span class="removeBold">{{ $countBid->get->offer_expiry }}</span>
                                                </p>
                                              @endif
                                              @if (isset($countBid->get->additionalInfo))
                                                <p class="d-flex justify-content-between small fw-bold">Additional Details or Countered Terms:
                                                  <span class="removeBold">{{ $countBid->get->additionalInfo }}</span>
                                                </p>
                                              @endif
                                              @if ($auction->user_id == $auth_id && !$auction->is_sold)
                                                <div class="form-group d-flex justify-content-space gap-1">
                                                  <form action="{{ route('agent.landlord.auction.bid.accept', $bid->id) }}" method="post">
                                                    @csrf
                                                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                                    <input type="hidden" name="bid_id" value="{{ $countBid->id }}">
                                                    @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                      <button type="submit" class="btn btn-success btn-sm p-2 borderless">Accept</button>
                                                    @endif
                                                  </form>
                                                  <form action="{{ route('agent.landlord.auction.bid.reject', $countBid->id) }}" id="deleteForm" method="post">
                                                    @csrf
                                                    <button type="button" style="background-color:#da2a43" class="btn btn-danger btn-sm" onclick="showToast()">Reject</button>
                                                  </form>
                                                </div>
                                              @endif
                                            @endforeach
                                          @else
                                            <div class="d-flex justify-content-center align-items-center w-100">
                                              <h6>No Counterbid</h6>
                                            </div>
                                          @endif
                                        @endif
                                      </div>
                                    </div>
                                  @endif
                            @endauth
                          </div>
                        </div>
                      </div>
                    </div>
                    <!-- End  -->
                  @endforeach
                @else
                  <div class="alert alert-danger text-center" role="alert">Bids are hidden!</div>
                @endif
              </div>
            </div>
          </div>
        </div>
        <button class="btn w-100 mt-0">
          <span class="bid m-0"><i class="fa fa-user"></i> </span>
        </button>
        <!-- End  -->
        <!-- Social Details  -->
        <div class="p-4 card">
          <p class="text-600">Share this link via</p>
          <div class="qr-code" style="width: 100%; height:200px;">
            {{ qr_code(route('agent.landlord.auction', @$auction->id), 200) }}
          </div>
          <div class="card-social">
            <ul class="icons">
              <a href="">
                <i class="fa-brands fa-facebook-f"></i>
              </a>

              <a href="">
                <i class="fa-brands fa-twitter"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-instagram"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-pinterest"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-linkedin"></i>
              </a>
            </ul>
            <p class="small opacity-8">Or copy link</p>
            <div class="field">
              <i class="fa fa-link"></i>
              <input type="text" readonly="" id="copylink"
                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
              <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
            </div>

          </div>
        </div>
        <!-- End  -->
      </div>
    </div>
  </div>
  <hr>
  <!-- Recommmended Section  -->
  <div class="container buyerOfferContentDetails">
    <h3 class="text-600 mb-4">Recommended For You</h3>
    <div class="cardsDetails row  justify-content-start">
      <!-- Card 1 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body pb-2 pt-2">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails mb-1">
              <span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon" width="15"><b>
                    4</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon" width="15"><b>
                    2</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}" alt="bed icon"
                    width="15"><b> 1,643 </b>Sq Ft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
            <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg><b>28d 03:15:29</b></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Card 2 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails">
              <span>
                <span><b>4</b> bds</span>
                <span><b>2</b> ba</span>
                <span><b>1,643</b> sqft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span class="float-end"><span><b>MLS
                    ID</b></span> <span>#12345</span></span></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <!-- Message  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  {{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
  <script src="{{ asset('assets/choices.min.js') }}"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
  {{-- <script>
        var durations = '{{ $diff_d }}d{{ $diff_H }}h{{ $diff_I }}m{{ $diff_S }}s';
        $('.timer-d').timer({
            countdown: true,
            duration: durations,
            format: '%d'
        });
        $('.timer-h').timer({
            countdown: true,
            duration: durations,
            format: '%h'
        });
        $('.timer-m').timer({
            countdown: true,
            duration: durations,
            format: '%m'
        });
        $('.timer-s').timer({
            countdown: true,
            duration: durations,
            format: '%s'
        });
    </script> --}}
  <script>
    // Function to display the Toastr toast notification and confirm before form submission
    function showToast() {
      // Define custom HTML content for the toast message with "Yes" and "No" buttons
      var toastContent =
        '<div><span>Are you sure you want to reject this bid?</span><br><br>' +
        '<div class="d-flex justify-content-between"><button type="button" class="btn btn-danger rounded" onclick="rejectBid()">Confirm</button>' +
        '<button type="button" class="btn btn-secondary border-radius-3" onclick="toastr.clear()">Cancel</button></div></div>';

      // Display custom Toastr notification with HTML content
      toastr.clear(); // Clear any existing toastr notifications
      toastr.info(toastContent, '', {
        closeButton: true,
        timeOut: 0,
        extendedTimeOut: 0
      });
    }
    
    // Function to handle "Yes" button click
    function rejectBid() {
      // Submit the form or perform any other action
      $('#deleteForm').submit();
    }

    function changeListed(val) {
      if (val == "Yes") {
        // alert("Yes");
        $('.listing_photos').addClass('d-none');
        $('.listing_link').removeClass('d-none');
      } else {
        // alert("No");
        $('.listing_link').addClass('d-none');
        $('.listing_photos').removeClass('d-none');
      }
    }
  </script>
  <script>
    $(function() {
      var multipleCancelButton = new Choices('.multiple', {
        removeItemButton: true,
        // maxItemCount:5,
        // searchResultLimit: 5,
        // renderChoiceLimit: 5
      });
    });
  </script>
@endpush

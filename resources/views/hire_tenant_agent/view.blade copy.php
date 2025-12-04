@extends('layouts.main')
@push('styles')
  <!-- //Listing Description css  -->
  <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
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

    .fa-dollar,
    .fa-percent {
      padding: 0 20px;
      background: #facd34;
      color: #fff;
      border: 0;
      font-weight: 700 !important;
      line-height: 39px !important;
      margin-right: -5px;
      z-index: 1;
      border-radius: 3px 0 0 3px;
    }

    .form-control,
    .form-select {
      border-radius: 0.25rem;
      box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
      border-radius: 0.25rem;
      background-color: #fafafb;
      margin-bottom: 15px;
    }

    ul {
      --icon-size: 1em;
      --gutter: .5em;
      padding: 0 0 0 calc(var(--icon-size) + 2em);
    }

    ul li {
      padding-left: var(--gutter);
      color: #34465c;
    }

    ul li::marker {
      content: "\f101";
      /* FontAwesome Unicode */
      font-family: FontAwesome;
      font-size: var(--icon-size);
      /* color: #006e9f; */
      color: #11b7cf;
    }

    ul.services li {
      padding-left: var(--gutter);
      color: #34465c;
      list-style: none;
      /* Remove default list style */
      position: relative;
      /* Set position relative for ::before pseudo-element */
    }

    ul.services li::before {
      content: "\f101";
      /* FontAwesome icon content */
      font-family: FontAwesome;
      font-size: var(--icon-size);
      /* Set the desired icon size */
      position: absolute;
      /* Position the icon */
      left: -1.5em;
      /* Adjust the icon position */
      color: #11b7cf;
      /* Set the icon color */
    }

    .removeBold {
      font-weight: normal;
    }
  </style>
@endpush

@section('content')
  @php
    $auth_id = auth()->user() ? auth()->user()->id : 0;
  @endphp
  <!-- Gallery Start Here  -->
  <div class="container listingDescription">
    <div class="row">
      <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
        <div class="card description">
          <div class="card-header">
            <h4>Desired Features: </h5>
          </div>
          <div class="card-body">
            <div class="row" style="flex-wrap: wrap;">
              @if (@$auction->get->working_with_agent != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Is the tenant
                  currently represented by another agent?
                  <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                </div>
              @endif
              @if (@$auction->get->cities != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> City:
                  @if (gettype(@$auction->get->cities) == 'array')
                    @foreach (@$auction->get->cities as $item)
                      <span class="removeBold">{{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->counties != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> County:
                  @if (gettype(@$auction->get->counties) == 'array')
                    @foreach (@$auction->get->counties as $item)
                      <span class="removeBold">{{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->states != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> State:
                  <span class="removeBold">{{ @$auction->get->states }}</span>
                </div>
              @endif
              @if (@$auction->get->listing_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Listing Date:
                  <span class="removeBold">{{ @$auction->get->listing_date }}
                  </span>
                </div>
              @endif
              @if (@$auction->get->expiration_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Expiration Date:
                  <span class="removeBold"> {{ @$auction->get->expiration_date }}
                  </span>
                </div>
              @endif
              @if (@$auction->get->auction_type != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Listing Type <span
                    class="removeBold">{{ @$auction->get->auction_type == 'Auction (Timer)' ? '(Auction (Timer))' : '' }}</span>:
                  <span class="removeBold">
                    @if ($auction->get->auction_type == 'Auction (Timer)')
                      {{ @$auction->get->auction_length }}
                    @endif
                    {{ @$auction->get->auction_type }}
                  </span>
                </div>
              @endif
              @if (@$auction->get->listing_title != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Title of Listing:
                  <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                </div>
              @endif
              @if (@$auction->get->county != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Need a
                  tenant’s agent for a property:
                  @if (gettype(@$auction->get->cities) == 'array')
                    @foreach (@$auction->get->cities as $item)
                      <span class="removeBold">{{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Property
                Styles :<span class="removeBold">({{ @$auction->get->property_type }})</span><br>
                @if (gettype(@$auction->get->property_items) == 'array')
                  @foreach (@$auction->get->property_items as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                  @endforeach
                @endif
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Property
                Conditions:
                @if (gettype(@$auction->get->condition_prop) == 'array')
                  @foreach (array_filter(@$auction->get->condition_prop) as $item)
                    <span class="removeBold"> {{ $item }}</span>
                    @if ($item == 'Other')
                      <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                    @endif
                  @endforeach
                @endif
              </div>
              @if (@$auction->get->condition_prop != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Leasing
                  Space:
                  <span class="removeBold">{{ $auction->get->tenant_lease }}</span>
                </div>
              @endif
              @if (@$auction->get->bedrooms != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Bedrooms
                  Needed:
                  <span class="removeBold">
                    @if (@$auction->get->bedrooms != 'Other')
                      {{ $auction->get->bedrooms }}
                    @elseif(@$auction->get->bedrooms == 'Other')
                      {{ @$auction->get->other_bedrooms }}
                    @endif
                  </span>
                </div>
              @endif
              @if (@$auction->get->bathrooms != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Bathrooms
                  Needed:
                  <span class="removeBold">
                    @if (@$auction->get->bathrooms != 'Other')
                      {{ $auction->get->bathrooms }}
                    @elseif(@$auction->get->bathrooms == 'Other')
                      {{ @$auction->get->other_bathrooms }}
                    @endif
                  </span>
                </div>
              @endif
              @if (
                  @$auction->get->garageOptions != null &&
                      @$auction->get->garageOptions != 'null' &&
                      @$auction->get->garageOptions != '')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Garage Needed<span
                    class="removeBold">({{ @$auction->get->garageOptions }})</span>:
                  @if (@$auction->get->garageOptions == 'Yes' || @$auction->get->garageOptions == 'Optional')
                    <span class="removeBold"> {{ @$auction->get->custom_garage }}</span>
                  @else
                    <span class="removeBold"> {{ @$auction->get->garageOptions }}</span>
                  @endif
                </div>
              @endif
              @if (
                  @$auction->get->carportOptions != null &&
                      @$auction->get->carportOptions != 'null' &&
                      @$auction->get->carportOptions != '')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Carport Needed<span
                    class="removeBold">({{ @$auction->get->carportOptions }})</span>:
                  @if (@$auction->get->carportOptions == 'Yes')
                    <span class="removeBold"> {{ @$auction->get->custom_carport }}</span>
                  @else
                    <span class="removeBold"> {{ @$auction->get->carportOptions }}</span>
                  @endif
                </div>
              @endif

              @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Sqft Needed:
                  <span class="removeBold">
                    {{ @$auction->get->minimum_heated_square != '' ? @$auction->get->minimum_heated_square : '' }}</span>
                </div>
              @endif
              @if (@$auction->get->minimum_net_leasable_square != null && @$auction->get->minimum_net_leasable_square != 'null')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Net Leasable
                  Sqft Needed:
                  <span class="removeBold">
                    {{ @$auction->get->minimum_net_leasable_square != '' ? @$auction->get->minimum_net_leasable_square : '' }}</span>
                </div>
              @endif
              @if (@$auction->get->garageOption != null && @$auction->get->garageOption != 'null')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Garage/Parking
                  Features Needed:
                  <span class="removeBold">
                    ({{ @$auction->get->garageOption }})
                  </span><br>
                  @if (@$auction->get->garageOption == 'Yes')
                    @foreach (@$auction->get->garage_parking as $item)
                      <span class="removeBold badge bg-secondary">
                        {{ @$item }}
                      </span>
                    @endforeach
                    @if (!empty($auction->get->other_services))
                      <span class="removeBold badge bg-secondary">
                        <br>{{ $auction->get->other_garage }}
                      </span>
                    @endif
                  @endif
                </div>
              @endif
              @if (@$auction->get->tenant_require != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Furnishings
                  Needed:
                  <span class="removeBold">
                    @if (gettype(@$auction->get->tenant_require) == 'array')
                      @foreach (@$auction->get->tenant_require as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                      @endforeach
                    @endif
                </div>
              @endif
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Minimum Total
                Acreage
                Needed:
                <span class="removeBold">
                  {{ @$auction->get->total_acreage != '' ? @$auction->get->total_acreage : '' }}</span>
              </div>
              @if (@$auction->get->poolOptions != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Pool Needed
                  :<span class="removeBold">({{ @$auction->get->poolOptions }})</span>
                  <span class="removeBold">
                    @if (@$auction->get->poolOptions)
                      {{ @$auction->get->poolOpt }}
                    @endif
                  </span>
                </div>
              @endif
              @if (@$auction->get->prefrence != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> View Preference
                  Needed:<span class="removeBold">({{ @$auction->get->prefrenceOptions }})</span><br>
                  @if (@$auction->get->prefrenceOptions == 'Yes' || @$auction->get->prefrenceOptions == 'Optional')
                    @foreach (@$auction->get->prefrence as $item)
                      <span class="removeBold badge bg-secondary">
                        {{ $item }}
                      </span>
                    @endforeach
                  @endif

                </div>
              @endif
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Eligibility/Interest
                in Leasing in 55-and-Over Communities:
                <span class="removeBold">
                  {{ @$auction->get->purchasing_props != '' ? @$auction->get->purchasing_props : '' }}</span>
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Non-Negotiable
                Amenities and Property Features
                <span class="removeBold">({{ @$auction->get->has_non_negotiable_terms }})</span>:<br>
                @if (@$auction->get->has_non_negotiable_terms == 'Yes')
                  @foreach ($auction->get->non_negotiable_terms as $item)
                    <span class="removeBold badge bg-secondary">{{ $item }}</span>
                  @endforeach
                @endif
              </div>

            </div>
            <hr>
            <div class="card-header">
                <h4>Tenant’s Leasing Term:  </h4>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Maximum Monthly Lease Price:
              <span class="removeBold">
                {{ $auction->get->budget ?? '' }}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered Lease Length:
              <span class="removeBold">
                {{ $auction->get->lease_by  ?? ''}}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered Lease Date:
              <span class="removeBold">
                {{ $auction->get->lease_for ?? ''}}</span>
            </div>
            <hr>
            <div class="card-header">
                <h4>Tenant’s Prescreening Criteria:   </h4>
            </div>
            @if(!$auction->get)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Number of Occupants:
                <span class="removeBold">
                    {{ $auction->get->people ?? '' }}</span>
                </div>
            @endif
            @if($auction->get)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Tenant’s Monthly Net Household Income:
                <span class="removeBold">
                    {{ $auction->get->monthly_income ?? '' }}</span>
                </div>
            @endif
            @if (@$auction->get)
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Pets:
              <span class="removeBold">
                {{ @$auction->get->petOptions != '' ? @$auction->get->petOptions : '' }}</span>
            </div>
            @endif
            @if (!@$auction->get  && @$auction->get->petOptions == 'Yes')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Number of Pet(s):
                <span class="removeBold">
                    {{ @$auction->get->petsNumber != '' ? @$auction->get->petsNumber : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Breed of Pet(s):
                    <span class="removeBold">
                        {{ @$auction->get->petsBreed != '' ? @$auction->get->petsBreed : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Type of Pet(s):
                    <span class="removeBold">
                        {{ @$auction->get->petsType != '' ? @$auction->get->petsType : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Weight of Pet(s):
                <span class="removeBold">
                    {{ @$auction->get->petsWeight != '' ? @$auction->get->petsWeight : '' }}</span>
                </div>
            @endif
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Tenant’s Credit Score
                Rating:
                <span class="removeBold">
                  {{ $auction->get->credit_score?? '' }}</span>
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Prior Eviction(s) in
                the
                Last 7 Years:<span class="removeBold">({{ @$auction->get->evicted }})</span>
                @if ( !@$auction->get && @$auction->get->evicted == 'Yes')
                  <span class="removeBold">
                    {{ @$auction->get->custom_evicted }}
                  </span>
                @endif
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Prior Felony
                Conviction(s)
                in the Last 7 Years:<span class="removeBold">({{ $auction->get->convicted ?? '' }})</span>
                <span class="removeBold">
                  @if ( !@$auction->get && @$auction->get->convicted == 'Yes')
                    <span class="removeBold">
                      {{ @$auction->get->custom_convicted }}
                    </span>
                  @endif
              </div>
            <hr>
            <div class="card-header">
              <h4>Desired Price and Terms: </h4>
            </div>

            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Tenant’s Monthly Net
              Household Income:
              <span class="removeBold">
                {{ $auction->get->monthly_income ?? '' }}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered Timeframe for
              the
              Tenant Agency Agreement:
              <span class="removeBold">
                {{ $auction->get->tenant_terms ?? ''}}</span>
            </div>
            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered Finder's Fee
              Amount
              the Tenant Will Pay to Agent:<span class="removeBold">({{ $auction->get->find_property  ?? ''}})</span>
              <span class="removeBold">
                @if ( !@$auction->get && $auction->get->find_property == 'Yes')
                  {{ $auction->get->property_fee }}
              </span>
              @endif
            </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Most Important Aspects the Tenant Considers When Hiring a Real Estate Agent:<span class="removeBold">{{ $auction->get->important_aspects ?? '' }}</span>
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Additional Details:<span class="removeBold">{{ $auction->get->additional_details ?? ''}}</span>
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Services the Tenant Requests from Their Agent:
                <ul>
                  @if (!@$auction->get)
                    @foreach ($auction->get->services as $service)
                      <li style="font-size: 16px;">
                        {{ $service }}
                    @endforeach
                    @if (!empty($auction->get->other_services))
                      <br>{{ $auction->get->other_services }}
                    @endif
                    </li>
                  @endif
                </ul>
              </div>
              <hr/>
              <div class="card-header">
                <h4>Tenant’s Info </h4>
              </div>
            @if ( !@$auction->get && $auction->get->first_name != null)
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> First Name:
                <span class="removeBold">
                  {{ $auction->get->first_name }}
                </span>
              </div>
            @endif
            @if ( !@$auction->get && $auction->get->last_name != null)
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Last Name:
                <span class="removeBold">
                  {{ $auction->get->last_name }}
                </span>
              </div>
            @endif
            @if ( !@$auction->get && $auction->get->email != null)
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Email:
                <span class="removeBold">
                  {{ $auction->get->email }}
                </span>
              </div>
            @endif
            @if (!@$auction->get &&  $auction->get->phone != null)
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Phone:
                <span class="removeBold">
                  {{ $auction->get->phone }}
                </span>
              </div>
            @endif
            <div class="row">
                @if ( !@$auction->get && $auction->get->video != null)
                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                    <span class="removeBold">
                        <video src="{{ asset('auction/videos/' . $auction->get->video) }}" style="width:100%;height:29vh;"
                            controls autoplay></video>

                        </span>
                    </div>
                    @endif
                    @if ( !@$auction->get && $auction->get->photo != null)
                  <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
                    <span class="removeBold">
                      <img src="{{ asset('auction/images/' . $auction->get->photo) }}" style="width:100%;height:29vh;" />
                    </span>
                  </div>
                @endif
            </div>
          </div>
        </div>
        @inject('auctionUser', 'App\Models\User')
        @php
          $auser = $auctionUser::find(@$auction->user_id);
        @endphp
        <!-- Review  -->
        <div class="card review">
          <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
              <img class="w-25"
                src="{{ $auser->avatar ? asset('images/avatar/' . $auser->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                alt="">
              <div>
                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
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
                <p class="mb-0 opacity-50">{{ $auser->name }} • last online 5 days ago.</p>
              </div>
            </div>
            <div class="right text-center">
              <a href="{{ route('author', [$auser->id]) }}"><button class="btn">Message</button></a>
              <a href="{{ route('author', [$auser->id]) }}"><button class="btn">View Profile</button></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
        <h1>{{ @$auction->title }}</h1>
        <hr>
        @inject('carbon', 'Carbon\Carbon')
        @php
          if (@$auction->get->auction_length_days > 0) {
              $start = $carbon::now();
              $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->get->auction_length_days);
              $diff = $end->diffInDays($start);
          }
        @endphp
        @if (@$auction->get->auction_length_days > 0)
          @php
            $diff_d = $diff;
            $diff_H = $start->diff($end)->format('%H');
            $diff_I = $start->diff($end)->format('%I');
            $diff_S = $start->diff($end)->format('%S');
          @endphp
          <div class="time d-flex justify-content-between text-center flex-wrap pb-2">
            <div>
              <h5><b class="timer-d"> {{ $diff_d }} </b></h5>
              <h6 class="opacity-50">Days</h6>
            </div>
            <div>
              <h5><b class="timer-h"> {{ $diff_H }} </b></h5>
              <h6 class="opacity-50">Hrs</h6>
            </div>
            <div>
              <h5><b class="timer-m"> {{ $diff_I }} </b></h5>
              <h6 class="opacity-50">Mins</h6>
            </div>
            <div>
              <h5><b class="timer-s"> {{ $diff_S }} </b></h5>
              <h6 class="opacity-50">Secs</h6>
            </div>
          </div>
        @endif
        @php
          $lowest_bid_price = @$auction->bids->min('brokerage') ?? @$auction->get->concession;
          $lowest_bid_price =
              $lowest_bid_price < @$auction->get->concession ? $lowest_bid_price : @$auction->get->concession;
          $lowest_bidder = @$auction->bids->where('brokerage', $lowest_bid_price)->first();
          $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp
        @if (@$auction->user_id != $auth_id)
          <a href="{{ route('auction-chat', ['tenant-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2"> <i
              class="fa-solid fa-paper-plane"></i> Send Message</a>
        @endif
        @if ($auth_id)
          @if (in_array(auth()->user()->user_type, ['agent']))
            <button class="btn w-100"
              onclick="javascript:window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';"
              {{-- {{ $my_bid || @$auction->user_id == $auth_id ? 'disabled' : '' }} --}}>
              <span class="bid">Bid Now </span>
              <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
              @if (@$auction->sold)
                <span class="badge bg-danger">Sold</span>
              @endif
            </button>
          @endif
        @else
          <a href="{{ route('login') }}">
            <button class="btn w-100">
              <span class="bid">Login for Bid </span>
              <span class="badge bg-light float-end text-dark">{{ @$auction->get->buyer_budget }}</span>
            </button>
          </a>
        @endif
        <div class="card higestBider">
          <div class="card-body">
            @if ($lowest_bidder)
              <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p>
            @else
              <p>No one has bid on this auction.</p>
            @endif
            <div class="accordion" id="accordionExample">
              <div class="accordion-item border-0">
                @foreach (@$auction->bids as $bid)
                  <!-- Item loop -->
                  <div class="accordion" type="button" data-bs-toggle="collapse"
                    data-bs-target="#item{{ @$bid->id }}" aria-expanded="true"
                    aria-controls="item{{ @$bid->id }}">
                    <div class="d-flex small accordion mr-0 text-center">
                      <div class="col-1">
                        <span class="badge">{{ $loop->iteration }}</span>
                      </div>
                      <div class="col-4">
                        {{ @$bid->user->name }} </div>
                      <div class="col-4 text-right">
                        {{ @$bid->get->agent_fee }} </div>
                      <div class="col-2">
                        Terms↓
                      </div>
                    </div>
                  </div>
                  <div id="item{{ @$bid->id }}" class="accordion-collapse collapse" aria-labelledby="headingOne"
                    data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <div id="bidding_history_data">
                        <div>
                          <p class="d-flex justify-content-between small"> First Name:
                            <span>{{ @$auction->get->first_name }}</span>
                          </p>
                          <p class="d-flex justify-content-between small">Tenant Agency Agreement Timeframe:
                            @if (@$bid->get->listing_terms != null && @$bid->get->listing_terms != '' && @$bid->get->listing_terms != 'Other')
                              <span>{{ @$bid->get->listing_terms }}</span>
                            @elseif(@$bid->get->custom_listing_terms != null && @$bid->get->custom_listing_terms != '')
                              <span>{{ @$bid->get->custom_listing_terms }}</span>
                            @endif
                          </p>
                          <p class="d-flex justify-content-between small">Agent Finder’s Fee:
                            @if (@$bid->get->finder_fee != null && @$bid->get->finder_fee != '')
                              @if (@$bid->get->finder_fee == 'Yes')
                                <span>{{ @$bid->get->custom_finder_fee }}</span>
                              @else
                                <span>{{ @$bid->get->finder_fee }}</span>
                              @endif
                            @endif
                          </p>
                          @if (@$bid->get->services)
                            <div>
                              <label>Services Offered by the Agent:</label>
                              <ul class="services">
                                @foreach (@$bid->get->services as $service)
                                  @if ($service == 'Other')
                                    @continue
                                  @endif
                                  <li style="font-size: 16px; margin-top:15px;">
                                    {{ $service }}</li>
                                @endforeach
                                @if (@$bid->get->other_services != '' && @$bid->get->other_services != 'null')
                                  <li style="font-size: 16px; margin-top:15px;">
                                    {{ @$bid->get->other_services }}</li>
                                @endif
                              </ul>
                            </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
        <button class="btn w-100 mt-0">
          <span class="bid m-0"><i class="fa fa-user"></i> </span>
        </button>
        <div class="p-4 card">
          <p class="text-600">Share this link via</p>
          <div class="qr-code" style="width: 100%; height:200px;">
            {{ qr_code(route('tenant.agent.view.auction.view', @$auction->id), 200) }}
          </div>
          <div class="card-social">
            <ul class="icons">
              <a href="">
                <i class="fab fa-facebook-f"></i>
              </a>
              <a href="">
                <i class="fab fa-twitter"></i>
              </a>
              <a href="">
                <i class="fab fa-instagram"></i>
              </a>
              <a href="">
                <i class="fab fa-pinterest"></i>
              </a>
              <a href="">
                <i class="fab fa-linkedin"></i>
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
      </div>
    </div>
  </div>
  <hr>
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
  @if (@$auction->get->auction_length_days > 0)
    <script>
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
    </script>
  @endif
@endpush

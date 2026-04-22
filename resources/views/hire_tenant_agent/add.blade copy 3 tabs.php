@extends('layouts.main')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
  <style>
    /* Custom styles for the form */
    .wizard-steps-progress {
      height: 5px;
      width: 100%;
      background-color: #CCC;
      position: absolute;
      top: 0;
      left: 0;
    }

    .steps-progress-percent {
      height: 100%;
      width: 0%;
      background-color: #11b7cf;
    }

    .wizard-step {
      display: none;
    }

    .wizard-step.active {
      display: block;
    }

    .tab-content {
      padding: 20px;
      border: 1px solid #ddd;
      border-top: none;
    }

    .nav-tabs .nav-link {
      border: 1px solid #ddd;
      border-bottom: none;
      margin-right: 5px;
      padding: 10px 20px;
      background-color: #f8f9fa;
    }

    .nav-tabs .nav-link.active {
      background-color: #fff;
      border-bottom: 1px solid #fff;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      font-weight: bold;
    }

    .form-control {
      min-height: 50px;
    }

    .input-cover {
      align-items: center;
      position: relative;
    }

    .input-cover .input-icon {
      position: absolute;
      left: 10px;
      font-size: 30px;
      color: #11b7cf;
    }

    .input-cover .form-control {
      padding-left: 50px;
    }

    .error {
      color: red;
      font-size: 14px;
    }

    .nav-tabs .nav-link.active {
  background-color: #049399 !important;
  color: white !important;
  border-color: #049399 !important;
}

  </style>
@endpush

@section('content')
  <div class="container pt-5 pb-5">
    <div class="card">
      <div class="row">
        <div class="col-12 p-4">
          <form class="p-4 pt-0 mainform" action="{{ route('tenant.hire.agent.auction') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="myTab" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="listing-details-tab" data-bs-toggle="tab" data-bs-target="#listing-details" type="button" role="tab" aria-controls="listing-details" aria-selected="true">Listing Details</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="property-preferences-tab" data-bs-toggle="tab" data-bs-target="#property-preferences" type="button" role="tab" aria-controls="property-preferences" aria-selected="false">Property Preferences</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="leasing-terms-tab" data-bs-toggle="tab" data-bs-target="#leasing-terms" type="button" role="tab" aria-controls="leasing-terms" aria-selected="false">Leasing Terms</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="pre-screening-tab" data-bs-toggle="tab" data-bs-target="#pre-screening" type="button" role="tab" aria-controls="pre-screening" aria-selected="false">Pre-Screening</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab" aria-controls="services" aria-selected="false">Services</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="additional-details-tab" data-bs-toggle="tab" data-bs-target="#additional-details" type="button" role="tab" aria-controls="additional-details" aria-selected="false">Additional Details</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="broker-compensation-tab" data-bs-toggle="tab" data-bs-target="#broker-compensation" type="button" role="tab" aria-controls="broker-compensation" aria-selected="false">Broker Compensation</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tenant-info-tab" data-bs-toggle="tab" data-bs-target="#tenant-info" type="button" role="tab" aria-controls="tenant-info" aria-selected="false">Tenant Info</button>
              </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="myTabContent">
              <!-- Listing Details Tab -->
              <div class="tab-pane fade show active" id="listing-details" role="tabpanel" aria-labelledby="listing-details-tab">
                <h4>Listing Details</h4>
                <div class="form-group">
                  <label class="fw-bold">Listing Title:</label>
                  <input type="text" name="listing_title" class="form-control" required>
                  <span class="error" id="listing_title_error"></span>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Current Representation Agreement Status with Broker:</label>
                  <select name="working_with_agent" class="form-control" required>
                    <option value="">Select</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                  <span class="error" id="working_with_agent_error"></span>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Listing Date:</label>
                  <input type="date" name="listing_date" class="form-control" required>
                  <span class="error" id="listing_date_error"></span>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Expiration Date:</label>
                  <input type="date" name="expiration_date" class="form-control" required>
                  <span class="error" id="expiration_date_error"></span>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Auction or Traditional Listing:</label>
                  <select name="auction_type" class="form-control" required>
                    <option value="">Select</option>
                    <option value="Auction">Auction</option>
                    <option value="Traditional">Traditional</option>
                  </select>
                  <span class="error" id="auction_type_error"></span>
                </div>
              </div>

              <!-- Property Preferences Tab -->
              
              <div class="tab-pane fade" id="property-preferences" role="tabpanel" aria-labelledby="property-preferences-tab">
                <h4>Property Preferences</h4>
            
                <div class="form-group">
                    <label class="fw-bold">Acceptable Cities:</label>
                    <input type="text" name="cities[]" class="form-control" required>
                    <span class="error" id="cities_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Acceptable Counties:</label>
                    <input type="text" name="counties[]" class="form-control" required>
                    <span class="error" id="counties_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Acceptable State:</label>
                    <input type="text" name="state" class="form-control" required>
                    <span class="error" id="state_error"></span>
                </div>
            
                <div class="form-group">
                     @php
                      $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];

                     @endphp
                  <label class="fw-bold">Acceptable Property Styles: </label>
                  <select name="property_type" id="property_type" class="form-control"
                    onchange="changePropertyType(this.value);check_hoa();property_questions();">
                    <option value="">Select</option>
                    @foreach ($property_types as $row_pt)
                      <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
                        data-icon='<i class="fa-solid fa-hotel"></i>'>
                        {{ $row_pt['name'] }}
                      </option>
                    @endforeach
                  </select>
                    <span class="error" id="property_styles_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Acceptable Leasing Space:</label>
                    <input type="text" name="leasing_space" class="form-control" required>
                    <span class="error" id="leasing_space_error"></span>
                </div>
            
              


                <div class="form-group">
                    @php
                     $property_condition = [
                        ['name' => 'New Construction', 'target' => ''],
                        ['name' => 'Completely Updated: No updates needed.', 'target' => ''],
                        ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''],
                        ['name' => 'Not Updated: Requires a complete update.', 'target' => ''],
                        ['name' => 'Open to any type of property condition.', 'target' => ''],
                        ['name' => 'Other ', 'target' => '.other_property_condition'],

                     ];

                    @endphp
                    <label class="fw-bold">Acceptable Property Conditions:</label>
                    <select name="condition_prop[]" id="condition_prop" class="form-control"
                   onchange="changePropertyType(this.value);check_hoa();property_questions();">
                   <option value="">Select</option>
                   @foreach ($property_condition as $row_pt)
                     <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
                       data-icon='<i class="fa-solid fa-hotel"></i>'>
                       {{ $row_pt['name'] }}
                     </option>
                   @endforeach
                 </select>
               </div>
                
               <div class="form-group">
                    @php
                     $bedroomsRes = [
                            ['name' => '1', 'target' => ''],
                            ['name' => '2', 'target' => ''],
                            ['name' => '3', 'target' => ''],
                            ['name' => '4', 'target' => ''],
                            ['name' => '5', 'target' => ''],
                            ['name' => '6', 'target' => ''],
                            ['name' => '7', 'target' => ''],
                            ['name' => '8', 'target' => ''],
                            ['name' => '9', 'target' => ''],
                            ['name' => '10', 'target' => ''],
                            ['name' => 'Other', 'target' => '.other_bedrooms_res'],
                            ];

                    @endphp
                    <label class="fw-bold">Minimum Bathrooms Needed:</label>
                    <select name="bathrooms" id="bathrooms" class="form-control"
                   onchange="changePropertyType(this.value);check_hoa();property_questions();">
                   <option value="">Select</option>
                   @foreach ($bedroomsRes as $row_pt)
                     <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
                       data-icon='<i class="fa-solid fa-hotel"></i>'>
                       {{ $row_pt['name'] }}
                     </option>
                   @endforeach
                 </select>
               </div>



            
                {{-- <div class="form-group">
                    <label class="fw-bold">Minimum Bedrooms Needed:</label>
                    <input type="number" name="bedrooms" class="form-control" required>
                    <span class="error" id="bedrooms_error"></span>
                </div> --}}
            
                <div class="form-group">
                    <label class="fw-bold">Minimum Bathrooms Needed:</label>
                    <input type="number" name="bathrooms" class="form-control" required>
                    <span class="error" id="bathrooms_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Minimum Heated Sqft Needed:</label>
                    <input type="number" name="minimum_heated_square" class="form-control">
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Minimum Total Acreage Needed:</label>
                    <input type="number" name="min_acreage" class="form-control">
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Furnishings Needed:</label>
                    <select name="tenant_require" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Fully Furnished">Fully Furnished</option>
                        <option value="Partially Furnished">Partially Furnished</option>
                        <option value="Not Furnished">Not Furnished</option>
                    </select>
                    <span class="error" id="furnishings_needed_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Carport Needed:</label>
                    <select name="carport_needed" class="form-control">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
=                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Garage Needed:</label>
                    <select name="garage_needed" class="form-control">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Pool Needed:</label>
                    <select name="pool_needed" class="form-control">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">View Preference Needed:</label>
                    <input type="text" name="view_preference" class="form-control">
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Eligibility/Interest in Leasing in 55-and-Over Communities:</label>
                    <select name="leasing_55_plus" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                    <span class="error" id="leasing_55_plus_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Non-Negotiable Amenities and Property Features:</label>
                    <textarea name="non_negotiable_amenities" class="form-control" rows="3"></textarea>
                </div>
            
            </div>

            
              <!-- Property Preferences Tab End -->

              <!-- leasing terms Tab -->
              
              <div class="tab-pane fade" id="leasing-terms" role="tabpanel" aria-labelledby="leasing-terms-tab">
                <h4>Leasing Terms</h4>
             
                
            
                <div class="form-group">
                    <label class="fw-bold">Maximum Monthly Lease Price:</label>
                    <input type="text" name="budget" class="form-control" required>
                    <span class="error" id="budget_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Offered Lease Length:</label>
                    <input type="text" name="custom_lease_for" class="form-control" required>
                    <span class="error" id="custom_lease_for_error"></span>
                </div>
            
                <div class="form-group">
                    <label class="fw-bold">Offered Lease Date:</label>
                    <input type="date" name="lease_by" class="form-control" required>
                    <span class="error" id="lease_by_error"></span>
                </div>
            
                         </div>

            
              <!-- leasing terms Tab End-->

            
            

              <!-- Other Tabs (Leasing Terms, Pre-Screening, Services, Additional Details, Broker Compensation, Tenant Info) -->
              <!-- Add fields for each tab as per the document -->

            </div>

            <!-- Navigation Buttons -->
            <div class="d-flex justify-content-between form-group mt-4">
              <div>
                <button type="button" class="btn btn-secondary wizard-step-back">Back</button>
              </div>
              <div>
                <button type="button" class="btn btn-primary wizard-step-next">Next</button>
                <button type="submit" class="btn btn-success wizard-step-finish">Save</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>


$(document).ready(function () {
  // Handle next button click
  $('.wizard-step-next').click(function () {
    var currentTab = $('.nav-tabs .nav-link.active');
    var currentTabContent = $(currentTab.attr('data-bs-target'));
    var isValid = true;

    // Validate all required fields in the current tab
    currentTabContent.find('input, select, textarea').each(function () {
      if ($(this).prop('required') && !$(this).val()) {
        isValid = false;
        $(this).addClass('is-invalid');
        $(this).next('.error').text('This field is required.');
      } else {
        $(this).removeClass('is-invalid');
        $(this).next('.error').text('');
      }
    });

    // If all fields are valid, proceed to the next tab
    if (isValid) {
      var nextTab = currentTab.parent().next().find('.nav-link');
      if (nextTab.length) {
        // Switch to next tab
        currentTab.removeClass('active');
        nextTab.addClass('active');

        // Switch to corresponding tab content
        var nextTabContent = $(nextTab.attr('data-bs-target'));
        $('.tab-pane').removeClass('show active'); // Hide all tab contents
        nextTabContent.addClass('show active'); // Show the selected tab content

        // Move focus to the newly selected tab for better UX
        nextTab.trigger('click');
      }
    }
  });

  // Handle back button click
  $('.wizard-step-back').click(function () {
    var currentTab = $('.nav-tabs .nav-link.active');
    var prevTab = currentTab.parent().prev().find('.nav-link');

    if (prevTab.length) {
      // Switch to previous tab
      currentTab.removeClass('active');
      prevTab.addClass('active');

      // Switch to corresponding tab content
      var prevTabContent = $(prevTab.attr('data-bs-target'));
      $('.tab-pane').removeClass('show active'); // Hide all tab contents
      prevTabContent.addClass('show active'); // Show the selected tab content

      // Move focus to the newly selected tab for better UX
      prevTab.trigger('click');
    }
  });
});

  </script>
@endpush

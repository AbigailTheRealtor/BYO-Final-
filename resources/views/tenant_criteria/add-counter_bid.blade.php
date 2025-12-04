@extends('layouts.main')
@push('styles')
  <style>
    .choices__list {
      z-index: 999;
    }

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

    label.warning {
      color: #f00;
    }

    ::placeholder {
      color: #cacaca !important;
      opacity: 1;
      /* Firefox */
    }

    :-ms-input-placeholder {
      /* Internet Explorer 10-11 */
      color: #cacaca !important;
    }

    ::-ms-input-placeholder {
      /* Microsoft Edge */
      color: #cacaca !important;
    }

    .hide_arrow::-webkit-outer-spin-button,
    .hide_arrow::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Firefox */
    .hide_arrow {
      -moz-appearance: textfield;
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

    .form-control {
      min-height: 50px;
    }

    .form-group {
      margin-top: 15px;
    }

    .options-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-start;
    }

    .option-container {
      align-items: center;
      cursor: pointer;
      display: flex;
      flex-direction: row;
      align-items: center;
      padding: 15px;
      margin: 10px;
      margin-left: 0;
      margin-bottom: 0;
    }

    .option-container.active {
      border-color: #006e9f;
      color: #006e9f;
    }

    .option-container .option-icon {
      font-size: 40px;
      color: #11b7cf;
    }

    .option-container .option-text {
      padding-left: 10px;
    }

    .text-error {
      width: 100%;
    }

    .text-error {
      border-color: rgba(var(--bs-danger-rgb), var(--bs-text-opacity)) !important;
    }

    .grid-picker {
      width: 100%;
      height: 0px;
      visibility: hidden;
    }

    ::-ms-browse {
      height: 50px;
    }

    ::-webkit-file-upload-button {
      height: 50px;
    }

    input[type=file]::file-selector-button {
      height: 50px;
    }
    .box {
      display: block;
      width: 200px;
      height: 160px;
      background-color: white;
      border-radius: 5px;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      /* overflow: hidden; */
      position: relative;
    }

    .js--image-preview {
      width: 200px;
      height: 160px;
    }

    .upload {
      margin-bottom: 100px;
    }

    .upload-options {
      position: relative;
      /* height: 65px; */
      background-color: $base-color;
      cursor: pointer;
      overflow: hidden;
      text-align: center;
      transition: background-color ease-in-out 150ms;
      color: red;
      width: 100%;
      border: 1px solid #dedddd;
      border-radius: 0px 0px 5px 5px;

      &:hover {
        background-color: lighten($base-color, 10%);
      }

      & input {
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        position: absolute;
        z-index: -1;
      }

      & label {
        display: flex;
        align-items: center;
        width: 100%;
        height: 100%;
        font-weight: 400;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
        overflow: hidden;

        &::after {
          content: "+";
          font-family: "Material Icons";
          z-index: 0;
          display: flex;
          justify-content: center;
          align-items: center;
          width: 198px;
          height: 50px;
          font-size: 28px;
          color: #e6e6e6;
        }

        & span {
          display: inline-block;
          width: 50%;
          height: 100%;
          text-overflow: ellipsis;
          white-space: nowrap;
          overflow: hidden;
          vertical-align: middle;
          text-align: center;

          &:hover i.material-icons {
            color: lightgray;
          }
        }
      }
    }

    .js--image-preview {
      height: 100%;
      width: 100%;
      /* position: relative; */
      overflow: hidden;
      background-image: url('/images/image.png');
      background-color: white;
      /* background-position: center center; */
      background-repeat: no-repeat;
      background-size: cover;

      &.js--no-default::after {
        display: none;
      }

      &:nth-child(2) {
        background-image: url("http://bastianandre.at/giphy.gif");
      }
    }

    i.material-icons {
      transition: color 100ms ease-in-out;
      font-size: 2.25em;
      line-height: 55px;
      color: white;
      display: block;
    }

    .drop {
      display: block;
      position: absolute;
      background: transparentize($base-color, 0.8);
      border-radius: 100%;
      transform: scale(0);
    }

    .animate {
      animation: ripple 0.4s linear;
    }

    @keyframes ripple {
      100% {
        opacity: 0;
        transform: scale(2.5);
      }
    }

    .video {
      height: 160px;
      width: 200px;
      position: relative;
      /* overflow: hidden; */

      background-color: white;
      /* background-position: center center; */
      background-repeat: no-repeat;
      background-size: cover;
      margin-bottom: 60px;

    }

    .bgImg {
      background-image: url('/images/play.png');
    }

    span.upload-button {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 198px;
      height: 50px;
      font-size: 28px;
      color: #e6e6e6;
    }

    .videoBox {
      width: 200px;
      border-radius: 5px;

    }

    .videoDiv {
      margin-top: -60px;
    }

    label.fileuploader-btn {
      /* position: absolute; */
      top: 100%;
      border-radius: 0px 0px 5px 5px;
      border: 1px solid #e2e2e2;
    }
  </style>
@endpush
@section('content')
  <div class="container p-4">
    <h4 class="title">
      {{ $title }}
    </h4>

    <div class="card m-4">
      <div class="card-title">
        {{--  --}}
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('tenant.criteria.save.counter-bid', $auction->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
            <div class="wizard-step" data-step='1'>
              @php
                $yes_or_nos = [
                    ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
              @endphp
              <div class="form-group">
                <label class="fw-bold" for="address">Address:</label>
                <input type="text" name="address" placeholder="" data-type="address" id="address"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot"
                    required>
              </div>

              <div class="form-group">
                <label class="fw-bold" for="address">City:</label>
                <input type="text" name="city" placeholder="" data-type="cities" id="city"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                  required>
              </div>

              <div class="form-group">
                <label class="fw-bold" for="address">County:</label>
                <input type="text" name="county" placeholder="" id="county"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                  required>
              </div>

              <div class="form-group">
                <label class="fw-bold" for="address">State:</label>
                <input type="text" name="state" placeholder="" data-type="states" id="state"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                  required>
              </div>
            </div>
            <div class="wizard-step" data-step="2">
                <div class="form-group">
                    <label class="fw-bold" for="custom_terms">{{Auth::user()->user_type == 'tenant' ? "Acceptable Lease Price:" : "Offered Lease Price:"}}</label>
                    <input type="number" name="price" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="custom_terms">{{Auth::user()->user_type == 'tenant' ? "Acceptable Lease Start Date:" : "Offered Lease Start Date:"}}</label>
                    <input type="date" name="leaseDate" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group">
                    @php
                        $leaseTime = [
                            ['name' => '3 Months', 'target' => ''],
                            ['name' => '6 Months', 'target' => ''],
                            ['name' => '9 Months', 'target' => ''],
                            ['name' => '1 Year', 'target' => ''],
                            ['name' => '2 Years', 'target' => ''],
                            ['name' => '3-5 Years', 'target' => ''],
                            ['name' => '5+ Years', 'target' => ''],
                            ['name' => 'Month to Month', 'target' => ''],
                            ['name' => 'Other', 'target' => '.otherLeaseDurationNo'],
                        ];
                    @endphp
                    <label class="fw-bold">{{Auth::user()->user_type == 'tenant' ? "Acceptable Lease Length:" : "Offered Lease Length::"}}</label>
                    <select class="grid-picker" name="leaseTime[]" id="leaseTermRes"
                        style="justify-content: flex-start;" required multiple>
                        <option value="">Select</option>
                        @foreach ($leaseTime as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(25% - 10px);"
                                data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main otherLeaseDurationNo d-none">
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Lease Duration:</label>
                            <input type="text" name="other_lease_duration" id="other_lease_duration" class="form-control has-icon"
                                data-icon="fa-regular fa-calendar-days">
                        </div>
                    </div>
                </div>
            </div>
            <div class="wizard-step" data-step="3">
                <div class="form-group">
                  <label class="fw-bold">
                    {{Auth::user()->user_type == 'tenant' ? 'Does the tenant request that the landlord pay the tenant’s agent commission?' : 'If the tenant is represented by an agent, will the landlord offer the agent a commission?'}}
                  </label>
                  @php
                    $representedRes = [
                        ['target' => '.commission', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['target' => '', 'name' => 'Not Applicable', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <select name="landlordOfferCommission" id="represented" class="grid-picker"
                    style="justify-content: flex-start;">
                    <option value=""></option>
                    @foreach ($representedRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}" ></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group main commission d-none">
                  <label class="fw-bold" for="custom_evicted">{{Auth::user()->user_type == 'tenant' ? 'What compensation is the tenant requesting the landlord to pay to the tenant’s agent?' : 'What is the commission offered to the tenant’s agent?'}}</label>
                  @php
                    $representedOtherOpt = [
                        ['name' => '1 month’s rent', 'target' => ''],
                        ['name' => '50% of one month’s rent', 'target' => ''],
                        ['name' => '10% of the value of the lease', 'target' => ''],
                        ['name' => '6% of the value of the lease', 'target' => ''],
                        ['name' => 'Other', 'target' => '.compensateReqOtherNo'],
                    ];
                  @endphp
                  <select name="commissionAmmountOffered" id="representedResYes" class="grid-picker"
                    style="justify-content: flex-start;">
                    <option value=""></option>
                    @foreach ($representedOtherOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main compensateReqOtherNo d-none">
                    <label class="fw-bold" for="custom_evicted">What compensation is the landlord willing to pay the tenant’s agent?</label>
                    <input type="text" name="landlordPaysAmount" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
            </div>
            <div class="wizard-step" data-step="4">
                <div class="form-group">
                <label class="fw-bold">Offer Expires:</label>
                <input type="datetime-local" name="offerExpires" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days" required>
                </div>
            </div>
            <div class="wizard-step" data-step='5'>
                <div class="form-group row">
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="first_name">First Name:</label>
                        <input type="text" name="first_name" placeholder="" id="first_name"
                            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                            value="{{ Auth::user()->first_name }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="last_name">Last Name:</label>
                        <input type="text" name="last_name" placeholder="" id="last_name"
                            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                            value="{{ Auth::user()->last_name }}">
                    </div>
                </div>
                <div class="form-group row">
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_phone">Phone Number:</label>
                        <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                            value="{{ Auth::user()->phone }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_email">Email:</label>
                        <input type="text" name="agent_email" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}">
                    </div>
                </div>
                @if (auth()->user()->user_type !== 'landlord' && auth()->user()->user_type !== 'tenant')
                    <div class="form-group row">
                        <div class="form-group col-md-6">
                            <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                            <input type="text" name="agent_brokerage" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-handshake" value="{{ Auth::user()->brokerage }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                                value="{{ Auth::user()->license_no }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="form-group col-md-6">
                            <label class="fw-bold" for="agent_mls_id">NAR Member ID (NRDS ID): </label>
                            <input type="number" name="agent_mls_id" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-id-card-clip" value="{{ Auth::user()->mls_id }}">
                        </div>
                    </div>
                @endif
            </div>
            <div class="d-flex justify-content-between form-group mt-4">
                <div>
                <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
                <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                    style="display: none;">Save</button>
                </div>
            </div>
        </form>
      </div>
    </div>
  </div>
@endsection
@push('scripts')
  <script>
    $(function() {
      $('.add-row').on('click', function() {
        var socialRow = `<tr>
                              <td>
                                  <select name="socialType[]" class="form-select">
                                      <option value="Facebook">Facebook</option>
                                      <option value="YouTube">YouTube</option>
                                      <option value="LinkedIn">LinkedIn</option>
                                      <option value="Twitter">Twitter</option>
                                      <option value="Instagram">Instagram</option>
                                  </select>
                              </td>
                              <td>
                                  <input type="text" name="social_link[]" class="form-control">
                              </td>
                            </tr>`;
        $('.social-links').append(socialRow);
      });
    });
  </script>
  <script>
    $(function() {
      $(document).ready(function() {
        /* var multipleCancelButton = new Choices('.multiple', {
            removeItemButton: true,
            // maxItemCount:5,
            // searchResultLimit: 5,
            // renderChoiceLimit: 5
        }); */
        $('.multiple').select2();

        $('.select2').each(function() {
          $(this).prependTo($(this).parent('.select2-parent'));
        });
      });
    });
  </script>
  <script>
    $(function() {
      $('.has-icon').each(function(i) {
        var cover = `<div class="input-cover input-cover-${i}"></div>`;
        $(this).before(cover);
        $(this).appendTo(`.input-cover-${i}`);
        var iconClass = $(this).data('icon');
        var id = $(this).attr('id');
        var htm = `<label for="${id}" class="input-icon"><i class="${iconClass} " ></i></label>`;
        $(this).before(htm);
      });

      $('.grid-picker').each(function(index, elm) {
        var st = $(elm).attr('style');
        var html =
          `<div class="options-container options-container-${index}" style="${st}"></div>`;
        $(elm).after(html);
        $(elm).appendTo(`.options-container-${index}`);
        $(elm).children('option').each(function(i) {
          var val = $(this).val();
          if (val != "") {
            var text = $(this).text();
            var classes = $(this).attr('class');
            var styles = $(this).attr('style') || "";
            var icon = $(this).data('icon') || "";
            var selected = $(this).attr('selected') || "";
            var target = $(this).data('target') || "";
            selected = selected && "active";
            icon = icon && icon + " ";
            var htm = `<div onclick="checkselect(this);" style="${styles}" class="${classes} ${selected} option-container" data-index="${i}" data-target="${target}">
                <div class="option-icon">${icon}</div>
                <div class="option-text">${text}</div>
                </div>`;
            $(`.options-container-${index}`).append(htm);
          }
        });
      });
    });
    function checkselect(elm) {
      var i = $(elm).data('index');
      var mult = $(elm).parent().children('select').attr('multiple') || false;
      // console.log(mult);
      if (mult == false) {
        var option = $(elm).parent().children('select').children(`option:eq(${i})`);
        var ov = option.val();
        $(elm).parent().children('.option-container').removeClass('active');
        $(elm).addClass('active');
        $(elm).parent().children('select').val(ov);
      } else {
        $(elm).toggleClass('active');
        var option = $(elm).parent().children('select').children(`option:eq(${i})`);
        var ov = option.val();
        var vals = $(elm).parent().children('select').val();
        if (vals.includes(ov)) {
          option.removeAttr('selected');
        } else {
          option.attr('selected', 'selected');
        }
      }

      // console.log(op);
      var v = $(elm).parent().children('select').val();
      $(elm).parent().children('select').trigger('change');
      console.log(v);
      check_custom();
    }

    function check_custom() {
      $('.option-container').each(function(i, elm) {
        var target = $(elm).data('target') || "";
        var is_active = $(elm).hasClass('active');
        if (target != "") {
          if (is_active) {
            $(target).removeClass("d-none");
          } else {
            $(target).addClass("d-none");
            $(target).find('input').val("");
          }
        }
      });
      // setTimeout(check_custom, 500);
    }
  </script>

  <script>
    $(function() {
      StepWizard.init();
    });
    var StepWizard = {
      init: function() {
        StepWizard.total_steps = $('.wizard-step').length;
        var v = $(".mainform").validate({
          errorClass: "text-error text-danger w-100",
          onkeyup: false,
          onfocusout: false,
        });
        StepWizard.setStep();
        $('.wizard-step-next').click(function(e) {
          console.log(StepWizard.currentStep)
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {

              $('.wizard-step.active').removeClass('active');
                StepWizard.backStep = StepWizard.currentStep;
              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          if ($('.wizard-step.active').prev().is('.wizard-step')) {

            $('.wizard-step.active').removeClass('active');
            $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
            StepWizard.setStep();
            console.log(StepWizard.currentStep)
            StepWizard.backStep = StepWizard.currentStep - 1;
            
          }
        });

        $('.wizard-step-finish').click(function(e) {
          $('.mainform').submit();
        });

      },
      setStep: function() {
        if ($('.wizard-step.active').length == 0) {
          $('.wizard-step').first().addClass('active');
        }
        if ($('.wizard-step.active').prev().is('.wizard-step')) {
          $('.wizard-step-back').show();
        } else {
          $('.wizard-step-back').hide();
        }

        if ($('.wizard-step.active').next().is('.wizard-step')) {
          $('.wizard-step-next').show();
          $('.wizard-step-finish').hide();
        } else {

          $('.wizard-step-next').hide();
          $('.wizard-step-finish').show();
        }
        $('.wizard-step').each(function(i, element) {
          var k = i + 1;
          if ($(element).hasClass('active')) {
            StepWizard.currentStep = k;
            StepWizard.data_step = k;
            StepWizard.nextStep = k + 1;
          }
        });
        StepWizard.stepChanged();
      },
      stepChanged: function() {
        var comp = (StepWizard.currentStep / StepWizard.total_steps) * 100;
        $('.steps-progress-percent').animate({
          width: comp.toFixed(0) + '%',
        });
      },
      currentStep: 1,
      total_steps: 0,
    };
  </script>

  <script>
    // google.maps.event.addDomListener(window, 'load', initialize);
    function initialize() {
      var inputField = document.getElementsByClassName('search_places');

      for (var i = 0; i < inputField.length; i++) {
        var t = inputField[i].dataset.type;
        console.log(t);
        if (t === "cities") {
          var options = {
            types: ['(cities)'],
            componentRestrictions: {
              country: "us"
            },
          };
        } else if (t === "states") {
          var options = {
            types: ['administrative_area_level_1'],
            componentRestrictions: {
              country: "us"
            },
          };
        } else if (t === "address") {
          var options = {
            types: [],
            componentRestrictions: {
              country: "us"
            },
          };
        } else {
          var options = {
            types: ['administrative_area_level_2'],
            componentRestrictions: {
              country: "us"
            },
          };
        }
        google.maps.event.addDomListener(inputField[i], 'keydown', function(e) {
          if (e.keyCode == 13) {
            if (e.preventDefault) {
              e.preventDefault();
            } else {
              e.cancelBubble = true;
              e.returnValue = false;
            }
          }
        });
        var autocomplete = new google.maps.places.Autocomplete(inputField[i], options);
        autocomplete.addListener('place_changed', function(e) {
          var place = autocomplete.getPlace();
          if (place) {
            console.log("place", place);
            var lat = place.geometry['locations'].lat();
            var lng = place.geometry['locations'].lng();
            if (t == "counties") {
              $('#lat').val(lat);
              $('#long').val(lng);
            }
          }
        });
      }
    }
    var leaseTerms = $('#leaseTermsOptions');
    $('#leaseTermsOpt').change(function() {
      //Disply and hide a div
      ($(this).val().includes('3 months') || $(this).val().includes('6 months') || $(this).val().includes(
          '9 months') || $(this).val().includes('1 year') || $(this).val().includes('2 years') || $(this).val()
        .includes('3-5 years') || $(this).val().includes('5+ years') || $(this).val().includes('Month to Month') || $(
          this).val().includes('Other')) ? leaseTerms.show():
        leaseTerms.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });


    var viewRefRes = $('#viewOptRes');
    $('#viewRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? viewRefRes.show():
        viewRefRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var viewRefComm = $('#viewOptComm');
    $('#viewOptionComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? viewRefComm.show():
        viewRefComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterViewRes = $('#waterViewResOpt');
    $('#waterViewRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterViewRes.show():
        waterViewRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterExtraRes = $('#waterExtraResOpt');
    $('#waterExtraRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterExtraRes.show():
        waterExtraRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterFrontageRes = $('#waterFrontageResOpt');
    $('#waterFrontageRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterFrontageRes.show():
        waterFrontageRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterAccessRes = $('#waterAccessResOpt');
    $('#waterAccessRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterAccessRes.show():
        waterAccessRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var garageParkingComm = $('#garageParkingOpt');
    $('#garageParkingComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? garageParkingComm.show():
        garageParkingComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterViewComm = $('#waterViewOptComm');
    $('#waterViewComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterViewComm.show():
        waterViewComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterExtraComm = $('#waterExtraOptComm');
    $('#waterExtraComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterExtraComm.show():
        waterExtraComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterFrontageComm = $('#waterfrontageOptComm');
    $('#waterFrontageComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterFrontageComm.show():
        waterFrontageComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterAccessComm = $('#waterAccessOptComm');
    $('#waterAccessComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterAccessComm.show():
        waterAccessComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
  </script>
  <script>
    $(document).ready(function(){
      $('div.main.d-none').find('input, select').prop('disabled', true);
      $(document).click(function(){
        $('div.main').each((i, item) => {
          if ($(item).hasClass('d-none')) {
            $(item).find('input, select').prop('disabled', true);
          }else{
            $(item).find('input, select').prop('disabled', false);
          }
        })
      })
    })
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush

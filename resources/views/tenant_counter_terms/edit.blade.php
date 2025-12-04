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

    :root {
      --switches-bg-color: #169499;
      --switches-label-color: white;
      --switch-bg-color: white;
      --switch-text-color: #169499;
    }

    body {
      font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
    }



    /* container for all of the switch elements
                                - adjust "width" to fit the content accordingly
                            */
    .switches-container {
      width: 16rem;
      position: relative;
      display: flex;
      padding: 0;
      position: relative;
      background: var(--switches-bg-color);
      line-height: 3rem;
      border-radius: 3rem;
      margin-left: auto;
      margin-right: auto;
    }

    /* input (radio) for toggling. hidden - use labels for clicking on */
    .switches-container input {
      visibility: hidden;
      position: absolute;
      top: 0;
    }

    /* labels for the input (radio) boxes - something to click on */
    .switches-container label {
      width: 50%;
      padding: 0;
      margin: 0;
      text-align: center;
      cursor: pointer;
      color: var(--switches-label-color);
    }

    /* switch highlighters wrapper (sliding left / right)
                                - need wrapper to enable the even margins around the highlight box
                            */
    .switch-wrapper {
      position: absolute;
      top: 0;
      bottom: 0;
      width: 50%;
      padding: 0.15rem;
      z-index: 3;
      transition: transform .5s cubic-bezier(.77, 0, .175, 1);
      /* transition: transform 1s; */
    }

    /* switch box highlighter */
    .switch {
      border-radius: 3rem;
      background: var(--switch-bg-color);
      height: 100%;
    }

    /* switch box labels
                                - default setup
                                - toggle afterwards based on radio:checked status
                            */
    .switch div {
      width: 100%;
      text-align: center;
      opacity: 0;
      display: block;
      color: var(--switch-text-color);
      transition: opacity .2s cubic-bezier(.77, 0, .175, 1) .125s;
      will-change: opacity;
      position: absolute;
      top: 0;
      left: 0;
    }

    /* slide the switch box from right to left */
    .switches-container input:nth-of-type(1):checked~.switch-wrapper {
      transform: translateX(0%);
    }

    /* slide the switch box from left to right */
    .switches-container input:nth-of-type(2):checked~.switch-wrapper {
      transform: translateX(100%);
    }

    /* toggle the switch box labels - first checkbox:checked - show first switch div */
    .switches-container input:nth-of-type(1):checked~.switch-wrapper .switch div:nth-of-type(1) {
      opacity: 1;
    }

    /* toggle the switch box labels - second checkbox:checked - show second switch div */
    .switches-container input:nth-of-type(2):checked~.switch-wrapper .switch div:nth-of-type(2) {
      opacity: 1;
    }
  </style>
@endpush
@section('content')
  <div class="container p-4">
    <h4>Tenant's Countered Terms</h4>

    <div class="card m-4">
      <div class="card-title">
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('tenant.update-counter-terms', $counter->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf

          @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp

          <div class="wizard-step">
            <div class="form-group">
              <label for="listing_title" class="fw-bold">What is the proposed timeframe outlined in the Tenant Agency
                Agreement?</label>
              <input type="text" name="timeframe" class="form-control has-icon" data-icon="fa-solid fa-qrcode" required
                value="{{ $counter->timeframe }}">
            </div>
          </div>
          <div class="wizard-step">
            @php
              $propFeeOpt = [['name' => 'Yes', 'target' => '.propFeeYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Would the tenant be willing to pay a finder’s fee to the agent for their
                assistance
                in finding them a property? </label>
              <select class="grid-picker" name="propFeeOpt" required>
                <option value="">Select</option>
                @foreach ($propFeeOpt as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    {{ $item['name'] == $counter->propFeeOpt ? 'selected' : '' }} class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group {{ 'Yes' == $counter->propFeeOpt ? 'd-block' : 'd-none' }} propFeeYes">
                @php
                  $propFee = [['name' => "1 month's rent", 'target' => ''], ['name' => "50% of one month's rent", 'target' => ''], ['name' => '10% of the value of the lease', 'target' => ''], ['name' => '6% of the value of the lease', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.propFeeOther']];
                @endphp
                <label class="fw-bold">What is the offered finder’s fee the tenant is willing to pay the agent for
                  their
                  assistance in finding them a property? </label>
                <select class="grid-picker" name="propFee" required>
                  <option value="">Select</option>
                  @foreach ($propFee as $item)
                    <option value="{{ $item['name'] }}" {{ $item['name'] == $counter->propFee ? 'selected' : '' }}
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-check-circle"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group {{ 'Other' == $counter->propFee ? 'd-block' : 'd-none' }} propFeeOther">
                  <label class="fw-bold">What is the offered fee the tenant is willing to pay the agent for their
                    assistance in finding them a property?</label>
                  <input type="text" class="form-control has-icon" name="propFeeOther"
                    value="{{ $counter->propFeeOther }}" data-icon="fa-solid fa-qrcode">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $services_data = [
                  ['name' => 'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.', 'target' => ''],
                  ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                  ['name' => "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.", 'target' => ''],
                  ['name' => "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.", 'target' => ''],
                  ['name' => "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.", 'target' => ''],
                  ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                  ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                  ['name' => 'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.', 'target' => ''],
                  ['name' => 'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.', 'target' => ''],
                  ['name' => 'Assist the buyer with the review and explanation of all contractual documents and disclosures.', 'target' => ''],
                  ['name' => 'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.', 'target' => ''],
                  ['name' => 'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.', 'target' => ''],
                  ['name' => 'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.', 'target' => ''],
                  ['name' => 'Other-Add additional services as needed.', 'target' => ''],
                  ['name' => 'Other-Add additional services as offered.', 'target' => '.other_services'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Select the services the buyer wants the hired agent to provide: </label>
              <select class="grid-picker" name="services[]" onchange="service_data(this.value)" id="services" multiple
                required>
                <option value="">Select</option>
                @foreach ($services_data as $item)
                  @php
                    $serviceData = json_decode($counter->services);

                  @endphp
                  <option value="{{ $item['name'] }}" {{ in_array($item['name'], $serviceData) ? 'selected' : '' }}
                    data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(100% - 0px);"
                    data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group {{ $counter->other_services != '' ? 'd-block' : 'd-none' }} serviceOther">
                <label class="fw-bold">Select the services that the buyer requests from an agent:</label>
                <input type="text" class="form-control has-icon" value="{{ $counter->other_services }}"
                  name="other_services" data-icon="fa-solid fa-qrcode" required />
              </div>
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Additional details or countered terms:</label>
              <textarea name="additionalDetails" class="form-control" rows="5">{{ $counter->additionalDetails }}</textarea>
            </div>
            <div class="form-group">
              <div class="container">
                <div class="switches-container">
                  <input type="radio" id="switchMonthly" name="status" value="1"
                    {{ $counter->status == '1' ? 'checked' : '' }} />
                  <input type="radio" id="switchYearly" name="status" value="0"
                    {{ $counter->status == '0' ? 'checked' : '' }} />
                  <label for="switchMonthly">Active</label>
                  <label for="switchYearly">Inactive</label>
                  <div class="switch-wrapper">
                    <div class="switch">
                      <div>Active</div>
                      <div>Inactive</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between form-group mt-4">
            <div>
              <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
            </div>
            <div>
              <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
              <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                style="display: none;">Update</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
@push('scripts')
  <script>
    function show_card_field() {
      $('.card-field').removeClass('d-none');
    }
  </script>
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
    function changeAuctionType(v) {
      if (v == "Normal (Timer)") {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').hide();
        $('.normal-length').show();
      } else {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').show();
        $('.normal-length').hide();
      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      // changeAuctionType("Normal (Timer)");
    });
  </script>
  <script>
    $(function() {
      $(document).ready(function() {
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
          // console.log(val, text, icon, classes);
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
          errorClass: "text-danger w-100",
          onkeyup: false,
          onfocusout: false,
          /* submitHandler: function() {
              // alert("Submitted, thanks!");
              $(".mainform").submit();
          } */
        });

        StepWizard.setStep();
        $('.wizard-step-next').click(function(e) {
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {
              $('.wizard-step.active').removeClass('active').next().addClass('active');
              StepWizard.setStep();
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          if ($('.wizard-step.active').prev().is('.wizard-step')) {
            $('.wizard-step.active').removeClass('active').prev().addClass('active');
            StepWizard.setStep();
          }
        });

        $('.wizard-step-finish').click(function(e) {
          if (v.form()) {
            $('.mainform').submit();
          }
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
              // Since the google event handler framework does not handle early IE versions, we have to do it by our self.: -(
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
            // place variable will have all the information you are looking for.
            var lat = place.geometry['location'].lat();
            var lng = place.geometry['location'].lng();
            if (t == "counties") {
              $('#lat').val(lat);
              $('#long').val(lng);
            }
          }
        });
      }
    }
  </script>

  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush

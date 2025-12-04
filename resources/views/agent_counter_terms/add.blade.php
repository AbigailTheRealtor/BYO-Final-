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

        <form class="p-4 pt-0 mainform" action="{{ route('agent.add-counter-terms') }}" method="POST"
          enctype="multipart/form-data">
          @csrf

          <input type="hidden" name="agentId" value="{{ $agentId }}">
          @php
            $yes_or_nos = [
                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
          @endphp
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold" for="timeframe">What is the proposed timeframe outlined in the Tenant Agency
                Agreement?</label>
              <input type="text" name="timeframe" class="form-control has-icon hide_arrow"
                data-icon="fa-solid fa-qrcode" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $agentCharge = [
                  ['name' => 'Yes', 'target' => '.agentChargeYes', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the agent charge a finder’s fee to secure a property for the tenant?</label>
              <select class="grid-picker" name="agentCharge" required>
                <option value="">Select</option>
                @foreach ($agentCharge as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group agentChargeYes d-none">
                <label class="fw-bold">What is the fee that the agent charges to secure a property for the tenant?
                </label>
                <input type="text" class="form-control has-icon" name="agentChargeOther"
                  data-icon="fas fa-dollar-sign">
              </div>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $agentFee = [
                  ['name' => 'Yes', 'target' => '.agentFeeYes', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the agent charge any fees to the tenant if the tenant cancels the Tenant Agency
                Agreement before the expiration date?</label>
              <select class="grid-picker" name="agentFee" required>
                <option value="">Select</option>
                @foreach ($agentFee as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group agentFeeYes d-none">
                <label class="fw-bold">What is the cancellation fee to the tenant?</label>
                <input type="text" class="form-control has-icon" name="agentFeeOther" data-icon="fas fa-dollar-sign">
              </div>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $services_data = [
                  [
                      'name' =>
                          'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                      'target' => '',
                  ],
                  ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                  [
                      'name' =>
                          "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                      'target' => '',
                  ],
                  [
                      'name' =>
                          "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                      'target' => '',
                  ],
                  [
                      'name' =>
                          "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                      'target' => '',
                  ],
                  ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                  ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                  [
                      'name' =>
                          'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                      'target' => '',
                  ],
                  [
                      'name' =>
                          'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                      'target' => '',
                  ],
                  [
                      'name' =>
                          'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                      'target' => '',
                  ],
                  [
                      'name' =>
                          'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                      'target' => '',
                  ],
                  [
                      'name' =>
                          'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                      'target' => '',
                  ],
                  [
                      'name' =>
                          'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                      'target' => '',
                  ],
                  ['name' => 'Other-Add additional services as offered.', 'target' => '.other_services'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Select the included services that the agent will provide to the tenant: </label>
              <select class="grid-picker" name="services[]" id="services" multiple required>
                <option value="">Select</option>
                @foreach ($services_data as $service)
                  <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}" class="card flex-row"
                    style="width:calc(100% - 0px);" data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                    {{ $service['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group other_services d-none">
              <label class="fw-bold">What additional services will the agent provide to the Seller?</label>
              <input type="text" name="other_services" id="other_services" class="form-control has-icon"
                data-icon="fa-solid fa-hand-point-right" required />
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Additional Details or Countered Terms: </label>
              <textarea class="form-control" name="additionalDetails" rows="5" required></textarea>
            </div>
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

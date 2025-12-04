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
  {{-- {{ dd($page_data['auto_bid']) }} --}}
  {{-- {{ dd($page_data['auction']->auto_bid) }} --}}
  <div class="container p-4">
    <h4 class="title">
      {{ $page_data['title'] }}
    </h4>
    <div class="card m-4">
      <div class="card-title">
        {{--  --}}
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('savePABid') }}" method="POST" enctype="multipart/form-data">
          @csrf

          <input type="hidden" name="auction_id" value="{{ @$page_data['auction']->id }}">
          @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp

          <div class="wizard-step">
            <h4 style="color: black">Please provide your offered price and terms for this property:</h4>
            <div class="form-group">
              <label class="fw-bold" for="price">Price:</label>
              <input type="number" step="0.01" name="price" placeholder="0.00" id="price"
                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                data-msg-required="Please enter Price" autofocus required>
            </div>

            @php
              $financings = [
                  ['name' => 'Cash', 'target' => ''],
                  ['name' => 'Conventional', 'target' => ''],
                  ['name' => 'FHA', 'target' => ''],
                  ['name' => 'VA', 'target' => ''],
                  ['name' => 'Bitcoin (BTC)', 'target' => ''],
                  ['name' => 'Ethereum (ETH)', 'target' => ''],
                  ['name' => 'Litecoin (LTC)', 'target' => ''],
                  ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                  ['name' => 'Dash (DASH)', 'target' => ''],
                  ['name' => 'Ripple (XRP)', 'target' => ''],
                  ['name' => 'Tether (USDT)', 'target' => ''],
                  ['name' => 'USD Coin (USDC)', 'target' => ''],
                  ['name' => 'NFT', 'target' => ''],
                  ['name' => 'USDA', 'target' => ''],
                  ['name' => 'Assumable', 'target' => ''],
                  ['name' => 'Exchange/Trade', 'target' => ''],
                  ['name' => 'Lease Option', 'target' => ''],
                  ['name' => 'Lease Purchase', 'target' => ''],
                  ['name' => 'Private Financing Available', 'target' => ''],
                  ['name' => 'Special Funding', 'target' => ''],
                  ['name' => 'Seller Financing', 'target' => ''],
                  ['name' => 'Jumbo', 'target' => ''],
                  ['name' => 'Non-QM', 'target' => ''],
                  ['name' => 'No-Doc', 'target' => ''],
                  ['name' => 'Other', 'target' => '.custom_term_financings'],
              ];
            @endphp

            <div class="form-group">
              <label class="fw-bold">Currency/ Financing Type:</label>
              <select class="grid-picker" name="financing" id="financing" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($financings as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(20% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_term_financings d-none">
              <label class="fw-bold">Currency/ Financing Type:</label>
              <input type="text" name="custom_term_financings" id="custom_term_financings"
                class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
            </div>

            <div class="form-group row">
              <div class="col-md-6">
                <label class="fw-bold">Escrow Amount(Reserve Terms): $ or %</label>
                <input type="text" name="escrow_amount" id="term_escrow_amount" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
              </div>
              <div class="col-md-6">
                <label class="fw-bold">Escrow Amount(Buy Now Terms): $ or %</label>
                <input type="text" name="escrow_amount2" id="term_escrow_amount" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
              </div>

              <div class="col-md-6">
                <label class="fw-bold">Inspection Period(Reserve Terms):</label>
                <input type="text" name="inspection_period" id="term_inspection_period" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
              </div>
              <div class="col-md-6">
                <label class="fw-bold">Inspection Period(Buy Now Terms):</label>
                <input type="text" name="inspection_period2" id="term_inspection_period" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
              </div>
            </div>

            <div class="form-group">
              <label class="fw-bold" for="closing_date">Closing Days(Reserve Terms):</label>
              <input type="text" name="closing_days" id="closing_days" placeholder="" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-days" required>
            </div>
            <div class="form-group">
              <label class="fw-bold" for="closing_date">Closing Days(Buy Now Terms):</label>
              <input type="text" name="closing_days2" id="closing_days" placeholder="" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-days" required>
            </div>

            <div class="form-group">
              @php
                $contingencies = [['name' => 'Home Inspection', 'target' => ''], ['name' => 'Appraisal', 'target' => ''], ['name' => 'Financing', 'target' => ''], ['name' => 'Sale of a property', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.custom_contingencies']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Contingencies:</label>
                <select class="grid-picker" name="contingencies" id="contingencies"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($contingencies as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(16% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_contingencies d-none">
                <label class="fw-bold">Contingencies:</label>
                <input type="text" name="custom_contingencies" id="custom_contingencies"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
              </div>
              {{-- <label class="fw-bold">Contingencies:</label>
                            <textarea name="contingencies" id="contingencies" class="form-control" cols="30" rows="5" required></textarea> --}}
            </div>

            <div class="form-group row">

              <div class="col-md-6">
                <label class="fw-bold">Seller’s Premium (Credit Seller will pay Buyer at closing)
                  Optional:</label>
                <input type="text" name="seller_premium" id="seller_premium" placeholder="" class="form-control "
                  data-icon="fa-solid fa-dollar">
              </div>


              <div class="col-md-6">
                <label class="fw-bold">Buyer’s Premium (Credit Buyer will pay Seller at closing)
                  Optional:</label>
                <input type="text" name="buyer_premium" id="buyer_premium" placeholder="" class="form-control "
                  data-icon="fa-solid fa-dollar">
              </div>

            </div>


            @if ($page_data['auction']->auto_bid == '1')
              {{-- <div class="form-group autobid_price "> --}}
              {{-- Changes 31 May 2023 --}}
              {{-- <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                                <input type="number" step="0.01" name="autobid_price" placeholder="0.00"
                                    id="autobid_price" class="form-control has-icon hide_arrow"
                                    data-icon="fa-solid fa-dollar">
                                <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                                <input type="number" step="0.01" name="autobid_price2" placeholder="0.00"
                                    id="autobid_price" class="form-control has-icon hide_arrow"
                                    data-icon="fa-solid fa-dollar"> --}}

              {{-- Changes 31 May 2023 --}}
              {{-- <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                <input type="number" step="0.01" name="autobid_price3" placeholder="0.00" id="autobid_price"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar">
              </div> --}}
            @endif



          </div>


          <div class="wizard-step">
            <h4 style="color: black">This information sent privately to the seller’s agent:</h4>
            @if (Auth::user()->user_type == 'buyer')
              @php
                $buyer_types = [['name' => 'Buyer', 'target' => '.simple-buyer'], ['name' => 'Wholesaller', 'target' => '.wholesaller'], ['name' => 'Asset Manager', 'target' => '.asset-manager'], ['name' => 'Attorney', 'target' => '.attorney'], ['name' => 'Builder', 'target' => '.builder']];
              @endphp

              <div class="form-group">
                <label class="fw-bold">Buyer Type:</label>
                <select class="grid-picker" name="buyer_type" id="buyer_type" style="justify-content: flex-start;"
                  onchange="show_card_field();" required>
                  <option value="">Select</option>
                  @foreach ($buyer_types as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(20% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="form-group card-field @if (Auth::user()->user_type == 'buyer') d-none @endif ">
              {{-- @if (Auth::user()->user_type == 'agent') --}}
              <label class="fw-bold">Business Card:</label>
              {{-- @else
                            <label class="simple-buyer d-none">Driver's License:</label>
                            <label class="wholesaller d-none">Proof of contract and ID:</label>
                            <label class="asset-manager d-none">Business Card or proof that Asset Manager is an Asset Manager:</label> --}}
              {{-- <label class="attorney d-none">Business Card or proof that Attorney is an Attorney:</label>
                            <label class="builder d-none">Business Card or proof that Builder is a Builder:</label> --}}
              {{-- @endif --}}
              <div class="">
                <input type="file" class="form-control" accept="image/*" name="card"
                  value="{{ @$auction->get->business_card }}" required>
              </div>
            </div>

            <div class="form-group">
              <label class="fw-bold">ID:</label>
              <input type="text" class="form-control" name="buyer_id">
            </div>

            <div class="form-group">
              <label class="fw-bold">Personal Video to the Seller: (Optional)</label>
              <input type="url" class="form-control" name="video_url">
            </div>

            <div class="form-group">
              <label class="fw-bold">Proof of funds/Pre-approval letter:</label>
              <input type="url" class="form-control" name="proof_of_fund_url">
            </div>

            <div class="form-group">
              <label class="fw-bold">Personal Letter to the Seller: (Optional)</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" name="note">
              </div>
            </div>

            <div class="form-group">
              <label class="fw-bold">Personal Audio to the Seller: (Optional)</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" accept="audio/*" name="audio">
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
          // console.log(val, text, icon, classes);
        });
        // console.log(html);
        // html += `</div>`;
        // $(elm).after(html);
        /* $('.option-container').on('click', function(index, elm) {
            // alert("ok");
            var ind = $(elm).data(index);
            var op = $(elm).parent().html();
            console.log(op);
        }); */
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

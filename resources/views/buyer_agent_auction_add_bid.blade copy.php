@extends('layouts.main')
@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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
      /* min-height: 50px; */
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
  </style>
@endpush
@section('content')
  {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
  @php
    $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
  @endphp
  <div class="container pt-5 pb-5">
    <div class="card">
      <div class="row">
        <div class="col-12 p-4">
          <form class="p-4 pt-0 mainform" action="{{ route('buyer_agent.saveBABid') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
            <div class="container">
              <h4 class="title">
                Add Bid on Hire Buyer's Agent auction - {{ @$auction->title }}
              </h4>
              <div class="wizard-steps-progress">
                <div class="steps-progress-percent"></div>
              </div>
              <div class="wizard-step">
                @php
                  $terms_of_contract = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Other', 'target' => '.custom_contract_terms']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">What is the agent's contract timeframe for working with the buyer?</label>
                  <select name="terms_of_contract" id="terms_of_contract" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($terms_of_contract as $toc)
                      <option value="{{ $toc['name'] }}" data-target="{{ $toc['target'] }}" class="card flex-column"
                        style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                        {{ $toc['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_contract_terms d-none">
                  <label class="fw-bold">What is the agent's contract timeframe for working with the buyer?</label>
                  <input type="text" name="custom_contract_terms" id="custom_contract_terms"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                </div>
              </div>
              @php
                $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Will the agent provide the buyer of the property with a credit at closing (up to
                    0.5%)</label>
                  <select class="grid-picker" name="has_buyer_credit_at_closing" id="buyer_credit_at_closing"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.has_buyer_credit_at_closing';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group has_buyer_credit_at_closing d-none">
                  <label class="fw-bold"> Offered Buyer’s Concession (Up to .5%):</label>
                  <input type="number" name="buyer_credit_at_closing" placeholder="0.00" id="concession" min="0.5"
                    step=".01" class="form-control has-icon search_places col-3" data-icon="fa-solid fa-percent"
                    data-msg-required="Offered Buyer’s Concession" placeholder="%">
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Does the agent charge any fees to the buyer?</label>
                  <select class="grid-picker" name="has_charges" id="buyer_credit_at_closing"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.has_charges';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="row has_charges d-none">
                  <div class="form-group">
                    <label class="fw-bold">What fee is being charged to the buyer?</label>
                    <input type="text" name="fee_being_charged" id="fee_being_charged" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" data-msg-required="Please Enter Amount">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What is the fee for? </label>
                    <input type="text" name="fee_for" id="fee_for" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" data-msg-required="Please Enter what is fee for">
                  </div>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Does the agent charge any fees to the buyer if the buyer cancels the
                    contract?</label>
                  <select class="grid-picker" name="hasagentCancellationFee" id="hasagentCancellationFee"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.hasagentCancellationFee';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group hasagentCancellationFee d-none">
                  <label class="fw-bold">What is the cancellation fee to the buyer?</label>
                  <input type="text" name="agentCancellationFee" id="agentCancellationFee"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Amount">
                </div>
              </div>

              <div class="wizard-step">
                @php
                  $services_data = [
                      ['name' => 'Buyer’s listing on the platform BidYourOffer.com', 'target' => ''],
                      ['name' => 'Sending prompt email notifications containing properties that meet the buyer\'s criteria as soon as they are listed, ensuring access to the most up-to-date listings.', 'target' => ''],
                      ['name' => 'Marketing the buyer\'s listing on numerous groups, pages, and affiliates through a QR code that links directly to the listing on BidYourOffer.com.', 'target' => ''],
                      ['name' => 'Promoting the buyer\'s listing on social media platforms through a QR code or listing link leading to the BidYourOffer.com listing.', 'target' => ''],
                      ['name' => 'Assisting with obtaining pre-approval or financing options to determine the buyer\'s purchasing power.', 'target' => ''],
                      ['name' => 'Providing detailed market analysis and comparative market information to help the buyer make informed decisions.', 'target' => ''],
                      ['name' => 'Scheduling and accompanying the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Scheduling video tours of the property as needed.', 'target' => ''],
                      ['name' => 'Assisting with negotiations and preparing offers to maximize the buyer\'s chances of securing their desired property.', 'target' => ''],
                      ['name' => 'Coordinating and overseeing the home inspection process, including recommending trusted inspectors.', 'target' => ''],
                      ['name' => 'Facilitating communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.', 'target' => ''],
                      ['name' => 'Assisting with the review and explanation of all contractual documents and disclosures.', 'target' => ''],
                      ['name' => 'Providing guidance and support throughout the entire purchase transaction, from offer acceptance to closing.', 'target' => ''],
                      ['name' => 'Offering post-purchase assistance, such as recommending service providers for home maintenance and renovations.', 'target' => ''],
                      ['name' => 'Other- Add additional services as needed.', 'target' => '.other_services'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the included services that the agent will provide to the buyer:</label>
                  <select class="grid-picker" name="services[]" id="services" multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group other_services d-none">
                  <label class="fw-bold">What additional services will the agent provide the buyer?</label>
                  <input type="text" name="other_services" id="other_services" class="form-control"
                    placeholder="Write custom services" required />
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">About Agent: <span class="text-danger"></span></label>
                  <textarea class="form-control" name="bio" rows="5" required>{{ @$auth->get->bio }}</textarea>
                </div>

              </div>

              <div class="wizard-step">

                <div class="form-group">
                  <label class="fw-bold">Why should you be hired as their agent? <span
                      class="text-danger"></span></label>
                  <textarea class="form-control" name="why_hire_you" rows="5" required>{{ @$auth->get->hired_agent }}</textarea>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">What sets you apart from other agents? <span
                      class="text-danger"></span></label>
                  <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ @$auth->get->apart_agent }}</textarea>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">What is your marketing strategy? <span class="text-danger"></span></label>
                  <textarea class="form-control" name="marketing_plan" rows="5" required>{{ @$auth->get->marketing_plan }}</textarea>
                </div>
              </div>



              <div class="wizard-step">
                <div class="row form-group">
                  @php
                    $auth = auth()->user();
                  @endphp
                  <div class="col-md-6">
                    <label class="fw-bold">Reviews Link:</label>
                    <input type="text" class="form-control" name="reviews_link"
                      value="{{ @$auth->get->review }}" />
                  </div>

                  <div class="col-md-6">
                    <label class="fw-bold">Website Link:</label>
                    <input type="text" class="form-control" name="website_link"
                      value="{{ @$auth->get->website_link }}" />
                  </div>
                </div>

                <div class="row form-group">
                  <div class="col-md-12 mb-2">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th colspan="2" class="text-center">Social Media Platforms</th>
                        </tr>
                        <tr>
                          <th>Type</th>
                          <th>Link</th>
                        </tr>
                      </thead>
                      <tbody class="social-links">
                        <tr>
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
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th colspan="2" class="text-right">
                            <a class="btn btn-primary add-row">Add New Row</a>
                          </th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

              </div>
              <div class="wizard-step">

                <div class="form-group">
                  <label class="fw-bold">What year did the agent get licensed? </label>
                  <input type="text" class="form-control" name="agent_license_year">
                </div>
              </div>

              <div class="wizard-step">

                <div class="form-group">
                  <label class="fw-bold">Virtual Buyer Presentation Video:</label>
                  <input type="file" class="form-control" name="virtual_buyer_presentation">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Virtual Buyer Presentation Link:</label>
                  <input type="url" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    name="virtual_buyer_presentation_link">
                </div>

                <div class="form-group">
                  <label class="fw-bold">Promotional marketing materials, such as postcards, flyers, brochures,
                    etc:</label>
                  <div class="d-flex align-items-baseline">
                    <input type="file" class="form-control" name="note"
                      value="{{ @$auth->get->promotional_material }}">
                  </div>
                </div>

                <div class="form-group">
                  <label class="fw-bold">Business Card:</label>
                  <div class="d-flex align-items-baseline">
                    <input type="file" class="form-control" name="card"
                      value="{{ @$auth->get->business_card }}">
                  </div>
                </div>
              </div>


              <div class="wizard-step">
                <div class="row form-group">
                  <div class="col-md-6">
                    <label class="fw-bold">First Name: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" id="first_name"
                      value="{{ auth()->user()->first_name }}" />
                  </div>

                  <div class="col-md-6">
                    <label class="fw-bold">Last Name: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" id="last_name"
                      value="{{ auth()->user()->last_name }}" />
                  </div>

                </div>

                <div class="row form-group">

                  <div class="col-md-6">
                    <label class="fw-bold">Phone Number: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="phone" id="phone"
                      value="{{ auth()->user()->phone }}" />
                  </div>

                  <div class="col-md-6">
                    <label class="fw-bold">Email: <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" id="email"
                      value="{{ auth()->user()->email }}" />
                  </div>

                  <div class="col-md-6">
                    <label class="fw-bold">Brokerage: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="brokerage" id="brokerage"
                      value="{{ auth()->user()->brokerage }}">
                  </div>

                  <div class="col-md-6">
                    <label class="fw-bold">Real Estate License #: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="license_no"
                      value="{{ auth()->user()->license_no }}" />
                  </div>

                </div>

                <div class="row form-group">

                  <div class="col-md-6">
                    <label class="fw-bold">NAR Member ID (NRDS ID): <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="mls_id" value="{{ auth()->user()->mls_id }}" />
                  </div>

                  {{-- <div class="col-md-6">
                                        <label class="fw-bold">
                                            Offered Buyer’s Concession (up to .5%):
                                        </label>
                                        <input type="number" name="concession" placeholder="0.00" id="concession"
                                            min="0" max=".05" step=".01"
                                            class="form-control has-icon search_places col-3" data-icon="fa-solid fa-percent"
                                            data-msg-required="">
                                    </div> --}}

                </div>

              </div>




              <div class="d-flex justify-content-between form-group mt-4">
                <div>
                  <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                  <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
                    style="display: none;">Next</button>
                  <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                    style="display: none;">Save</button>
                </div>
              </div>
              {{-- <div class="form-group mt-4 text-right">
                                <button class="btn btn-lg text-600 btn-success" type="submit">Save</button>
                            </div> --}}
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
@push('scripts')
  <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>

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

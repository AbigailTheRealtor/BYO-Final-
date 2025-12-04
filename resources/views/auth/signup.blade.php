<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bid Your Agent</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-5.2.2/css/bootstrap.min.css') }}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/global.css') }}" />
    <!-- Sign in Css  -->
    {{-- <link rel="stylesheet" href="{{ asset('assets/css/signup.css') }}" /> --}}
    <!-- Font style  -->
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <!-- //Font Icon  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <link rel="shortcut icon" href="{{ asset(get_setting('favicon')) }}" type="image/x-icon">
    <style>
        .error {
            color: red;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .row.form-group {
            padding-left: 15px;
            padding-right: 15px;
        }

        .card {
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.2);
            border: none;
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
    </style>
</head>

<body>
    <div class="signupMainDiv">
        <div class="row justify-content-center">
            {{-- <div class="col-4 leftCol" style="background:#11b7cf; min-height:100vh;">
                <img class="w-100" src="{{ asset('assets/pictures/signup/signup.jpg') }}" alt="" />

                <div class="p-4">
                    <div class="logo" style="width:200px; height:200px;">
                        <a href="{{ url('/') }}">
                            <img src="{{ asset('images/logo-light.png') }}" alt="logo" class="navbar-brand-dark"
                                style="width:100%; height:100%; object-fit:contain;" />
                        </a>
                    </div>
                    <h3 class="text-white">Benefits of Joining Our Partner Agent Network</h3>
                    <ul>
                        <li class="text-white mb-2" style="font-size:18px;">Access to highly motivated referrals from
                            pre-screened sellers and buyers.</li>
                        <li class="text-white mb-2" style="font-size:18px;">No upfront or monthly fees. Only pay a
                            referral fee at close of escrow.</li>
                        <li class="text-white mb-2" style="font-size:18px;">24/7 support from our UpNest Advisors to
                            help you win more listings.</li>
                    </ul>
                </div>
            </div> --}}
            <div class="col-8 signup p-5">
                <div class="d-flex justify-content-between">

                    <div>
                        <h1>Sign Up</h1>
                        <p>Already a member? <a href="{{ route('login') }}">Sign In</a></p>
                    </div>
                </div>
                <div class="signupForm mt-0">

                    <form method="POST" class="register_form" action="{{ route('register') }}">
                        @csrf
                        <div class="card">
                            <div class="card-body">
                                {{-- <h5 class="card-title">Card title</h5> --}}

                                <div class="step-1">
                                    <h5 class="card-title">Your professional details</h5>
                                    <div class="row form-group position-relative">
                                        <div class="col-md-4">
                                            <label for="">First Name *</label>
                                            <input type="text" class="form-control" placeholder="First Name"
                                                name="first_name" value="" data-rule="first_name" required />
                                            @if ($errors->has('first_name'))
                                                <div class="error">{{ $errors->first('first_name') }}</div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">Middle Initial</label>
                                            <input type="text" class="form-control" placeholder="Middle  Initial"
                                                name="middle_name" value="" data-rule="middle_name" required />
                                            @if ($errors->has('middle_name'))
                                                <div class="error">{{ $errors->first('middle_name') }}</div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">Last Name *</label>
                                            <input type="text" class="form-control" placeholder="Last Name"
                                                name="last_name" value="" data-rule="last_name" required />
                                            @if ($errors->has('last_name'))
                                                <div class="error">{{ $errors->first('last_name') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row form-group">
                                        <div class="col-md-12">
                                            <label for="">Pick Your Account Type:</label>
                                            <select class="form-select" aria-label="Default select example"
                                                name="user_type" id="userType">
                                                <option value="" selected>Pick your account type</option>
                                                <option value="seller">Seller</option>
                                                <option value="buyer">Buyer</option>
                                                <option value="landlord">Landlord</option>
                                                <option value="tenant">Tenant</option>
                                                <option value="agent">Real Estate Agent</option>
                                            </select>
                                            @if ($errors->has('user_type'))
                                                <div class="error">{{ $errors->first('user_type') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row form-group d-none real_estate_only my-4">
                                        <div class="col-md-12">
                                            <label for="">Office address *</label>
                                            <input type="text" class="form-control"
                                                placeholder="199 Market St, San Francisco, CA" name="office_address"
                                                value="" data-rule="address" />
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">Suite</label>
                                            <input type="text" class="form-control" name="office_suite_no"
                                                value="" />
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">Building #</label>
                                            <input type="text" class="form-control" name="office_building_no"
                                                value="" />
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">Zip code *</label>
                                            <input type="number" class="form-control hide_arrow" minlength="5"
                                                maxlength="5" placeholder="00000" name="office_zip" data-rule="zip"
                                                value=""/>
                                        </div>
                                        <div class="col-md-12">
                                            <label for="">Brokerage *</label>
                                            <input type="text" placeholder="Best Realty" class="form-control"
                                                name="brokerage" id="brokerage" data-rule="brokerage" value=""/>
                                            @if ($errors->has('brokerage'))
                                                <div class="error">{{ $errors->first('brokerage') }}</div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">License # *</label>
                                            <input type="number" class="form-control hide_arrow" minlength="9"
                                                maxlength="9" placeholder="000000000" name="license_no" value=""
                                                data-rule="license"/>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">License Date *</label>
                                            <input type="date" class="form-control" data-rule="date"
                                                name="license_date" value=""/>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="">NAR ID *</label>
                                            <input type="number" class="form-control hide_arrow" minlength="8"
                                                maxlength="8" placeholder="00000000" name="nar_id" value=""
                                                data-rule="nar"/>
                                        </div>
                                        <div class="col-md-12">
                                            <label>MLS ID *</label>
                                            <div class="col-md-12">
                                                <input type="text" class="form-control" placeholder="MLS ID"
                                                    name="mls_id" data-rule="mls" value=""/>
                                                @if ($errors->has('mls_id'))
                                                    <div class="error">{{ $errors->first('mls_id') }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row form-group">
                                        <div class="col-md-4">
                                            <label class="fw-bold" for="email">Email *</label>
                                            <input type="email" placeholder="john.smith@example.com"
                                                class="form-control required" data-error="0" data-rule="email"
                                                name="email" id="email" value="" required />
                                            @if ($errors->has('email'))
                                                <div class="error">{{ $errors->first('email') }}</div>
                                            @endif
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="fw-bold" for="address">Phone Number *:</label>
                                            <input type="text" name="phone_number" placeholder="Phone Number"
                                                data-type="phone" id="phone_number"
                                                class="form-control has-icon search_places"
                                                data-icon="fa-solid fa-flag-usa"
                                                data-msg-required="Please enter phone number" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="fw-bold" for="address">City *:</label>
                                            <input type="text" name="city" placeholder="City"
                                                data-type="cities" id="city"
                                                class="form-control has-icon search_places"
                                                data-icon="fa-solid fa-city" data-msg-required="Please enter city"
                                                required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="fw-bold" for="address">State *:</label>
                                            <input type="text" name="state" placeholder="State"
                                                data-type="states" id="state"
                                                class="form-control has-icon search_places"
                                                data-icon="fa-solid fa-flag-usa"
                                                data-msg-required="Please enter state" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="fw-bold" for="address">County *:</label>
                                            <input type="text" name="county" placeholder="County" id="county"
                                                data-type="counties" class="form-control has-icon search_places"
                                                data-icon="fa-solid fa-tree-city"
                                                data-msg-required="Please enter county" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="fw-bold" for="zip_code">Zip code *</label>
                                            <input type="number" class="form-control hide_arrow" minlength="5"
                                                maxlength="5" placeholder="00000" name="sales_zip" data-rule="zip" id="zip_code"
                                                value="" required />
                                        </div>
                                    </div>
                                    <hr>
                                    {{-- <div class="row form-group">
                                        <div class="col-md-10">How many transactions have you closed in the past 12
                                            months? *</div>
                                        <div class="col-md-2">
                                            <input type="number" step="1" name="total_transactions"
                                                class="form-control" data-rule="number" value="0" required>
                                        </div>
                                    </div> --}}
                                    <div class="row form-group mt-4">
                                        <div class="col-md-12 text-end">
                                            <button type="button" onclick="step_validate(1);"
                                                class="btn btn-success">Next</button>
                                        </div>
                                    </div>
                                </div>


                                <div class="step-2 d-none">
                                    <h5 class="card-title">Your professional details</h5>
                                    {{-- <div class="row form-group">
                                        <div class="col-md-6">
                                            <label for="">Address *</label>
                                            <input type="text" class="form-control"
                                                placeholder="199 Market St, San Francisco, CA" name="sales_address"
                                                value="" data-rule="address" required />
                                        </div>
                                        <div class="col-md-3">
                                            <label for="">Sales Price *</label>
                                            <input type="number" class="form-control hide_arrow" placeholder="$0"
                                                name="sales_price" data-rule="price" value="" required />
                                        </div>
                                    </div> --}}
                                    {{-- <div class="row form-group position-relative">
                                        <div class="col-md-12">
                                            <label>Realtor.com Profile</label>
                                            <input type="text"
                                                placeholder="https://www.realtor.com/realestateagents/your_id"
                                                class="form-control" name="realtor_profile" id="realtor_profile"
                                                value="" />
                                            @if ($errors->has('realtor_profile'))
                                                <div class="error">{{ $errors->first('realtor_profile') }}</div>
                                            @endif
                                        </div>
                                    </div> --}}
                                    <div class="row form-group position-relative">
                                        <div class="col-md-12">
                                            <label>Password *</label>
                                            <input type="password" class="form-control" placeholder="Password"
                                                name="password" data-rule="password" value="" required />
                                            @if ($errors->has('password'))
                                                <div class="error">{{ $errors->first('password') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row form-group position-relative">
                                        <div class="col-md-12">
                                            <label>Username *</label>
                                            <input type="text" class="form-control required"
                                                placeholder="User Name" name="user_name" data-error="0"
                                                value="" data-rule="username" required />
                                            <i data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                                data-bs-trigger="hover focus" data-bs-placement="top"
                                                data-bs-content="Your unique account name. Must be 3 to 10 characters and can include lowercase letters, numbers, and hyphens."
                                                class="fa fa-info-circle"></i>
                                            @if ($errors->has('user_name'))
                                                <div class="error">{{ $errors->first('user_name') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row form-group opacity-80 d-flex align-items-center">
                                        <div class="col-md-12 my-4">
                                            <div class="form-check">
                                                <input class="form-check-input me-2" type="checkbox" id="terms"
                                                    name="terms">
                                                <label class="form-check-label ml-3" for="terms">
                                                    Accept <a href="#">Terms & conditions</a>
                                                </label>
                                            </div>
                                            @if ($errors->has('terms'))
                                                <div class="error">{{ $errors->first('terms') }}</div>
                                            @endif
                                        </div>
                                    </div>


                                    <div class="form-group text-end">
                                        <button type="button" onclick="step_validate(2)"
                                            class="btn btn-success">Next</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <div class="nav-footer-nav row position-fixed bottom-0" style="padding-left: 12px">
        <div class="col-12">
            <ul class="d-flex align-items-center justify-content-between ps-0">
                <li>
                    <a href="sellerWork.html"> <i class="fa fa-home"></i> <span>Seller</span></a>
                </li>

                <li>
                    <a href="sellerWorkAgent.html"> <i class="fa fa-home"></i><span> Seller’s Agent</span></a>
                </li>

                <li>
                    <a href="addListing.html" class="add-listing"><i class="fa fa-plus text-white"></i> </a>
                </li>

                <li>
                    <a href="buyerWork.html"> <i class="fa fa-home"></i><span>Buyer</span></a>
                </li>

                <li>
                    <a href="buyerWorkAgent.html"> <i class="fa fa-home"></i><span>Buyer’s Agent</span></a>
                </li>
            </ul>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/d7dd5c0801.js" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/bootstrap-5.2.2/js/bootstrap.proper.min.js') }}"></script>
    <script src="{{ asset('assets/bootstrap-5.2.2/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-3.6.1.min.js') }}"></script>
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
    <script>
        $(function() {
            $('.required').keyup(function(e) {
                var v = $(this).val();
                var type = $(this).attr('type');
                var rule = $(this).data('rule');
                $(this).parent().children('.form_error').remove();

                var elm = this;
                if (rule == "email") {
                    $.ajax({
                        type: "GET",
                        url: "{{ url('/check_email') }}/" + v,
                        dataType: "json",
                        success: function(response) {
                            if (response.success) {
                                if (response.count > 0) {
                                    var error_htm =
                                        `<div class="text-danger form_error">Email already registered!</div>`;
                                    $(elm).after(error_htm);
                                    $(elm).data('error', 1);
                                } else {
                                    $(elm).data('error', 0);
                                }
                            }
                        }
                    });
                }
                if (rule == "username") {
                    $.ajax({
                        type: "GET",
                        url: "{{ url('/check_username') }}/" + v,
                        dataType: "json",
                        success: function(response) {
                            if (response.success) {
                                if (response.count > 0) {
                                    var error_htm =
                                        `<div class="text-danger form_error">Username is not available!</div>`;
                                    $(elm).after(error_htm);
                                    $(elm).data('error', 1);
                                } else {
                                    $(elm).data('error', 0);
                                }
                            }
                        }
                    });
                }

            });
        });

        function step_validate(step) {
            $('.form_error').remove();
            var err = 0;
            var checked_all = 0;
            $(`.step-${step} [required]`).each(function() {
                // console.log("ok");
                var v = $(this).val();
                var type = $(this).attr('type');
                var rule = $(this).data('rule');
                if (v.trim() == "") {
                    var error_htm = `<div class="text-danger form_error">This field is required!</div>`;
                    $(this).after(error_htm);
                    err++;
                }
                if (v != "") {
                    if (type == "number" && v <= 0) {
                        var error_htm = `<div class="text-danger form_error">This field is required!</div>`;
                        $(this).after(error_htm);
                        err++;
                    } else {
                        var min_len = $(this).attr('minlength');
                        if (min_len > 0) {
                            if (min_len != v.length) {
                                var error_htm =
                                    `<div class="text-danger form_error">Please enter valid value!</div>`;
                                $(this).after(error_htm);
                                err++;
                            }
                        }
                    }
                    if (type == "email") {
                        var is_valid_email = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,10})+$/.test(v);
                        if (!is_valid_email) {
                            var error_htm =
                                `<div class="text-danger form_error">Please enter valid email address!</div>`;
                            $(this).after(error_htm);
                            err++;
                        } else {
                            var error = $(this).data('error');
                            if (error > 0) {
                                var error_htm =
                                    `<div class="text-danger form_error">Email already registered!</div>`;
                                $(this).after(error_htm);
                                err++;
                            }
                        }
                    }
                    if (rule == "password") {
                        var is_valid_password = (v.length >= 8);
                        if (!is_valid_password) {
                            var error_htm =
                                `<div class="text-danger form_error">Password length must be greater than or equal 8!</div>`;
                            $(this).after(error_htm);
                            err++;
                        }
                    }
                    if (rule == "username") {
                        var error = $(this).data('error');
                        if (error > 0) {
                            var error_htm =
                                `<div class="text-danger form_error">Username is not available!</div>`;
                            $(this).after(error_htm);
                            err++;
                        }
                    }
                }
            });
            if (step == 1) {
                if (err == 0) {
                    $('.step-1').addClass('d-none');
                    $('.step-2').removeClass('d-none');
                }
            } else if (step == 2) {
                if (err == 0) {
                    if ($('#terms').is(":checked")) {
                        $('.register_form').submit();
                    } else {
                        alert("Accept terms and conditions to register!");
                    }
                }
            }
        }
    </script>
    <!-- PopOver Script  -->
    <script>
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        const popoverList = [...popoverTriggerList].map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl));
        $(function() {
            $('#userType').on('change', () => {
                let userType = $('#userType').val();
                // alert(user_type);
                if (userType == "agent") {
                    $('.real_estate_only').removeClass('d-none');
                    $('.real_estate_only').find('input').attr('required', 'required');
                } else {
                    $('.real_estate_only').addClass('d-none');
                    $('.real_estate_only').find('input').removeAttr('required');
                }
            })
        });
    </script>
</body>

</html>

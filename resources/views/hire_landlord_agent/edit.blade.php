@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">

    <style>
        .choices__list {
            z-index: 999;
        }

        .bedroom .option-text {
            padding: 0 !important;
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

        .address_confidential {
            color: #6666668c;
            font-family: emoji;

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

        /* Style to hide the custom_occupant_type by default */

        /* Utility checklist cards for tenant_pays and rent_includes */
        .utility-checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
        }
        .utility-checklist-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 8px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
            background: #fff;
            min-height: 72px;
            user-select: none;
        }
        .utility-checklist-card:hover {
            border-color: #adb5bd;
            background: #f8f9fa;
        }
        .utility-checklist-card.utility-selected {
            border-color: #0d6efd;
            background: #e8f0fe;
            box-shadow: 0 0 0 1px #0d6efd;
        }
        .utility-checklist-icon {
            font-size: 1.25rem;
            margin-bottom: 5px;
            color: #6c757d;
            transition: color 0.15s ease;
        }
        .utility-checklist-card.utility-selected .utility-checklist-icon {
            color: #0d6efd;
        }
        .utility-checklist-label {
            font-size: 0.72rem;
            font-weight: 500;
            line-height: 1.2;
            color: #495057;
        }
        .utility-checklist-card.utility-selected .utility-checklist-label {
            color: #0d6efd;
            font-weight: 600;
        }
        .utility-checklist-card input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
    </style>
@endpush
@section('content')
    {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
    @php
        $yes_or_nos = [
            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
        ];
    @endphp
    <div class="container pt-5 pb-5">
        <h4 class="title">
            {{ $title }}
        </h4>
        <div class="card">
            <div class="card-body">
                <div class="wizard-steps-progress">
                    <div class="steps-progress-percent"></div>
                </div>
                <form class="p-4 pt-0 validate mainform" id="edit-landlord-agent-auction" action="{{ route('landlord.hire.agent.auction.update', $auction->id) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                </form>
            </div>
        </div>
    </div>
    <template class="questions_temp">
    </template>
@endsection
@push('scripts')
    @include('patch-script', 
        ['moduleName' => 'edit-landlord-agent-auction', 
        'patchName' => 'edit-landlord-agent-auction', 
        'id' => $auction->id, 
        'initializeScripts' => 
        [
        'initializeFields', 
        'initializeIcons', 
        'initializeVideoPicker', 
        'changeAuctionType',
        'loadGoogleMapsScript', 
        ]
        ]);
    <script>
        $(document).ready(function() {
            $(document).on('change', '#working_with_agent', function() {
                var val = $(this).val();
                if (val == "Yes") {
                    $('.wizard-step-next').attr("disabled", "disabled");
                    $('.yes_message').text(
                        "This is a service for landlords that are not currently working with a licensed agent."
                    );
                } else {
                    $('.wizard-step-next').removeAttr("disabled");
                    $('.yes_message').text("");
                }
            });
        });
    </script>
    <script>
        // Video Preview
        async function initializeVideoPicker() {
            // Click button to activate hidden file input
            $(document).on('click', '.fileuploader-btn', function() {
                $('.fileuploader').click();
            });

            // Click above calls the open dialog box
            // Once something is selected the change function will run
            $(document).on('change', '.fileuploader',function() {
                $('#fileSizeError').remove();
                if (this.files[0].size > 50000000) {
                    $('.videoDiv').after(
                        '<span id="fileSizeError"  style="color: red;">Please upload a file less than 50MB. Thanks!</span>'
                    );
                    $(this).val('');
                    $('#saveBtn').prop('disabled', true);
                } else {
                    $('#saveBtn').prop('disabled', false);
                    $('#fileSizeError').remove();
                }
                // Check if a file has been selected
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    var file = this.files[0];

                    if (file.type.startsWith('image/')) {
                        reader.onload = function(event) {
                            $('.video').empty();
                            $('.video').append('<img src="' + event.target.result +
                                '" width="200" height="160">');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else if (file.type.startsWith('video/')) {
                        reader.onload = function(event) {
                            var fileContent = event.target.result;
                            $('.video').empty();
                            $('.video').append('<video src="' + fileContent +
                                '" width="200" height="160" controls autoplay></video>');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else {
                        // File is neither image nor video
                        alert('Please select either an image or a video file.');
                        this.value = ''; // Clear the file input
                        return;
                    }

                    // Hide the preview icon and show the upload button
                    $('.video').removeClass('bgImg');
                    $('.upload-button').show();
                } else {
                    // If no file is selected, and if .video container is empty, show the preview icon and hide the add button
                    if ($('.video').is(':empty')) {
                        $('.video').addClass('bgImg');
                        $('.upload-button').show();
                    } else {
                        // If a file already exists, hide the preview icon and show the add button
                        $('.video').removeClass('bgImg');
                        $('.upload-button').show();
                    }
                }
            });
        };




        // Video Preview
        function initImageUpload(box) {
            let uploadField = box.querySelector('.image-upload');

            uploadField.addEventListener('change', getFile);

            function getFile(e) {
                let file = e.currentTarget.files[0];
                checkType(file);
            }

            function previewImage(file) {
                let thumb = box.querySelector('.js--image-preview'),
                    reader = new FileReader();

                reader.onload = function() {
                    thumb.style.backgroundImage = 'url(' + reader.result + ')';
                }
                reader.readAsDataURL(file);
                thumb.className += ' js--no-default';
            }

            function checkType(file) {
                let imageType = /image.*/;
                if (!file.type.match(imageType)) {
                    throw 'Datei ist kein Bild';
                } else if (!file) {
                    throw 'Kein Bild gewählt';
                } else {
                    previewImage(file);
                }
            }

        }

        // initialize box-scope
        function initializeImagePicker(){
            var boxes = document.querySelectorAll('.box');

            for (let i = 0; i < boxes.length; i++) {
                let box = boxes[i];
                initDropEffect(box);
                initImageUpload(box);
            }
        }


        /// drop-effect
        function initDropEffect(box) {
            let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;

            // get clickable area for drop effect
            area = box.querySelector('.js--image-preview');
            area.addEventListener('click', fireRipple);

            function fireRipple(e) {
                area = e.currentTarget
                // create drop
                if (!drop) {
                    drop = document.createElement('span');
                    drop.className = 'drop';
                    this.appendChild(drop);
                }
                // reset animate class
                drop.className = 'drop';

                // calculate dimensions of area (longest side)
                areaWidth = getComputedStyle(this, null).getPropertyValue("width");
                areaHeight = getComputedStyle(this, null).getPropertyValue("height");
                maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));

                // set drop dimensions to fill area
                drop.style.width = maxDistance + 'px';
                drop.style.height = maxDistance + 'px';

                // calculate dimensions of drop
                dropWidth = getComputedStyle(this, null).getPropertyValue("width");
                dropHeight = getComputedStyle(this, null).getPropertyValue("height");

                // calculate relative coordinates of click
                // logic: click coordinates relative to page - parent's position relative to page - half of self height/width to make it controllable from the center
                x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
                y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;

                // position drop and animate
                drop.style.top = y + 'px';
                drop.style.left = x + 'px';
                drop.className += ' animate';
                e.stopPropagation();

            }
        }

        function validateFile() {
            const fileInput = document.querySelector('input[name="video"]');
            const fileSizeError = document.getElementById('fileSizeError');
            const maxFileSize = 5 * 1024 * 1024; // 5MB

            if (fileInput.files[0].size > maxFileSize) {
                fileSizeError.style.display = 'block';
                fileInput.value = ''; // Clear the file input to prevent submission
                return false;
            } else {
                fileSizeError.style.display = 'none';
                return true;
            }
        }

        function show_garage_opt() {
            var h = $('#garage').val();
            if (h == "Yes" || h == "Optional") {
                $('.garage_opt').show();
            } else {
                $('.garage_opt').hide();
            }
        }
    </script>
    <script>
        function add_county_row() {
            var county_row = $('.county_temp').html();
            $('.county_btn_row').before(county_row);
            initialize();
        }

        function add_city_row() {
            var city_row = $('.city_temp').html();
            $('.city_btn_row').before(city_row);
            initialize();
        }
    </script>
    <script>
        function changeAuctionType(v) {
            if (v == "Auction Listing") {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').hide();
                $('.normal-length').show();
                $('.auction_length_cover').show();

            } else {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').show();
                $('.normal-length').hide();
                $('.auction_length_cover').hide();
            }
        }
    </script>
    <script>
        $(document).on('change', '.exchange_trade_for', function() {
            var items = $(this).val();
            if (items == "Another home") {
                $('.custom_trade').addClass('d-none');

                $('.item_values').removeClass('d-none');
            } else if (items == "Vehicles") {
                $('.custom_trade').addClass('d-none');
                $('.item_values').removeClass('d-none');
            } else if (items == "Boats") {
                $('.custom_trade').addClass('d-none');
                $('.item_values').removeClass('d-none');
            } else if (items == "Motorhomes") {
                $('.custom_trade').addClass('d-none');
                $('.item_values').removeClass('d-none');
            } else if (items == "Artwork") {
                $('.custom_trade').addClass('d-none');
                $('.item_values').removeClass('d-none');
            } else if (items == "Jewelry") {
                $('.custom_trade').addClass('d-none');
                $('.item_values').removeClass('d-none');
            } else if (items == "Other") {
                $('.item_values').addClass('d-none');
                $('.custom_trade').removeClass('d-none');
            }
        });
    </script>
    <script>
        function changePropertyType(p) {
            if (p == "Residential Property") {

                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.business_type_next').addClass('d-none');
                $('.business_type_next_hide').removeClass('d-none');
                $('.hide_vacant').addClass('d-none');
                $('.road_frontage_next_hide').removeClass('d-none');
                $('.residential_and_income_hide').addClass('d-none');
                $('.residential_and_income').removeClass('d-none');
                $('.for_income_only').addClass('d-none');
                $('.for_residential_only').removeClass('d-none');
                $('.residential_hide').removeClass('d-none');
                $('.bedroomRes').removeClass('d-none');
                $('.traditional_hide').addClass('d-none');
                $('.commercial_hide').addClass('d-none');
                $('.business-length').hide();
                $('.removeResidential').addClass('d-none');
                $('.resFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', false);
                    $(this).show();
                });
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', true);
                    $(this).hide();
                });


            } else if (p == "Income Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').show();
                $('.commercial-length').hide();
                $('.residential_hide').removeClass('d-none');
                $('.residential_remove').remove();
                $('.vacant_land-length').hide();
                $('.business-length').hide();

            } else if (p == "Commercial Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
                $('.business_type_next').removeClass('d-none');
                $('.business_type_next_hide').addClass('d-none');
                $('.residential_and_income').addClass('d-none');
                $('.residential_hide').addClass('d-none');
                $('.bedroomRes').addClass('d-none');
                $('.traditional_hide').removeClass('d-none');
                $('.commercial_hide').removeClass('d-none');
                $('.vacant_land-length').hide();
                $('.business-length').hide();
                $('.removeCommercial').addClass('d-none');
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', false);
                    $(this).show();
                });
                $('.resFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', true);
                    $(this).hide();
                });



            } else if (p == "Vacant Land (Current Use)") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.business_type_next').removeClass('d-none');
                $('.business_type_next_hide').addClass('d-none');
                $('.residential_and_income').addClass('d-none');
                $('.residential_hide').addClass('d-none');
                $('.vacant_land-length').show();
                $('.business-length').hide();

            } else if (p == "Business Opportunity") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.business_type_next').removeClass('d-none');
                $('.business_type_next_hide').addClass('d-none');
                $('.residential_and_income').addClass('d-none');
                $('.residential_hide').addClass('d-none');
                $('.vacant_land-length').hide();
                $('.business-length').show();
            } else {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.business_type_next').addClass('d-none');
                $('.business_type_next_hide').removeClass('d-none');
                $('.hide_vacant').addClass('d-none');
                $('.road_frontage_next_hide').removeClass('d-none');
                $('.vacant_land-length').hide();
                $('.business-length').hide();

            }
        }
    </script>
    <script>
        function changePropertyStyle(p) {
            if (p == "Vacant Land") {
                // alert('ok');
                $('.property_style_next_hide').addClass('d-none');
                $('.road_frontage_next_hide').addClass('d-none');
                $('.business_opportunity_show').addClass('d-none');
                $('.vacant_land_show').removeClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.show_vacant').removeClass('d-none');
                $('.vacant_land_hide').remove();
                // $('.remove_business_opportunity').remove();
                $('.business_opportunity_remove').show();

            } else {
                $('.vacant_land_show').addClass('d-none');
                $('.hide_vacant').addClass('d-none');
                $('.road_frontage_next_hide').removeClass('d-none');
                $('.business_opportunity_show').removeClass('d-none');
                $('#remove_pets_question').remove();
                // $('.vacant_land_remove').remove();
                $('.show_vacant').addClass('d-none');
            }
        }
    </script>
    <script>
        function changeWaterView(w) {
            if (w == "Yes" || w == "Optional") {
                $('.water_view_residential_and_income').show();
            } else {
                $('.water_view_residential_and_income').hide();
            }
        }

        function changeWaterAccess(w) {
            if (w == "Yes" || w == "Optional") {
                $('.water_access_residentail').show();
            } else {
                $('.water_access_residentail').hide();
            }
        }

        function changeWaterExtras(w) {
            if (w == "Yes" || w == "Optional") {
                $('.water_extras_residential_and_income').show();
            } else {
                $('.water_extras_residential_and_income').hide();
            }
        }

        function changeWaterFrontage(w) {
            if (w == "Yes" || w == "Optional") {
                $('.water_frontage_residential_and_income').show();
            } else {
                $('.water_frontage_residential_and_income').hide();
            }
        }

        function changeViewPrefrence(w) {
            if (w == "Yes" || w == "Optional") {
                $('.water_view_preference_residential_and_income').show();
            } else {
                $('.water_view_preference_residential_and_income').hide();
            }
        }
    </script>
    <script>
        function initializeIcons(){
            $('.has-icon').each(function(i) {
                var cover = `<div class="input-cover input-cover-${i}"></div>`;
                $(this).before(cover);
                $(this).appendTo(`.input-cover-${i}`);
                var iconClass = $(this).data('icon');
                var id = $(this).attr('id');
                var htm = `<label for="${id}" class="input-icon"><i class="${iconClass} " ></i></label>`;
                $(this).before(htm);
            });
        }

        function initializeFields(){
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
        }

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
            // console.log(v);
            check_custom();
        }

        function check_custom() {
            $('.option-container').each(function(i, elm) {
                var target = $(elm).data('target') || "";
                var is_active = $(elm).hasClass('active');
                if (target != "") {
                    // console.log("is_active", is_active, target);
                    if (is_active) {
                        $(target).removeClass("d-none");
                    } else {
                        $(target).addClass("d-none");
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
                    /* submitHandler: function() {
                        // alert("Submitted, thanks!");
                        $(".mainform").submit();
                    } */
                });

                StepWizard.setStep();
                property_type;
                $('#property_type').on('change', function() {
                    property_type = $(this).val();
                    // Count the remaining steps without removing them
                });
                $('.wizard-step-next').click(function(e) {
                    console.log(StepWizard.currentStep);
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {
                            $('.wizard-step.active').removeClass('active');
                            if (StepWizard.currentStep == 7 && property_type ==
                                'Income Property') {
                                StepWizard.nextStep = 10;
                                StepWizard.backStep = 7;
                            } else if (StepWizard.currentStep == 13 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 15;
                                StepWizard.backStep = 13;
                            } else if (StepWizard.currentStep == 18 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 20;
                                StepWizard.backStep = 18;
                            } else if (StepWizard.currentStep == 23 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 25;
                                StepWizard.backStep = 23;
                            } else if (StepWizard.currentStep == 8 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 10;
                                StepWizard.backStep = 8;
                            } else if (StepWizard.currentStep == 11 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 14;
                                StepWizard.backStep = 11;
                            } else if (StepWizard.currentStep == 15 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 17;
                                StepWizard.backStep = 15;
                            } else if (StepWizard.currentStep == 17 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 19;
                                StepWizard.backStep = 17;
                            } else if (StepWizard.currentStep == 19 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 22;
                                StepWizard.backStep = 19;
                            } else {
                                StepWizard.backStep = StepWizard.currentStep;
                            }
                            $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
                            StepWizard.setStep();
                            if (
                                StepWizard.currentStep == 19 &&
                                (property_type ==
                                    'Income Property')
                            ) {
                                $('.wizard-step-next').hide();
                                $('.wizard-step-finish').show();
                            }
                            if (
                                StepWizard.currentStep == 34 &&
                                (property_type == 'Commercial Property' || property_type ==
                                    'Residential Property')
                            ) {
                                $('.wizard-step-next').hide();
                                $('.wizard-step-finish').show();
                            }

                        }
                    }
                });

                $('.wizard-step-back').click(function(e) {
                    if ($('.wizard-step.active').prev().is('.wizard-step')) {
                        $('.wizard-step.active').removeClass('active');
                        $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
                        StepWizard.setStep();
                        if (StepWizard.currentStep == 10 && property_type ==
                            'Income Property') {
                            StepWizard.backStep = 7;
                        }
                        // else if (StepWizard.currentStep == 12 && property_type ==
                        //   'Residential Property') {
                        //   StepWizard.backStep = 10;
                        // }
                        else if (StepWizard.currentStep == 15 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 13;
                        } else if (StepWizard.currentStep == 20 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 18;
                        } else if (StepWizard.currentStep == 25 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 23;
                        } else if (StepWizard.currentStep == 10 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 8;
                        } else if (StepWizard.currentStep == 14 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 11;
                        } else if (StepWizard.currentStep == 17 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 15;
                        } else if (StepWizard.currentStep == 19 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 17;
                        } else if (StepWizard.currentStep == 22 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 19;
                        } else {
                            StepWizard.backStep = StepWizard.currentStep - 1;
                        }
                    }
                });

                $('.wizard-step-finish').click(function(e) {

                    //Remove All the SLides Except THe Vacant Land
                    if (property_type === 'Vacant Land (Current Use)') {
                        var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                            return parseInt($(this).attr('data-step')) >= 5 && parseInt($(this)
                                .attr('data-step')) <= 32;
                        });
                        $stepsToRemove.each(function() {
                            $(this).closest('div[data-step]').remove();
                        });
                    }
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
                var comp = 0;

                if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 34) {
                    // Calculate progress for Residential and Income property steps (5 to 19)
                    comp = 20 + (((StepWizard.currentStep - 5) / (34 - 5)) * 80);
                } else if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 34) {
                    // Calculate progress for Commercial and Business opportunity steps (20 to 32)
                    comp = 20 + (((StepWizard.currentStep - 5) / (34 - 5)) * 80);
                } else if (StepWizard.currentStep >= 33 && StepWizard.currentStep <= 43) {
                    // Calculate progress for Vacant land steps (33 to 43)
                    comp = 20 + (((StepWizard.currentStep - 33) / (43 - 33)) * 80);
                } else {
                    // Default progress calculation for other steps
                    comp = ((StepWizard.currentStep - 1) / 8) * 20;
                }


                $('.steps-progress-percent').animate({
                    width: comp.toFixed(0) + '%',
                });
            },

            currentStep: 1,
            nextStep: 2,
            bsckStep: 1,
            total_steps: 0,
            data_step: 1,

        };
        $(document).on('change', '#need_water_view', function() {
            // alert('ok');

            var selectedValue = $(this).val();

            if (selectedValue == 'No') {
                $('#prefer_water_view').hide();
                $('#extra_water').hide();

            }

            if (selectedValue == 'No') {
                $('#prefer_water_view').hide();
                $('#extra_water').hide();

            }


            if (selectedValue == 'Yes') {
                $('#prefer_water_view').show();
                $('#extra_water').show();

            }
        });
    </script>

    <script>
        $(document).ready(function(){
            $(document).on('change', '#broker_compensation', function(){
                let val = $(this).val();
                if(val !== 'Negotiable'){
                    $('.compensation_broker_yes').removeClass('d-none');
                }else{
                    $('.compensation_broker_yes').addClass('d-none');
                }
            });

            $(document).on('change', '#handle_compensation', function(){
                let val = $(this).val();
                if(val !== 'No compensation will be offered to the tenant’s broker.'){
                    $('.handle_compensation_broker_yes').removeClass('d-none');
                }else{
                    $('.handle_compensation_broker_yes').addClass('d-none');
                }
            })

            $(document).on('change', '#compensation_amount', function(){
                let val = $(this).val();
                if(val !== 'Negotiable'){
                    $('.compensation_amount_yes').removeClass('d-none');
                }else{
                    $('.compensation_amount_yes').addClass('d-none');
                }
            })

            $(document).on('change', '#early_termination', function(){
                $('.early_termination_yes').removeClass('d-none');
            })

            $(document).on('change', '#compensation_new_lease_amount', function(){
                let val = $(this).val();

                if(val !== 'Negotiable'){
                    $('.compensation_new_lease_amount').removeClass('d-none');
                }else{
                    $('.compensation_new_lease_amount').addClass('d-none');
                }
            })

            $(document).on('change', '#payment_timing', function(){
                $('.payment_timing_days').removeClass('d-none');
            })
        })
    </script>

    <script>
        function initializeMap() {
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
        $(document).ready(function(){
            $('select').trigger('change');
        })
        
    </script>
    @php $mapsKey = config('services.google.places_key', ''); @endphp
    @if($mapsKey !== '' && $mapsKey !== null)
    <script>
        function loadGoogleMapsScript() {
            var script = document.createElement('script');
            let googlePlacesApiKey = "{{ $mapsKey }}";
            script.src = `https://maps.googleapis.com/maps/api/js?key=${googlePlacesApiKey}&libraries=places&callback=initializeMap`;
            script.async = true;
            script.defer = true;
    
            document.body.appendChild(script);
        }
    </script>
    @else
    <div style="border: 2px solid #f59e0b; background-color: #fffbeb; color: #92400e; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 4px 0;">
        &#9888; Google Maps is not configured for this environment &mdash; address autocomplete is unavailable.
    </div>
    @endif
@endpush

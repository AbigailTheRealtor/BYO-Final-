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


        .box {
            display: block;
            width: 400px;
            height: auto;
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
            width: 100%;
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
                <form class="p-4 pt-0 mainform" id="edit-landlord-auction" action="{{ route('agent.landlord.auction.update', $auction->id) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    
                </form>
            </div>
        </div>
    </div>
    @php
        $roomDataBackend = json_decode($auction->get->room_details_data);
    @endphp
@endsection
@push('scripts')
    @include('patch-script', 
    ['moduleName' => 'edit-landlord-auction', 
    'patchName' => 'edit-landlord-auction', 
    'id' => $auction->id, 
    'initializeScripts' => 
    [
    'initializeRoomDetailsFields',
    'initializeFields', 
    'initializeIcons', 
    'initializeVideoPicker', 
    'changeAuctionType', 
    'initializeCompensationFields',
    'loadGoogleMapsScript', 
    // 'initializeImagePicker'
    ]
    ]);
    <script>
        // Video Preview
        async function initializeVideoPicker() {
          // Click button to activate hidden file input
          $('.fileuploader-btn').on('click', function() {
            $('.fileuploader').click();
          });
    
          // Click above calls the open dialog box
          // Once something is selected the change function will run
          $('.fileuploader').change(function() {
              $('#errorDiv').remove();
            if (this.files[0].size > 55000000) {
              $(this).parent().after('<span id="errorDiv" style="color: red;">Please upload a file less than 50MB. Thanks!!</span>');
              $(this).val('');
              $('#saveBtn').prop('disabled', true);
            } else {
              $('#saveBtn').prop('disabled', false);
              $('#errorDiv').remove();
            }
            // Check if a file has been selected
            if (this.files && this.files[0]) {
              var reader = new FileReader();
              var file = this.files[0];
    
              if (file.type.startsWith('image/')) {
                reader.onload = function(event) {
                  $('.video').empty();
                  $('.video').append('<img src="' + event.target.result + '" width="200" height="160">');
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
        $(function() {
          show_garage_opt("");
        });
    </script>
    <script>
        function changeAuctionType(v) {
            console.log('Auction_Type', v);
            if (v == "Auction Listing") {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').hide();
                $('.normal-length').show();
                $('.auction_length_cover').show();
                $('.traditional').hide();
                $('.timerAuction').show();
            } else if (v == "Traditional Listing") {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').show();
                $('.normal-length').hide();
                $('.auction_length_cover').hide();
                $('.traditional').show();
                $('.timerAuction').hide();
            }
        }
    
        // Nisar Changing
        Filevalidation = () => {
    
            var txt = "";
            const fi = document.getElementById('file');
            // Check if any file is selected.
            if (fi.files.length > 0) {
                for (const i = 0; i <= fi.files.length - 1; i++) {
    
                    const fsize = fi.files.item(i).size;
                    const file = Math.round((fsize / 1024));
                    // The size of the file.
                    if (file >= 102400) {
                        // alert(
                        //     "File too Big, please select a file less than 2mb");
                        txt = "File too Big, please select a file less than 100mb";
                        document.getElementById("demo").innerHTML = txt;
                        // var myText = "File too Big, please select a file less than 2mb";
                    } else if (file < 102400) {
                        txt = "";
                        document.getElementById("demo").innerHTML = txt;
                    } else {
                        document.getElementById('size').innerHTML = '<b>' +
                            file + '</b> KB';
                    }
                }
            }
        }
        // Nisar Changing End
    </script>
    <script>
        function changePropertyType(p) {
            console.log('Property_type', p);
            if (p == "Residential Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.residential_show').removeClass('d-none');
                $('.commercial_show').addClass('d-none');
                // nisar changing
                $('#leasable-sqft').remove();
                $('#price').remove();
                $('#terms').remove();
                $('.resFields').each(function() {
                    $(this).find('select, input,label,div,option,textarea').prop('disabled', false).show();
                });
                $('.commercialFields').each(function() {
                    $(this).find('select, input,label,div,option ,textarea').prop('disabled', true).hide();
                });
            } else if (p == "Commercial Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
                $('.residential_show').addClass('d-none');
                $('.commercial_show').removeClass('d-none');
    
                // nisar changing
                $('#price').show();
                $('#bathroom').remove();
                $('#commercial').remove();
                $('#furnishings').remove();
                $('#pool').remove();
                $('#priceSqft').remove();
                $('#RentIncludes').remove();
                $('#acceptPet').remove();
                $('.commercialFields').each(function() {
                    $(this).find('select, input,label,div,option ,textarea').prop('disabled', false).show();
                });
                $('.resFields').each(function() {
                    $(this).find('select,input,label,div,option ,textarea').prop('disabled', true).hide();
                });
            } else {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').hide();
            }
        }
    </script>
    <script>
        function initializeCompensationFields() {
            $('#compensation_structure').change(function(){
                let selected = $(this).val();
                if(selected !== "There is no compensation offered to the tenant's broker."){
                    $('.compensationYes').removeClass('d-none');
                }else{
                    $('.compensationYes').addClass('d-none');
                }
            })
        }
    </script>
    <script>
        $(function() {
            StepWizard.init();
        });
        var StepWizard = {
            init: function() {
                StepWizard.total_steps = $('.wizard-step').length;
                var property_type;
                var v = $(".mainform").validate({
                    errorClass: "text-error text-danger w-100",
                    onkeyup: false,
                    onfocusout: false,
                });
                StepWizard.setStep();
                $('#property_type').on('change', function() {
                    property_type = $(this).val();
                });
                $('.wizard-step-next').click(function(e) {
                    console.log(StepWizard.currentStep)
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {
    
                            $('.wizard-step.active').removeClass('active');
                            if (StepWizard.currentStep == 13 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 15;
                                StepWizard.backStep = 13;
                            } else if (StepWizard.currentStep == 9 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 11;
                                StepWizard.backStep = 9;
                            } else if (StepWizard.currentStep == 17 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 21;
                                StepWizard.backStep = 17;
                            } else if (StepWizard.currentStep == 22 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 24;
                                StepWizard.backStep = 22;
                            } else if (StepWizard.currentStep == 26 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 29;
                                StepWizard.backStep = 26;
                            } else if (StepWizard.currentStep == 32 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 35;
                                StepWizard.backStep = 32;
                            } else if (StepWizard.currentStep == 41 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 43;
                                StepWizard.backStep = 41;
                            } else if (StepWizard.currentStep == 28 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 30;
                                StepWizard.backStep = 28;
                            } else if (StepWizard.currentStep == 34 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 37;
                                StepWizard.backStep = 34;
                            } else if (StepWizard.currentStep == 38 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 42;
                                StepWizard.backStep = 38;
                            } else {
                                StepWizard.backStep = StepWizard.currentStep;
    
                            }
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
                        if (StepWizard.currentStep == 15 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 13;
                        } else if (StepWizard.currentStep == 11 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 9;
                        } else if (StepWizard.currentStep == 21 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 17;
                        } else if (StepWizard.currentStep == 24 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 22;
                        } else if (StepWizard.currentStep == 29 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 26;
                        } else if (StepWizard.currentStep == 35 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 32;
                        } else if (StepWizard.currentStep == 43 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 41;
                        } else if (StepWizard.currentStep == 30 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 28;
                        } else if (StepWizard.currentStep == 37 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 34;
                        } else if (StepWizard.currentStep == 42 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 38;
                        } else {
                            StepWizard.backStep = StepWizard.currentStep - 1;
                        }
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
        function initializeRoomDetailsFields(){
            const roomTypeSelect = $('#room_typeRes');
            const fieldsContainer = $('#dynamicFieldsContainer');
            const roomTypeData = $('#room_type_input');
            const roomData = @json($roomDataBackend);
            const roomDetailsData = JSON.parse(roomData);
    
            const fieldData = {}; // object to store field data
    
            function sanitizeId(optionName) {
                return optionName
                    .toLowerCase() // Optional: make it lowercase for consistency
                    .replace(/[^a-z0-9]/g, '-'); // Replace invalid characters with underscores
            }
    
            // Handle changes in the select element
            $(roomTypeSelect).change(function () {
                const selectedOptions = $(this).val(); // Get selected options
                console.log('selectedOptions', selectedOptions);
                if(!selectedOptions) return; // Exit if no options are selected
    
                // Add fields for new options
                selectedOptions.forEach(option => {
                    const roomTypeBackendData = roomDetailsData[option];
                    let sanitizedOpt = sanitizeId(option);
                    if (!fieldData[option]) {
                        createFields(sanitizedOpt, option, roomTypeBackendData);
                    }
                });
    
                // Remove fields for unselected options
                Object.keys(fieldData).forEach(option => {
                    const sanitizedIds = selectedOptions.map((option) => {
                        const sanitizedOption = sanitizeId(option);
                        return `${sanitizedOption}`; // Create unique ID for each
                    });
                    if (!selectedOptions.includes(option)) {
                        let sanitizedOpt = sanitizeId(option);
                        removeFields(sanitizedOpt, option);
                    }
                });
    
                updateHiddenField(); // Update the hidden field after changes
            });
    
    
            // Create fields for a selected option
            function createFields(option, optionName, roomTypeBackendData) {
    
                    $(fieldsContainer).append(`<div id="${option}-fields-container"></div`)
    
                    const roomDimensionHtml = `
                        <hr data-room-type="${option}">
                        <h5 data-room-type="${option}">Room Type: ${optionName}</h5>
                        <div class="form-group roomDet" data-option="${optionName}">
                            <label class="fw-bold">Approximate Room Dimensions (Width x Length)</label>
                            <input type="text" name="roomDimensions" id="dynamic-input-roomDimensions-${optionName}" data-option="${optionName}" value="${roomTypeBackendData['roomDimensions']}"  class="form-control dynamic-room-input" required>
                        </div>
                    `;
    
                    // <button type="button" class="btn btn-secondary btn-sm w-100 addRoomBtn mt-2" data-option="${optionName}">
                    //  <i class="fa-solid fa-plus"></i> Add New Row
                    // </button>
    
                    $(`#${option}-fields-container`).append(roomDimensionHtml);
                    $(`#dynamic-input-roomDimensions-${optionName}`).trigger('input');
    
                    // Add Room Levels dropdown
                    const roomLevels = [
                        { name: "Basement", target: "" },
                        { name: "First", target: "" },
                        { name: "Second", target: "" },
                        { name: "Third", target: "" },
                        { name: "Upper", target: "" },
                    ];
                    appendDropdown("Room Level:", "room_level", roomLevels, 1, 'fa-regular fa-circle-check', true);
    
                    // Add Bedroom Closets dropdown
                    const bedroomClosets = [
                        { name: "Built-in Closet", target: "" },
                        { name: "Coat Closet", target: "" },
                        { name: "Dual Closets", target: "" },
                        { name: "Linen Closet", target: "" },
                        { name: "No Closet", target: "" },
                        { name: "Storage Closet", target: "" },
                        { name: 'Walk-in Closet', target: ""},
                    ];
                    appendDropdown("Closet Type:", "bedroomCloset", bedroomClosets, 2, 'fa-regular fa-circle-check');
    
                    // Add Room Primary Floor Covering dropdown
                    const roomPrimary = [
                        { name: "Bamboo", target: "" },
                        { name: "Brick/Stone", target: "" },
                        { name: "Carpet", target: "" },
                        { name: "Ceramic Tile", target: "" },
                        { name: "Concrete", target: "" },
                        { name: "Cork", target: "" },
                        { name: "Engineered Hardwood", target: "" },
                        { name: "Epoxy", target: "" },
                        { name: "Forestry Stewardship Certified", target: "" },
                        { name: "Granite", target: "" },
                        { name: "Laminate", target: "" },
                        { name: "Linoleum", target: "" },
                        { name: "Marble", target: "" },
                        { name: "Parquet", target: "" },
                        { name: "Porcelain Tile", target: "" },
                        { name: "Quarry Tile", target: "" },
                        { name: "Reclaimed Wood", target: "" },
                        { name: "Recycled/Composite Flooring", target: "" },
                        { name: "Slate", target: "" },
                        { name: "Terrazzo", target: "" },
                        { name: "Tile", target: "" },
                        { name: "Travertine", target: "" },
                        { name: "Vinyl", target: "" },
                        { name: "Wood", target: "" },
                        { name: "Other", target: ".floor_covering_other" },
                    ];
                    appendDropdown("Room Primary Floor Covering:", "roomPrimary", roomPrimary, 3, 'fa-regular fa-circle-check', false, true);
    
                    // Add Room Features dropdown
                    const roomFeatures = [
                        { name: "Bar", target: "" },
                        { name: "Bath with Spa/Hydro Massage Tub", target: "" },
                        { name: "Bath With Whirlpool", target: "" },
                        { name: "Bidet", target: "" },
                        { name: "Breakfast Bar", target: "" },
                        { name: "Built-In Shelving", target: "" },
                        { name: "Built-In Shower Bench", target: "" },
                        { name: "Ceiling Fan(s)", target: "" },
                        { name: "Claw Foot Tub", target: "" },
                        { name: "Closet Pantry", target: "" },
                        { name: "Cooking Island", target: "" },
                        { name: "Desk Built-In", target: "" },
                        { name: "Dual Sinks", target: "" },
                        { name: "En Suite Bathroom", target: "" },
                        { name: "Exhaust Fan", target: "" },
                        { name: "Garden Bath", target: "" },
                        { name: "Granite Counters", target: "" },
                        { name: "Handicap Accessible", target: "" },
                        { name: "Heated Floors", target: "" },
                        { name: "Island", target: "" },
                        { name: "Jack and Jill Bathroom", target: "" },
                        { name: "Makeup/Vanity Space", target: "" },
                        { name: "Multiple Shower Heads", target: "" },
                        { name: "Pantry", target: "" },
                        { name: "Rain Shower Head", target: "" },
                        { name: "Sauna", target: "" },
                        { name: "Shower- No Tub", target: "" },
                        { name: "Single Vanity", target: "" },
                        { name: "Sink-Pedestal", target: "" },
                        { name: "Split Vanities", target: "" },
                        { name: "Steam Shower", target: "" },
                        { name: "Stone Counters", target: "" },
                        { name: "Sunken Shower", target: "" },
                        { name: "Tall Countertops", target: "" },
                        { name: "Tile Counters", target: "" },
                        { name: "Tub with Separate Shower Stall", target: "" },
                        { name: "Tub with Shower", target: "" },
                        { name: "Urinal", target: "" },
                        { name: "Walk-In Pantry", target: "" },
                        { name: "Walk-In Tub", target: "" },
                        { name: "Water Closet/Priv Toliet", target: "" },
                        { name: "Wet Bar", target: "" },
                        { name: "Window/Skylight in Bath", target: "" },
                        { name: "Other", target: ".roomFeatureOther" },
                    ];
                    appendDropdown("Room Features:", "room_feature", roomFeatures, 4, 'fa-regular fa-circle-check', false, true);
    
                    // Function to append dropdowns dynamically
                    function appendDropdown(labelText, name, options, index, icon, multiple = false, otherFields = false) {
                        let optionsHtml = options
                            .map(
                                (opt) =>
                                    `<option value="${opt.name}" data-target="${opt.target}" data-icon="<i class='${icon}'></i>"
                                        style="width:calc(33.3% - 10px);" class="card flex-row"
                                        ${Array.isArray(roomTypeBackendData[name]) 
                                            ? (roomTypeBackendData[name].includes(opt.name) ? 'selected' : '') 
                                            : (roomTypeBackendData[name] === opt.name ? 'selected' : '')}>
                                        ${opt.name}
                                    </option>`
                            )
                            .join("");
    
                        const targetName = options.find(item => item.name === 'Other' ? item : null);

                        const otherFieldsHtml = 
                            `<div class="form-group ${targetName?.target?.slice(1)} ${roomTypeBackendData[name] === 'Other' ? '' : 'd-none'}">
                                <label class="fw-bold">${labelText}</label>
                                <input type="text" name="${name}Other" id="dynamic-input-${name}-${optionName}" data-option="${optionName}" value="${roomTypeBackendData[name + 'Other']}" class="form-control has-icon dynamic-room-input"
                                    data-icon="fa-regular fa-circle-check" required>
                            </div>`;
    
                        const dropdownHtml = `
                            <div class="form-group roomDet" data-option="${optionName}" data-index="${index}" id="room-det-${optionName}-${index}">
                                <label class="fw-bold">${labelText}</label>
                                <select class="grid-picker dynamic-room-select" id="dynamic-select-${name}-${optionName}" name="${name}" data-option="${optionName}" style="justify-content: flex-start;" ${multiple ? 'multiple': ''} required>
                                    <option value="">Select</option>
                                    ${optionsHtml}
                                </select>
                            </div>
                        `;

                        $(`#${option}-fields-container`).append(dropdownHtml);
                        $(`#dynamic-select-${name}-${optionName}`).trigger('change');
                        if (otherFields) {
                            $(`#room-det-${optionName}-${index}`).append(otherFieldsHtml);
                            $(`#dynamic-input-${name}-${optionName}`).trigger('input');
                        }
                    }
    
                initializeNewIcons(option); //Initialize icons for the option
                initializeNewSelectFields(option); // Initialize select fields for the option
                fieldData[`${optionName}`] = {}; // Initialize data for the option
            }
    
            // Remove fields for an unselected option
            function removeFields(option, optionName) {
                $(`[data-option="${option}"]`).remove(); // Remove field group
                $(`[data-room-type="${option}"]`).remove(); // Remove field group
                $(`#${option}-fields-container`).remove(); // Remove field container
                delete fieldData[`${optionName}`]; // Remove data for the option
            }
    
            // Update the hidden field whenever inputs change
            $(document).on('input', '.dynamic-room-input', function () {
                const option = $(this).data('option');
                const name = $(this).attr('name');
                const value = $(this).val();
    
                if (!fieldData[option]) fieldData[option] = {}; // Initialize key
                fieldData[option][name] = value; // Update value
    
                updateHiddenField(); // Update hidden field
            });
    
            $(document).on('change', '.dynamic-room-select', function () {
                const option = $(this).data('option');
                const name = $(this).attr('name');
                const value = $(this).val();
    
                if (!fieldData[option]) fieldData[option] = {}; // Initialize key
                fieldData[option][name] = value; // Update value
    
                updateHiddenField(); // Update hidden field
            });
    
            // Update the hidden field with the current data
            function updateHiddenField() {
                roomTypeData.val(JSON.stringify(fieldData)); // Update hidden field
                console.log('roomTypeDataVal', roomTypeData.val());
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
                var htm = `<label for="${id}" class="input-icon"><i class="${iconClass}"></i></label>`;
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
    
        function initializeNewIcons(option){
            $(`#${option}-fields-container .has-icon`).each(function(i) {
                var cover = `<div class="input-cover ${option}-fields-container-input-cover-${i}"></div>`;
                $(this).before(cover);
                $(this).appendTo(`.${option}-fields-container-input-cover-${i}`);
                var iconClass = $(this).data('icon');
                var id = $(this).attr('id');
                var htm = `<label for="${id}" class="input-icon"><i class="${iconClass}"></i></label>`;
                $(this).before(htm);
            });
        }
    
        function initializeNewSelectFields(option){
            $(`#${option}-fields-container .grid-picker`).each(function(index, elm) {
                console.log('newFields', {elm, option, index});
                var st = $(elm).attr('style');
                var html =
                    `<div class="options-container options-container-${option}-${index}" style="${st}"></div>`;
                $(elm).after(html);
                $(elm).appendTo(`.options-container-${option}-${index}`);
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
                        $(`.options-container-${option}-${index}`).append(htm);
                    }
                });
            })
        }
    
        $(document).on('change', '#has_water_view', function() {
    
            var selectedOptionWater = $(this).val();
            if (selectedOptionWater == 'No') {
                $('#water_show').hide();
                $('#water_extras_show').hide();
            }
            if (selectedOptionWater == 'Yes') {
                $('#water_show').show();
                $('#water_extras_show').show();
            }
    
        });
    
        //water view changing by waqas
        function checkselect(elm) {
            var i = $(elm).data('index');
            var mult = $(elm).parent().children('select').attr('multiple') || false;
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
            var v = $(elm).parent().children('select').val();
            $(elm).parent().children('select').trigger('change');
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
                    }
                }
            });
        }
    </script>
    <script>
        function initializeMap() {
            var inputField = document.getElementsByClassName('search_places');
    
            for (var i = 0; i < inputField.length; i++) {
                var t = inputField[i].dataset.type;
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
    
                inputField[i].addEventListener('keydown', function (e) {
                    if (e.keyCode === 13) { // Check for Enter key
                        if (e.preventDefault) {
                            e.preventDefault();
                        } else {
                            // Handling for older browsers
                            e.cancelBubble = true;
                            e.returnValue = false;
                        }
                    }
                });
    
    
    
                var autocomplete = new google.maps.places.Autocomplete(inputField[i], options);
    
                autocomplete.addListener('place_changed', function(e) {
                    var place = autocomplete.getPlace();
                    if (place) {
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
        
        $(document).on('change', '#leaseTermRes', function() {
            var leaseRes = $('#leaseTermOptions');
            //Disply and hide a div
            ($(this).val().includes('3 Months') || $(this).val().includes('6 Months') || $(this).val().includes(
                    '9 Months') || $(this).val().includes('1 Year') || $(this).val().includes('2 Years') || $(this)
                .val()
                .includes('3-5 Years') || $(this).val().includes('5+ Years') || $(this).val().includes(
                    'Month to Month') || $(
                    this).val().includes('Other')) ? leaseRes.show():
                leaseRes.hide()
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
    
        
        $(document).on('change', '#feeReqOption', function() {
            var feeReqOptDiv = $('#feeReq');
            //Disply and hide a div
            ($(this).val().includes('Required') || $(this).val().includes('Optional')) ? feeReqOptDiv.show():
                feeReqOptDiv.hide()
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
    
        function roomFtn() {
            if ($('#room_typeRes').val() !== '') {
                $('.roomDet').each(function() {
                    $(this).show();
                });
            } else {
                $('.roomDet').each(function() {
                    $(this).hide();
                });
            }
    
        }
    
        $('#has_hoa').change(function(){
            if($(this).val() == 'Yes'){
                $('.HOA_show').removeClass('d-none');
            }else{
                $('.HOA_show').addClass('d-none');
            }
        })
    </script>
    <script>
        function loadGoogleMapsScript() {
            var script = document.createElement('script');
            let googlePlacesApiKey = "{{env('GOOGLE_PLACES_API_KEY')}}";
            script.src = `https://maps.googleapis.com/maps/api/js?key=${googlePlacesApiKey}&libraries=places&callback=initializeMap`;
            script.async = true;
            script.defer = true;
    
            document.body.appendChild(script);
        }
    </script>
@endpush

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

      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>
        <form class="p-4 pt-0 mainform" id="edit-property-auction" action="{{ route('update-seller-property-listing', $auction->id) }}" method="POST" enctype="multipart/form-data">
          @csrf
        </form>
      </div>
    </div>
  </div>
  @php
    $roomDataBackend = json_decode($auction->get->room_details_data, true);
    $unitDataBackend = json_decode($auction->get->unit_type_data, true);
  @endphp
@endsection
@push('scripts')
@include('patch-script', 
    ['moduleName' => 'edit-property-auction', 
    'patchName' => 'edit-property-auction', 
    'id' => $auction->id, 
    'initializeScripts' => 
    [
    'initializeRoomDetailsFields',
    'initializeUnitTypeDetailsFields',
    'initializeFields', 
    'initializeIcons', 
    'initializeVideoPicker',
    'loadGoogleMapsScript', 
    // 'initializeImagePicker'
    ]
    ]);
<script>
  // Video Preview
  async function initializeVideoPicker() {
    // Click button to activate hidden file input
    $(document).on('click','.fileuploader-btn', function() {
      $('.fileuploader').click();
    });

    // Click above calls the open dialog box
    // Once something is selected the change function will run
    $('.fileuploader').change(function() {
      $('#errorDiv').remove();
      if (this.files[0].size > 50000000) {
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
</script>
<script>
  $(document).on('change', '#auction_type', function(){
      let v=$(this).val();
    if (v == "Auction Listing") {
      $('.auction_length').val("");
      $('.auction_length').parent().children('.option-container').removeClass('active');
      $('.traditional-length').hide();
      $('.normal-length').show();
      $('.auction_length_cover').show();
      $('.timeAuction').show();
      $('.traditionalTime').hide();
    } else {
      $('.auction_length').val("");
      $('.auction_length').parent().children('.option-container').removeClass('active');
      $('.traditional-length').show();
      $('.normal-length').hide();
      $('.auction_length_cover').hide();
      $('.timeAuction').hide();
      $('.traditionalTime').show();
    }
  })
</script>
<script>
  function changePropertyStyle(p) {
    if (p == "Vacant Land") {
      $('.property_style_next_hide').addClass('d-none');
      $('.road_frontage_next_hide').addClass('d-none');
      $('.hide_vacant').removeClass('d-none');
      $('.hide_vacant').removeClass('d-none');
      $('.').remove();

    } else if (p == "Busniess Opportunity") {
      $('.business_oportunity_remove').remove();
    }
  }
</script>
<script>
  function changeCurrentUse(p) {
    $('.current_use_next_hide').addClass('d-none');
  }
</script>
<script>
  function changeFrontExposure(p) {
    $('.front_exposure_next_hide').addClass('d-none');
  }
</script>
<script>
  function changeLotFeature(p) {
    $('.lot_feature_next_hide').addClass('d-none');
  }
</script>
<script>
  function change_adjacent_use(p) {
    $('.adjacent_use_next_hide').addClass('d-none');
  }
</script>
<script>
  function changeRoadFrontage(p) {
    $('.road_frontage_next_hide').addClass('d-none');
  }
</script>
<script>
  function changeRoadSurfaceType(p) {
    $('.road_surface_type_next_hide').addClass('d-none');
  }
</script>
<script>
  function Utilities_Water_Sewer(p) {
    $('.utilities_water_sewer_next_hide').addClass('d-none');
  }
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
      $('.vacant_land-length').hide();
      $('.business-length').hide();
      $('.property_style').hide();
      $('.currentUse').hide();
      $('.fireplace').show();
      $('.businessType').hide();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
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
      $('.currentUse').hide();
      $('.property_style').hide();
      $('.businessType').hide();
      $('.fireplace').show();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });

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
      $('.vacant_land-length').hide();
      $('.business-length').hide();
      $('.currentUse').hide();
      $('.property_style').hide();
      $('.businessType').hide();
      $('.fireplace').hide();
      $('.commercialFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.resFields,.incomeFields,.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });

    } else if (p == "Vacant Land") {
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
      $('.property_style').hide();
      $('.currentUse').show();
      $('.businessType').hide();
      $('.fireplace').show();
      $('.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.resFields').each(function() {
        $(this).find('select,img, input ,textarea').prop('disabled', true);
      });

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
      $('.currentUse').hide();
      $('.businessType').show();
      $('.property_style').show();
      $('.fireplace').hide();
      $('.commercialFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.resFields,.incomeFields,.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });
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
      $('.currentUse').hide();
      $('.businessType').hide();
      $('.property_style').hide();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });
      $('.commercialFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });

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
  var property_type = null;
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
      $(document).on('change', '#property_type', function() {
        property_type = $(this).val();
      });


      $('.wizard-step-next').click(function(e) {
        //   console.log(StepWizard.currentStep)

        if (v.form()) {
          if ($('.wizard-step.active').next().is('.wizard-step')) {

            // $('.wizard-step.active').removeClass('active').next().addClass('active');
            $('.wizard-step.active').removeClass('active');
            console.log(StepWizard.currentStep)
            if (StepWizard.currentStep == 7 && property_type == 'Vacant Land') {
              StepWizard.nextStep = 78;
              StepWizard.backStep = 7;
            } 
            else if (StepWizard.currentStep == 7 && (property_type == 'Residential Property' ||
                property_type ==
                'Income Property')) {
              StepWizard.nextStep = 8;
              StepWizard.backStep = 7;
            }
            else if (StepWizard.currentStep == 8 && property_type == 'Income Property') {
              StepWizard.nextStep = 11;
              StepWizard.backStep = 8
            }
            else if (StepWizard.currentStep == 10 && property_type == 'Residential Property') {
              StepWizard.nextStep = 12;
              StepWizard.backStep = 10
            } else if (StepWizard.currentStep == 8 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 44;
              StepWizard.backStep = 8;
            } else if(StepWizard.currentStep == 28 && property_type == 'Income Property'){
              StepWizard.nextStep = 30;
              StepWizard.backStep = 28;
            }
            else if (StepWizard.currentStep == 44 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')
            ) {
              StepWizard.nextStep = 46;
              StepWizard.backStep = 44;
            } 
            else if (StepWizard.currentStep == 47 &&  property_type == 'Business Opportunity' ) {
              StepWizard.nextStep = 49;
              StepWizard.backStep = 47;
            }else if (StepWizard.currentStep == 47 &&  property_type == 'Commercial Opportunity' ) {
              StepWizard.nextStep = 50;
              StepWizard.backStep = 47;
            }
            else if (StepWizard.currentStep == 63 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 65;
              StepWizard.backStep = 63;
            }
            else if (StepWizard.currentStep == 68 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 70;
              StepWizard.backStep = 68;
            }
            else if (StepWizard.currentStep == 43 && (property_type == 'Residential Property' || property_type == 'Income Property')) {
              StepWizard.nextStep = 94;
              StepWizard.backStep = 43;
            }
            else if (StepWizard.currentStep == 77 && (property_type == 'Commercial Property' || property_type == 'Business Opportunity')) {
              StepWizard.nextStep = 94;
              StepWizard.backStep = 77;
            }
            else {
              StepWizard.backStep = StepWizard.currentStep;
            }
            $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
            StepWizard.setStep();
            if (StepWizard.currentStep == 94) {
              if (property_type == 'Commercial Property' || property_type == 'Income Property') {
                $('.ai-faq-commercial-income-section').removeClass('d-none');
                $('.ai-faq-business-section').addClass('d-none');
                $('.ai-faq-vacant-section').addClass('d-none');
              } else if (property_type == 'Business Opportunity') {
                $('.ai-faq-commercial-income-section').addClass('d-none');
                $('.ai-faq-business-section').removeClass('d-none');
                $('.ai-faq-vacant-section').addClass('d-none');
              } else if (property_type == 'Vacant Land') {
                $('.ai-faq-commercial-income-section').addClass('d-none');
                $('.ai-faq-business-section').addClass('d-none');
                $('.ai-faq-vacant-section').removeClass('d-none');
              } else {
                $('.ai-faq-commercial-income-section').addClass('d-none');
                $('.ai-faq-business-section').addClass('d-none');
                $('.ai-faq-vacant-section').addClass('d-none');
              }
            }
          }
        }
      });


      $('.wizard-step-back').click(function(e) {
        if ($('.wizard-step.active').prev().is('.wizard-step')) {

          $('.wizard-step.active').removeClass('active');
          $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
          StepWizard.setStep();
          console.log(StepWizard.currentStep)
          
          if (StepWizard.currentStep == 11 && property_type == 'Income Property') {
              StepWizard.backStep = 8
              StepWizard.nextStep = 11;
            }else if (StepWizard.currentStep == 12 && property_type == 'Residential Property') {
              StepWizard.backStep = 10
              StepWizard.nextStep = 12;
            }else if (StepWizard.currentStep == 30 && property_type == 'Income Property') {
              StepWizard.backStep = 28
              StepWizard.nextStep = 30;
            }else if (StepWizard.currentStep == 44 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 8;
              StepWizard.nextStep = 44;
            }else if (StepWizard.currentStep == 46 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 44;
              StepWizard.nextStep = 46;
            }else if (StepWizard.currentStep == 49 && property_type == 'Business Opportunity'){
              StepWizard.backStep = 47;
              StepWizard.nextStep = 49;
            }else if (StepWizard.currentStep == 50 &&  property_type == 'Commercial Property'){
              StepWizard.backStep = 47;
              StepWizard.nextStep = 50;
            }else if (StepWizard.currentStep == 65 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 63;
              StepWizard.nextStep = 65;
            }else if (StepWizard.currentStep == 70 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 68;
              StepWizard.nextStep = 70;
            } else if (StepWizard.currentStep == 8 && (property_type == 'Residential Property' || property_type == 'Income Property')) {
              StepWizard.backStep = 7;
            } else if (StepWizard.currentStep == 78 && property_type == 'Vacant Land') {
              StepWizard.backStep = 7;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;
            }
        }
      });

      // Assuming the code provided is within a function or a document.ready block

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
      var comp = 0;

      if (property_type === 'Residential Property' || property_type === 'Income Property') {
        if (StepWizard.currentStep >= 7 && StepWizard.currentStep <= 41) {
          comp = 20 + (((StepWizard.currentStep - 7) / (41 - 7)) * 80);
        }
      }
      else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        // Calculate progress for commercial and business opportunity steps (42 to 76)
        comp = 20 + (((StepWizard.currentStep - 7) / (75 - 7)) * 80);
        if (StepWizard.currentStep == 21) {
          comp = 20 + (((StepWizard.currentStep - 21) / (55 - 21)) * 80);
        }
      }
      else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        if (StepWizard.currentStep >= 1 && StepWizard.currentStep <= 8) {
            // Steps 1 to 8
            comp = 20 + ((StepWizard.currentStep - 1) / 7) * 20; // 20% for this range
        } else if (StepWizard.currentStep >= 9 && StepWizard.currentStep <= 41) {
            // Jump from step 8 to 42, so 8 is 20%, and 42 is 100% (80% for steps 9 to 41)
            comp = 20 + (80 * (StepWizard.currentStep - 9) / 33); // Steps 9 to 41 contribute to the middle range
        } else if (StepWizard.currentStep >= 42 && StepWizard.currentStep <= 61) {
            // Steps 42 to 61
            comp = 100 * ((StepWizard.currentStep - 42) / 19) + 20; // 80% for this range (20% already added)
        } else if (StepWizard.currentStep >= 62 && StepWizard.currentStep <= 66) {
            // Steps 62 to 66
            comp = 100 * ((StepWizard.currentStep - 62) / 4) + 100; // 100% (20% + 80% + 20% of the last range)
        } else if (StepWizard.currentStep >= 67 && StepWizard.currentStep <= 75) {
            // Steps 67 to 75
            comp = 100 * ((StepWizard.currentStep - 67) / 8) + 120; // 20% for last range
        }else if (StepWizard.currentStep === 75) {
            // Step 75 is the final step
            comp = 100; // Final step, complete
        }
      }
      
      else if (property_type === 'Vacant Land') {
        // Calculate progress for vacant land steps (77 to 91)
        comp = 20 + (((StepWizard.currentStep - 76) / (91 - 76)) * 80);
      } else {

        // Default progress calculation for other property types (steps 1 to 8)
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

  $('#property_picture').click(function() {
    $('.wizard-step-next').hide();
    $('.wizard-step-finish').show();
  });
  $('#property_picture1').click(function() {
    $('.wizard-step-next').hide();
    $('.wizard-step-finish').show();
  });

  $('#unit_type').change(function(){
    $('.unit-info').removeClass('d-none');
  })
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

    $(document).on('change', '#has_furnishing', function(){
      let w=$(this).val();
      if (w == "Yes" || w == "Optional") {
        $('#has_furnishing_residential_and_income').show();
      } else {
        $('#has_furnishing_residential_and_income').hide();
      }
    })
    $(document).on('change', '#otherStucture', function(){
      let w=$(this).val();
      if (w == "Additional Single Family Home" || w == "In-Law- Suite") {
        $('#otherSturctureUnit').show();
      } else {
        $('#otherSturctureUnit').hide();
      }
    })
</script>
<script>
    function initializeUnitTypeDetailsFields(){
        const unitTypeSelect = $('#unit_type_1');
        const fieldsContainer = $('#dynamicFieldsContainer');
        const unitTypeData = $('#unit_type_input');
        const unitDetails = @json($unitDataBackend);
        let unitDetailsData;
        if (Array.isArray(unitDetails) && unitDetails.length === 1 && unitDetails[0] === null || !unitDetails){
          unitDetailsData = null;
        }else{
          unitDetailsData = JSON.parse(unitDetails);
        };
        console.log('unitDetailsData',unitDetailsData);

        const fieldData = {}; // object to store field data

        function sanitizeId(optionName) {
            return optionName
                .toLowerCase() // Optional: make it lowercase for consistency
                .replace(/[^a-z0-9]/g, '-'); // Replace invalid characters with underscores
        }

        // Handle changes in the select element
        $(unitTypeSelect).change(function () {
            const selectedOptions = $(this).val(); // Get selected options

            // Add fields for new options
            selectedOptions.forEach(option => { 
              const unitTypeBackendData = unitDetailsData ? unitDetailsData[option] : null;
              let sanitizedOpt = sanitizeId(option);
              if (!fieldData[option]) {
                  createFields(sanitizedOpt, option, unitTypeBackendData);
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
        function createFields(option, optionName, unitTypeBackendData) {

                $(fieldsContainer).append(`<div id="${option}-fields-container"></div`)

                const unitDimensionHtml = `
                    <hr data-room-type="${option}">
                    <h5 data-room-type="${option}">Unit Type: ${optionName}</h5>

                    <div class="form-group" data-option="${optionName}>
                      <label class="fw-bold">Beds/Unit:</label>
                      <input type="number" name="beds_unit" value="${unitTypeBackendData ? unitTypeBackendData[beds_unit] : ''}" data-option="${optionName}" id="dynamic-room-input-beds_unit-${option}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-hotel">
                    </div>

                    <div class="form-group" data-option="${optionName}>
                      <label class="fw-bold">Baths/Unit:</label>
                      <input type="number" name="baths_unit" value="${unitTypeBackendData ? unitTypeBackendData[baths_unit] : ''}" id="dynamic-room-input-baths_unit-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-hotel">
                    </div>

                    <div class="form-group" data-option="${optionName}>
                      <label class="fw-bold">Sqft Heated:</label>
                      <input type="number" name="sqt_ft_heated" value="${unitTypeBackendData ? unitTypeBackendData[sqt_ft_heated] : ''}" id="dynamic-room-input-sqt_ft_heated-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-ruler-combined">
                    </div>

                    <div class="form-group" data-option="${optionName}>
                      <label class="fw-bold">Number of Units:</label>
                      <input type="number" name="number_of_units" value="${unitTypeBackendData ? unitTypeBackendData[number_of_units] : ''}" id="dynamic-room-input-number_of_units-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-hotel">
                    </div>
                `;

                $(`#${option}-fields-container`).append(unitDimensionHtml);
                const inputSelectors = [
                  `#dynamic-room-input-beds_unit-${option}`,
                  `#dynamic-room-input-baths_unit-${option}`,
                  `#dynamic-room-input-sqt_ft_heated-${option}`,
                  `#dynamic-room-input-number_of_units-${option}`
                ];

                inputSelectors.forEach(selector => {
                  $(selector).trigger('input');
                });

                // Add Room Levels dropdown
                const unitsOccupied = [
                    { name: "Yes", target: `.custom_occupied-${option}`, icon: 'fa-regular fa-circle-check' },
                    { name: "No", target: `.custom_occupied_rent-${option}`, icon: 'fa-regular fa-circle-xmark' },
                ];
                appendDropdown("Are any units occupied?", "occupied", unitsOccupied, 1, false, false);

                // Function to append dropdowns dynamically
                function appendDropdown(labelText, name, options, index, multiple = false, otherFields = false) {
                    let optionsHtml = options
                        .map(
                            (opt) =>
                                `<option value="${opt.name}" data-target="${opt.target}" data-icon="<i class='${opt.icon}'></i>" 
                                    style="width:calc(33.3% - 10px);" class="card flex-row" 
                                    ${unitTypeBackendData ? (Array.isArray(unitTypeBackendData[name]) 
                                      ? (unitTypeBackendData[name].includes(opt.name) ? 'selected' : '') 
                                      : (unitTypeBackendData[name] === opt.name ? 'selected' : '')) : ''}>
                                    ${opt.name}
                                </option>`
                        )
                        .join("");

                    const dropdownHtml = `
                        <div class="form-group data-option="${optionName}" data-index="${index}">
                            <label class="fw-bold">${labelText}</label>
                            <select class="grid-picker dynamic-room-select" id="dynamic-select-${name}-${optionName}" name="${name}" data-option="${optionName}" style="justify-content: flex-start;" ${multiple ? 'multiple': ''}>
                                <option value="">Select</option>
                                ${optionsHtml}
                            </select>

                            <div class="form-group d-none custom_occupied-${option} data-option="${optionName}"">
                              <label class="fw-bold">Number of Occupied Units:  </label>
                              <input type="number" name="custom_occupied" value="${unitTypeBackendData ? unitTypeBackendData[custom_occupied] : ''}" id="dynamic-room-input-custom_occupied-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-hotel">
                            </div>
                            <div class="form-group d-none custom_occupied-${option}" data-option="${optionName}">
                              <label class="fw-bold">Current Rent</label>
                              <input type="number" name="current_rent" value="${unitTypeBackendData ? unitTypeBackendData[current_rent] : ''}" id="dynamic-room-input-current_rent-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-dollar-sign">
                            </div>
                            <div class="form-group custom_occupied_rent-${option} d-none" data-option="${optionName}">
                              <label class="fw-bold">Expected Rent</label>
                              <input type="number" name="expected_rent" value="${unitTypeBackendData ? unitTypeBackendData[expected_rent] : ''}" id="dynamic-room-input-expected_rent-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-dollar-sign">
                            </div>
                        </div>

                        <div class="form-group" data-option="${optionName}">
                          <label class="fw-bold">Garage Spaces:</label>
                          <input type="number" name="garage_spaces_unit" value="${unitTypeBackendData ? unitTypeBackendData[garage_spaces_unit] : ''}" id="dynamic-room-input-garage_spaces_unit-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-warehouse">
                        </div>

                        <div class="form-group" data-option="${optionName}">
                          <label class="fw-bold">Carport Spaces:</label>
                          <input type="number" name="carport_spaces_unit" value="${unitTypeBackendData ? unitTypeBackendData[carport_spaces_unit] : ''}" id="dynamic-room-input-carport_spaces_unit-${option}" data-option="${optionName}" class="form-control has-icon dynamic-room-input" data-icon="fa-solid fa-warehouse">
                        </div>

                        <div class="form-group col-md-12" data-option="${optionName}">
                          <label class="fw-bold">Unit Type Description:</label>
                          <textarea name="unit_type_of_description" value="${unitTypeBackendData ? unitTypeBackendData[unit_type_of_description] : ''}" id="dynamic-room-input-unit_type_of_description-${option}" data-option="${optionName}" class="form-control dynamic-room-input" cols="30" rows="10"></textarea>
                        </div>
                    `;

                    $(`#${option}-fields-container`).append(dropdownHtml);
                    const inputSelectorsTwo = [
                      `#dynamic-room-input-custom_occupied-${option}`,
                      `#dynamic-room-input-current_rent-${option}`,
                      `#dynamic-room-input-expected_rent-${option}`,
                      `#dynamic-room-input-garage_spaces_unit-${option}`,
                      `#dynamic-room-input-carport_spaces_unit-${option}`,
                      `#dynamic-room-input-unit_type_of_description-${option}`
                    ]
                    inputSelectorsTwo.forEach(selector => {
                      $(selector).trigger('input');
                    })

                    $(`#dynamic-select-${name}-${optionName}`).trigger('change');
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
            unitTypeData.val(JSON.stringify(fieldData)); // Update hidden field
            console.log('unitTypeDataVal', unitTypeData.val());
        }

    };
</script>
<script>
    function initializeRoomDetailsFields(){
        const roomTypeSelect = $('#room_type');
        const fieldsContainer = $('#dynamicFieldsContainerRoomType');
        const roomTypeData = $('#room_type_input');
        const roomDetails = @json($roomDataBackend);
        let roomDetailsData;
        if (Array.isArray(roomDetails) && roomDetails.length === 1 && roomDetails[0] === null || !roomDetails){
          roomDetailsData = null;
        }else{
          roomDetailsData = JSON.parse(roomDetails);
        };
        console.log('roomData', roomDetailsData);

        const fieldData = {}; // object to store field data

        function sanitizeId(optionName) {
            return optionName
                .toLowerCase() // Optional: make it lowercase for consistency
                .replace(/[^a-z0-9]/g, '-'); // Replace invalid characters with underscores
        }

        // Handle changes in the select element
        $(roomTypeSelect).change(function () {
            const selectedOptions = $(this).val(); // Get selected options
            if(!selectedOptions) return;
            // Add fields for new options
            selectedOptions.forEach(option => { 
              const roomTypeBackendData = roomDetailsData ? roomDetailsData[option] : null;
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
                        <label class="fw-bold">Approximate Room Dimensions:</label>
                        <input type="text" name="approximate_room_dimensions" id="dynamic-input-roomDimensions-${optionName}" data-option="${optionName}" value="${roomDetailsData ? roomTypeBackendData['approximate_room_dimensions'] : ''}" data-icon="fa-solid fa-ruler-combined"  class="form-control dynamic-room-input form-control has-icon" required>
                    </div>
                `;

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
                    { name: "Other", target: `.floor_covering_other-${option}` },
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
                    { name: "Other", target: `.roomFeatureOther-${option}` },
                ];
                appendDropdown("Room Features:", "room_feature", roomFeatures, 4, 'fa-regular fa-circle-check', false, true);

                // Function to append dropdowns dynamically
                function appendDropdown(labelText, name, options, index, icon, multiple = false, otherFields = false) {
                    let optionsHtml = options
                        .map(
                            (opt) =>
                                `<option value="${opt.name}" data-target="${opt.target}" data-icon="<i class='${icon}'></i>" 
                                    style="width:calc(33.3% - 10px);" class="card flex-row" style="width:calc(33.3% - 10px);"
                                    ${roomDetailsData ? (Array.isArray(roomTypeBackendData[name]) 
                                      ? (roomTypeBackendData[name].includes(opt.name) ? 'selected' : '') 
                                      : (roomTypeBackendData[name] === opt.name ? 'selected' : '')) : ''}>
                                    ${opt.name}
                                </option>`
                        )
                        .join("");

                    const targetName = options.find(item => item.name === 'Other' ? item : null);

                    const dropdownHtml = `
                        <div class="form-group roomDet" data-option="${optionName}" data-index="${index}">
                            <label class="fw-bold">${labelText}</label>
                            <select class="grid-picker dynamic-room-select" id="dynamic-select-${name}-${optionName}" name="${name}" data-option="${optionName}" style="justify-content: flex-start;" ${multiple ? 'multiple': ''} required>
                                <option value="">Select</option>
                                ${optionsHtml}
                            </select>
                            ${otherFields ? 
                            `<div class="form-group ${targetName?.target?.slice(1)}-${option} ${roomDetailsData ? (roomTypeBackendData[name] === 'Other' ? '' : 'd-none') : ''}">
                                <label class="fw-bold">${labelText}</label>
                                <input type="text" name="${name}Other" data-option="${optionName}" value="${roomDetailsData ? roomTypeBackendData[name + 'Other'] : ''}" class="form-control has-icon dynamic-room-input" id="dynamic-input-${name}-${optionName}"
                                    data-icon="fa-regular fa-circle-check" required>
                            </div>` : ''
                            }
                        </div>
                    `;

                    $(`#${option}-fields-container`).append(dropdownHtml);
                    $(`#dynamic-select-${name}-${optionName}`).trigger('change');
                    $(`#dynamic-input-${name}-${optionName}`).trigger('input');
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
    function initializeNewIcons(option){
      $(`#${option}-fields-container .has-icon`).each(function(i) {
          var cover = `<div class="input-cover input-cover-${i}-${option}"></div>`;
          $(this).before(cover);
          $(this).appendTo(`.input-cover-${i}-${option}`);
          var iconClass = $(this).data('icon');
          var id = $(this).attr('id');
          var htm = `<label for="${id}" class="input-icon"><i class="${iconClass}"></i></label>`;
          $(this).before(htm);
        }
      );
    }

    function initializeNewSelectFields(option){
      $(`#${option}-fields-container .grid-picker`).each(function(index, elm) {
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

    // $('select').trigger('change');
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

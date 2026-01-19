<!DOCTYPE html>
<html lang="en">

<head>
      <meta charset="UTF-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ auth()->id() }}">
    @endauth
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ isset($title) ? $title . ' - ' . get_setting('title') : get_setting('title') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-5.2.2/css/bootstrap.min.css') }}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/global.css') }}" />
    <!-- loader css -->
    <link rel="stylesheet" href="{{ asset('assets/css/loader.css') }}" />
    <!-- //Author css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/myAccountGlobal.css') }}" />
    <!-- Buyer Make Offer add file for card  -->
    <link rel="stylesheet" href="{{ asset('assets/css/buyerMakeOffer.css') }}" />
    <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="{{ asset(get_setting('favicon')) }}" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-1.13.2/datatables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">


    <!-- Load compiled CSS & JS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>

    {{-- Bootstrap JS is loaded in the body at line 229, not here to avoid duplicate loading --}}






    @livewireStyles

    <style>

        .service-cards {
            border: 1px solid #e0e0e0;
        }

        .service-card {
            border: 1px solid #e0e0e0;
            border-left: 10px solid #e0e0e0;
            padding: 10px;
            display: flex;
            margin: 10px;
            align-items: center;
            justify-content: flex-start;
            cursor: pointer;
            border-radius: 4px;
        }

        .service-card.active,
        .service-card:hover {
            /* background-color: #006e9f; */
            /* color: #006e9f; */
            border-color: #006e9f;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.5);
        }

        .service-card.active .icon {
            color: #006e9f;
        }

        .service-card .icon {
            margin-right: 20px;
            font-size: 24px;
            color: #CCC;
        }
        .qr-code svg{
            width:100% !important;
            height: 100% !important;
        }

        .select-btn {
            padding: 1px 10px;
            border: 1px solid rgba(0, 0, 0, 0.175);
            border-radius: 5px;
            color: #11b7cf;
            background-color: transparent;
            margin-bottom: 5px;
        }

        .select-btn.active {
            border-color: #11b7cf;
        }

        .loader .loader-content .loader-img{
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        .loader .loader-content .loader-img img{
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        #ajax_loading_screen{
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 99999;
            width: 100%;
            height: 100%;
            background-color: #9c9c9c75;
        }

        .ajax_loader-img{
            object-fit: contain;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .ajax_loader-img img{
            width: 200px;
            height: 200px;
        }

        .index-layer{
            z-index:100 !important;
        }

    </style>
    @stack('styles')
    <style>
        .error {
            color: red;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>
    <!-- Header  -->
    @include('layouts.partials.header')
    <!-- End  -->
    <!-- BEGIN LOADER -->
    <div id="load_screen">
        <div class="loader">
            <div class="loader-content">
                <div class="loader-img align-self-center">
                    <img src="{{ asset(get_setting('logo')) }}" alt="" />
                </div>
            </div>
        </div>
    </div>
    <!--  END LOADER -->

    <div id="ajax_loading_screen">
        <div class="ajax_loader-img align-self-center">
            <img src="{{ asset('images/Spinner.gif') }}" alt="" />
        </div>
    </div>


    @yield('content')



    <!-- Footer  -->
    <footer class="footer">
        <a href="{{ route('home') }}"><img src="{{ asset('assets/pictures/footerLogo.png') }}" alt="footerLogo" /></a>
        <div class="footerNav">
            <a href="#">About Us</a>
            <a href="{{ route('sellerWorks') }}">How it works Sellers</a>
            <a href="#">Contact Us</a>
            <a href="{{ route('faqs') }}">Faq's</a>
        </div>
        <div class="social">
            <a href=""><i class="fa-brands fa-facebook"></i></a>
            <a href=""><i class="fa-brands fa-twitter"></i></a>
            <a href=""><i class="fa-brands fa-instagram"></i></a>
            <a href=""><i class="fa-brands fa-youtube"></i></a>
        </div>
        <p class="text-light p-4">{{ get_setting('footer_text') }}</p>
    </footer>
    <div class="nav-footer-nav position-fixed bottom-0 container index-layer">
        <ul class=" d-flex align-items-center justify-content-between ps-0">
            <li>
                <a href="{{ route('sellerWorks') }}"> <i class="fa fa-home"></i> <span>Seller</span></a>
            </li>

            <li>
                <a href="{{ route('sellerWorksAgent') }}"> <i class="fa fa-home"></i><span> Seller’s Agent</span></a>
            </li>

            <li>
                <a href="{{ route('add-listing') }}" class="add-listing"><i class="fa fa-plus text-white"></i> </a>
            </li>

            <li>
                <a href="{{ route('buyerWorks') }}"> <i class="fa fa-home"></i><span>Buyer</span></a>
            </li>

            <li>
                <a href="{{ route('buyerWorksAgent') }}"> <i class="fa fa-home"></i><span>Buyer’s Agent</span></a>
            </li>
        </ul>
    </div>

    @livewireScripts
    {{-- <script src="https://kit.fontawesome.com/d7dd5c0801.js" crossorigin="anonymous"></script> --}}
    <script src="{{ asset('assets/js/jquery-3.6.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/loader.js') }}"></script>
    {{-- <script src="{{ asset('assets/bootstrap-5.2.2/js/bootstrap.bundle.min.js') }}"></script> --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- PopOver Script  -->
    <script>
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        const popoverList = [...popoverTriggerList].map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.2/datatables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>

    <script>
        function showAjaxLoader(show){
            if(show){
                console.log('true');
                $('#ajax_loading_screen').css('display', 'block');
                $('body').css({'overflow': 'hidden', 'pointer-events': 'none'});
            }else{
                console.log('false');
                $('#ajax_loading_screen').css('display', 'none');
                $('body').css({'overflow': 'auto', 'pointer-events': 'unset'});
            }
        }
        $(function() {
            $('.service-option').each(function(i, element) {
                $(element).after($(element).val());
                var v = $(element).val();
                if ($(element).is(':checked')) {
                    $(element).parent().addClass('active');
                    if (v == 'Other') {
                        $('.other-service').show();
                        $('.other-service-textarea').focus();
                    }
                } else {
                    $(element).parent().removeClass('active');
                    if (v == 'Other') {
                        $('.other-service').hide();
                        $('.other-service-textarea').val('');
                    }
                }
            });

            $('.service-option').change(function() {
                var v = $(this).val();
                if ($(this).is(':checked')) {
                    $(this).parent().addClass('active');
                    if (v == 'Other') {
                        $('.other-service').show();
                        $('.other-service-textarea').focus();
                    }
                } else {
                    $(this).parent().removeClass('active');
                    if (v == 'Other') {
                        $('.other-service').hide();
                        $('.other-service-textarea').val('');
                    }
                }
            });
        });
    </script>
    <script>
        $(document).ready(function () {
            $(function () {
                $('.data-table').dataTable({
                    //
                });
            });

            $(document).on('click', '.select-btn', function() {
                $(this).closest('.icon-select-btn-div').find('.select-btn').removeClass('active');
                $(this).addClass('active');
                let type = $(this).data('type');
                let elem = $(this).closest('.form-group').find('.input-icon').children();
                let inputElem  = $(this).closest('.form-group').find('input');
                console.log('elemVal', inputElem.val())
                if (type == 'percent') {
                    elem.removeClass("fa-solid fa-dollar-sign");
                    elem.addClass("fa-solid fa-percent");
                    elem.attr('data-symbol', 'percent');
                    if(inputElem.val() !== ''){
                        const firstCharacter = inputElem.val().charAt(0);
                        if(firstCharacter == '$' || firstCharacter == '%'){
                            let inputVal = inputElem.val().substring(1);
                            inputElem.val(inputVal + '' + '%');
                        }else{
                            let inputVal = inputElem.val();
                            inputElem.val(inputVal + '' + '%');
                        }
                    }else{
                        inputElem.val('%');
                    }
                } else {
                    elem.removeClass("fa-solid fa-percent");
                    elem.addClass("fa-solid fa-dollar-sign");
                    elem.attr('data-symbol', 'amount');
                    if(inputElem.val() !== ''){
                        const firstCharacter = inputElem.val().charAt(inputElem.val().length - 1);
                        if(firstCharacter == '$' || firstCharacter == '%'){
                            let inputVal = inputElem.val().slice(0, -1);;
                            inputElem.val('$' + '' + inputVal);
                        }else{
                            let inputVal = inputElem.val();
                            inputElem.val('$' + '' + inputVal);
                        }
                    }else{
                        inputElem.val('$');
                    }
                }
            })

            //remove disabled attr, trigger click
            $(document).on('click', '.image-input-label', function(event) {
                //event.preventDefault(); // Prevent default label behavior

                const fileInput = $(this).find('.image-input');
                fileInput.prop('disabled', false);
                //fileInput.click(); // Directly trigger if it's already enabled
            });



            // $('.image-input').on('click', function () {
            //     $(this).prop('disabled', false);
            // })


           /*******************Functions to sort and submit the reordered photos*************************/
           const MAX_FILES = 5;
           // Handle file selection and display thumbnails for each input
            $(document).on('change', '.image-input', function (event) {
                const box = $(this).closest('.box');
                const filesArray = Array.from(event.target.files);

                if (filesArray.length > MAX_FILES) {
                    alert(`You can only select up to ${MAX_FILES} images.`);
                    $(this).val(''); // Clear the input if the limit is exceeded
                    $(box).find('.thumbnails-container').empty();
                    return;
                }

                box.data('filesArray', filesArray);  // Store filesArray in the .box element

                displayThumbnails(filesArray, box);
                updateHiddenInputs(box);  // Initial load of hidden inputs

                // Disable the original input to avoid duplicate submission
                // $(this).prop('disabled', true);
            });

            // Function to display thumbnails
            function displayThumbnails(files, box) {
                const container = box.find('.thumbnails-container');
                container.empty();

                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = $('<img>')
                            .attr('src', e.target.result)
                            .attr('draggable', false)
                            .attr('index', index)
                            .attr('name',  file.name);
                        container.append(img);
                    };
                    reader.readAsDataURL(file);
                });

                initSortable(container, box);
            }

            // Initialize SortableJS for drag-and-drop reordering
            function initSortable(container, box) {
                const filesArray = box.data('filesArray');
                new Sortable(container[0], {
                    animation: 150,
                    onEnd: function () {
                        const reorderedImages = [];
                        container.find('img').each(function (i, img) {
                            $(img).attr('index',  i);

                            // Find the corresponding file from filesArray
                            const file = filesArray.find(file => file.name === $(img).attr('name'));

                            // Add the file to the reordered array if it exists
                            if (file) {
                                reorderedImages.push(file);
                            }
                        });
                        // console.log('reorderedImages', reorderedImages);
                        box.data('filesArray', reorderedImages);  // Update stored filesArray
                        updateHiddenInputs(box);  // Update hidden inputs on every reorder
                    }
                });
            }

           // Function to update hidden inputs based on reordered files
            function updateHiddenInputs(box) {
                const container = box.find('.hidden-inputs-container');
                container.empty();

                const filesArray = box.data('filesArray');
                filesArray.forEach((file, index) => {
                    const fileInput = $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', `photoNames[${index}]`)
                        .val(file.name);

                    // Use DataTransfer to attach the file for submission
                    // const dataTransfer = new DataTransfer();
                    // dataTransfer.items.add(file);
                    // fileInput[0].files = dataTransfer.files;

                    container.append(fileInput);
                });
            }

            //select default video type
            $('.video_type_check[name="video_upload"]').trigger('click');

            //check only clicked checkbox and uncheck others
            $(document).on('click', '.video_type_check', function(){
                $('.video_type_check').prop('checked', false);
                $(this).prop('checked', true);

                displayVideoTypeElement($(this));
            })

            //display the appropriate video type element
            function displayVideoTypeElement(check){
                var videoType = check.attr('name');
                let parent = $(check).closest('.video_div');
                $(parent).find('.video_type').val(videoType);
                console.log('videoType', videoType);
                if(videoType === 'video_upload'){
                    $(parent).find('.video-type-element').addClass('d-none');
                    $(parent).find('.video-upload').removeClass('d-none');
                }else if(videoType === 'youtube_video'){
                    $(parent).find('.video-type-element').addClass('d-none');
                    $(parent).find('.youtube-video').removeClass('d-none');
                }else if(videoType === 'vimeo_video'){
                    $(parent).find('.video-type-element').addClass('d-none');
                    $(parent).find('.vimeo-video').removeClass('d-none');
                }
            }

            //limit the documents selected by disclosure element
            $(document).on('change', '.documents-input', function(event){
                const filesArray = Array.from(event.target.files);
                if (filesArray.length > 5) {
                    alert(`You can only select up to ${MAX_FILES} images.`);
                    $(this).val(''); // Clear the input if the limit is exceeded
                    return;
                }
            })
        })
    </script>





    {{-- Global force-redirect listener for Livewire form submissions --}}
    <script>
        window.addEventListener('force-redirect', function(event) {
            var url = event.detail.url;
            console.log('Forcing redirect to:', url);
            window.location.href = url;
        });
    </script>

    <style>
        /* Input icon styling for has-icon elements */
        .input-cover {
            position: relative;
        }
        .input-icon-fa {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #049399;
            z-index: 4;
            pointer-events: none;
        }
        .input-cover .has-icon {
            padding-left: 38px;
        }
        /* Handle Select2 multi-selects */
        .input-cover .select2-container .select2-selection--multiple {
            padding-left: 38px;
        }
    </style>

    @stack('scripts')


</body>

</html>

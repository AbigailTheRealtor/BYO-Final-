<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? get_setting('title') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-5.2.2/css/bootstrap.min.css') }}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/global.css') }}" />
    @stack('styles')
    <!-- loader css -->
    <link rel="stylesheet" href="{{ asset('assets/css/loader.css') }}" />
    <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="{{ asset(get_setting('favicon')) }}" type="image/x-icon">
</head>
<style>
    .notification-btn {
  background: none;
  border: none;
  cursor: pointer;
}

.notification-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  z-index: 1;
  display: none;
  background-color: #f9f9f9;
  min-width: 200px;
  box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
  padding: 10px;
}

.notification-dropdown a {
  display: block;
  padding: 10px;
  text-decoration: none;
  color: #333;
}

.notification-dropdown a:hover {
  background-color: #f1f1f1;
}

.notification-dropdown.show {
  display: block;
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


</style>

<body>
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
    <!-- Header  -->
    @include('layouts.partials.header')
    <!-- End  -->




    <div class="bannerImg">
        <!-- //Background Overly -->
        <div class="bannerOverly"></div>
        <div class="mainContent position-relative">
            <div class="content">




                @yield('content')






                <!-- Footer  -->
                <footer class="footer">
                    <a href="{{ route('home') }}"><img src="{{ asset('assets/pictures/footerLogo.png') }}"
                            alt="footerLogo" /></a>
                    <div class="footerNav">
                        <a href="#">About Us</a>
                        <a href="{{ route('sellerWorks') }}">How it works Sellers</a>
                        <a href="#">Contact Us</a>
                        <a href="{{ route('faqs') }}">Faq's</a>
                    </div>
                    <div class="social">
                        <button class="notification-btn" onclick="toggleDropdown()">
                            <i class="fas fa-bell"></i>
                        </button>
                        <a href=""><i class="fa-brands fa-facebook"></i></a>
                        <a href=""><i class="fa-brands fa-twitter"></i></a>
                        <a href=""><i class="fa-brands fa-instagram"></i></a>
                        <a href=""><i class="fa-brands fa-youtube"></i></a>
                    </div>
                    <p class="text-light p-4">{{ get_setting('footer_text') }}</p>
                </footer>
            </div>
        </div>
    </div>
    </div>
    <div class="nav-footer-nav position-fixed bottom-0 container">
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

    <script src="https://kit.fontawesome.com/d7dd5c0801.js" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/bootstrap-5.2.2/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/loader.js') }}"></script>


    @stack('scripts')


</body>

</html>

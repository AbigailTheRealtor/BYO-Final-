<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="viho admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities.">
    <meta name="keywords"
        content="admin template, viho admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="pixelstrap">
    <link rel="icon" href="{{ asset(get_setting('favicon')) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset(get_setting('favicon')) }}" type="image/x-icon">
    <title>{{ $title ?? '' }} - {{get_setting('title')}}</title>
    <!-- Google font-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap"
        rel="stylesheet">
    <!-- Font Awesome-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/fontawesome.css') }}">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/icofont.css') }}">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/themify.css') }}">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/flag-icon.css') }}">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/feather-icon.css') }}">
    <!-- Plugins css start-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/prism.css') }}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/datatables.css')}}">
    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/bootstrap.css') }}">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/style.css') }}">
    <link id="color" rel="stylesheet" href="{{ asset('assets/admin/css/color-1.css') }}" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/responsive.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <style>
        .img-fluid1 {
            max-width: 100%;
            height: 50px;
            object-fit: contain;
        }
    </style>
    @stack('styles')
</head>

<body>
    <!-- Loader starts-->
    <div class="loader-wrapper">
        <div class="theme-loader">
            <div class="loader-p"></div>
        </div>
    </div>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper" id="pageWrapper">
        <!-- Page Header Start-->
        <div class="page-main-header">
            <div class="main-header-right row m-0">
                <div class="main-header-left">
                    <div class="logo-wrapper"><a href="{{ route('admin.dashboard') }}"><img class="img-fluid1"
                                src="{{ asset(get_setting('logo')) }}" alt=""></a></div>
                    <div class="dark-logo-wrapper"><a href="{{ route('admin.dashboard') }}"><img class="img-fluid1"
                                src="{{ asset(get_setting('logo')) }}" alt=""></a></div>
                    <div class="toggle-sidebar"><i class="status_toggle middle" data-feather="align-center"
                            id="sidebar-toggle"></i></div>
                </div>
                <div class="left-menu-header col">
                    {{-- <ul>
                        <li>
                            <form class="form-inline search-form">
                                <div class="search-bg"><i class="fa fa-search"></i>
                                    <input class="form-control-plaintext" placeholder="Search here.....">
                                </div>
                            </form><span class="d-sm-none mobile-search search-bg"><i class="fa fa-search"></i></span>
                        </li>
                    </ul> --}}
                    <h3>{{$title??""}}</h3>
                </div>
                <div class="nav-right col pull-right right-menu p-0">
                    <ul class="nav-menus">
                        {{--<li><a class="text-dark" href="#!" onclick="javascript:toggleFullScreen()"><i
                                    data-feather="maximize"></i></a></li>
                         <li class="onhover-dropdown">
                            <div class="bookmark-box"><i data-feather="star"></i></div>
                            <div class="bookmark-dropdown onhover-show-div">
                                <div class="form-group mb-0">
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i
                                                    class="fa fa-search"></i></span></div>
                                        <input class="form-control" type="text"
                                            placeholder="Search for bookmark...">
                                    </div>
                                </div>
                                <ul class="m-t-5">
                                    <li class="add-to-bookmark"><i class="bookmark-icon"
                                            data-feather="inbox"></i>Email<span class="pull-right"><i
                                                data-feather="star"></i></span></li>
                                    <li class="add-to-bookmark"><i class="bookmark-icon"
                                            data-feather="message-square"></i>Chat<span class="pull-right"><i
                                                data-feather="star"></i></span></li>
                                    <li class="add-to-bookmark"><i class="bookmark-icon"
                                            data-feather="command"></i>Feather Icon<span class="pull-right"><i
                                                data-feather="star"></i></span></li>
                                    <li class="add-to-bookmark"><i class="bookmark-icon"
                                            data-feather="airplay"></i>Widgets<span class="pull-right"><i
                                                data-feather="star"> </i></span></li>
                                </ul>
                            </div>
                        </li>
                        <li class="onhover-dropdown">
                            <div class="notification-box"><i data-feather="bell"></i><span
                                    class="dot-animated"></span></div>
                            <ul class="notification-dropdown onhover-show-div">
                                <li>
                                    <p class="f-w-700 mb-0">You have 3 Notifications<span
                                            class="pull-right badge badge-primary badge-pill">4</span></p>
                                </li>
                                <li class="noti-primary">
                                    <div class="media"><span class="notification-bg bg-light-primary"><i
                                                data-feather="activity"> </i></span>
                                        <div class="media-body">
                                            <p>Delivery processing </p><span>10 minutes ago</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="noti-secondary">
                                    <div class="media"><span class="notification-bg bg-light-secondary"><i
                                                data-feather="check-circle"> </i></span>
                                        <div class="media-body">
                                            <p>Order Complete</p><span>1 hour ago</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="noti-success">
                                    <div class="media"><span class="notification-bg bg-light-success"><i
                                                data-feather="file-text"> </i></span>
                                        <div class="media-body">
                                            <p>Tickets Generated</p><span>3 hour ago</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="noti-danger">
                                    <div class="media"><span class="notification-bg bg-light-danger"><i
                                                data-feather="user-check"> </i></span>
                                        <div class="media-body">
                                            <p>Delivery Complete</p><span>6 hour ago</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </li>
                        <li>
                            <div class="mode"><i class="fa fa-moon-o"></i></div>
                        </li>
                        <li class="onhover-dropdown"><i data-feather="message-square"></i>
                            <ul class="chat-dropdown onhover-show-div">
                                <li>
                                    <div class="media"><img class="img-fluid rounded-circle me-3"
                                            src="{{ asset('assets/admin/images/user/4.jpg') }}" alt="">
                                        <div class="media-body"><span>Ain Chavez</span>
                                            <p class="f-12 light-font">Lorem Ipsum is simply dummy...</p>
                                        </div>
                                        <p class="f-12">32 mins ago</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="media"><img class="img-fluid rounded-circle me-3"
                                            src="{{ asset('assets/admin/images/user/1.jpg') }}" alt="">
                                        <div class="media-body"><span>Erica Hughes</span>
                                            <p class="f-12 light-font">Lorem Ipsum is simply dummy...</p>
                                        </div>
                                        <p class="f-12">58 mins ago</p>
                                    </div>
                                </li>
                                <li>
                                    <div class="media"><img class="img-fluid rounded-circle me-3"
                                            src="{{ asset('assets/admin/images/user/2.jpg') }}" alt="">
                                        <div class="media-body"><span>Kori Thomas</span>
                                            <p class="f-12 light-font">Lorem Ipsum is simply dummy...</p>
                                        </div>
                                        <p class="f-12">1 hr ago</p>
                                    </div>
                                </li>
                                <li class="text-center"> <a class="f-w-700" href="javascript:void(0)">See All </a>
                                </li>
                            </ul>
                        </li> --}}
                        <li class="onhover-dropdown p-0">
                            <button class="btn btn-primary-light" type="button"><a href="{{route('logout')}}"><i
                                        data-feather="log-out"></i>Log out</a></button>
                        </li>
                    </ul>
                </div>
                <div class="d-lg-none mobile-toggle pull-right w-auto"><i data-feather="more-horizontal"></i></div>
            </div>
        </div>
        <!-- Page Header Ends                              -->
        <!-- Page Body Start-->
        <div class="page-body-wrapper horizontal-menu">
            <!-- Page Sidebar Start-->
            <style>
                /* .menu-content .fa{
                font-size:18px;
            } */
                .page-wrapper.compact-wrapper .page-body-wrapper header.main-nav .main-navbar .nav-menu>li .nav-submenu li a {
                    padding: 10px 7px;
                    font-size: 13px;
                    color: #333333;
                    display: block;
                    position: relative;
                    letter-spacing: 0.07em;
                    padding-left: 34px !important;
                }
            </style>
            @php
                $route_name = Route::currentRouteName();
            @endphp
            <header class="main-nav">
                @include('layouts.partials.admin_sidebar')
            </header>
            <!-- Page Sidebar Ends-->
            <div class="page-body">
                <div class="container-fluid d-none">
                    <div class="page-header">
                        <div class="row">
                            <div class="col-sm-6">
                                {{-- <h3>{{$title??""}}</h3> --}}
                                {{-- <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="../ltr/index.html">Home</a></li>
                                    <li class="breadcrumb-item">Starter Kit</li>
                                    <li class="breadcrumb-item">Color Version</li>
                                    <li class="breadcrumb-item active">Layout Light</li>
                                </ol> --}}
                            </div>
                            <div class="col-sm-6">
                                <!-- Bookmark Start-->
                                {{-- <div class="bookmark">
                                    <ul>
                                        <li><a href="javascript:void(0)" data-container="body"
                                                data-bs-toggle="popover" data-placement="top" title=""
                                                data-original-title="Tables"><i data-feather="inbox"></i></a></li>
                                        <li><a href="javascript:void(0)" data-container="body"
                                                data-bs-toggle="popover" data-placement="top" title=""
                                                data-original-title="Chat"><i data-feather="message-square"></i></a>
                                        </li>
                                        <li><a href="javascript:void(0)" data-container="body"
                                                data-bs-toggle="popover" data-placement="top" title=""
                                                data-original-title="Icons"><i data-feather="command"></i></a></li>
                                        <li><a href="javascript:void(0)" data-container="body"
                                                data-bs-toggle="popover" data-placement="top" title=""
                                                data-original-title="Learning"><i data-feather="layers"></i></a></li>
                                        <li><a href="javascript:void(0)"><i class="bookmark-search"
                                                    data-feather="star"></i></a>
                                            <form class="form-inline search-form">
                                                <div class="form-group form-control-search">
                                                    <input type="text" placeholder="Search..">
                                                </div>
                                            </form>
                                        </li>
                                    </ul>
                                </div> --}}
                                <!-- Bookmark Ends-->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Container-fluid starts-->
                <div class="container-fluid">
                    <div class="row starter-main">
                        @yield('content')
                    </div>
                </div>
                <!-- Container-fluid Ends-->
            </div>
            <!-- footer start-->
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6 footer-copyright">
                            <p class="mb-0">{{get_setting('footer_text')}}</p>
                        </div>
                        <div class="col-md-6">

                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <!-- latest jquery-->
    <script src="{{ asset('assets/admin/js/jquery-3.5.1.min.js') }}"></script>
    <!-- feather icon js-->
    <script src="{{ asset('assets/admin/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/icons/feather-icon/feather-icon.js') }}"></script>
    <!-- Sidebar jquery-->
    <script src="{{ asset('assets/admin/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/admin/js/config.js') }}"></script>
    <!-- Bootstrap js-->
    <script src="{{ asset('assets/admin/js/bootstrap/popper.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/bootstrap/bootstrap.min.js') }}"></script>
    <!-- Plugins JS start-->
    {{-- <script src="https://kit.fontawesome.com/d7dd5c0801.js" crossorigin="anonymous"></script> --}}
    <script src="{{ asset('assets/admin/js/prism/prism.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/clipboard/clipboard.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/custom-card/custom-card.js') }}"></script>
    <script src="{{asset('assets/admin/js/datatable/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{ asset('assets/admin/js/tooltip-init.js') }}"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="{{ asset('assets/admin/js/script.js') }}"></script>
    {{-- <script src="{{ asset('assets/admin/js/theme-customizer/customizer.js') }}"></script> --}}
    <!-- login js-->

    <script>
        $(function () {
            $('.datatable').DataTable();
        });
    </script>

    <!-- Plugin used-->
    @stack('scripts')
</body>

</html>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bid Offer</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-5.2.2/css/bootstrap.min.css') }}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/global.css') }}" />
    <!-- confirmOtp in Css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/confirmOtp.css') }}" />
    <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
    <!-- //Font Icon  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
</head>

<body>
    <div class="confirmOtpMainDiv">
        <div class="row">
            <div class="col-4 leftCol">
                <img class="w-100" src="{{ asset('assets//pictures/confirmOtp_banner.jpg') }}" alt="" />
            </div>
            <div class="col-8 confirmOtp rightCol position-relative">
                <div class="loader position-absolute top-50 start-50">
                    <i class="fa fa-spinner fa-3x fa-spin"></i>

                </div>

                <div class="logo">
                    <a href="home.html"> <img src="{{ asset('assets/pictures/logo.png') }}" alt="logo"
                            class="navbar-brand-dark" /></a>
                </div>
                <div class="confirmOtpForm">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <div class="section1 text-center">
                            <i class="fa fa-envelope"></i>
                            <h4 class="mt-4">Please verify your email address.</h4>
                            <p> We have sent a verification code to the email below;<br><span
                                    style="font-size:12px; color:red;">Please check your Spam.</span> </p>
                            <input class="bg-light my-4 col-lg-8 border p-3 mx-auto" type="text"
                                value="{{ $user->email }}" readonly>
                            <div class=""></div>
                            <a href="uploadSignupFile.html"><button class="btn"> resend email</button></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>

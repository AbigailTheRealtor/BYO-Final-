<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bid Offer</title>
    <link rel="stylesheet" href="{{asset('assets/bootstrap-5.2.2/css/bootstrap.min.css')}}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{asset('assets/css/global.css')}}" />
    <!-- Sign in Css  -->
    <link rel="stylesheet" href="{{asset('assets/css/signin.css')}}">
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <!-- //Font Icon  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
</head>
<body>
    <div class="signMainDiv">
        <div class="row">
            <div class="col-4 leftCol">
                <img class="w-100" src="{{asset('assets//pictures/signin/banner.jpg')}}" alt="">
            </div>
            <div class="col-8 signin">
                <div class="logo">
                    <a href="home.html">
                        <img src="{{asset('assets/pictures/logo.png')}}" alt="logo" class="navbar-brand-dark"></a>
                </div>
                <div class="signinForm">
                    <h1>Get  Password</h1>
                    <p>Please enter your registered email below</p>
                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="form-group position-relative">
                          <input type="text" class="form-control" placeholder="Email" name="email" id="email" value="" autocomplete="current-password">
                          <i class="fa fa-envelope"></i>
                        </div>


                      {{-- <a href="resetPasswordOtp.html"> --}}
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase">Send</button>
                        </div>
                      {{-- </a> --}}
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Jquery  -->
    <script src="{{asset('assets/js/jquery-3.6.1.min.js')}}"></script>
    <script src="{{asset('assets/bootstrap-5.2.2/js/bootstrap.proper.min.js')}}"></script>
     <!-- Toggle password visibility -->
    <script>
    $(document).ready(function() {
        $("#show-password").click(function(){
          var paswd= $('#upassword');
          paswd.attr("type")== "password" ? paswd.attr("type","text") : paswd.attr("type","password");
        });
    });
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bid Offer</title>
    <link rel="stylesheet" href="{{asset('assets/bootstrap-5.2.2/css/bootstrap.min.css')}}" />
    <!-- //Global css  -->
    <link rel="stylesheet" href="{{asset('assets/css/global.css')}}" />
    <!-- Sign in Css  -->
    <link rel="stylesheet" href="{{asset('assets/css/signin.css')}}" />
    <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
    <!-- //Font Icon  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <style>
        .error{
            color: red;
        }
    </style>
  </head>
  <body>
    <div class="signMainDiv">
      <div class="row">
        <div class="col-sm-12 col-md-4 col-lg-4 leftCol">
          <img class="w-100" src="{{asset('assets/pictures/signin/banner.jpg')}}" alt="" />
        </div>
        <div class="col-sm-12 col-md-8 col-lg-8 signin">
          <div class="logo">
            <a href="{{url('/')}}"> <img src="{{asset('assets/pictures/logo.png')}}" alt="logo" class="navbar-brand-dark" /></a>
          </div>
          <div class="signinForm">
            <h1>Sign In</h1>
            {{-- <x-auth-validation-errors class="mb-4" :errors="$errors" /> --}}
            {{-- {{$errors}} --}}
            <p>Don't have an account? <a href="{{route('register')}}">Sign Up</a></p>
            <form method="POST" action="{{ route('login') }}">
                @csrf
              <div class="form-group position-relative">
                <input type="text" class="form-control" placeholder="Email" name="email" id="user_login" value="{{old('email')}}" autocomplete="current-password" />
                <i class="fa fa-envelope"></i>
                @if($errors->has('email'))
                    <div class="error">{{ $errors->first('email') }}</div>
              @endif
              </div>

              <div class="form-group position-relative">
                <input type="password" placeholder="Password" class="form-control" name="password" id="upassword" value="" autocomplete="current-password" />
                <i class="fa fa-lock"></i>
                <i class="fa fa-eye" id="show-password"></i>
                @if($errors->has('password'))
                    <div class="error">{{ $errors->first('password') }}</div>
              @endif
              </div>


            {{-- <a href="dashboard.html"> --}}
              <div class="form-group text-center">
                <button type="submit" class="btn btn-lg font-weight-bold text-uppercase">Sign in</button>
              </div>
            {{-- </a> --}}


            <div class="row opacity-80 d-flex align-items-center">
              <div class="col-md-6 d-flex form-check">
                <input class="form-check-input me-2" type="checkbox" id="remember" name="remember" />
                <label class="form-check-label ml-3 pt-1" for="remember"> Remember </label>
              </div>
              <div class="col-md-6 text-end">
                <a href="{{ route('password.request') }}" rel="nofollow"><u>Lost password?</u></a>
              </div>
            </div>
            {{-- <div class="d-flex justify-content-center align-items-center">
              <span>OR</span>
            </div>
            <div class="row justify-content-center mt-4">
              <div class="col-lg-6 col-12">
                <a href="{{route('facebook.login')}}" class="btn btn-sm fb-btn w-100">
                  <i class="fa fa-facebook"></i>
                </a>
              </div>
              <div class="col-lg-6 col-12">
                <a href="{{route('google.login')}}" class="btn btn-sm google-btn w-100">
                  <i class="fa fa-google"></i>
                </a>
              </div>
            </div> --}}
        </form>
          </div>
        </div>
      </div>

      <!-- Footer  -->

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
    </div>

    <!-- Add Jquery  -->
    <script src="{{asset('assets/js/jquery-3.6.1.min.js')}}"></script>
    <script src="{{asset('assets/bootstrap-5.2.2/js/bootstrap.proper.min.js')}}"></script>
    <!-- Toggle password visibility -->
    <script>
      $(document).ready(function () {
        $("#show-password").click(function () {
          var paswd = $("#upassword");
          paswd.attr("type") == "password" ? paswd.attr("type", "text") : paswd.attr("type", "password");
        });
      });
    </script>
  </body>
</html>

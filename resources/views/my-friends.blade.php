@extends('layouts.main')
@push('styles')

@endpush
@section('content')
<div class="mainDashboard">
    <div class="container">
        @include('layouts.partials.dashboard_user_section')
      <div class="dashboardContentDetails mt-3">
        <div class="myFriends card">
          <div class="row">
            @include('layouts.partials.sidenav')
            <div class="rightCol col-sm-12 col-md-8 col-lg-8">
              <div class="container mt-5 myBid">
                <h1>My Friends</h1>
                <!-- Section 1  -->
                <select class="form-select mt-4 mb-3 w-25">
                  <option value="friends">My Friends</option>
                  <option value="followers">My Followers</option>
                  <option value="blocked">Blocked</option>
                </select>
                <!-- End  -->
                <div class="card">
                  <!-- <img src="assets/pictures/author/banner.jpg" class="card-img-top" alt="banner"> -->
                  <div class="card-body">
                    <!-- Review  -->
                    <div class="review container">
                      <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                        <div class="left d-flex align-items-center flex-wrap">
                          <div class="position-relative image">
                            <img src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="" />
                          </div>
                          <div class="ms-2">
                            <p class="mb-2">
                              <span><b>Adalpha12</b></span>
                            </p>
                            <span class="mb-0 opacity-50 text-sm-center sm"> Last Online 1 Month ago</span>
                          </div>
                        </div>
                        <div class="right text-center">
                          <button class="btn btn-lg">Block User</button>
                        </div>
                      </div>
                    </div>
                    <!-- End  -->
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
@push('scripts')

@endpush

@extends('layouts.admin')
@section('content')
{{-- <div class="mainDashboard">
    <div class="container">
        @include('layouts.partials.dashboard_user_section')
        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.admin_sidebar')
                    <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                        <div class="container mt-5 buyer_buyerAgent">
                            <h1>All Auctions </h1>
                            <div class="card my-4">
                                <!-- <img src="assets/pictures/author/banner.jpg" class="card-img-top" alt="banner"> -->
                                <div class="card-body">
                                    <!-- Review  -->
                                    <div class="review container">
                                        <div
                                            class="card-body d-flex align-items-center justify-content-between flex-wrap">
                                            <div class="left d-flex align-items-center flex-wrap mb-2">
                                                <div class="position-relative image">
                                                    <img src="https://ppt1080.b-cdn.net/images/avatar/none.png"
                                                        alt="" />
                                                </div>
                                                <div class="ms-2">
                                                    <p class="mb-0">
                                                        <span><b>Adalpha12</b></span>
                                                    </p>
                                                    <p class="small mb-0">Seller</p>

                                                    <span class="mb-0 opacity-50 text-sm-center sm"> Last Online 1
                                                        Month ago</span>
                                                </div>
                                            </div>
                                            <div class="right text-center">
                                                <button class="btn btn-lg" data-bs-toggle="modal"
                                                    data-bs-target="#status">Active</button>
                                                <button class="btn btn-lg" data-bs-toggle="modal"
                                                    data-bs-target="#delete">Delete</button>
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
    <!-- Delete Modal  -->
    <div class="modal fade" id="delete" tabindex="-1" aria-labelledby="deleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="deleteLabel">Action Auction</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5">
                    <form>
                        <p class="text-center my-3">Are you sure delete this one ?</p>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Yes </button>
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> No </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- status Modal  -->
    <div class="modal fade" id="status" tabindex="-1" aria-labelledby="statusLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="statusLabel">Action Auction</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5">
                    <form>
                        <p class="text-center my-3">Are you sure InActive this one ?</p>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Inactive </button>
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Cancel </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div> --}}
@endsection

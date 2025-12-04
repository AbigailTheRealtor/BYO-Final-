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
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <h1>Sellers Agent</h1>
                                <div>
                                    <button data-bs-toggle="modal" data-bs-target="#addSeller">Add Seller Agent</button>
                                </div>
                            </div>
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
                                                    <p class="mb-2">
                                                        <span><b>Adalpha12</b></span>
                                                    </p>
                                                    <span class="mb-0 opacity-50 text-sm-center sm"> Last Online 1
                                                        Month ago</span>
                                                </div>
                                            </div>
                                            <div class="right text-center">
                                                <button class="btn btn-lg" data-bs-toggle="modal"
                                                    data-bs-target="#editsellerAgent">Edit</button>
                                                <button class="btn btn-lg" data-bs-toggle="modal"
                                                    data-bs-target="#deleteSellerAgent">Delete</button>
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
    <!-- Add Seller Modal -->
    <div class="modal fade" id="addSeller" tabindex="-1" aria-labelledby="addSellerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="addSellerLabel">Add Seller Agent</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5">
                    <form>
                        <div class="form-group position-relative">
                            <input type="text" class="form-control" placeholder="Full Name" name="log" value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="email" placeholder="Email Address" class="form-control" name="email"
                                id="user_pass" value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="password" class="form-control" placeholder="Password" name="password"
                                value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="text" class="form-control" placeholder="User Name" name="username"
                                value="" />
                        </div>
                        <select class="form-select mb-3">
                            <option value="">Pick your account type.</option>
                            <option value="member">Buyer/Buyer’s Agent</option>
                            <option value="agency">Seller / Seller's Agent</option>
                        </select>
                        <select class="form-select mb-3">
                            <option>Select Account Type</option>
                            <option value="member">Active </option>
                            <option value="agency">Inactive</option>
                        </select>
                        <div class="form-group mb-4 d-flex justify-content-between align-items-center">
                            <label class="fs-6"><b>Uploaded Documents</b></label>
                            <a href="/"><button>View Documents</button></a>
                        </div>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Save changes</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Edit Buyer Modal -->
    <div class="modal fade" id="editsellerAgent" tabindex="-1" aria-labelledby="editsellerAgentLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="editsellerAgentLabel">Edit</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5">
                    <form>
                        <div class="form-group position-relative">
                            <input type="text" class="form-control" placeholder="Full Name" name="log" value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="email" placeholder="Email Address" class="form-control" name="email"
                                id="user_pass" value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="password" class="form-control" placeholder="Password" name="password"
                                value="" />
                        </div>
                        <div class="form-group position-relative">
                            <input type="text" class="form-control" placeholder="User Name" name="username"
                                value="" />
                        </div>
                        <select class="form-select mb-3">
                            <option value="member">Buyer/Buyer’s Agent</option>
                            <option value="agency">Seller / Seller's Agent</option>
                        </select>
                        <select class="form-select mb-3">
                            <option value="member">Active </option>
                            <option value="agency">Inactive</option>
                        </select>
                        <div class="form-group mb-4 d-flex justify-content-between align-items-center">
                            <label class="fs-6"><b>Uploaded Documents</b></label>
                            <a href="/"><button>View Documents</button></a>
                        </div>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Save changes</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- Delete Modal  -->
    <div class="modal fade" id="deleteSellerAgent" tabindex="-1" aria-labelledby="deleteSellerAgentLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="deleteSellerAgentLabel">Delete Seller Agent</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5">
                    <form>
                        <p class="text-center my-3"> Are you sure delete this one?</p>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-lg font-weight-bold text-uppercase"
                                data-bs-dismiss="modal"> Delete</button>
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

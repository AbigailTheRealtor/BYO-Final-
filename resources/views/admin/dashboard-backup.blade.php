@extends('layouts.main')
@push('name')

@endpush
@section('content')
<div class="mainDashboard">
    <div class="container">
        @include('layouts.partials.dashboard_user_section')
        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.admin_sidebar')
                    <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                        <div class="container mt-5 buyer_buyerAgent">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <h1>Dashboard</h1>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Buyer Modal -->
    <div class="modal fade" id="addBuyer" tabindex="-1" aria-labelledby="addBuyerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="addBuyerLabel">Add New Buyer</h1>
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
    <div class="modal fade" id="editBuyer" tabindex="-1" aria-labelledby="editBuyerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="editBuyerLabel">Edit</h1>
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
    <div class="modal fade" id="deleteBuyer" tabindex="-1" aria-labelledby="deleteBuyerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="deleteBuyerLabel">Delete Buyer</h1>
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
</div>
@endsection

@extends('layouts.admin')
@push('styles')
<style>
    .card .card-header{
        padding: 15px;
    }
    .badge-count{
        min-width: 50px;
        background-color: #FFF;
        border: 1px solid #e0e0e0;
        z-index:9;
    }
    .card-icon{
        font-size: 100px; position:absolute; right: 0px; bottom:0; color: #f2f2f2;
    }
</style>
@endpush
@section('content')
    <div class="row">

        <div class="col-xl-4 box-col-12 des-xl-100">
            <div class="card">
                <div class="card-header">
                    <i class="icon icon-user card-icon"></i>
                    <div class="header-top d-sm-flex justify-content-between align-items-center" style="min-height: 70px;">
                        <h5>Total<br> Sellers</h5>
                        <div class="badge badge-count">
                            <h5>{{$total_sellers}}</h5>
                        </div>
                    </div>
                </div>

            </div>
        </div>





        <div class="col-xl-4 box-col-12 des-xl-100">
            <div class="card">
                <div class="card-header">
                    <i class="icon icon-user card-icon"></i>
                    <div class="header-top d-sm-flex justify-content-between align-items-center" style="min-height: 70px;">
                        <h5>Total<br> Buyers</h5>
                        <div class="badge badge-count">
                            <h5>{{$total_buyer}}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="col-xl-4 box-col-12 des-xl-100">
            <div class="card">
                <div class="card-header">
                    <i class="icon icon-user card-icon"></i>
                    <div class="header-top d-sm-flex justify-content-between align-items-center" style="min-height: 70px;">
                        <h5>Total <br> Agents</h5>
                        <div class="badge badge-count">
                            <h5>{{$total_agents}}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>




    </div>
@endsection

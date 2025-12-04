@extends('layouts.main')
@push('styles')
<style>
    .userMain .userBlock{
    /* box-shadow: 0px 0px 23px -3px #ccc; */
    padding-bottom: 12px;
    margin-bottom: 30px;
    overflow: hidden;
    background:#fff;
    border:  1px solid #e0e0e0;
    border-radius: 5px;
}
.userMain .userBlock .backgrounImg{
    overflow: hidden;
    height: 100px;
}
.userMain .userBlock .backgrounImg img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.userMain .userBlock .userImg{
    text-align: center;
    width: 80px;
    height: 80px;
    border-radius: 100%;
    margin: auto;
    overflow: hidden;
    margin-top: -40px;
}
.userMain .userBlock .userImg img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.userMain .userBlock .userDescription{
    text-align: center;
}
.userMain .userBlock .userDescription h5{
    margin-bottom: 2px;
    font-weight: 600;
}
.userMain .userBlock .userDescription p{
    margin-bottom: 5px;
}
.userMain .userBlock .userDescription .btn{
    /* padding: 0px 23px 0px 23px; */
    /* height: 22px; */
    border-radius: 4px;
    /* font-size: 12px; */
    /* background: #0198dd; */
    color: #fff;
}
.userMain .userBlock .userDescription .btn:hover{

    opacity:0.7;
}

.userMain .userBlock .followrs{
    display: inline-flex;
    margin-right: 10px;
    border-right: 1px solid #ccc;
    padding-right: 10px;
}
.userMain .userBlock .followrs .number{
    font-size: 15px;
    font-weight: bold;
    margin-right: 5px;
    margin-top: -1px;
}
</style>
@endpush
@section('content')
{{-- <div class="container">
    @include('layouts.partials.search_menu')
</div> --}}
<div class="buyerOfferContentDetails">
    <div class="container">
        <p><span><b>Explore</b></span> <span><i>{{$count}} results</i></span></p>



      <div class="row userMain">

        @inject('carbon', 'Carbon\Carbon')
        @forelse ($agents as $agent)
        <div class="col-md-3 col-sm-4">
            <div class="userBlock">
                <a href="{{route('author', $agent->id)}}">
                <div class="backgrounImg">
                    <img src="{{asset('images/cover/'.($agent->cover_photo?$agent->cover_photo:"3.jpg"))}}">
                </div>
                <div class="userImg">
                    <img src="{{asset('images/avatar/'.($agent->avatar?$agent->avatar:"17.png"))}}">
                </div>
                </a>
                <div class="userDescription">
                    <a href="{{route('author', $agent->id)}}"><h5>{{$agent->name}}</h5></a>
                    <p>@if($agent->city) {{$agent->city->name}} @else Unknown City @endif</p>


                     {{-- <div class="followrs">
                        <span class="number">137</span>
                         <span>Followers</span>
                     </div> --}}
                    {{-- <button class="btn">Follow</button> --}}
                    <a href="#"><button class="btn btn-success"><i class="fa-regular fa-envelope"></i> Message Me</button></a>
                </div>

            </div>
        </div>
        @empty
        <div class="card p-4 text-center">
            <h3>No Record Found!</h3>
        </div>
        @endforelse


            {{ $agents->links('pagination.listing') }}
      </div>
    </div>
    <p class="text-center small opacity-50 mt-n4 tiny text-uppercase"> {{$count}} RESULTS FOUND</p>
</div>
@endsection
@push('scripts')

@endpush

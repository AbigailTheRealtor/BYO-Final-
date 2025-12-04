{{-- CSS styles for the animation --}}
<style>
    /* CSS styles for the animation (placed here in the main blade file) */
    .check-btn {
        position: relative; /* Ensure the pseudo-element is positioned relative to this element */
    }

    /* Style for the checkmark */
    .check-btn::before {
        content: "\2713"; /* Unicode character for a checkmark */
        opacity: 0;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 24px;
        color: white;
    }

    /* Style for the checked state */
    .check-btn.checked::before {
        opacity: 1;
    }
    /* Style for the background color in the checked state */
    .check-btn.checked {
        background-color: #5cb85c; /* Green color for the checked state */
    }
</style>

{{-- Partial view --}}
@foreach ($star_agent as $agent)
<div class="card userBlock col-md-4 mt-4" style="width: 18rem;margin-right:14px;">
    <a href="{{route('author', $agent->id)}}">
        <img class="card-img-top" src="{{asset('images/cover/'.($agent->cover_photo?$agent->cover_photo:"3.jpg"))}}" alt="Preferred Agent">
        <div class="card-body">
            <h5 class="card-title"><a href="{{route('author', $agent->id)}}">{{$agent->name}}</a></h5>
            <p class="card-text">{{$agent->info('city')}}</p>
            <a href="#" class="btn btn-success check-btn" data-agent-id="{{$agent->id}}">Check Me</a>
        </div>
    </a>
</div>
@endforeach


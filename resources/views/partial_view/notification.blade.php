@php
    use App\Models\User;
    use Carbon\Carbon;
@endphp

@php
    try {
@endphp
@php
    $countExpiredProperties = 0;
@endphp
@foreach ($notifications as $notification)
    @php
        $messageArray = json_decode($notification->message, true);
        $property_auction = $notification->property_auction_id;
        $bid_user = User::find($notification->propertyAuctionBids->user_id);
        // $propertyAuction=User::find($notification->propertyAuctionBids->auction);
        $countExpiredProperties++;
    @endphp



    <a class="dropdown-item1" href="{{ url('property/listing/view/' . $property_auction) }}">
        <div class="field">
            <h5>{{ $bid_user->name }}</h5>
            @foreach ($messageArray as $message)
                <p style="font-size: 14px;">{{ $message }}</p>
            @endforeach
        </div>
    </a>
@endforeach

@php
    } catch (Throwable $e) {
        // Handle the error, log it, or perform any necessary actions
    }
@endphp

@foreach ($properties_tenant as $property)
    @if ($property->user_id == Auth::user()->id && Carbon::parse($property->get->expiration_date) < Carbon::today())
          @php
                 $countExpiredProperties++;
          @endphp
        <a class="dropdown-item1" href="{{ url('/tenant/criteria/auctions') }}">
            <div class="field">
                <h5>Tenant Criteria</h5>
                <p style="font-size: 14px;">Your Tenant Criteria Property Expiration Date has exceeded. Please renew your listing and expiration date.</p>
                <!-- Add more bidder names here if needed -->
            </div>
        </a>
    @endif
@endforeach
@foreach ($buyer_criteria as $property)
    @if ($property->user_id == Auth::user()->id && $property->get->expiration_date < Carbon::today())
    @php
    $countExpiredProperties++;
    @endphp
        <a class="dropdown-item1" href="{{ url('/renew_buyer_criteria/' . $property->id) }}">
            <div class="field">
                <h5>Buyer Criteria</h5>
                <p style="font-size: 14px;">Your Buyer Criteria Expiration Date has exceeded. Please renew your listing and expiration date.</p>
                <!-- Add more bidder names here if needed -->
            </div>
        </a>
    @endif
@endforeach
@foreach ($landlord_auction as $property)
    @if ($property->user_id == Auth::user()->id && $property->expiration_date < Carbon::today())
    @php
    $countExpiredProperties++;
    @endphp
        <a class="dropdown-item1" href="{{ url('/renew_landloard_auction/' . $property->id) }}">
            <div class="field">
                <h5>Landlord Auction</h5>
                <p style="font-size: 14px;">Your Landlord Expiration Date has exceeded. Please renew your listing and expiration date.</p>
                <!-- Add more bidder names here if needed -->
            </div>
        </a>
    @endif
@endforeach
    <input type="hidden" id="count_data" value="{{ $countExpiredProperties }}">

<style>
  .search-menu {
    border-bottom: 0px;
  }

  .search-menu .search-menu-item {
    font-size: 14px;
    padding: 5px 15px;
    margin-right: 10px;
    background-color: #049399;
    border-radius: 0px 3px 0px 3px;
    transform: skewX(-30deg);
    margin-bottom: 10px;
  }

  .search-menu .search-menu-item:hover {
    box-shadow: inset 3px 0px 25px 0px #0ce7ef;
  }

  .search-menu .search-menu-item a {
    color: #FFF;
    display: block;
    transform: skewX(30deg);
  }

  .search-menu .search-menu-item.active {
    background-color: #000;
  }
</style>
<div class="p-2 border-bottom">
  <ul class="nav nav-tabs search-menu" id="myTab" role="tablist">
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('searchListing') }}">Property Auctions</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('buyer.criteria.searchListing') }}">Buyer's Criteria Auctions</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('tenant.criteria.auctions.search') }}">Tenant's Criteria Auctions</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('seller.agent.searchListing') }}">Hiring Seller's Agent</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('buyer.agent.searchListing') }}">Hiring Buyer's
        Agent</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('landlord.agent.auctions.search') }}">Hiring Landlord's Agent</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('tenant.agent.auctions.search') }}">Hiring Tenant's Agent</a>
    </li>
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('service.searchListing') }}">Agent Service Needed</a>
    </li>
    {{-- <li class="nav-item search-menu-item" role="presentation">
            <a href="{{ route('seller.service.auction.search') }}">Seller Service Auction</a>
        </li> --}}
    <li class="nav-item search-menu-item" role="presentation">
      <a href="{{ route('agent.landlord.auctions.search') }}">Auctions for Landlords</a>
    </li>

  </ul>
</div>
@push('scripts')
  <script>
    var uri = (window.location.href).split('?')[0];
    $('.search-menu-item').removeClass('active');
    $(function() {
      $('.search-menu-item').each(function(index, element) {
        // $(element).hasClass('active');
        var href = $(element).children('a').attr('href');
        if (href == uri) {
          $(element).addClass('active');
        }
      });
    });
  </script>
@endpush

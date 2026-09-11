{{--
    The global "+" in the mobile bottom bar, shared by layouts/main and layouts/master
    so the two cannot drift.

    In `combined` mode this is byte-for-byte what both layouts did before: the legacy
    Create Property Listing wizard. In BidYourAgent mode that destination is a
    BidYourOffer surface — and is refused server-side — so the primary action becomes
    the Hire Agent entry point for whoever is looking at it.
--}}
<li>
    @bidyouroffer
    <a href="{{ route('add-listing') }}" class="add-listing"><i class="fa-solid fa-plus text-white"></i> </a>
    @endbidyouroffer
    @if (! \App\Support\Product\ProductContext::servesBidYourOffer())
    <a href="{{ \App\Support\Product\HireAgentEntryPoints::forCurrentUser() }}" class="add-listing" aria-label="Hire an agent"><i class="fa-solid fa-plus text-white"></i> </a>
    @endif
</li>

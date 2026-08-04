{{--
    Hire Agent Listing Detail Framework — information card.

    The card shell only. Its body is the role's own content, passed as the slot, because Milestone
    4 asks for a shared presentation pattern and role-specific CONTENT — not four roles forced
    through one set of fields.

    It exists because the four views had drifted apart on the wrapper alone: Seller, Buyer and
    Tenant open a card with `card-header section-header` + `h4.section-title`, while Landlord's
    first two cards use a bare `card-header` with an inline-styled `h4`. That difference is
    accidental, not intentional, and it is the kind that multiplies. Roles adopt this component as
    their cards are touched; nothing is forced to convert in one sweep.

    @param string $title  card heading
    @param bool   $extraSpacing  adds the existing services-section-header breathing room
--}}
@props(['title', 'extraSpacing' => false])

<div {{ $attributes->merge(['class' => 'card description hla-card']) }}>
    <div class="card-header section-header {{ $extraSpacing ? 'services-section-header' : '' }}">
        <h4 class="section-title">{{ $title }}</h4>
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>

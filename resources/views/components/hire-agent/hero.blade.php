{{--
    Hire Agent Listing Detail Framework — shared hero.

    One hero for all four roles. It renders whatever HireAgentHeroData::for() hands it and makes
    no role decisions of its own: there is no @if ($role === 'seller') anywhere below, which is
    the point — role differences live in the presenter's data contract, not in branching markup.

    DELIBERATELY ABSENT, per the governing rules: countdown, remaining time, auction duration,
    "bidding ends", timer-derived urgency, competing-proposal count, rank, highest proposal, last
    bidder. The hero answers "what is this listing", never "how long do you have". The only date
    it can show is the posted date, which is a fact about the past. The presenter additionally
    suppresses the retired "Bidding Period" listing-type label so a legacy row cannot smuggle that
    vocabulary back in through this component.

    Actions are optional and passed in by the role view, so the hero never invents a control that
    has no backend behind it.

    @param string $role     one of seller|buyer|landlord|tenant
    @param mixed  $auction  the role's auction model
    @param slot   $actions  optional; save/share/message controls supplied by the role view
--}}
@props(['role', 'auction'])

@php
    $hero = \App\Support\HireAgent\HireAgentHeroData::for($role, $auction);
@endphp

<div class="hla-hero" data-hla-hero data-hla-role="{{ $role }}">
    <div class="row align-items-start g-3">
        <div class="col-12 col-md-8">
            <h1 class="hla-hero-title">{{ $hero['title'] }}</h1>

            @if ($hero['subtitle'])
                <p class="hla-hero-subtitle">{{ $hero['subtitle'] }}</p>
            @endif

            @if (! empty($hero['facts']))
                <div class="hla-hero-facts">
                    @foreach ($hero['facts'] as $fact)
                        <div class="hla-hero-fact">
                            <span class="hla-hero-fact-label">{{ $fact['label'] }}</span>
                            <span class="hla-hero-fact-value">{{ $fact['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="hla-hero-meta">
                @if ($hero['status'])
                    <span class="hla-hero-chip" data-hla-hero-status>
                        <i class="fa-solid fa-circle-info"></i>{{ $hero['status'] }}
                    </span>
                @endif
                @if ($hero['listingType'])
                    <span class="hla-hero-chip">
                        <i class="fa-solid fa-tag"></i>{{ $hero['listingType'] }}
                    </span>
                @endif
                @if ($hero['posted'])
                    <span class="hla-hero-chip">
                        <i class="fa-solid fa-calendar-day"></i>Posted {{ $hero['posted'] }}
                    </span>
                @endif
            </div>
        </div>

        <div class="col-12 col-md-4">
            @if ($hero['figure'])
                <div data-hla-hero-figure>
                    <span class="hla-hero-figure-label">{{ $hero['figure']['label'] }}</span>
                    <p class="hla-hero-figure">{{ $hero['figure']['value'] }}</p>
                </div>
            @endif

            @isset($actions)
                <div class="hla-hero-actions">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</div>

{{--
    VIHO — listing hero.

    A summary panel for the top of a listing detail page: an eyebrow, a title, one prominent
    figure, a short row of facts, and a slot for whatever controls the caller wants beside them.

    IT RESOLVES NOTHING. Every value arrives finished. This component does not read a status and
    decide it is green, does not format a price, does not parse a date, does not know what a role
    is and cannot see who is looking at the page. `status.tone` and `status.icon` are supplied by
    the caller for exactly that reason — the vocabulary of statuses belongs to the product that
    has statuses, and a shared component that owned that mapping would own the vocabulary of every
    product that ever consumes it.

    NO MEDIA, BY DESIGN. There is no image, carousel or placeholder frame. A caller whose listing
    has no photographs must not be handed an empty grey rectangle, and a caller whose listing does
    have photographs is better served by a media component than by an optional half of this one.

    THE FIGURE IS PRE-FORMATTED. `figure.value` is echoed exactly as given, currency symbol,
    separators and all. Formatting money here would mean choosing a locale on behalf of callers
    that have already chosen one.

    @param ?string $eyebrow     small label above the title
    @param string  $title       required
    @param ?string $subtitle
    @param ?string $identifier  pre-formatted, e.g. "Listing ID: ABC-12345678"
    @param ?array  $status      ['label' => string, 'tone' => string, 'icon' => ?string]
    @param ?array  $figure      ['label' => string, 'value' => string]
    @param array   $facts       [['label' => string, 'value' => string], …] — 0 to 4
    @param slot    $actions     optional controls, rendered beside the figure
--}}
@props([
    'eyebrow'    => null,
    'title'      => '',
    'subtitle'   => null,
    'identifier' => null,
    'status'     => null,
    'figure'     => null,
    'facts'      => [],
])

@php
    $statusLabel = is_array($status) ? ($status['label'] ?? null) : null;
    $statusTone  = is_array($status) ? ($status['tone']  ?? 'neutral') : 'neutral';
    $statusIcon  = is_array($status) ? ($status['icon']  ?? null) : null;

    $figureLabel = is_array($figure) ? ($figure['label'] ?? null) : null;
    $figureValue = is_array($figure) ? ($figure['value'] ?? null) : null;

    // Defensive rather than decorative: a caller that hands over a malformed row should lose that
    // row, not emit an empty definition pair that reads as missing data to someone on the page.
    $factRows = array_values(array_filter(
        is_array($facts) ? $facts : [],
        fn ($f) => is_array($f) && ($f['label'] ?? '') !== '' && ($f['value'] ?? '') !== ''
    ));
@endphp

<section {{ $attributes->merge(['class' => 'viho-hero']) }} data-viho-hero>
    <div class="viho-hero-identity">
        @if ($eyebrow)
            <p class="viho-hero-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="viho-hero-title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="viho-hero-subtitle">{{ $subtitle }}</p>
        @endif

        @if ($statusLabel || $identifier)
            <div class="viho-hero-meta">
                @if ($statusLabel)
                    <x-viho.badge :variant="$statusTone" pill :icon="$statusIcon">{{ $statusLabel }}</x-viho.badge>
                @endif

                @if ($identifier)
                    <span class="viho-hero-identifier">{{ $identifier }}</span>
                @endif
            </div>
        @endif
    </div>

    @if ($figureValue || $factRows || isset($actions))
        <div class="viho-hero-detail">
            <div class="viho-hero-detail-main">
                @if ($figureValue)
                    <div class="viho-hero-figure">
                        @if ($figureLabel)
                            <span class="viho-hero-figure-label">{{ $figureLabel }}</span>
                        @endif
                        <p class="viho-hero-figure-value">{{ $figureValue }}</p>
                    </div>
                @endif

                @if ($factRows)
                    <dl class="viho-hero-facts">
                        @foreach ($factRows as $fact)
                            <div class="viho-hero-fact">
                                <dt class="viho-hero-fact-label">{{ $fact['label'] }}</dt>
                                <dd class="viho-hero-fact-value">{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            @isset($actions)
                <div class="viho-hero-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>

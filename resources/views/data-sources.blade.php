@extends('layouts.main')

{{--
    Data Sources & Licenses — the public surface that discharges our attribution
    obligations in full.

    The compact attribution component beside a listing's points of interest names
    the sources that particular page used and links here. This page is the long
    form: every configured source, every license behind it, and the verbatim
    repository NOTICE file that Apache-2.0 §4(d) requires us to reproduce.

    Everything below is rendered from `config/location_attribution.php` and from
    the NOTICE file itself. Nothing is restated in markup, so a source cannot be
    added to the system and forgotten here.
--}}

@section('content')
<div class="container py-5" style="max-width: 900px;">

    <h1 class="fw-bold mb-2" style="color:#1e293b;">Data Sources &amp; Licenses</h1>
    <p class="text-muted mb-4">
        Property and neighborhood information on this site is assembled from several
        third-party data sources. This page names each of them, the license it is
        provided under, and the notices those licenses require us to carry.
    </p>

    @foreach($sources as $sourceId => $source)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-1" style="color:#1e293b;">
                    @if(!empty($source['url']))
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer"
                           style="color:inherit;">{{ $source['name'] }}</a>
                    @else
                        {{ $source['name'] }}
                    @endif
                </h2>

                @if(!empty($source['statement']))
                    <p class="text-muted mb-2" style="font-size:.9rem;">{{ $source['statement'] }}</p>
                @endif

                <div style="font-size:.82rem;color:#6b7280;">
                    <strong>{{ count($source['licenses'] ?? []) > 1 ? 'Licenses' : 'License' }}:</strong>
                    <ul class="mb-1 mt-1">
                        @foreach($source['licenses'] ?? [] as $license)
                            <li>
                                @if(!empty($license['url']))
                                    <a href="{{ $license['url'] }}" target="_blank" rel="noopener noreferrer"
                                       style="color:inherit;text-decoration:underline;">{{ $license['name'] }}</a>
                                @else
                                    {{ $license['name'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    {{-- Stated plainly rather than inferred from the license list: a
                         reader should not have to know which license identifiers
                         compel attribution to know whether this one does. --}}
                    @if(($source['attribution_required'] ?? false) === true)
                        <div class="mt-1">Attribution required by license.</div>
                    @else
                        <div class="mt-1">Public domain or otherwise unrestricted; attribution given for clarity.</div>
                    @endif

                    @if(($source['notice_required'] ?? false) === true)
                        <div class="mt-1">
                            This source additionally requires that the upstream NOTICE file be
                            reproduced, that recipients be given a copy of the license, and that
                            changes to the data be disclosed. All three are below:
                            the <a href="#upstream-notice" style="color:inherit;text-decoration:underline;">upstream notice</a>,
                            a <a href="{{ route('data-sources.license') }}" style="color:inherit;text-decoration:underline;">copy of the Apache License 2.0</a>,
                            and our <a href="#modification-notice" style="color:inherit;text-decoration:underline;">notice of changes</a>.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    {{-- Every block below is {{ }}-escaped and preformatted. These are plain-text
         legal artifacts; they are not markup and must never be interpreted as any. --}}

    <h2 class="h5 fw-bold mt-5 mb-2" style="color:#1e293b;">Attribution notices</h2>
    <p class="text-muted" style="font-size:.9rem;">
        The following is the notice file distributed with this application, reproduced
        in full.
    </p>
    <pre class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.78rem;line-height:1.55;white-space:pre-wrap;word-break:break-word;color:#334155;">{{ $notice }}</pre>

    @if(!empty($upstreamNotice))
        <h2 id="upstream-notice" class="h5 fw-bold mt-5 mb-2" style="color:#1e293b;">
            Foursquare OS Places — upstream notice
        </h2>
        {{-- Labelled as Foursquare's, and rendered apart from our own statement below.
             The obligation is to preserve THIS text as theirs; a merged block would
             leave a reader unable to tell whose sentences are whose. --}}
        <p class="text-muted" style="font-size:.9rem;">
            Reproduced verbatim from the notice published by Foursquare Labs, Inc. The text
            in this block is theirs, not ours.
        </p>
        <pre class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.78rem;line-height:1.55;white-space:pre-wrap;word-break:break-word;color:#334155;">{{ $upstreamNotice }}</pre>
    @endif

    @if(!empty($modificationNotice))
        <h2 id="modification-notice" class="h5 fw-bold mt-5 mb-2" style="color:#1e293b;">
            Our changes to that data
        </h2>
        <p class="text-muted" style="font-size:.9rem;">
            Written by us, not by Foursquare or the Overture Maps Foundation. Published here
            because the licenses above require changes to the data to be disclosed.
        </p>
        <pre class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.78rem;line-height:1.55;white-space:pre-wrap;word-break:break-word;color:#334155;">{{ $modificationNotice }}</pre>
    @endif

</div>
@endsection

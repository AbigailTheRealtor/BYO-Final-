@php
    // property_photos is stored as a JSON-encoded array of filenames.
    // The EAV accessor may return a string (raw JSON) or an already-decoded array.
    // Normalize to a plain PHP array in every case.
    $rawPropertyPhotos = @$auction->get->property_photos ?? null;
    if (is_string($rawPropertyPhotos) && !empty($rawPropertyPhotos)) {
        $decoded = json_decode($rawPropertyPhotos, true);
        $viewPropertyPhotos = is_array($decoded) ? $decoded : [$rawPropertyPhotos];
    } elseif (is_array($rawPropertyPhotos)) {
        $viewPropertyPhotos = $rawPropertyPhotos;
    } else {
        $viewPropertyPhotos = [];
    }
    // Remove any blank entries
    $viewPropertyPhotos = array_values(array_filter($viewPropertyPhotos, fn($p) => !empty(trim((string) $p))));

    // Keep singular alias for legacy $viewPropertyPhoto references (unused in this file now)
    $viewPropertyPhoto   = !empty($viewPropertyPhotos) ? $viewPropertyPhotos[0] : null;

    $viewVideoTourUrl     = @$auction->get->video_tour_url    ?? null;
    $viewVirtualTourUrl   = @$auction->get->virtual_tour_url  ?? null;
    $viewListingDocument  = @$auction->get->listing_documents ?? null;

    // Sanitize: only allow http/https URLs for user-supplied tour links
    $safeUrl = function (?string $url): ?string {
        if (empty($url)) {
            return null;
        }
        $parsed = parse_url(trim($url));
        if (!$parsed || !isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return null;
        }
        return trim($url);
    };

    $viewVideoTourUrl   = $safeUrl($viewVideoTourUrl);
    $viewVirtualTourUrl = $safeUrl($viewVirtualTourUrl);

    /*
     | M6 — THE LISTING DOCUMENT IS DELIVERED THROUGH THE AUTHORIZED ROUTE, AND THIS FILE DOES
     | NOT DECIDE WHO MAY HAVE IT.
     |
     | Until now this partial linked the document with a raw public storage URL, reachable by
     | anyone who could read the page or guess the filename. It was the LAST such site in the
     | Blade layer, carried as the single entry in BladePublicMediaSeamTest::DEFERRED, and it was
     | deferred out of R2-E0b on the explicit grounds that replacing it is an authorization
     | change rather than a URL-seam change. This is that change.
     |
     | The old idiom is described rather than quoted here on purpose: BladePublicMediaSeamTest
     | scans every line of every view, and a comment that spelled it out would read as a live
     | call site. Prose should not need an exemption from the guard it is describing.
     |
     | THE RULE IS ASKED, NEVER REBUILT. ListingDocumentAccessService::canViewDownload() is the
     | authority, exactly as ListingDocumentController::show() consults it on every request. This
     | file does not test ownership, does not read user_type, and does not know what
     | REQUEST_REQUIRED means. Reimplementing the rule here — even correctly — would create the
     | second opinion that lets a view and a controller drift apart, which is how the M4 Hire
     | Agent hero came to publish compensation the page body was gating.
     |
     | The render gate and the route enforcement are NOT redundant, they are different jobs:
     | the route decides whether the file may be delivered, and this decides whether a control is
     | offered that would succeed. Without the gate the page would show a Download button that
     | 403s for every viewer who is not the owner or an authorized listing agent.
     |
     | FAIL CLOSED ON A MISSING TYPE. $listingDocumentType is supplied by the including view
     | (landlord / seller). A caller that forgets it, or passes a type the catalog does not
     | support, gets NO document control — not a public link, and not a guess. The alternative,
     | defaulting to a role, would silently hand one listing type's document rules to another.
     */
    $listingDocumentType = $listingDocumentType ?? null;

    $canViewListingDocument = false;

    if (! empty($viewListingDocument)
        && is_string($listingDocumentType)
        && \App\Services\Documents\ListingDocumentCatalog::supportsListingType($listingDocumentType)) {
        $canViewListingDocument = app(\App\Services\Documents\ListingDocumentAccessService::class)
            ->canViewDownload(
                auth()->user(),
                $listingDocumentType,
                (int) data_get($auction, 'id'),
                'listing_documents'
            );
    }

    /*
     | The section's own visibility follows the AUTHORIZED document, not the stored one. A listing
     | whose only extra content is a document the viewer may not have now renders nothing at all,
     | rather than a "Photos, Tours & Documents" heading with an empty body — the same defect the
     | M5.5 proposal console had, and the same fix.
     */
    $hasPhotosToursDocs = !empty($viewPropertyPhotos)
        || !empty($viewVideoTourUrl)
        || !empty($viewVirtualTourUrl)
        || $canViewListingDocument;

    // Convert a YouTube or Vimeo URL to an embed URL (returns null for unsupported)
    $videoEmbedUrl = !empty($viewVideoTourUrl)
        ? \App\Support\VideoEmbedHelper::getEmbedUrl($viewVideoTourUrl)
        : null;

    // Determine document icon based on file extension
    $docExtension = null;
    $docIconClass = 'fa-solid fa-file';
    if (!empty($viewListingDocument)) {
        $docExtension = strtolower(pathinfo($viewListingDocument, PATHINFO_EXTENSION));
        $docIconClass = match($docExtension) {
            'pdf'              => 'fa-solid fa-file-pdf',
            'doc', 'docx'      => 'fa-solid fa-file-word',
            'jpg', 'jpeg', 'png', 'webp' => 'fa-solid fa-file-image',
            default            => 'fa-solid fa-file',
        };
    }
@endphp

@if ($hasPhotosToursDocs)
<hr>
<div class="card-header section-header">
    <h4 class="section-title">Photos, Tours &amp; Documents</h4>
</div>

<div class="row py-2 px-2">

    {{-- Property Photos (stored as JSON array of filenames) --}}
    @if (!empty($viewPropertyPhotos))
    <div class="col-12 mb-3">
        <p class="fw-bold mb-1">
            <i class="fa-solid fa-images me-1 text-secondary"></i>
            Property Photo{{ count($viewPropertyPhotos) > 1 ? 's (' . count($viewPropertyPhotos) . ')' : '' }}
        </p>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($viewPropertyPhotos as $photoFilename)
                <img src="{{ \App\Support\Storage\ListingMediaUrl::get('auction/images/' . $photoFilename) }}"
                     alt="Property Photo"
                     class="img-fluid rounded"
                     style="max-height: 260px; max-width: 100%; object-fit: cover;" />
            @endforeach
        </div>
    </div>
    @endif

    {{-- Video Tour --}}
    @if (!empty($viewVideoTourUrl))
    <div class="col-12 mb-3">
        <p class="fw-bold mb-1"><i class="fa-solid fa-video me-1 text-secondary"></i> Video Tour</p>
        @if ($videoEmbedUrl)
            <div class="ratio ratio-16x9" style="max-width: 640px;">
                <iframe src="{{ $videoEmbedUrl }}"
                        title="Video Tour"
                        allowfullscreen
                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                </iframe>
            </div>
        @else
            <a href="{{ $viewVideoTourUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-play me-1"></i> Watch Video Tour
            </a>
        @endif
    </div>
    @endif

    {{-- 3D / Virtual Tour --}}
    @if (!empty($viewVirtualTourUrl))
    <div class="col-12 mb-3">
        <p class="fw-bold mb-1"><i class="fa-solid fa-cube me-1 text-secondary"></i> 3D / Virtual Tour</p>
        <a href="{{ $viewVirtualTourUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-vr-cardboard me-1"></i> View 3D Tour
        </a>
    </div>
    @endif

    {{-- Documents --}}
    {{-- M6: gated on the access service, not on the presence of a stored filename. See the
         governance note in the @php block above — the R2-E0b deferral this replaces is closed. --}}
    @if ($canViewListingDocument)
    <div class="col-12 mb-2">
        <p class="fw-bold mb-1"><i class="fa-solid fa-paperclip me-1 text-secondary"></i> Documents</p>
        {{-- Delivered by ListingDocumentController, which re-checks canViewDownload() on every
             request and streams from the PRIVATE disk. No public URL for this file exists.
             The document key is the literal catalogued key, never request input or a filename:
             the controller resolves the stored path from the listing itself. --}}
        <a href="{{ route('listing.document.show', [
                'listingType' => $listingDocumentType,
                'listingId'   => data_get($auction, 'id'),
                'documentKey' => 'listing_documents',
           ]) }}"
           target="_blank"
           rel="noopener noreferrer"
           class="btn btn-outline-dark btn-sm">
            <i class="{{ $docIconClass }} me-1"></i>
            Download / View Document
            @if ($docExtension)
                <span class="text-muted ms-1">({{ strtoupper($docExtension) }})</span>
            @endif
        </a>
    </div>
    @endif

</div>
@endif

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

    $hasPhotosToursDocs = !empty($viewPropertyPhotos)
        || !empty($viewVideoTourUrl)
        || !empty($viewVirtualTourUrl)
        || !empty($viewListingDocument);

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
                <img src="{{ asset('storage/auction/images/' . $photoFilename) }}"
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
    @if (!empty($viewListingDocument))
    <div class="col-12 mb-2">
        <p class="fw-bold mb-1"><i class="fa-solid fa-paperclip me-1 text-secondary"></i> Documents</p>
        <a href="{{ asset('storage/auction/documents/' . $viewListingDocument) }}"
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

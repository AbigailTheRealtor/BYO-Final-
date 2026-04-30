<h3 class="fw-bold mb-3">Photos, Tours &amp; Documents</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📷 Upload property photos, link video or 3D tours, and attach any relevant documents to help Agents better understand and market the property. All fields are optional.
            </strong>
        </div>
    </div>
</div>

<!-- Property Photos -->
<div class="form-group">
    <label class="fw-bold">
        Property Photos:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Upload one or more photos of the property. Accepted formats: JPG, JPEG, PNG, WEBP. You can select multiple files at once.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <div class="input-group">
            <input type="file" wire:model="newPropertyPhotos" id="property-photos-input" class="form-control has-icon"
                data-icon="fa-solid fa-images" accept=".jpg,.jpeg,.png,.webp" multiple>
        </div>
    </div>
    <div wire:loading wire:target="newPropertyPhotos" class="mt-1 text-muted small">Uploading...</div>
    @error('newPropertyPhotos.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

    @if (!empty($propertyPhotos))
        <div class="mt-3">
            <div class="fw-bold mb-2">Uploaded Photos ({{ count($propertyPhotos) }}):</div>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($propertyPhotos as $index => $photo)
                    <div style="position:relative; width:160px; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; padding: 6px; background:#fafafa;">
                        <img src="{{ asset('storage/auction/images/' . $photo) }}"
                            style="width:100%; height:110px; object-fit:cover; border-radius:4px;" />
                        <button type="button"
                            wire:click="deletePropertyPhoto({{ $index }})"
                            wire:confirm="Are you sure you want to delete this photo?"
                            class="btn btn-danger btn-sm mt-2 w-100">
                            <i class="fa-solid fa-trash me-1"></i> Delete
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<!-- Video Tour URL -->
<div class="form-group mt-3">
    <label class="fw-bold">
        Video Tour URL:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Paste a link to a video tour of the property (e.g., YouTube, Vimeo, or direct video URL).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="url" wire:model="videoTourUrl" class="form-control has-icon"
            data-icon="fa-solid fa-video"
            placeholder="Enter video tour URL (e.g., https://www.youtube.com/watch?v=...)">
    </div>
</div>

<!-- 3D Tour URL -->
<div class="form-group mt-3">
    <label class="fw-bold">
        3D Tour URL:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Paste a link to a 3D or virtual tour of the property (e.g., Matterport, Zillow 3D Home).">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <input type="url" wire:model="virtualTourUrl" class="form-control has-icon"
            data-icon="fa-solid fa-cube"
            placeholder="Enter 3D tour URL (e.g., https://my.matterport.com/show/...)">
    </div>
</div>

<!-- Documents -->
<div class="form-group mt-3">
    <label class="fw-bold">
        Documents:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Upload any relevant property documents (e.g., disclosures, inspection reports, HOA rules). Accepted formats: PDF, DOC, DOCX, JPG, JPEG, PNG.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>
    <div class="input-cover">
        <div class="input-group">
            <input type="file" wire:model="listingDocuments" id="listing-documents-input" class="form-control has-icon"
                data-icon="fa-solid fa-file-alt" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        </div>
    </div>
    @if ($listingDocuments && is_string($listingDocuments))
        <div class="mt-2">
            <a href="{{ asset('storage/auction/documents/' . $listingDocuments) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-file me-1"></i> View Document
            </a>
            <button type="button" wire:click="deleteListingDocument" wire:confirm="Are you sure you want to delete this document?"
                class="btn btn-danger btn-sm ms-1">
                Delete Document
            </button>
        </div>
    @elseif ($listingDocuments && !is_string($listingDocuments))
        <div class="mt-2 text-muted small">
            Document ready to save: {{ $listingDocuments->getClientOriginalName() }}
        </div>
    @endif
    <div wire:loading wire:target="listingDocuments" class="mt-1 text-muted small">Uploading...</div>
    @error('listingDocuments') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<h3 class="fw-bold mb-3">Photos &amp; Tours</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📷 Upload property photos and link video or 3D tours to market the property. All fields are optional.</strong>
        </div>
    </div>
</div>

<!-- Property Photos -->
<div class="form-group">
    <label class="fw-bold mb-2 d-block">
        Property Photos:
        <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
            title="Upload one or more photos of the property. Accepted formats: JPG, JPEG, PNG, WEBP. You can select multiple files at once.">
            <i class="fa-solid fa-circle-info"></i>
        </span>
    </label>

    {{-- Hidden native file input — fully functional, triggered by the dropzone label below --}}
    <input type="file" wire:model="newPropertyPhotos" id="property-photos-input-landlord"
        accept=".jpg,.jpeg,.png,.webp" multiple
        style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none;"
        tabindex="-1"
        @if(count($propertyPhotos ?? []) >= 50) disabled title="Photo limit reached (50/50)" @endif>

    {{-- Dropzone Upload Card --}}
    @if(count($propertyPhotos ?? []) < 50)
        <label for="property-photos-input-landlord" id="landlord-photo-dropzone"
            class="d-block text-center p-4 mb-3"
            style="border:2px dashed #049399;border-radius:10px;cursor:pointer;background:#f8fffe;transition:background .15s,border-color .15s;"
            onmouseover="this.style.background='#e6f9fa'"
            onmouseout="this.style.background='#f8fffe'">
            <div style="font-size:2.8rem;color:#049399;margin-bottom:.4rem;">
                <i class="fa-solid fa-camera"></i>
            </div>
            <div style="font-size:1.05rem;font-weight:700;color:#222;margin-bottom:.2rem;">
                Drag photos here or click to upload
            </div>
            <div style="font-size:.85rem;color:#666;margin-bottom:.75rem;">
                Upload up to 50 property photos. The first photo will be used as the cover photo.
            </div>
            <span class="btn btn-sm px-4 py-1" style="pointer-events:none;background:#049399;color:#fff;border-radius:6px;">
                <i class="fa-solid fa-plus me-1"></i> Add Photos
            </span>
            <div class="mt-2" style="font-size:.78rem;color:#999;">
                You can select multiple photos at once &bull; JPG, JPEG, PNG, WEBP accepted
            </div>
        </label>
    @else
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Photo limit reached (50/50). Delete a photo below to add new ones.</span>
        </div>
    @endif

    {{-- Upload count & loading --}}
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small">
            <i class="fa-solid fa-images me-1"></i>
            <strong>{{ count($propertyPhotos ?? []) }}</strong> / 50 photos uploaded
        </span>
        @if(count($propertyPhotos ?? []) > 0 && count($propertyPhotos ?? []) < 50)
            <span class="text-muted small">
                <i class="fa-solid fa-arrows-up-down-left-right me-1"></i>Drag cards or use ↑ ↓ to reorder
            </span>
        @endif
    </div>

    <div wire:loading wire:target="newPropertyPhotos" class="mb-2">
        <div class="d-flex align-items-center gap-2 text-muted small">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            Uploading photos, please wait…
        </div>
    </div>
    @error('newPropertyPhotos.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    @error('newPropertyPhotos') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

    {{-- Gallery Grid --}}
    @if (!empty($propertyPhotos))
        <div id="photo-gallery-sortable-landlord" class="d-flex flex-wrap gap-3 mt-1">
            @foreach ($propertyPhotos as $index => $photo)
                <div data-filename="{{ $photo }}"
                    style="position:relative;width:185px;border:2px solid {{ $index === 0 ? '#049399' : '#ddd' }};border-radius:8px;overflow:hidden;padding:8px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.08);cursor:grab;">
                    @if ($index === 0)
                        <div class="text-center mb-1"
                            style="font-size:.67rem;font-weight:700;background:#049399;color:#fff;border-radius:4px;padding:3px 6px;letter-spacing:.05em;text-transform:uppercase;">
                            ⭐ Cover Photo
                        </div>
                    @else
                        <div style="height:22px;"></div>
                    @endif
                    <img src="{{ asset('storage/auction/images/' . $photo) }}"
                        style="width:100%;height:120px;object-fit:cover;border-radius:4px;" />
                    <div class="d-flex gap-1 mt-2">
                        <button type="button"
                            wire:click="movePhotoUp({{ $index }})"
                            @if($index === 0) disabled @endif
                            class="btn btn-outline-secondary btn-sm flex-fill"
                            title="Move up">↑</button>
                        <button type="button"
                            wire:click="movePhotoDown({{ $index }})"
                            @if($index === count($propertyPhotos) - 1) disabled @endif
                            class="btn btn-outline-secondary btn-sm flex-fill"
                            title="Move down">↓</button>
                    </div>
                    <button type="button"
                        wire:click="deletePropertyPhoto({{ $index }})"
                        wire:confirm="Are you sure you want to delete this photo?"
                        class="btn btn-danger btn-sm mt-1 w-100">
                        <i class="fa-solid fa-trash me-1"></i> Delete
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-4 mt-1"
            style="color:#bbb;border:1px dashed #e0e0e0;border-radius:8px;background:#fafafa;">
            <i class="fa-solid fa-image" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>
            <p class="mb-0 small" style="color:#aaa;">
                Add property photos to help Agents better understand and market the property.
            </p>
        </div>
    @endif
</div>

<!-- Video Tour URL -->
<div class="form-group mt-4">
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
    @if ($videoTourUrl)
        @php $landlordEmbedUrl = \App\Support\VideoEmbedHelper::getEmbedUrl($videoTourUrl); @endphp
        @if ($landlordEmbedUrl)
            <div class="ratio ratio-16x9 mt-2" style="max-width: 560px;">
                <iframe src="{{ $landlordEmbedUrl }}"
                        title="Video Tour Preview"
                        allowfullscreen
                        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                </iframe>
            </div>
        @else
            <p class="text-muted small mt-2"><i class="fa-solid fa-circle-exclamation me-1"></i> Preview not available for this URL.</p>
        @endif
    @endif
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


<script>
(function () {
    function initLandlordPhotoSortable() {
        var el = document.getElementById('photo-gallery-sortable-landlord');
        if (!el) return;
        if (el._sortableInstance) {
            el._sortableInstance.destroy();
            el._sortableInstance = null;
        }
        el._sortableInstance = Sortable.create(el, {
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd: function () {
                var items = el.querySelectorAll('[data-filename]');
                var order = [];
                items.forEach(function (item) {
                    order.push(item.getAttribute('data-filename'));
                });
                @this.call('reorderPhotos', order);
            }
        });
    }

    function initLandlordDropzone() {
        var dropzone = document.getElementById('landlord-photo-dropzone');
        var fileInput = document.getElementById('property-photos-input-landlord');
        if (!dropzone || !fileInput) return;

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.background = '#d4f2f4';
            dropzone.style.borderColor = '#027a7f';
        });
        dropzone.addEventListener('dragleave', function (e) {
            dropzone.style.background = '#f8fffe';
            dropzone.style.borderColor = '#049399';
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.style.background = '#f8fffe';
            dropzone.style.borderColor = '#049399';
            var files = e.dataTransfer.files;
            if (!files || files.length === 0) return;
            var dt = new DataTransfer();
            Array.from(files).forEach(function (f) { dt.items.add(f); });
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        });
    }

    document.addEventListener('livewire:load', function () {
        initLandlordPhotoSortable();
        initLandlordDropzone();
    });
    document.addEventListener('livewire:update', function () {
        initLandlordPhotoSortable();
        initLandlordDropzone();
    });
})();
</script>

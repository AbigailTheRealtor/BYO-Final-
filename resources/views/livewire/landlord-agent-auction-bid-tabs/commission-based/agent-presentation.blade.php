<h4>Agent Presentation & Promotional Materials</h4>
<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📣 Upload or link your presentation and marketing materials. This section may include your virtual Agent presentation, digital business card, and any marketing materials (e.g., flyers, brochures, or listing packets) that showcase your brand and experience. </strong>
        </div>
    </div>
</div>
<!-- Virtual Presentation Section -->
<div class="card mb-4 border-primary">

    <div class="card-header bg-primary text-white">
        <i class="fas fa-video me-2"></i> Virtual Agent Presentation
    </div>
    <div class="card-body">
        <!-- Video URL -->
        <div class="form-group mb-3">
            <label class="fw-bold">Virtual Agent Presentation(Link):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Paste a link to a short video introducing yourself or your services. You can use YouTube, Vimeo, or another video platform.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="url" wire:model="presentation_link" class="form-control has-icon"
                    placeholder="Enter virtual agent presentation link (e.g., https://youtube.com/example)"
                    data-icon="fa-solid fa-link">
            </div>
            <small class="text-muted">Enter YouTube, Vimeo, or other video platform URL</small>
        </div>

        @if(!empty($embedUrl))
        <div class="ratio ratio-16x9 mt-2" style="width:25%; height:40vh;">
            <iframe
                src="{{ $embedUrl }}"
                frameborder="0"
                allow="autoplay; accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen>
            </iframe>
        </div>
    @endif

        <!-- Video Upload -->
        <!-- <div class="form-group">
            <label class="fw-bold">Virtual Agent Presentation (Upload):</label>

            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Upload a video file (MP4, MOV, AVI, max 10MB) if you prefer to include the video directly instead of using a link.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="file" wire:model="video_upload" id="video-input" class="form-control" accept="video/*">
            </div>
            <small class="text-muted">Max 10MB (MP4, MOV, AVI)</small>
            <span id="video-error" class="text-danger" style="display: none;"></span>

        </div>

        @if (
            $video_upload &&
                $video_upload->getMimeType() &&
                strpos($video_upload->getMimeType(), 'video/') === 0 &&
                $video_upload->getSize() <= 10 * 1024 * 1024)
            {{-- <div class="col-md-6 col-6 pt-2 fw-bold">Uploaded Video:
                <span class="removeBold">
                    @if (is_string($video_upload))
                        <video controls style="width:80%;height:29vh;">
                            <source src="{{ asset('storage/auction/videos/' . $video_upload) }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    @else
                        <video controls style="width:80%;height:29vh;">
                            <source src="{{ $video_upload->temporaryUrl() }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    @endif
                </span>
            </div> --}}
            <div class="col-md-6 col-6 pt-2 fw-bold">Uploaded Video:
                <span class="removeBold">
                    @if (is_string($video_upload))
                        <video controls style="width:80%;height:29vh;">
                            <source src="{{ asset('storage/auction/videos/' . $video_upload) }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>

                        <span class="removeBold">
                            <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                                <source src="{{ $video->temporaryUrl() }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </span>
                    @else
                        <span class="removeBold">
                            <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                                <source src="{{ $video_upload->temporaryUrl() }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </span>
                    @endif
                </span>
            </div>
        @endif -->

    </div>
</div>

<!-- Business Card Section -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <i class="fas fa-address-card me-2"></i> Business Card
    </div>

    <div class="card-body">

        <div class="form-group mb-3">
            <label class="fw-bold">Business Card (Link):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Link to your virtual business card. Popular platforms include SavvyCard, Linq, HiHello, and Popl.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="url" wire:model="business_card_link" class="form-control has-icon"
                    placeholder="Enter business card link (e.g., https://www.savvycard.com/yourname)"
                    data-icon="fa-solid fa-link">
            </div>
            <small class="text-muted">Enter a link to your virtual business card (e.g., SavvyCard, HiHello, or
                Linq)</small>
        </div>

        <div class="form-group">

            <label class="fw-bold">Business Card (Upload):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Upload a static business card file (JPG, PNG, or WEBP). This should include your name, contact info, and branding.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="file" wire:model="business_card" class="form-control" accept="image/*,.pdf"
                    id="business-card">
            </div>
            <small class="text-muted">Upload your business card (JPG, PNG or WEBP)</small>

        </div>
    </div>

    <span id="business-card-error" class="text-danger" style="display: none;"></span>
    @if (
        $business_card && is_object($business_card) &&
            method_exists($business_card, 'getMimeType') &&
            $business_card->getMimeType() &&
            strpos($business_card->getMimeType(), 'image/') === 0 &&
            $business_card->getSize() <= 10 * 1024 * 1024)
        <!-- Preview of newly uploaded file -->
        <div class="col-md-6 col-6 pt-2 fw-bold" id="photo-preview" style="display: block; margin-left: 11px;">
            Uploaded Photo:
            <span class="removeBold">
                <img src="{{ $business_card->temporaryUrl() }}" style="width:80%;height:29vh;" />
            </span>
        </div>
    @elseif (!empty($business_card_stored_path))
        {{-- Saved from default profile --}}
        <div class="col-md-6 col-6 pt-2 fw-bold" style="margin-left: 11px;">
            Saved Business Card/Photo:
            <span class="removeBold">
                <img src="{{ asset('storage/' . $business_card_stored_path) }}" style="width:80%;height:29vh;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
                <a href="{{ asset('storage/' . $business_card_stored_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-1" style="display:none;">
                    <i class="fa-solid fa-file me-1"></i> View Saved File
                </a>
            </span>
            <small class="text-muted d-block mt-1">From your saved default profile — upload a new file above to replace it.</small>
        </div>
    @endif

</div>

<!-- Marketing Materials Section -->
<div class="card mb-4 border-info">
    <div class="card-header bg-info text-white">
        <i class="fas fa-bullhorn me-2"></i> Marketing Materials
    </div>

    <div class="card-body">

        @foreach ($promoMaterials as $idx => $item)
            <div class="border rounded p-3 mb-3 position-relative" wire:key="promo-material-{{ $idx }}">

                {{-- Marketing Material Type --}}
                <div class="form-group mb-3">
                    <label class="fw-bold">Marketing Material Type:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Select the type of marketing material you'd like to share. You can select multiple types and upload or link files for each.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <select wire:model="promoMaterials.{{ $idx }}.type" class="form-control has-icon"
                            data-icon="fa-solid fa-images" wire:loading.attr="disabled"
                            wire:target="promoMaterials.{{ $idx }}.files">
                            <option value="">Select</option>
                            <option value="Brochures">Brochures</option>
                            <option value="Digital Ads">Digital Ads</option>
                            <option value="Flyers">Flyers</option>
                            <option value="Listing Presentation">Listing Presentation</option>
                            <option value="Marketing Guides">Marketing Guides</option>
                            <option value="Neighborhood Report">Neighborhood Report</option>
                            <option value="Postcards">Postcards</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    @error('promoMaterials.' . $idx . '.type')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror

                    {{-- "Other" custom input --}}
                    @if (($promoMaterials[$idx]['type'] ?? '') === 'Other')
                        <div class="mt-2">
                            <input type="text" wire:model="promoMaterials.{{ $idx }}.other"
                                class="form-control"
                                placeholder="Enter marketing material type (e.g., Door Hanger, Event Invitation, Custom Branded Folder)"
                                wire:loading.attr="disabled" wire:target="promoMaterials.{{ $idx }}.files">
                        </div>
                        @error('promoMaterials.' . $idx . '.other')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    @endif
                </div>

                {{-- Marketing Material Type (Link) --}}
                <div class="form-group mb-3">
                    <label class="fw-bold">Marketing Material Type (Link):</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Paste a link to your marketing material hosted on platforms like Canva, Google Drive, Dropbox, or OneDrive.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="url" wire:model.defer="promoMaterials.{{ $idx }}.link"
                            class="form-control has-icon"
                            placeholder="Enter marketing material link (e.g., https://www.canva.com/yourdesign)"
                            data-icon="fa-solid fa-link" wire:loading.attr="disabled"
                            wire:target="promoMaterials.{{ $idx }}.files">
                    </div>
                    <small class="text-muted">
                        Enter a link to your marketing material (e.g., Canva, Google Drive, Dropbox, or other
                        file-sharing platforms).
                    </small>
                    @error('promoMaterials.' . $idx . '.link')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                </div>

                {{-- File Upload --}}
                @php
                    $typeLabel = $promoMaterials[$idx]['type'] ?? '';
                    $uploadLabel = $typeLabel === 'Other' ? 'Marketing Material Type (Upload)' : $typeLabel . ' Upload';
                @endphp

                <div class="form-group">
                    <label class="fw-bold">{{ $uploadLabel }}:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Upload files related to the selected material type (e.g., a flyer or brochure). Supported formats: PDF, JPG, PNG, DOC, PPT.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="file" wire:model="promoMaterials.{{ $idx }}.files"
                            class="form-control" multiple accept="image/*,.pdf,.doc,.docx,.ppt,.pptx"
                            id="promo-materials-{{ $idx }}">
                    </div>
                    <small class="text-muted">Upload one or more files (PDF, JPG, PNG, DOC, PPT)</small>
                    @error('promoMaterials.' . $idx . '.files')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                    @error('promoMaterials.' . $idx . '.files.*')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                    {{-- Saved file references from default profile --}}
                    @php $storedFiles = array_filter(is_array($promoMaterials[$idx]['files'] ?? null) ? $promoMaterials[$idx]['files'] : [], fn($f) => is_string($f) && !empty($f)); @endphp
                    @if (!empty($storedFiles))
                        <div class="mt-2">
                            <small class="text-muted fw-bold"><i class="fa-solid fa-folder-open me-1"></i> Saved files from default profile:</small>
                            <ul class="list-unstyled mb-0 mt-1">
                                @foreach ($storedFiles as $filePath)
                                    <li><a href="{{ asset('storage/' . $filePath) }}" target="_blank" class="small text-primary"><i class="fa-solid fa-file me-1"></i>{{ basename($filePath) }}</a></li>
                                @endforeach
                            </ul>
                            <small class="text-muted">Upload new file(s) above to add or replace these.</small>
                        </div>
                    @endif
                </div>

                {{-- Remove Button --}}
                <div class="d-flex justify-content-end mt-2">
                    @if (count($promoMaterials) > 1 && $idx > 0)
                        <button type="button" class="btn btn-outline-danger btn-sm"
                            wire:click="removeMaterial({{ $idx }})" wire:loading.attr="disabled"
                            wire:target="promoMaterials.{{ $idx }}.files">
                            Remove
                        </button>
                    @endif
                </div>

            </div>
        @endforeach

        {{-- Add New Entry --}}
        <button type="button" class="btn btn-outline-primary" wire:click="addMaterial" wire:loading.attr="disabled"
            wire:target="promoMaterials.*.files">
            + Add Another Marketing Material
        </button>
    </div>
</div>



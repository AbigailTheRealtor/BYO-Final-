<h4>Agent Presentation & Promotional Materials</h4>

{{-- Debug message display --}}
@if (session()->has('message'))
    <div class="alert alert-success mt-2 mb-3">
        {{ session('message') }}
    </div>
@endif

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>📣 Upload or link your presentation and marketing materials. This section may include your virtual Agent presentation, digital business card, and any marketing materials (e.g., flyers, brochures, or listing packets) that showcase your brand and experience.</strong>
        </div>
    </div>
</div>
<!-- Social Proof & Track Record -->
<div class="card mb-4 border-info">
    <div class="card-header bg-info text-white">
        <i class="fa-solid fa-trophy me-2"></i> Social Proof &amp; Track Record
    </div>
    <div class="card-body">
        <p class="text-muted mb-3 small">Highlight your client testimonials, achievements, past tenant placements, and search wins — so clients can evaluate your reputation.</p>

        <!-- Testimonial 1 -->
        <div class="form-group mb-3">
            <label class="fw-bold">Testimonial 1:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Share a short client testimonial or quote from a past tenant client. This is displayed on your bid to build trust.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="review_1" class="form-control" rows="2"
                placeholder="Enter Testimonial 1 (e.g., &quot;Found us a pet-friendly 2BR under asking with first month free. Best tenant agent we've worked with!&quot; — Emily R., Carrollwood, FL)"></textarea>
        </div>

        <!-- Testimonial 2 -->
        <div class="form-group mb-3">
            <label class="fw-bold">Testimonial 2:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Share a second client testimonial or quote.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="review_2" class="form-control" rows="2"
                placeholder="Enter Testimonial 2 (e.g., &quot;Relocated from out of state — our agent found us a great place before we even arrived. Incredible service!&quot; — James T., South Tampa, FL)"></textarea>
        </div>

        <!-- Testimonial 3 -->
        <div class="form-group mb-3">
            <label class="fw-bold">Testimonial 3:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Share a third client testimonial or quote.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="review_3" class="form-control" rows="2"
                placeholder="Enter Testimonial 3 (e.g., &quot;Negotiated our rent down $200/mo and got us out of a bad lease situation. Truly went above and beyond!&quot; — Sarah M., St. Petersburg, FL)"></textarea>
        </div>

        <!-- Client Reviews (Links) -->
        <div class="form-group mb-3">
            <label class="fw-bold">Client Reviews:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Link to your reviews on Zillow, Realtor.com, Google, or another platform. This helps clients build trust in your track record.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            @foreach($reviews_links as $index => $review)
                @php $reviewArr = is_array($review) ? $review : (is_object($review) ? (array) $review : ['text' => '']); @endphp
                <div class="d-flex align-items-center mb-2">
                    <div class="input-cover flex-grow-1">
                        <input type="url" wire:model="reviews_links.{{ $index }}.text"
                            class="form-control has-icon" data-icon="fa-solid fa-star"
                            placeholder="Enter Review Page URL (e.g., https://www.zillow.com/profile/yourname/reviews)">
                    </div>
                    @if(count($reviews_links) > 1)
                    <button type="button" wire:click="removeReviewLink({{ $index }})"
                        class="btn btn-outline-danger btn-sm ms-2"><i class="fa-solid fa-trash"></i></button>
                    @endif
                </div>
            @endforeach
            <button type="button" wire:click="addReviewLink" class="btn btn-sm btn-outline-secondary mt-1">
                <i class="fa-solid fa-plus me-1"></i> Add Another Review Link
            </button>
        </div>

        <!-- Awards & Recognition -->
        <div class="form-group mb-3">
            <label class="fw-bold">Awards &amp; Recognition:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Share any awards, designations, or industry recognition you've received (e.g., Top Tenant Rep, Five-Star Agent, Certified Relocation Specialist).">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="awards_recognition" class="form-control" rows="3"
                placeholder="Enter Awards & Recognition (e.g., Top Tenant Rep Agent 2023, Five-Star Agent on Zillow, Certified Relocation Specialist)"></textarea>
        </div>

        <!-- Recent Tenant Placements -->
        <div class="form-group mb-3">
            <label class="fw-bold">Recent Tenant Placements:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Describe 2–3 recent rentals you've helped tenants secure. Include property type, neighborhood, rent range, and any notable outcomes.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="sold_listed_examples" class="form-control" rows="3"
                placeholder="Enter Recent Tenant Placements (e.g., Helped a family relocate from NY, found a 3BR/2BA in Carrollwood at $2,200/mo with a month of free rent negotiated)"></textarea>
        </div>

    </div>
</div>

<!-- Agent Website & Social Media -->
<div class="card mb-4 border-secondary">
    <div class="card-header bg-secondary text-white">
        <i class="fa-solid fa-globe me-2"></i> Agent Website &amp; Social Media
    </div>
    <div class="card-body">
        <!-- Website Link -->
        <div class="form-group mb-3">
            <label class="fw-bold">Agent Website:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Enter the URL to your professional real estate website or agent profile page.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            @if(is_array($website_link))
                @foreach($website_link as $index => $link)
                <div class="d-flex align-items-center mb-2">
                    <div class="input-cover flex-grow-1">
                        <input type="url" wire:model="website_link.{{ $index }}"
                            class="form-control has-icon" data-icon="fa-solid fa-globe"
                            placeholder="Enter Website URL (e.g., https://yourwebsite.com)">
                    </div>
                    @if(count($website_link) > 1)
                    <button type="button" wire:click="removeWebsiteLink({{ $index }})"
                        class="btn btn-outline-danger btn-sm ms-2"><i class="fa-solid fa-trash"></i></button>
                    @endif
                </div>
                @endforeach
                <button type="button" wire:click="addWebsiteLink" class="btn btn-sm btn-outline-secondary mt-1">
                    <i class="fa-solid fa-plus me-1"></i> Add Another Website
                </button>
            @else
                <div class="input-cover">
                    <input type="url" wire:model="website_link"
                        class="form-control has-icon" data-icon="fa-solid fa-globe"
                        placeholder="Enter Website URL (e.g., https://yourwebsite.com)">
                </div>
            @endif
        </div>

        <!-- Social Media Profiles -->
        <div class="form-group">
            <label class="fw-bold">Social Media Profiles:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Add links to your professional social media profiles. These are optional but help clients verify your brand and online presence.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            @foreach($social_media as $index => $media)
                @php $mediaArr = is_object($media) ? (array) $media : (is_array($media) ? $media : []); @endphp
                <div class="input-group mb-2">
                    <select wire:model="social_media.{{ $index }}.platform"
                        wire:change="updatePlaceholder({{ $index }}, $event.target.value)"
                        class="form-select" style="max-width:140px;">
                        <option value="">Platform</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Instagram">Instagram</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="TikTok">TikTok</option>
                        <option value="X">X (Twitter)</option>
                        <option value="YouTube">YouTube</option>
                    </select>
                    <input type="url" wire:model="social_media.{{ $index }}.url"
                        class="form-control"
                        placeholder="{{ $mediaArr['placeholder'] ?? 'Enter Social Media Profile URL (e.g., https://)' }}">
                    @if(count($social_media) > 1)
                    <button type="button" wire:click="removeSocialMedia({{ $index }})"
                        class="btn btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                    @endif
                </div>
            @endforeach
            <button type="button" wire:click="addSocialMedia" class="btn btn-sm btn-outline-secondary mt-1">
                <i class="fa-solid fa-plus me-1"></i> Add Social Media
            </button>
        </div>
    </div>
</div>

<!-- Virtual Presentation Section -->
<div class="card mb-4 border-primary">

    <div class="card-header bg-primary text-white">
        <i class="fa-solid fa-video me-2"></i> Virtual Agent Presentation
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
                <input type="text" wire:model.lazy="presentation_link" class="form-control has-icon"
                    placeholder="Enter virtual agent presentation link (e.g., https://youtube.com/example)"
                    data-icon="fa-solid fa-link">
            </div>
            <small class="text-muted">Enter YouTube, Vimeo, or other video platform URL</small>
        </div>

        <!-- Video Embed Preview -->
        @if (!empty($presentation_link))
            @php
                $embedUrl = null;
                $isEmbeddable = false;
                
                // YouTube patterns
                if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $presentation_link, $ytMatches)) {
                    $embedUrl = 'https://www.youtube.com/embed/' . $ytMatches[1];
                    $isEmbeddable = true;
                }
                // Vimeo patterns
                elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $presentation_link, $vimeoMatches)) {
                    $embedUrl = 'https://player.vimeo.com/video/' . $vimeoMatches[1];
                    $isEmbeddable = true;
                }
            @endphp

            @if ($isEmbeddable && $embedUrl)
                <div class="mt-3 mb-3">
                    <label class="fw-bold text-muted mb-2">Video Preview:</label>
                    <div class="ratio ratio-16x9" style="max-width: 560px;">
                        <iframe src="{{ $embedUrl }}" 
                            title="Virtual Agent Presentation" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>
            @else
                <div class="mt-2 mb-3">
                    <a href="{{ $presentation_link }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Video Link
                    </a>
                </div>
            @endif
        @endif

    </div>
</div>

<!-- Business Card Section -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <i class="fa-solid fa-address-card me-2"></i> Business Card
    </div>

    <div class="card-body">

        <div class="form-group mb-3">
            <label class="fw-bold">Business Card (Link):</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Link to your virtual business card. Popular platforms include SavvyCard, Linq, HiHello, and Popl.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="input-cover">
                <input type="text" wire:model="business_card_link" class="form-control has-icon"
                    placeholder="Enter business card link (e.g., https://www.savvycard.com/yourname)"
                    data-icon="fa-solid fa-link">
            </div>
            <small class="text-muted">Enter a link to your virtual business card (e.g., SavvyCard, HiHello, or
                Linq)</small>
        </div>

        {{-- Display Existing Business Card --}}
        @if (!empty($existingBusinessCard))
            @php
                $bcFileName = basename($existingBusinessCard);
                $bcFileExt = strtolower(pathinfo($bcFileName, PATHINFO_EXTENSION));
                $bcIsImage = in_array($bcFileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            @endphp
            <div class="form-group mb-3">
                <label class="fw-bold text-success">
                    <i class="fa-solid fa-circle-check me-1"></i> Uploaded Business Card:
                </label>
                <div class="mt-2">
                    <div class="d-flex align-items-center justify-content-between border rounded p-2 bg-light">
                        <div class="d-flex align-items-center">
                            @if ($bcIsImage)
                                <img src="{{ asset('storage/' . $existingBusinessCard) }}" 
                                    alt="{{ $bcFileName }}" 
                                    class="me-2" 
                                    style="max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 4px;">
                            @else
                                <i class="fa-solid fa-file-{{ $bcFileExt === 'pdf' ? 'pdf text-danger' : 'alt text-secondary' }} fa-2x me-2"></i>
                            @endif
                            <div>
                                <span class="text-truncate d-block" style="max-width: 200px;" title="{{ $bcFileName }}">
                                    {{ Str::limit($bcFileName, 25) }}
                                </span>
                                <a href="{{ asset('storage/' . $existingBusinessCard) }}" target="_blank" class="small text-primary">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View
                                </a>
                            </div>
                        </div>
                        <button type="button" 
                            class="btn btn-outline-danger btn-sm" 
                            wire:click="removeExistingBusinessCard"
                            wire:loading.attr="disabled"
                            title="Remove this file">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif

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
        <i class="fa-solid fa-bullhorn me-2"></i> Marketing Materials
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
                        <input type="text" wire:model.defer="promoMaterials.{{ $idx }}.link"
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
                    // Get existing files (strings) from this material entry
                    $existingFiles = array_filter($promoMaterials[$idx]['files'] ?? [], 'is_string');
                @endphp

                {{-- Display Existing Uploaded Files --}}
                @if (!empty($existingFiles))
                    <div class="form-group mb-3">
                        <label class="fw-bold text-success">
                            <i class="fa-solid fa-circle-check me-1"></i> Uploaded Files:
                        </label>
                        <div class="mt-2">
                            @foreach ($promoMaterials[$idx]['files'] as $fileIdx => $file)
                                @if (is_string($file) && !empty($file))
                                    @php
                                        $fileName = basename($file);
                                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                        $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex align-items-center">
                                            @if ($isImage)
                                                <img src="{{ asset('storage/' . $file) }}" 
                                                    alt="{{ $fileName }}" 
                                                    class="me-2" 
                                                    style="max-width: 50px; max-height: 50px; object-fit: cover; border-radius: 4px;">
                                            @else
                                                <i class="fa-solid fa-file-{{ $fileExt === 'pdf' ? 'pdf text-danger' : 'alt text-secondary' }} fa-2x me-2"></i>
                                            @endif
                                            <div>
                                                <span class="text-truncate d-block" style="max-width: 200px;" title="{{ $fileName }}">
                                                    {{ Str::limit($fileName, 25) }}
                                                </span>
                                                <a href="{{ asset('storage/' . $file) }}" target="_blank" class="small text-primary">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View
                                                </a>
                                            </div>
                                        </div>
                                        <button type="button" 
                                            class="btn btn-outline-danger btn-sm" 
                                            wire:click="removeExistingFile({{ $idx }}, {{ $fileIdx }})"
                                            wire:loading.attr="disabled"
                                            title="Remove this file">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="form-group">
                    <label class="fw-bold">{{ $uploadLabel }}:</label>
                    <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                        title="Upload files related to the selected material type (e.g., a flyer or brochure). Supported formats: PDF, JPG, PNG, DOC, PPT.">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div class="input-cover">
                        <input type="file" wire:model="promoMaterials.{{ $idx }}.files"
                            wire:click="clearFileError({{ $idx }})"
                            class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.ppt,.pptx"
                            id="promo-materials-{{ $idx }}">
                    </div>
                    <small class="text-muted">Upload one or more files (PDF, JPG, JPEG, PNG, WEBP, DOC, PPT)</small>
                    <small class="text-info d-block"><i class="fa-solid fa-circle-info"></i> Tip: If upload fails, use the link field above instead.</small>
                    @error('promoMaterials.' . $idx . '.files')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
                    @error('promoMaterials.' . $idx . '.files.*')
                        <small class="text-danger d-block">{{ $message }}</small>
                    @enderror
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

<!-- Marketing Success Stories -->
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <i class="fa-solid fa-chart-line me-2"></i> Marketing Success Stories
    </div>
    <div class="card-body">
        <div class="form-group">
            <label class="fw-bold">Marketing Success Stories:</label>
            <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                title="Share examples of successful tenant searches or negotiations — e.g., securing a rental below asking or with special landlord concessions.">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <textarea wire:model.defer="marketing_success_examples" class="form-control" rows="3"
                placeholder="Enter Marketing Success Stories (e.g., Negotiated first month free + reduced deposit for a relocating client)"></textarea>
        </div>
    </div>
</div>

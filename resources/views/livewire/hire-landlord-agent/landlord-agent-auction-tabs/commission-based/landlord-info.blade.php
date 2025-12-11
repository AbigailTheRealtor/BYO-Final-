<h3> {{ ucfirst($user_type) }} Information</h3>

<div class="alert alert-info bg-light-info border-info mb-4">
    <div class="d-flex align-items-center">
        <div>
            <strong>👤 Provide your contact details. You may also upload a photo or video to personalize your request
                and help Agents better understand your needs.
            </strong>
        </div>
    </div>
</div>
<!-- First Name -->
<div class="form-group">
    <label class="fw-bold">First Name:<span class="text-danger">*</span></label>

    <div class="input-cover">
        <input type="text" wire:model="first_name" class="form-control has-icon" data-icon="fa-solid fa-user"
            placeholder="Enter first name">
    </div>
</div>

<!-- Last Name -->
<div class="form-group">
    <label class="fw-bold">Last Name:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="text" wire:model="last_name" class="form-control has-icon" data-icon="fa-solid fa-user"
            placeholder="Enter last name">
    </div>
</div>

<!-- Phone Number -->
<div class="form-group">
  <label class="fw-bold">Phone Number:<span class="text-danger">*</span></label>
  <div class="input-cover">
   <input
  type="text"
  id="phone_number"
  wire:model.defer="phone_number"
  class="form-control has-icon"
  data-icon="fa-solid fa-phone"
  placeholder="555-555-5555"
  inputmode="numeric"
  autocomplete="tel"
  maxlength="12"
  oninput="formatPhoneNumber(this)"
/>
  </div>
</div>

<script>
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    if (value.length >= 7) {
        input.value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6);
    } else if (value.length >= 4) {
        input.value = value.substring(0, 3) + '-' + value.substring(3);
    } else {
        input.value = value;
    }
}
</script>

<!-- Email -->
<div class="form-group">
    <label class="fw-bold">Email Address:<span class="text-danger">*</span></label>
    <div class="input-cover">
        <input type="email" wire:model="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
            placeholder="Enter email address ">
    </div>
</div>
@if ($service_type === 'full_service')

    <!-- Photo Upload -->
    <div class="form-group">
        <label class="fw-bold">
            Personal Photo:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Upload a photo of yourself to personalize your listing and help build trust with Agents.">
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <div class="input-group">
                <input type="file" wire:model="photo" id="photo-input" class="form-control has-icon"
                    data-icon="fas fa-camera" accept="image/*">
            </div>
        </div>
        <span id="photo-error" class="text-danger" style="display: none;"></span>
    </div>

    <!-- Display Uploaded Photo -->
    @if ($photo)
        <!-- Validate that it's an image and less than 10MB -->
        <div class="col-md-6 col-6 pt-2 fw-bold" id="photo-preview"
            style="width: 100%; max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
            Personal Photo:
            <span class="removeBold">
                <!-- Display the temporary uploaded photo -->
                @if (is_string($photo))
                    <!-- If $photo is a string (existing file path) -->
                    <img src="{{ asset('storage/auction/images/' . $photo) }}" style="width:100%;height:29vh;" />

                    @if ($auctionId)
                        <button wire:click="deletePhoto" wire:confirm="Are you sure you want to delete this photo?"
                            class="btn btn-danger btn-sm mt-2">
                            Delete Photo

                        </button>
                    @endif
                @else
                    <!-- If $photo is an UploadedFile object (newly uploaded file) -->
                    <img src="{{ $photo->temporaryUrl() }}" style="width:100%;height:29vh;" />

                    @if ($photo->temporaryUrl())
                        <button wire:click="deletePhoto" wire:confirm="Are you sure you want to delete this photo?"
                            class="btn btn-danger btn-sm mt-2">
                            Delete Photo

                        </button>
                    @endif
                @endif
            </span>
        </div>
    @endif

    <!-- Video Upload -->
    <!-- <div class="form-group">
        <label class="fw-bold">
            Personal Video:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Upload a short video that explains what the Tenant is looking for in an Agent.">
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <div class="input-group">
                <input type="file" wire:model="video" id="video-input" class="form-control has-icon"
                    data-icon="fas fa-video" accept="video/*">
            </div>
        </div>
        <span id="video-error" class="text-danger" style="display: none;"></span>
    </div>

    <div id="video-loader" wire:loading wire:target="video, photo"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9999; display: flex; justify-content: center; align-items: center; visibility: hidden;">
        <div style="text-align: center; color: white;">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h3 class="mt-3">Uploading...</h3>
            <p>Please wait while we process your files.</p>
        </div>
    </div>

    @if ($video)
        <div class="col-md-6 col-6 pt-2 fw-bold"
            style="width: 100%; max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
            Personal Video:
            <span class="removeBold">
                @if (is_string($video))
                   <span class="removeBold">
                                        <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                                            <source src="{{ asset('storage/auction/videos/' .$video) }}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </span>
                    @if ($auctionId)
                        <button wire:click="deleteVideo" wire:confirm="Are you sure you want to delete this video?"
                            class="btn btn-danger btn-sm mt-2">
                            Delete video
                        </button>
                    @endif
                @else
                    {{-- <video controls style="width:100%;height:29vh;">
                        <source src="{{ $video->temporaryUrl() }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video> --}}


                     <span class="removeBold">
                                        <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                                            <source src="{{ $video->temporaryUrl() }}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    </span>

                    @if ($video->temporaryUrl())
                        <button wire:click="deleteVideo" wire:confirm="Are you sure you want to delete this video?"
                            class="btn btn-danger btn-sm mt-2">
                            Delete video
                        </button>
                    @endif
                @endif
            </span>
        </div>
    @endif -->

       <!-- Video Link (YouTube/Vimeo) -->
    <div class="form-group">
        <label class="fw-bold">
            Personal Video Link:
            <span class="tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top"
                title="Paste a YouTube or Vimeo link that explains what the Tenant is looking for in an Agent.">
                {{-- 💬 --}}
                <i class="fa-solid fa-circle-info"></i>

            </span>
        </label>
        <div class="input-cover">
            <input type="url" wire:model="video_link" class="form-control has-icon"
                data-icon="fa-solid fa-video" placeholder="Enter video link (e.g. YouTube, Vimeo)">
                 <button class="btn btn-primary input-group-text-seller" type="button" wire:click="previewVideo">
            Enter
        </button>
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
    </div>

    <div class="alert alert-warning mt-3 p-2 small">
        <strong> 🛡️ Privacy Notice: </strong> Your last name, email address, and phone number are only visible to the platform admin. Only your first name and any uploaded photo or video will appear on the public listing. This ensures Agents contact you through the platform and protects your personal information.
    </div>
@endif

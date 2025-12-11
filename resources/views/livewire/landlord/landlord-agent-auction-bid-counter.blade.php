@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
<style>
    .wizard-steps-progress{height:5px;width:100%;background:#CCC;position:absolute;top:0;left:0}
    .steps-progress-percent{height:100%;width:0%;background:#11b7cf}
    .wizard-step{display:none}.wizard-step.active{display:block}
    .tab-content{padding:20px;border:1px solid #ddd;border-top:none}
    .nav-tabs .nav-link{border:1px solid #ddd;border-bottom:none;margin-right:5px;padding:10px 20px;background:#f8f9fa}
    .nav-tabs .nav-link.active{background:#fff;border-bottom:1px solid #fff}
    .form-group{margin-bottom:15px}.form-group label{font-weight:bold}.form-control{min-height:50px}
    .input-cover .form-control{padding-left:50px;width:100%}
    #bio,#why_hire_you,#what_sets_you_apart,#marketing_plan{padding:10px!important}
    .nav-tabs .nav-link.active{background:#049399!important;color:#fff!important;border-color:#049399!important}
    .input-cover{position:relative;display:flex;align-items:center}
    .input-cover .input-icon{position:absolute;left:10px;font-size:25px;color:#11b7cf;pointer-events:none;top:50%;transform:translateY(-50%)}
    .has-icon{padding-left:40px}
    .error{display:block;color:red;font-size:14px;margin-top:5px;width:100%}
    .d-none{display:none}.hidden{display:none}
    .badge{font-size:.9rem;padding:.5em .75em;display:inline-flex;align-items:center}
    .badge a{opacity:.7}.badge a:hover{opacity:1;text-decoration:none}
    .autocomplete-dropdown{position:absolute;z-index:1000;width:100%;max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:0 0 4px 4px;background:#fff}
    .autocomplete-dropdown .list-group-item{cursor:pointer;border:none;border-bottom:1px solid #eee}
    .autocomplete-dropdown .list-group-item:hover{background:#f8f9fa}
    /* Submit disabled look */
    #save-button.disabled{opacity:.5;cursor:not-allowed;pointer-events:none}
    .fee-option-card{transition:.3s;box-shadow:0 2px 4px rgba(0,0,0,.05)}
    .fee-option-card:hover{box-shadow:0 4px 8px rgba(0,0,0,.1)}
    .input-group-text{transition:.3s}
    .form-control:focus{border-color:#049399;box-shadow:0 0 0 .2rem rgba(4,147,153,.25)}
</style>
@endpush


<div class="container pt-5 pb-5">
  <div class="card">
    <div class="row">
      <div class="col-12 p-4">

        @if (session()->has('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session()->has('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form wire:submit.prevent="submit">
          @php
            $tabs = ['Broker Compensation and Agency Agreement','Additional Details'];
            $user_type = "tenant";
            $tabs[] = match (strtolower($user_type)) {
                'tenant' => 'Offered Services',
                'landlord' => 'Services the Landlord Requests from Their Agent',
                'seller' => 'Services the Seller Requests from Their Agent',
                'buyer' => 'Services the Buyer Requests from Their Agent',
                default => 'Services',
            };
          @endphp

          <ul class="nav nav-tabs" id="myTab" role="tablist">
            @foreach ($tabs as $index => $tab)
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                  wire:click="setActiveTab({{ $index }})"
                  id="{{ str_replace(' ', '-', strtolower($tab)) }}-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#{{ str_replace(' ', '-', strtolower($tab)) }}"
                  type="button"
                  role="tab"
                  aria-controls="{{ str_replace(' ', '-', strtolower($tab)) }}"
                  aria-selected="{{ $activeTab === $index ? 'true' : 'false' }}">
                  {{ $tab }}
                </button>
              </li>
            @endforeach
          </ul>

          <div class="tab-content mt-3">
            <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">
             @include('livewire.landlord-agent-auction-bid-tabs.commission-based.broker-compensation')

            </div>

            <input  type="hidden"  wire:mode="parent_counter_id"  value={{$parent_counter_id}} />
            <input  type="hidden"  wire:mode="bidId"  value={{$bidId}} />
            <input  type="hidden" wire:mode="auctionId" value={{$pab->id}} />



            <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.additional-details')
            </div>

            <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}" id="services">
                                @include('livewire.landlord-agent-auction-bid-tabs.commission-based.services')
            </div>
          </div>

          <div class="d-flex justify-content-between form-group mt-4">
            <div>
              <button type="button" class="btn btn-secondary wizard-step-back">Previous</button>
            </div>
            <div>
              <button type="button" class="btn btn-primary wizard-step-next">Next</button>

              <!-- Proper submit button -->
              <button type="submit"
                      id="save-button"
                      class="btn btn-success wizard-step-finish disabled"
                      wire:loading.attr="disabled">
                Submit
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

@push('scripts')



<script>
    function getErrorEl(input) {
        // Prefer explicit linkage via data-error-id; otherwise, fall back to nearest .form-group .error
        const byId = input.dataset.errorId && document.getElementById(input.dataset.errorId);
        if (byId) return byId;
        const group = input.closest('.form-group');
        return group ? group.querySelector('.error') : null;
    }

    // Allow digits, commas, and a single decimal point; format with commas; keep caret stable
    function validateInput(input) {
        const errorEl = getErrorEl(input);
        const oldVal = input.value;
        let caret = input.selectionStart;

        // Count commas before caret for later adjustment
        const commasBefore = (oldVal.slice(0, caret).match(/,/g) || []).length;

        // Keep only digits, commas, periods
        let v = oldVal.replace(/[^0-9.,]/g, '');

        // Only one decimal point
        const firstDot = v.indexOf('.');
        if (firstDot !== -1) {
            // remove any additional dots
            v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
        }

        // No leading dot
        if (v.startsWith('.')) v = v.slice(1);

        // Format with commas
        const formatted = formatNumberWithCommas(v);
        input.value = formatted;

        // Adjust caret by net change in commas before caret
        const commasAfter = (formatted.slice(0, caret).match(/,/g) || []).length;
        const delta = commasAfter - commasBefore;
        const newPos = Math.max(0, Math.min(formatted.length, caret + delta));
        input.setSelectionRange(newPos, newPos);

        // Error message if original had invalid chars
        if (/[^0-9.,]/.test(oldVal)) {
            errorEl && (errorEl.innerText =
                "Please enter a valid number. Use a period for decimals (e.g., 50,000.50). Letters and special characters are not permitted."
            );
        } else {
            errorEl && (errorEl.innerText = "");
        }
    }

    function formatNumberWithCommas(value) {
        const clean = value.replace(/,/g, '');
        const parts = clean.split('.');
        let intPart = parts[0] || '';
        const decPart = parts[1] !== undefined ? '.' + parts[1] : '';

        // insert commas in integer part
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return intPart + decPart;
    }

    function handlePaste(event) {
        event.preventDefault();
        const input = event.target;
        const errorEl = getErrorEl(input);

        let text = (event.clipboardData || window.clipboardData).getData('text');

        // Strip invalids
        text = text.replace(/[^0-9.,]/g, '');

        // Only one decimal point
        const firstDot = text.indexOf('.');
        if (firstDot !== -1) {
            text = text.slice(0, firstDot + 1) + text.slice(firstDot + 1).replace(/\./g, '');
        }

        // No leading dot
        if (text.startsWith('.')) text = text.slice(1);

        input.value = formatNumberWithCommas(text);
        errorEl && (errorEl.innerText = "");
        // Trigger validation formatting + caret fix once more
        validateInput(input);
    }

    function reformatNumber(input) {
        const errorEl = getErrorEl(input);
        let v = input.value.replace(/,/g, '');
        const parts = v.split('.');
        let intPart = parts[0] || '';
        let decPart = parts[1] || '';

        // Limit to two decimals on blur (optional; remove if you want unlimited)
        if (decPart) decPart = decPart.slice(0, 2);

        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = decPart ? `${intPart}.${decPart}` : intPart;

        errorEl && (errorEl.innerText = "");
    }
</script>
<script>



// =============== Tooltip Init ===============
let tooltipInstances = [];
function initializeTooltips() {
  destroyAllTooltips();
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipInstances = tooltipTriggerList.map(function (el) {
    const tooltip = new bootstrap.Tooltip(el, { trigger: 'hover focus', html: true });
    el.addEventListener('click', function (e) {
      e.stopPropagation(); tooltip.show(); setTimeout(() => tooltip.hide(), 3000);
    });
    return tooltip;
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-bs-toggle="tooltip"]')) hideAllTooltips();
  });
}
function destroyAllTooltips(){ tooltipInstances.forEach(t => t.dispose && t.dispose()); tooltipInstances = []; }
function hideAllTooltips(){ tooltipInstances.forEach(t => t.hide && t.hide()); }

// =============== Icon Injection ===============
window.addIconsToInputs = function (root = document) {
  root.querySelectorAll('.has-icon:not([data-icon-initialized="1"])').forEach(input => {
    const iconClass = input.getAttribute('data-icon'); if (!iconClass) return;
    const wrapper = input.closest('.input-cover') || input.parentElement; if (!wrapper) return;
    if (getComputedStyle(wrapper).position === 'static') wrapper.style.position = 'relative';
    const icon = document.createElement('i'); icon.className = `input-icon ${iconClass}`;
    wrapper.insertBefore(icon, input);
    input.setAttribute('data-icon-initialized','1');
  });
};

// =============== Validation ===============
function checkFieldValidity(field){
  if(!field.required) return true;
  const value = field.value;
  if(!value) return false;
  if(field.type==='number' && field.hasAttribute('min') && parseInt(value)<parseInt(field.getAttribute('min'))) return false;
  if(field.type==='url' && value && !value.startsWith('http')) return false;
  if(field.type==='email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return false;
  return true;
}
function validateFieldWithErrors(field){
  if(!field.required) return true;
  const isValid = checkFieldValidity(field);
  let errorSpan = document.getElementById(`${field.id}_error`);
  if(!errorSpan){
    errorSpan = document.createElement('span');
    errorSpan.className='error mt-2 text-danger';
    errorSpan.id = `${field.id}_error`;
    // safer append near the field
    const container = field.closest('.form-group') || field.parentNode;
    (container || field.parentNode).appendChild(errorSpan);
  }
  if(!isValid){
    field.classList.add('is-invalid');
    errorSpan.textContent = 'This field is required';
    return false;
  } else {
    field.classList.remove('is-invalid');
    errorSpan.textContent='';
    return true;
  }
}
function validateServicesTab(currentTab){
  if(!currentTab || currentTab.id!=='services') return true;
  let isValid = true;
  // remove previous error
  currentTab.querySelectorAll('.service-error').forEach(e => e.remove());

  const hasServices = currentTab.querySelectorAll('input[type="checkbox"][wire\\:model="services"]:checked:not(#other-services-checkbox)').length>0;
  const otherCheckbox = currentTab.querySelector('#other-services-checkbox');

  // Services validation removed - selecting services is now optional
  return true;
}
function validateCurrentTabWithErrors(){
  const currentTab = document.querySelector('.tab-pane.active');
  if(!currentTab) return true;
  let isValid = true, firstInvalid = null;
  currentTab.querySelectorAll('[required]').forEach(field=>{
    if(!validateFieldWithErrors(field)){ isValid=false; if(!firstInvalid) firstInvalid=field; }
  });
  if(currentTab.id==='services') isValid = isValid && validateServicesTab(currentTab);
  if(firstInvalid) firstInvalid.scrollIntoView({behavior:'smooth', block:'center'});
  return isValid;
}
function checkAllTabsValidity(){ return [...document.querySelectorAll('[required]')].every(checkFieldValidity); }
function updateSaveButton(){
  const saveButton = document.getElementById('save-button');
  if(!saveButton) return;
  if(checkAllTabsValidity()){
    saveButton.classList.remove('disabled'); saveButton.disabled=false;
  } else {
    saveButton.classList.add('disabled'); saveButton.disabled=true;
  }
}

// =============== Phone Formatter stub ===============
function initPhoneFormatter(){ /* your phone init here (kept as stub) */ }

// =============== Init ===============
document.addEventListener('DOMContentLoaded', function () {
  initializeTooltips();
  window.addIconsToInputs();
  initPhoneFormatter();
  updateSaveButton();
});

// Re-run UI initializers after Livewire updates DOM
document.addEventListener('livewire:load', function () {
  initializeTooltips();
  window.addIconsToInputs();
  initPhoneFormatter();
  updateSaveButton();
});
if (typeof Livewire !== 'undefined') {
  Livewire.hook('message.processed', () => {
    setTimeout(() => {
      initializeTooltips();
      window.addIconsToInputs();
      initPhoneFormatter();
      updateSaveButton();
    }, 10);
  });
}

// =============== Event Delegation (survives re-renders) ===============
document.addEventListener('click', function(e){
  // Next
  const nextBtn = e.target.closest('.wizard-step-next');
  if(nextBtn){
    e.preventDefault();
    if(validateCurrentTabWithErrors()){
      const links = [...document.querySelectorAll('.nav-link')];
      const current = document.querySelector('.nav-link.active');
      const idx = links.indexOf(current);
      const next = links[idx+1];
      if(next) next.click();
      document.querySelector('.tab-content')?.scrollIntoView({behavior:'smooth'});
    }
  }
  // Back
  const backBtn = e.target.closest('.wizard-step-back');
  if(backBtn){
    e.preventDefault();
    const links = [...document.querySelectorAll('.nav-link')];
    const current = document.querySelector('.nav-link.active');
    const idx = links.indexOf(current);
    const prev = links[idx-1];
    if(prev) prev.click();
    document.querySelector('.tab-content')?.scrollIntoView({behavior:'smooth'});
  }
});

// Delegate input/blur so new Livewire fields are validated
document.addEventListener('input', function(e){
  if(e.target.matches('input, textarea, select')){ checkFieldValidity(e.target); updateSaveButton(); }
});
document.addEventListener('blur', function(e){
  if(e.target.matches('input, textarea, select')){ checkFieldValidity(e.target); updateSaveButton(); }
}, true);
</script>



@endpush

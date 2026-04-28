@props([
    'listingType',
    'listingId',
    'isOwner'   => false,
    'aiFaq'     => [],
    'shareToken'=> null,
])

@php
    $answeredCount = 0;
    $questionGroups = [];

    if ($listingType === 'tenant') {
        $questions = config('tenant_ai_faq.questions', []);
        $byCategory = [];
        foreach ($questions as $q) {
            $cat = $q['category'] ?? 'General';
            if (!isset($byCategory[$cat])) $byCategory[$cat] = [];
            $byCategory[$cat][$q['key']] = $q['label'];
        }
        foreach ($byCategory as $cat => $items) {
            $answers = [];
            foreach ($items as $key => $label) {
                $val = trim($aiFaq[$key] ?? '');
                if ($val !== '') {
                    $answers[] = ['label' => $label, 'answer' => $val];
                    $answeredCount++;
                }
            }
            if (!empty($answers)) {
                $questionGroups[] = ['category' => $cat, 'answers' => $answers];
            }
        }
    } else {
        $configKey = 'ai_faq_' . $listingType;
        $configGroups = config($configKey . '.questions', []);
        foreach ($configGroups as $cat => $questions) {
            $answers = [];
            foreach ($questions as $key => $label) {
                $val = trim($aiFaq[$key] ?? '');
                if ($val !== '') {
                    $answers[] = ['label' => $label, 'answer' => $val];
                    $answeredCount++;
                }
            }
            if (!empty($answers)) {
                $questionGroups[] = ['category' => $cat, 'answers' => $answers];
            }
        }
    }

    $shareUrl = $shareToken ? url('/ai-knowledge/' . $shareToken) : null;
@endphp

@if ($isOwner)
<div class="card border-0 shadow-sm mb-4" id="ai-knowledge-base-card">
    <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-robot me-2"></i>
            <span class="fw-bold fs-6">AI Knowledge Base</span>
        </div>
        @if ($answeredCount > 0)
            <span class="badge bg-success rounded-pill">{{ $answeredCount }} answer{{ $answeredCount !== 1 ? 's' : '' }}</span>
        @else
            <span class="badge bg-secondary rounded-pill">No answers yet</span>
        @endif
    </div>
    <div class="card-body">

        {{-- Share Link Section --}}
        <div class="mb-3">
            <p class="text-muted small mb-2">
                <i class="fa-solid fa-lock me-1"></i>
                The <strong>AI Data Link</strong> is a private, token-secured URL that returns your knowledge base answers as structured JSON — for use with AI assistants, chatbots, or integrations. Only answered questions are included. Contact info and compensation details are never exposed.
            </p>

            @if ($shareUrl)
                <div class="input-group mb-2">
                    <input type="text"
                           id="ai-share-url-{{ $listingId }}"
                           class="form-control form-control-sm font-monospace"
                           value="{{ $shareUrl }}"
                           readonly>
                    <button class="btn btn-outline-secondary btn-sm"
                            type="button"
                            onclick="copyAiDataLink('{{ $listingId }}')"
                            title="Copy AI Data Link">
                        <i class="fa-regular fa-copy me-1"></i>Copy Link
                    </button>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ $shareUrl }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Preview JSON
                    </a>
                    <button type="button"
                            class="btn btn-outline-warning btn-sm"
                            onclick="regenerateAiToken('{{ $listingType }}', {{ $listingId }})"
                            title="Rotate this token and generate a new link. The old link will stop working.">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Rotate Token
                    </button>
                </div>
            @else
                <div>
                    <button type="button"
                            class="btn btn-success btn-sm"
                            onclick="generateAiToken('{{ $listingType }}', {{ $listingId }})"
                            @if($answeredCount === 0) disabled title="Add at least one answer before generating a link" @endif>
                        <i class="fa-solid fa-link me-1"></i>Generate AI Data Link
                    </button>
                    @if ($answeredCount === 0)
                        <span class="text-muted small ms-2">Complete at least one AI question to enable this.</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Knowledge Base Review Accordion --}}
        @if ($answeredCount > 0)
            <hr>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold small text-muted text-uppercase" style="letter-spacing:.05em">Review Answered Questions</span>
                <button class="btn btn-link btn-sm p-0 text-decoration-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#ai-kb-review-{{ $listingId }}"
                        aria-expanded="false">
                    <i class="fa-solid fa-chevron-down me-1"></i><span class="toggle-label">Show</span>
                </button>
            </div>
            <div class="collapse" id="ai-kb-review-{{ $listingId }}">
                @foreach ($questionGroups as $group)
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">{{ $group['category'] }}</h6>
                        @foreach ($group['answers'] as $item)
                            <div class="mb-2">
                                <div class="small fw-semibold text-secondary">{{ $item['label'] }}</div>
                                <div class="small text-dark bg-light rounded px-2 py-1 mt-1">{{ $item['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-light border mb-0 small py-2">
                <i class="fa-solid fa-circle-info me-1 text-muted"></i>
                No AI knowledge base answers have been saved yet. Edit this listing to add answers to the AI Questions section.
            </div>
        @endif

    </div>
</div>

{{-- Inline JS for this component --}}
<script>
function copyAiDataLink(listingId) {
    var input = document.getElementById('ai-share-url-' + listingId);
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        showAiKbToast('AI Data Link copied to clipboard!', 'success');
    }).catch(function() {
        input.select();
        document.execCommand('copy');
        showAiKbToast('AI Data Link copied!', 'success');
    });
}

function generateAiToken(listingType, listingId) {
    postAiToken('{{ route('ai.knowledge.generate') }}', listingType, listingId);
}

function regenerateAiToken(listingType, listingId) {
    if (!confirm('Rotating the token will invalidate the current link. Any AI tools using the old URL will stop working. Continue?')) return;
    postAiToken('{{ route('ai.knowledge.regenerate') }}', listingType, listingId);
}

function postAiToken(url, listingType, listingId) {
    var token = document.querySelector('meta[name="csrf-token"]');
    var csrf = token ? token.getAttribute('content') : '';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ listing_type: listingType, listing_id: listingId }),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.url) {
            showAiKbToast('AI Data Link generated! Reloading...', 'success');
            setTimeout(function() { window.location.reload(); }, 900);
        } else {
            showAiKbToast(data.error || 'Failed to generate link.', 'danger');
        }
    })
    .catch(function() {
        showAiKbToast('Request failed. Please try again.', 'danger');
    });
}

function showAiKbToast(message, type) {
    var container = document.getElementById('ai-kb-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ai-kb-toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
        document.body.appendChild(container);
    }
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' alert-dismissible shadow-sm py-2 px-3 mb-2';
    toast.innerHTML = message + '<button type="button" class="btn-close btn-sm" onclick="this.parentElement.remove()"></button>';
    container.appendChild(toast);
    setTimeout(function() { if (toast.parentElement) toast.remove(); }, 4000);
}

// Toggle chevron on the review collapse
(function() {
    var collapseEl = document.getElementById('ai-kb-review-{{ $listingId }}');
    if (collapseEl) {
        collapseEl.addEventListener('show.bs.collapse', function() {
            var btn = document.querySelector('[data-bs-target="#ai-kb-review-{{ $listingId }}"]');
            if (btn) {
                btn.querySelector('i').classList.replace('fa-chevron-down', 'fa-chevron-up');
                var lbl = btn.querySelector('.toggle-label');
                if (lbl) lbl.textContent = 'Hide';
            }
        });
        collapseEl.addEventListener('hide.bs.collapse', function() {
            var btn = document.querySelector('[data-bs-target="#ai-kb-review-{{ $listingId }}"]');
            if (btn) {
                btn.querySelector('i').classList.replace('fa-chevron-up', 'fa-chevron-down');
                var lbl = btn.querySelector('.toggle-label');
                if (lbl) lbl.textContent = 'Show';
            }
        });
    }
})();
</script>
@endif

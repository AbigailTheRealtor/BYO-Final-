{{--
    Phase 5 — why Your Home Taste moved THIS listing, in words.

    `$explanation` is TasteRerankExplanation's output: a headline and at most two
    already-worded sentences, or null. It carries no score, weight, percentage,
    key or id, so nothing internal can be printed here. Null (the listing did not
    move, or moved only because a neighbour did) renders nothing.
--}}
@if(is_array($explanation ?? null) && ! empty($explanation['lines']))
    <div class="small text-muted border-top pt-2 mt-2" data-taste-rerank-explanation>
        <div class="fw-semibold"><i class="fas fa-sliders-h me-1" aria-hidden="true"></i>{{ $explanation['headline'] }}</div>
        <ul class="mb-0 ps-3">
            @foreach($explanation['lines'] as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
@endif

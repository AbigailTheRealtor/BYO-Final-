@props(['title'])
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header section-header">
        <h4 class="section-title">{{ $title }}</h4>
    </div>
    <div class="card-body">
        <div class="row">
            {{ $slot }}
        </div>
    </div>
</div>

@foreach ($sections as $sectionTitle => $fields)
<div class="section">
    <div class="section-title">{{ $sectionTitle }}</div>
    <table class="fields">
        @foreach ($fields as $label => $value)
        <tr>
            <td class="label">{{ $label }}:</td>
            <td>
                @php
                    $items = is_string($value) ? explode(', ', $value) : [$value];
                @endphp
                @if (count($items) > 1)
                    @foreach ($items as $item)
                        <span class="chip">{{ trim($item) }}</span>
                    @endforeach
                @else
                    {{ $value }}
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</div>
@endforeach

@if (!empty($services))
<div class="section">
    <div class="section-title">Services</div>
    <ul class="services-list">
        @foreach ($services as $service)
            @if (!empty(trim($service)))
            <li>{{ trim($service) }}</li>
            @endif
        @endforeach
    </ul>
</div>
@endif

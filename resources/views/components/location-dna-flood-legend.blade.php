{{--
  Flood Zone Legend Partial
  =========================
  Rendered below the map div when flood zone overlay data is available.

  Props:
    $floodZoneLegend – array of unique zone designation strings (e.g. ["AE", "VE", "X"])
--}}
@php
  /**
   * Returns display metadata for a FEMA flood zone designation.
   * Matches the color logic in the companion JavaScript renderFloodZones() helper.
   */
  function floodZoneMeta(string $zone): array
  {
      $z = strtoupper($zone);
      if ($z === 'X' || str_starts_with($z, 'X')) {
          return [
              'bg'     => '#dcfce7',
              'color'  => '#15803d',
              'border' => '#86efac',
              'label'  => 'Minimal Risk',
          ];
      }
      if ($z === 'VE' || $z === 'V' || (strlen($z) > 1 && str_starts_with($z, 'V'))) {
          return [
              'bg'     => '#fee2e2',
              'color'  => '#b91c1c',
              'border' => '#fca5a5',
              'label'  => 'Coastal High-Hazard',
          ];
      }
      if (str_starts_with($z, 'A')) {
          return [
              'bg'     => '#ffedd5',
              'color'  => '#c2410c',
              'border' => '#fdba74',
              'label'  => 'Special Flood Hazard',
          ];
      }
      return [
          'bg'     => '#f1f5f9',
          'color'  => '#475569',
          'border' => '#cbd5e1',
          'label'  => 'Other/Undetermined',
      ];
  }
@endphp

@if(!empty($floodZoneLegend))
<div class="ldna-flood-legend">
  <span class="ldna-flood-legend-title">
    <i class="fa-solid fa-water me-1"></i> FEMA Flood Zones:
  </span>
  @foreach($floodZoneLegend as $zone)
    @php $meta = floodZoneMeta($zone); @endphp
    <span class="ldna-flood-chip"
          style="background:{{ $meta['bg'] }};color:{{ $meta['color'] }};border-color:{{ $meta['border'] }};"
          title="{{ $meta['label'] }}">
      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;
                   background:{{ $meta['color'] }};opacity:.8;flex-shrink:0;"></span>
      Zone {{ $zone }}
    </span>
  @endforeach
  <span style="font-size:.75rem;color:#78716c;margin-left:auto;">
    Source: FEMA NFHL
  </span>
</div>
@endif

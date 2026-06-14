{{--
  School District Legend Partial
  ==============================
  Rendered below the map div when school district overlay data is available.

  Props:
    $schoolDistrictLegend – array of unique district name strings
--}}
@if(!empty($schoolDistrictLegend))
<div class="ldna-school-district-legend">
  <span class="ldna-school-district-legend-title">
    <i class="fa-solid fa-school me-1"></i> School Districts:
  </span>
  @foreach($schoolDistrictLegend as $name)
    <span class="ldna-school-district-chip">
      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;
                   background:#1d4ed8;opacity:.75;flex-shrink:0;"></span>
      {{ $name }}
    </span>
  @endforeach
  <span style="font-size:.75rem;color:#64748b;margin-left:auto;">
    Source: U.S. Census TIGER/Line
  </span>
</div>
@endif

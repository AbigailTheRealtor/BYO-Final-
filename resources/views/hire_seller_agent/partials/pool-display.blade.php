@php
    $poolVal = @$auction->get->pool_needed;
    $poolTypeData = @$auction->get->pool_type;
    $poolTypes = [];
    if ($poolTypeData) {
        $decoded = is_string($poolTypeData) ? (json_decode($poolTypeData, true) ?? []) : (array)$poolTypeData;
        if (!empty($decoded)) {
            $first = reset($decoded);
            if (is_bool($first) || $first === '1' || $first === 1 || $first === '0' || $first === 0 || $first === true || $first === false) {
                foreach ($decoded as $key => $val) {
                    if ($val && $val !== '0' && $val !== 0 && $val !== false) {
                        $poolTypes[] = ucfirst($key);
                    }
                }
            } else {
                $poolTypes = array_values($decoded);
            }
        }
    }
    $poolDisplay = \App\Helpers\ListingDisplayHelper::formatYesList($poolVal, $poolTypes);
@endphp
<div class="col-md-12 col-12 pt-2 fw-bold">
    Pool:
    <span class="removeBold">{{ $poolDisplay }}</span>
</div>

<div class="col-md-12 col-12 pt-2 fw-bold">
    Pool:<span class="removeBold"> {{ @$auction->get->pool_needed }}@if (@$auction->get->pool_needed === 'Yes')@php
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
        @endphp @if (!empty($poolTypes))({{ implode(', ', $poolTypes) }})@endif @endif</span>
</div>

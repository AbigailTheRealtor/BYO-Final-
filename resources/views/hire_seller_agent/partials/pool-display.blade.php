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
{{--
    S2 — the Pool row, converted with the rest of Seller's fields.

    $redesign IS PASSED EXPLICITLY BY BOTH CALL SITES rather than inherited. @include shares the
    parent's scope, so $hsaDetailRedesign would in fact be visible here — but this partial is
    included from two places in the property section, and a partial that silently depends on a
    variable the caller happens to have is one rename away from rendering the legacy branch on a
    redesigned page with no error. The `?? false` keeps it inert for any caller that forgets.

    The pool-type decoding above is untouched: it still handles both storage shapes the
    questionnaire has produced — a checkbox map of type => bool, and a plain list of type names.
--}}
<x-hire-agent.field :redesign="$redesign ?? false" label="Pool" :value="$poolDisplay" />

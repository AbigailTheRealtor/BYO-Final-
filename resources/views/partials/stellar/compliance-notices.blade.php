{{--
    Governed compliance notices, rendered directly beneath the line they belong to.

    Formatting only: the TEXT is never written here. It is the taxonomy definition's
    own `compliance.notice`, carried on a result entry as `notices` by
    BuyerMatchResultBuilder and on a picker option as `notice`. Today only the pet
    policy tag declares one — "Assistance animals are not pets and are not governed
    by pet policies." — so every pet-policy line shows it and no other line does.

    Optional: $notices (list<string>).
--}}
@foreach (($notices ?? []) as $complianceNotice)
    <small class="d-block text-muted mt-1" data-compliance-notice>{{ $complianceNotice }}</small>
@endforeach

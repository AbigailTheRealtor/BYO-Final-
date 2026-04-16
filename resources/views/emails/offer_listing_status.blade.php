@component('mail::message')
# Offer Listing {{ ucfirst($status) }}

@if ($status === 'approved')
Your offer listing **"{{ $listing->title ?? 'your listing' }}"** has been approved and is now live.
@else
Your offer listing **"{{ $listing->title ?? 'your listing' }}"** has been reviewed and unfortunately was not approved at this time.
@endif

If you have any questions, please don't hesitate to reach out.

Thanks,<br>
{{ config('app.name') }}
@endcomponent

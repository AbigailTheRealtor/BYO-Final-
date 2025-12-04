@component('mail::message')
 @foreach ($prefered_agents as $agent)
    <p>Hey {{ $agent['name'] }}! Mr {{ $seller_name }} Notifies You</p>
@endforeach
Thanks,<br>
{{ config('app.name') }}
@endcomponent

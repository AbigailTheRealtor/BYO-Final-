@props([
    'avatar'  => null,
    'alt'     => 'Agent avatar',
])

@php
    $avatarFallback = 'https://ppt1080.b-cdn.net/images/avatar/none.png';
    if ($avatar && (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0 || strpos($avatar, '/') === 0)) {
        $avatarSrc = $avatar;
    } elseif ($avatar) {
        $avatarSrc = asset('images/avatar/' . $avatar);
    } else {
        $avatarSrc = $avatarFallback;
    }
@endphp

<img
    src="{{ $avatarSrc }}"
    onerror="this.onerror=null; this.src='{{ $avatarFallback }}';"
    alt="{{ $alt }}"
    {{ $attributes }}
>

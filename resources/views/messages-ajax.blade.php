<li>
    <a href="{{ $auction_link }}" target="_blank">
        <div class="card fw-bold p-4">{{ $chat_title }}</div>
    </a>
</li>
@foreach ($current_token->chats as $item)
    @php
        if ($item->user_id == auth()->id()) {
            $class = 'sent';
        } else {
            $class = 'replies';
        }
    @endphp
    <li class="{{ $class }}">
        <p class="ms-2">{{ $item->message }}</p>
    </li>
@endforeach

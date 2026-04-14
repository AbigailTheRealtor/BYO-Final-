<li>
    <a href="{{ $auction_link }}" target="_blank">
        <div class="card fw-bold p-4">{{ $chat_title }}</div>
    </a>
</li>
@foreach ($current_token->chats->where('is_bot', 0)->sortBy('created_at') as $item)
    @php
        $class = ($item->user_id == auth()->id()) ? 'sent' : 'replies';
    @endphp
    <li class="{{ $class }}">
        <p class="ms-2">{{ $item->message }}</p>
    </li>
@endforeach

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionChatUnread extends Model
{
    use HasFactory;

    public function token()
    {
        return $this->belongsTo(AuctionChatToken::class, 'auction_chat_token_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function chat()
    {
        return $this->belongsTo(AuctionChat::class, 'auction_chat_id', 'id');
    }
}

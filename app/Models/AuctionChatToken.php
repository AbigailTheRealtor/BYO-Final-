<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuctionChatToken extends Model
{
    use HasFactory;

    public function auction()
    {
        return $this->morphTo();
    }

    public function chat_users()
    {
        return $this->hasMany(AuctionChatUser::class);
    }

    public function chats()
    {
        return $this->hasMany(AuctionChat::class);
    }
}

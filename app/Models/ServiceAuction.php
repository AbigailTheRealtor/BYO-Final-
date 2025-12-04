<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAuction extends Model
{
    use HasFactory;

    public function bot_questions()
    {
        return $this->morphMany(BotQuestion::class, 'auction');
    }
}

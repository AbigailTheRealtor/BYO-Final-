<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function creating(User $user)
    {
        if (empty($user->short_id)) {
            $user->short_id = uniqid();
        }
        
        if (empty($user->user_name)) {
            $user->user_name = $user->email ?? uniqid('user_');
        }
        
        if (empty($user->phone_number)) {
            $user->phone_number = $user->phone ?? '';
        }
    }

    public function retrieved(User $user)
    {
        if (empty($user->short_id)) {
            $user->short_id = uniqid();
            $user->save();
        }
    }
}

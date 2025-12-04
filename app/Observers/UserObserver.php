<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function retrieved(User $user)
    {
        if ($user->short_id == "") {
            $user->short_id = uniqid();
            $user->save();
        }
    }

    public function created(User $user)
    {
        $user->short_id = uniqid();
        $user->save();
    }
}

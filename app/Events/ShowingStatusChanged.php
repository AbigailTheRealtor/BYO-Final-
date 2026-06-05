<?php

namespace App\Events;

use App\Models\Showing;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// TODO: Wire up listeners in the Notifications task.
class ShowingStatusChanged
{
    use Dispatchable, SerializesModels;

    public Showing $showing;
    public string  $previousStatus;
    public User    $actor;

    public function __construct(Showing $showing, string $previousStatus, User $actor)
    {
        $this->showing        = $showing;
        $this->previousStatus = $previousStatus;
        $this->actor          = $actor;
    }
}

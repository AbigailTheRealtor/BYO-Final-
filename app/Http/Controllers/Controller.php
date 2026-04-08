<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Previously auto-transitioned Bidding Period listings to Pending on timer expiry.
     * Now neutralized: the Bidding Period timer is informational only and must not
     * create a blocking status. Kept as a no-op to preserve call-site compatibility.
     */
    protected function autoTransitionBpToPending($auction): void
    {
        // No-op: timer expiry no longer auto-blocks bid actions or listing status.
    }
}

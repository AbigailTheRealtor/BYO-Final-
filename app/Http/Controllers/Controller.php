<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // Milestone 3: autoTransitionBpToPending() was removed from this base controller.
    //
    // It once flipped a Bidding Period listing to Pending when its countdown elapsed — timer
    // completion mutating listing status. An earlier change had already neutralised it to a no-op
    // kept only for call-site compatibility; this checkpoint retires the Hire Agent timer, so the
    // four Hire Agent detail controllers that called it no longer do, and the shell goes with
    // them. Those four were its only callers.
}

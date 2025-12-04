<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Http\Controllers\Controller;
use App\Models\BuyerCriteriaAuction;
use Illuminate\Support\Facades\Auth;
use App\Models\TenantCriteriaAuction;

class NotificationController extends Controller
{
    //

    public function notification(Request $request){
            $user=Auth::user();
            $notifications=$user->notifications;
            $properties_tenant=TenantCriteriaAuction::all();
            $buyer_criteria=BuyerCriteriaAuction::all();
            $landlord_auction=LandlordAuction::all();
            $html=(string)view('partial_view.notification',compact('notifications','user','properties_tenant','buyer_criteria','landlord_auction'));
            $count=$notifications->count(0);
            return response()->json([
                'message'=>'200',
                'html'=>$html,
                'count'=>$count,

            ]);
    }
}

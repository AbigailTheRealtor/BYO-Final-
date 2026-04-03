<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function fetch(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications
            ->sortByDesc('created_at')
            ->take(20)
            ->values();

        return response()->json($notifications);
    }

    public function go(Request $request, $notificationId)
    {
        $user = Auth::user();
        
        $notification = $user->notifications()->where('id', $notificationId)->first();
        
        if (!$notification) {
            abort(403, 'Notification not found or access denied.');
        }
        
        $data = $notification->data;
        $type = $data['type'] ?? 'general';
        $bidId = $data['bid_id'] ?? null;
        $auctionId = $data['auction_id'] ?? null;
        $summaryLink = $data['summary_link'] ?? null;
        $auctionType = $data['auction_type'] ?? null;
        
        $destination = $this->resolveDestination($type, $bidId, $auctionId, $summaryLink, $user, $auctionType);
        
        return redirect($destination);
    }

    private function resolveDestination($type, $bidId, $auctionId, $summaryLink, $user, $auctionType = null)
    {
        switch ($type) {
            case 'bid_accepted':
            case 'counter_bid_accepted':
                if ($summaryLink) {
                    return $summaryLink;
                }
                return route('dashboard');
                
            case 'bid_countered':
            case 'counter_bid_submitted':
                if ($bidId) {
                    if ($auctionType === 'landlord_agent') {
                        if ($user->user_type === 'agent') {
                            return route('landlord.hire.agent.auction.bid.view-counter', $bidId);
                        } else {
                            return route('landlord.agent.auction.view', $auctionId) . '?view=counter&bid_id=' . $bidId;
                        }
                    }
                    if ($user->user_type === 'agent') {
                        return route('tenant.hire.agent.auction.bid.view-counter', $bidId);
                    } else {
                        return route('tenant.agent.auction.view', $auctionId) . '?view=counter&bid_id=' . $bidId;
                    }
                }
                return route('dashboard');
                
            case 'bid_submitted':
            case 'bid_received':
            case 'bid_modified':
                if ($auctionId) {
                    if ($auctionType === 'landlord_agent') {
                        return route('landlord.agent.auction.view', $auctionId) . '?view=bids';
                    }
                    return route('tenant.agent.auction.view', $auctionId) . '?view=bids';
                }
                return route('dashboard');
                
            case 'bid_rejected':
                if ($auctionId) {
                    return route('tenant.agent.auction.view', $auctionId);
                }
                return route('dashboard');
                
            default:
                if ($auctionId) {
                    return route('tenant.agent.auction.view', $auctionId);
                }
                return route('dashboard');
        }
    }

    public function dismiss(Request $request, $notificationId)
    {
        $user = Auth::user();
        
        $notification = $user->notifications()->where('id', $notificationId)->first();
        
        if (!$notification) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Notification not found'], 404);
            }
            return redirect()->back()->with('error', 'Notification not found.');
        }
        
        $notification->markAsRead();
        
        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }
        
        return redirect()->back()->with('success', 'Notification dismissed.');
    }

    // public function markRead(Request $request)
    // {
    //     $notification = $request->user()
    //         ->unreadNotifications
    //         ->where('id', $request->id)
    //         ->first();

    //     if ($notification) $notification->markAsRead();

    //     return response()->json(['status' => 'success']);
    // }

    // public function markAllRead(Request $request)
    // {
    //     $request->user()->unreadNotifications->markAsRead();

    //     return response()->json(['status' => 'success']);
    // }


    public function markRead(Request $request)
    {
        $notification = $request->user()
            ->unreadNotifications()
            ->where('id', $request->id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
            $notification->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success']);
        }
        
        return redirect()->back()->with('success', 'Notification dismissed.');
    }


    public function markAllRead(Request $request)
    {
        $notifications = $request->user()->unreadNotifications;

        foreach ($notifications as $notification) {
            $notification->markAsRead();
            $notification->delete();   // DELETE each one
        }

        return response()->json(['status' => 'success']);
    }
}

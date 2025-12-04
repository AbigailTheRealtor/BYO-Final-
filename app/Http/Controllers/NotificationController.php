<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            $notification->delete();   // DELETE after marking as read
        }

        return response()->json(['status' => 'success']);
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

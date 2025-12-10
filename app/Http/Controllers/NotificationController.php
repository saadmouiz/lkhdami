<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);
        
        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }
        
        $notification->update(['lu' => true]);
        
        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('lu', false)
            ->update(['lu' => true]);
        
        return redirect()->back()->with('success', 'Toutes les notifications ont été marquées comme lues');
    }

    public function getUnreadCount()
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('lu', false)
            ->count();
        
        return response()->json(['count' => $count]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, $id)
    {
        $notification = DatabaseNotification::findOrFail($id);

        $this->authorize('update', $notification);

        $notification->markAsRead();

        return redirect()
            ->route('notifications.index');
    }
}

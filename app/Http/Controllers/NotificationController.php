<?php

namespace App\Http\Controllers;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()
            ->get();

        return view('notifications.index', compact('notifications'));
    }
}

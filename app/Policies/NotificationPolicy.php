<?php

namespace App\Policies;

use Illuminate\Notifications\DatabaseNotification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationPolicy
{
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return (int) $user->id === (int) $notification->notifiable_id;
    }
}

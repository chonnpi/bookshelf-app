<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return (string) $notification->notifiable_id === (string) $user->id;
    }
}

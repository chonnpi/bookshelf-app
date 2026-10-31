<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingReminder extends Notification
{
    use Queueable;

    protected $readingPlan;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReadingPlan $readingPlan)
    {
        $this->readingPlan = $readingPlan;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reading_plan_id' => $this->readingPlan->id,
            'book_title' => $this->readingPlan->book->title,
            'message' => "「{$this->readingPlan->book->title}」の読書期限まであと3日です！",
        ];
    }
}

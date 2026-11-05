<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReadingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $readingPlan;

    protected $timing;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReadingPlan $readingPlan, string $timing)
    {
        $this->readingPlan = $readingPlan;
        $this->timing = $timing;
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
            'target_date' => $this->readingPlan->target_date,
            'message' => "「{$this->readingPlan->book->title}」の読書期限まであと3日です!",
        ];
    }
}

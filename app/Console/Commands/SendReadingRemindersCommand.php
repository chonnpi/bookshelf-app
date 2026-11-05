<?php

namespace App\Console\Commands;

use App\Models\ReadingPlan;
use App\Notifications\ReadingReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReadingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-reading-reminders-command';

    public function handle()
    {
        $today = Carbon::today();

        $targetDate = [
            '3日前通知' => $today->copy()->addDays(3),
            '当日通知' => $today,
            '3日後通知' => $today->copy()->subDays(3),
        ];

        foreach ($targetDate as $timing => $date) {
            $plans = ReadingPlan::where('target_date', $date->toDateString())
                ->whereNotIn('status', ['completed', 'expired'])
                ->with('user')
                ->get();
            foreach ($plans as $plan) {
                $plan->user->notify(new ReadingReminderNotification($plan, $timing));
            }
        }
        $this->info('リマインダー通知の送信が完了しました');
    }
}

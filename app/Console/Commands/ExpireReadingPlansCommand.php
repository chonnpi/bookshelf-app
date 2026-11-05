<?php

namespace App\Console\Commands;

use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireReadingPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-reading-plans-command';

    public function handle()
    {
        $today = Carbon::today();

        ReadingPlan::where('target_date', '<', '$today')
            ->whereNotIn('status', ['completed', 'expire'])
            ->update(['status' => 'expired']);

        $this->info('期限切れの読書計画を自動執行しました');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\ReadingPlan;
use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::today();


        $bookIds = Book::pluck('id')->toArray();

        // 1. target_date = 3日後 / status = 'in_progress' → 3日前リマインダー対象
        ReadingPlan::create([
            'id' => 1,
            'user_id' => 1, // 山田太郎のID
            'book_id' => $bookIds[0],
            'target_date' => $today->copy()->addDays(3),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // 2. target_date = 当日 / status = 'in_progress' → 当日リマインダー対象
        ReadingPlan::create([
            'id' => 2,
            'user_id' => 1,
            'book_id' => $bookIds[1],
            'target_date' => $today->copy(),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // 3. target_date = 3日前 / status = 'in_progress' → バッチで Auto-expire 化 + 3日後再エンゲージメント対象
        ReadingPlan::create([
            'id' => 3,
            'user_id' => 1,
            'book_id' => $bookIds[2],
            'target_date' => $today->copy()->subDays(3),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // 4. target_date = 7日後 / status = 'in_progress' → リマインダー対象外
        ReadingPlan::create([
            'id' => 4,
            'user_id' => 1,
            'book_id' => $bookIds[3],
            'target_date' => $today->copy()->addDays(7),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // 5. target_date = 10日前 / status = 'completed' / completed_at = 5日前 → 完了済み
        ReadingPlan::create([
            'id' => 5,
            'user_id' => 1,
            'book_id' => $bookIds[4],
            'target_date' => $today->copy()->subDays(10),
            'status' => 'completed',
            'completed_at' => $today->copy()->subDays(5),
        ]);

        // 6. target_date = 5日後 / status = 'in_progress' → 403認可確認用
        ReadingPlan::create([
            'id' => 6,
            'user_id' => 2,
            'book_id' => $bookIds[5],
            'target_date' => $today->copy()->addDays(5),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }
}
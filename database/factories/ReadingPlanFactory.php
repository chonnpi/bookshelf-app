<?php

namespace Database\Factories;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * ファクトリに対応するモデル名
     */
    protected $model = ReadingPlan::class;

    /**
     * モデルのデフォルト状態の定義
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // リレーションのあるモデルのファクトリを指定すると、自動で紐づく親データも生成されます
            'user_id' => User::factory(),
            'book_id' => Book::factory(),

            // 未来の日付をランダムに生成（読書計画の目標日として適切にするため）
            'target_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),

            // デフォルトのステータスは「未読」にしておきます
            'status' => ReadingPlanStatus::Unread,

            // 完了日時はデフォルトでnull（未読・読書中のため）
            'completed_at' => null,
        ];
    }
}

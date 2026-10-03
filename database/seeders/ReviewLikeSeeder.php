<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviews = Review::with('user')->get();
        $userIds = User::pluck('id');
        foreach ($reviews as $review) {
            $randomCount = rand(0, 3);
            if ($randomCount === 0) {
                continue;
            }

            $eligibleUserIds = $userIds->reject(function ($id) use ($review) {
                return $id === $review->user_id;
            });

            $finalCount = min($randomCount, $eligibleUserIds->count());

            if ($finalCount > 0) {
                $randomUserIds = $eligibleUserIds->random($finalCount);
                $review->likeUsers()->syncWithoutDetaching($randomUserIds);
            }
        }
    }
}

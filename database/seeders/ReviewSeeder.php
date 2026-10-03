<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        $distribution = [2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3];

        $reviewCounter = 0;

        foreach ($books as $index => $book) {
            $count = $distribution[$index];

            for ($i = 0; $i < $count; $i++) {
                $userId = $users->get($reviewCounter % $users->count())->id;

                Review::factory()->create([
                    'user_id' => $userId,
                    'book_id' => $book->id,
                    'rating' => rand(3, 5),
                    'comment' => fake()->realText(100),
                ]);
                $reviewCounter++;
            }
        }
    }
}

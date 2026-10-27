<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_ranking_page(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
    }

    public function test_index_displays_top_10_books_sorted_by_avg_rating_and_review_count(): void
    {
        $bookWithoutReviews = Book::factory()->create();

        $bookRank3 = Book::factory()->create();
        Review::factory()->create(['book_id' => $bookRank3->id, 'rating' => 3]);

        $bookRank2 = Book::factory()->create();
        Review::factory()->create(['book_id' => $bookRank2->id, 'rating' => 5]);

        $bookRank1 = Book::factory()->create();
        Review::factory()->create(['book_id' => $bookRank1->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $bookRank1->id, 'rating' => 5]);

        $extraBooks = Book::factory()->count(9)->create();
        foreach ($extraBooks as $extraBook) {
            Review::factory()->create(['book_id' => $extraBook->id, 'rating' => 2]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($bookRank1, $bookRank2, $bookRank3) {
            if ($rankedBooks->count() !== 10) {
                return false;
            }

            $orderedIds = $rankedBooks->pluck('id')->all();

            return $orderedIds[0] === $bookRank1->id
                && $orderedIds[1] === $bookRank2->id
                && $orderedIds[2] === $bookRank3->id;
        });
    }
}

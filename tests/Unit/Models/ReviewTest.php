<?php

namespace Tests\Unit\Models;

use App\Models\book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->assertTrue($review->user->is($user));
    }

    public function test_review_belongs_to_book(): void
    {
        $book = book::factory()->create();
        $review = Review::factory()->for($book)->create();

        $this->assertTrue($review->book->is($book));
    }

    public function test_review_belongs_to_many_liked_by_users(): void
    {
        $users = User::factory()->count(2)->create();
        $review = Review::factory()->hasAttached($users, [], 'likedByUsers')->create();

        $this->assertCount(2, $review->likedByUsers);
        $this->assertTrue($review->likedByUsers->contains($users->first()));
    }
}

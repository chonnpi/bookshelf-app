<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_registered_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->for($user)->create();

        $this->assertCount(2, $user->registeredBooks);
        $this->assertTrue($user->registeredBooks->first()->is($books->first()));
    }

    public function test_user_has_many_my_reviews(): void
    {
        $user = User::factory()->create();
        $reviews = Review::factory()->count(2)->for($user)->create();

        $this->assertCount(2, $user->myReviews);
        $this->assertTrue($user->myReviews->first()->is($reviews->first()));
    }

    public function test_user_belongs_to_many_liked_reviews(): void
    {
        $reviews = Review::factory()->count(2)->create();
        $user = User::factory()->hasAttached($reviews, [], 'likedReviews')->create();

        $this->assertCount(2, $user->likedReviews);
        $this->assertTrue($user->likedReviews->contains($reviews->first()));
    }

    public function test_user_belongs_to_many_favorite_books(): void
    {
        $books = Book::factory()->count(2)->create();
        $user = User::factory()->hasAttached($books, [], 'favoriteBooks')->create();

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->contains($books->first()));
    }
}

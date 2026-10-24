<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertTrue($book->user->is($user));
    }

    public function test_book_belongs_to_many_genres(): void
    {
        $genres = Genre::factory()->count(2)->create();
        $book = Book::factory()->hasAttached($genres)->create();

        $this->assertCount(2, $book->genres);
        $this->assertTrue($book->genres->contains($genres->first()));
    }

    public function test_book_has_many_reviews(): void
    {
        $book = Book::factory()->create();
        $reviews = Review::factory()->count(2)->for($book)->create();

        $this->assertCount(2, $book->reviews);
        $this->assertTrue($book->reviews->first()->is($reviews->first()));
    }

    public function test_book_belongs_to_many_favorite_users(): void
    {
        $users = User::factory()->count(2)->create();
        $book = Book::factory()->hasAttached($users, [], 'favoriteUsers')->create();

        $this->assertCount(2, $book->favoriteUsers);
        $this->assertTrue($book->favoriteUsers->contains($users->first()));
    }
}

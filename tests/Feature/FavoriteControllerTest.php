<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_logged_in_user_favorites_with_pagination(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $favoriteBooks = Book::factory()->count(11)->create();
        foreach ($favoriteBooks as $book) {
            $user->favoriteBooks()->attach($book->id);
        }

        $otherBook = Book::factory()->create();
        $otherUser->favoriteBooks()->attach($otherBook->id);

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertOk();
        $response->assertViewIs('favorites.index');

        $response->assertViewHas('books', function ($books) use ($otherBook) {
            $isCountCorrect = $books->count() === 10;
            $doesNotContainOther = ! $books->contains($otherBook);

            return $isCountCorrect && $doesNotContainOther;
        });
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_toggle_attaches_favorite_when_not_registered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'お気に入りに登録しました');
    }

    public function test_toggle_detaches_favorite_when_already_registered(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->attach($book->id);

        $response = $this->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'お気に入りを解除しました');
    }

    public function test_toggle_redirects_guest_to_login(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('login'));
    }
}

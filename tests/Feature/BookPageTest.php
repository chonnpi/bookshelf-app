<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_index_page_is_accessible(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('books.index');
        $response->assertViewHas('books');
    }

    public function test_authenticated_user_is_redirected_when_accessing_login_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');
        $response->assertRedirect('/');
    }

    public function test_book_index_page_displays_books_with_genres(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->hasAttached($genre)->create();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($book->title);
        $response->assertSee($book->author);
        $response->assertSee($genre->name);
    }
}

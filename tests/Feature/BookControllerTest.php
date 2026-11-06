<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_paginated_books_with_genres_and_handles_search_and_sort(): void
    {
        $genreA = Genre::factory()->create();
        $genreB = Genre::factory()->create();

        $oldBook = Book::factory()->create(['title' => 'C_Book_Old', 'author' => '山田太郎', 'created_at' => now()->subDays(2)]);
        $newBook = Book::factory()->create(['title' => 'A_Book_New', 'author' => '佐藤次郎', 'created_at' => now()]);
        $midBook = Book::factory()->create(['title' => 'B_Book_Mid', 'author' => '鈴木三郎', 'created_at' => now()->subDay()]);

        $oldBook->genres()->attach($genreA);
        $newBook->genres()->attach($genreB);

        Review::factory()->create(['book_id' => $newBook->id, 'rating' => 5]);
        Review::factory()->create(['book_id' => $midBook->id, 'rating' => 3]);

        $response = $this->get(route('books.index'));
        $response->assertOk()
            ->assertViewIs('books.index')
            ->assertViewHas('books')
            ->assertViewHas('genres');

        $response = $this->get(route('books.index', ['keyword' => 'Laravel']));
        $response->assertDontSee('C_Book_Old');

        $response = $this->get(route('books.index', ['keyword' => '山田']));
        $response->assertSee('C_Book_Old')->assertDontSee('A_Book_New');

        $response = $this->get(route('books.index', ['genre' => $genreA->id]));
        $response->assertSee('C_Book_Old')->assertDontSee('A_Book_New');

        $response = $this->get(route('books.index'));
        $this->assertEquals([$newBook->id, $midBook->id, $oldBook->id], $response->viewData('books')->pluck('id')->toArray());

        $response = $this->get(route('books.index', ['sort' => 'oldest']));
        $this->assertEquals([$oldBook->id, $midBook->id, $newBook->id], $response->viewData('books')->pluck('id')->toArray());

        $response = $this->get(route('books.index', ['sort' => 'title']));
        $this->assertEquals(['A_Book_New', 'B_Book_Mid', 'C_Book_Old'], $response->viewData('books')->pluck('title')->toArray());

        $response = $this->get(route('books.index', ['sort' => 'rating']));
        $this->assertEquals([$newBook->id, $midBook->id, $oldBook->id], $response->viewData('books')->pluck('id')->toArray());
    }

    public function test_create_returns_view_with_genres_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->get(route('books.create'));

        $response->assertOk();
        $response->assertViewIs('books.create');
        $response->assertViewHas('genres');
    }

    public function test_create_redirects_guest_to_login(): void
    {
        $response = $this->get(route('books.create'));
        $response->assertRedirect(route('login'));
    }

    public function test_fetch_by_isbn_returns_formatted_data_on_success(): void
    {
        $user = User::factory()->create();
        $isbn = '9784798157573';

        config([
            'services.google.books_api_url' => 'https://googleapis.com',
            'services.google.books_api_key' => 'fake-key',
        ]);

        Http::fake([
            'https://googleapis.com/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'テスト駆動開発',
                            'authors' => ['Kent Beck', '和田 卓人'],
                            'description' => 'TDDの解説書。',
                            'publishedDate' => '2017-10-24',
                            'imageLinks' => [
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson("/books/isbn/{$isbn}");
        $response->assertStatus(200)
            ->assertJson([
                'title' => 'テスト駆動開発',
                'author' => 'Kent Beck, 和田 卓人',
                'published_date' => '2017-10-24',
            ]);
    }

    public function test_fetch_by_isbn_handles_incomplete_published_date(): void
    {
        $user = User::factory()->create();
        $isbn = '9784798157573';

        config([
            'services.google.books_api_url' => 'https://googleapis.com',
            'services.google.books_api_key' => 'fake-key',
        ]);

        Http::fake([
            'https://googleapis.com/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '古い書籍',
                            'publishedDate' => '2017',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson("/books/isbn/{$isbn}");

        $response->assertStatus(200)
            ->assertJson([
                'title' => '古い書籍',
                'published_date' => '',
            ]);
    }

    public function test_fetch_by_isbn_fails_validation_if_not_13_digits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/books/isbn/123456789012')
            ->assertStatus(400)
            ->assertJson(['error' => '正しい13桁のISBNコードを入力してください。']);
    }

    public function test_store_saves_book_and_syncs_genres(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $validData = [
            'title' => 'テスト書籍タイトル',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026/01/01',
            'description' => 'これはテスト用の説明文です。',
            'image_url' => 'https://example.com',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $validData);

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍タイトル',
            'user_id' => $user->id,
        ]);

        $book = Book::where('title', 'テスト書籍タイトル')->first();
        $this->assertTrue($book->genres->contains($genre->id));

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を登録しました！');
    }

    public function test_store_fails_if_title_is_missing(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $invalidData = [
            'title' => '',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026/01/01',
            'description' => 'テスト説明',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->from(route('books.create'))->post(route('books.store'), $invalidData);

        $this->assertDatabaseMissing('books', [
            'author' => 'テスト著者',
        ]);

        $response->assertSessionHasErrors(['title']);

        $response->assertRedirect(route('books.create'));
    }

    public function test_store_fails_if_isbn_is_invalid_length(): void
    {
        $user = User::factory()->create();

        $invalidData = [
            'title' => 'テストタイトル',
            'author' => 'テスト著者',
            'isbn' => '123',
            'published_date' => '2026/01/01',
        ];

        $response = $this->actingAs($user)->post(route('books.store'), $invalidData);
        $response->assertSessionHasErrors(['isbn']);
    }

    public function test_show_displays_single_book_with_relations(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewIs('books.show');
        $response->assertViewHas('book');
        $response->assertSee($book->title);
    }

    public function test_edit_returns_view_for_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('books.edit', $book));

        $response->assertOk();
        $response->assertViewIs('books.edit');
    }

    public function test_edit_redirects_non_owner_with_error(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('books.edit', $book));

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('error', '自分が登録した書籍以外は編集できません');
    }

    public function test_edit_redirects_guest_to_login(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.edit', $book));

        $response->assertRedirect(route('login'));
    }

    public function test_update_modifies_book_for_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'title' => '古いタイトル']);
        $genre = Genre::factory()->create();

        $updateData = [
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9876543210123',
            'published_date' => '2026/01/02',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($user)->put(route('books.update', $book), $updateData);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '新しいタイトル',
        ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', '書籍を更新しました！');
    }

    public function test_update_fails_if_title_is_missing(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => '古いタイトル',
        ]);

        $invalidData = [
            'title' => '',
            'author' => '新しい著者',
        ];

        $response = $this->actingAs($user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $invalidData);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '古いタイトル',
        ]);

        $response->assertSessionHasErrors(['title']);

        $response->assertRedirect(route('books.edit', $book));
    }

    public function test_update_redirects_non_owner_with_error(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
            'title' => '元のタイトル',
        ]);

        $updateData = [
            'title' => '他人が勝手に変えようとしたタイトル',
            'author' => '新しい著者',
            'isbn' => '1234567890123',
            'published_date' => '2026/01/01',
            'genres' => [$genre->id],
        ];

        $response = $this->actingAs($otherUser)
            ->put(route('books.update', $book), $updateData);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '元のタイトル',
        ]);

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('error', '自分が登録した書籍以外は更新できません');
    }

    public function test_update_redirects_guest_to_login(): void
    {
        $book = Book::factory()->create(['title' => '元のタイトル']);

        $updateData = [
            'title' => 'ログインなしで変えようとしたタイトル',
            'author' => '新しい著者',
        ];

        $response = $this->put(route('books.update', $book), $updateData);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '元のタイトル',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_book_for_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        $this->assertModelMissing($book);

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました！');
    }

    public function test_destroy_prevents_non_owner_from_deleting(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        $this->assertModelExists($book);

        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('error', '自分が登録した書籍以外は削除できません');
    }
}

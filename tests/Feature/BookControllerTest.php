<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_paginated_books_with_genres(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->hasAttached($genre)->create();

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertViewIs('books.index');
        $response->assertViewHas('books');
        $response->assertSee($book->title);
        $response->assertSee($genre->name);
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

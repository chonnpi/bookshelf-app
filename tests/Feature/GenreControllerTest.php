<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_genres_with_books_count(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $books = Book::factory()->count(2)->create();
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        $response = $this->actingAs($user)->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');

        $response->assertViewHas('genres', function ($genres) use ($genre) {
            return $genres->first()->id === $genre->id && $genres->first()->books_count === 2;
        });
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get(route('genres.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_create_returns_view(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('genres.create'));

        $response->assertOk();
        $response->assertViewIs('genres.create');
    }

    public function test_store_saves_new_genre(): void
    {
        $user = User::factory()->create();
        $genreData = ['name' => 'SF・ファンタジー'];

        $response = $this->actingAs($user)
            ->post(route('genres.store'), $genreData);

        $this->assertDatabaseHas('genres', $genreData);
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを登録しました！');
    }

    public function test_store_redirects_guest_to_login(): void
    {
        $response = $this->post(route('genres.store'), ['name' => '未ログイン登録']);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('genres', ['name' => '未ログイン登録']);
    }

    public function test_store_fails_when_name_is_required(): void
    {
        $user = User::factory()->create();

        $invalidData = ['name' => ''];

        $response = $this->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), $invalidData);

        $response->assertSessionHasErrors(['name']);

        $response->assertRedirect(route('genres.create'));

        $this->assertDatabaseMissing('genres', ['name' => '']);
    }

    public function test_show_displays_genre_with_paginated_books(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '技術書']);

        $books = Book::factory()->count(11)->create();
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.show');
        $response->assertViewHas('genre', $genre);
        $response->assertSee('技術書');
        $response->assertViewHas('books', function ($books) {
            return $books->count() === 10
                && $books->total() === 11
                && $books->hasMorePages();
        });
    }

    public function test_show_redirects_guest_to_login(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.show', $genre));

        $response->assertRedirect(route('login'));
    }

    public function test_edit_returns_view_with_genre_data(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'SF小説']);

        $response = $this->actingAs($user)->get(route('genres.edit', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.edit');
        $response->assertViewHas('genre', $genre);

        $response->assertSee('SF小説');
    }

    public function test_edit_redirects_guest_to_login(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->get(route('genres.edit', $genre));

        $response->assertRedirect(route('login'));
    }

    public function test_update_modifies_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '古いジャンル名']);
        $updateData = ['name' => '新しいジャンル名'];

        $response = $this->actingAs($user)
            ->put(route('genres.update', $genre), $updateData);

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '新しいジャンル名',
        ]);
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを更新しました！');
    }

    public function test_update_redirects_guest_to_login(): void
    {
        $genre = Genre::factory()->create(['name' => '元のジャンル名']);

        $response = $this->put(route('genres.update', $genre), [
            'name' => '勝手に書き換えようとしたジャンル名',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '元のジャンル名',
        ]);
    }

    public function test_update_fails_when_name_is_required(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '元のジャンル名']);

        $response = $this->actingAs($user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), [
                'name' => '',
            ]);

        $response->assertRedirect(route('genres.edit', $genre));

        $response->assertSessionHasErrors(['name']);

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '元のジャンル名',
        ]);
    }

    public function test_destroy_deletes_genre_when_it_has_no_books(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを削除しました！');
    }

    public function test_destroy_fails_when_genre_has_books(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create();
        $book->genres()->attach($genre->id);

        $response = $this->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $this->assertDatabaseHas('genres', ['id' => $genre->id]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('error', 'このジャンルには書籍が登録されているため削除できません');
    }

    public function test_destroy_redirects_guest_to_login(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->delete(route('genres.destroy', $genre));
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;

use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_books_with_reviews_and_genres(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre->id);

        Review::factory()->create(['book_id' => $book->id, 'rating' => 4]);
        Review::factory()->create(['book_id' => $book->id, 'rating' => 5]);

        $response = $this->getJson('/api/v1/books');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'author',
                    'reviews_count',
                    'average_rating',
                    'genres' => [
                        '*' => ['id', 'name'],
                    ],
                ],
            ],
        ]);

        $response->assertJsonPath('data.0.reviews_count', 2);
        $response->assertJsonPath('data.0.average_rating', 4.5);
        $response->assertJsonPath('data.0.genres.0.id', $genre->id);
    }

    public function test_index_filters_by_keyword(): void
    {
        $targetBook1 = Book::factory()->create(['title' => 'Laravelの教科書', 'author' => '山田']);
        $targetBook2 = Book::factory()->create(['title' => 'PHP入門', 'author' => 'Laravel太郎']);
        $otherBook = Book::factory()->create(['title' => 'Rubyの本', 'author' => '鈴木']);

        $response1 = $this->getJson('/api/v1/books?keyword=Laravel');
        $response1->assertJsonCount(2, 'data');

        $response2 = $this->getJson('/api/v1/books?keyword=太郎');
        $response2->assertJsonCount(1, 'data');
        $response2->assertJsonPath('data.0.id', $targetBook2->id);
    }

    public function test_index_filters_by_genre_id(): void
    {
        $genreA = Genre::factory()->create();
        $bookA = Book::factory()->create();
        $bookA->genres()->attach($genreA->id);

        $response = $this->getJson("/api/v1/books?genre_id={$genreA->id}");

        $response->assertJsonCount(1, 'data');
    }

    public function test_index_validation_fails_with_invalid_params(): void
    {
        $response = $this->getJson('/api/v1/books?' . http_build_query([
            'keyword' => str_repeat('a', 101),
            'genre_id' => 99999,
            'page' => 0,
            'per_page' => 101,
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['keyword', 'genre_id', 'page', 'per_page']);
    }

    public function test_store_saves_book_with_genres_successfully_when_authenticated(): void
    {
        $user = User::factory()->create();
        $genre1 = Genre::factory()->create();
        $genre2 = Genre::factory()->create();

        Sanctum::actingAs($user);

        $bookData = [
            'title' => '新しい技術書',
            'author' => '著者A',
            'isbn' => '9784123456789',
            'published_date' => '2026/10/27',
            'description' => '本の詳細です。',
            'genres' => [$genre1->id, $genre2->id],
        ];

        $response = $this->postJson('/api/v1/books', $bookData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => '新しい技術書',
            'author' => '著者A',
            'user_id' => $user->id,
        ]);

        $book = Book::where('title', '新しい技術書')->first();
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre1->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre2->id]);

        $response->assertJsonPath('data.title', '新しい技術書');
        $response->assertJsonCount(2, 'data.genres');
    }

    public function test_store_fails_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/books', [
            'title' => '未認証本',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'ログイン（認証）が必要です']);
    }

    public function test_store_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', [
            'title' => '',
            'author' => '',
            'isbn' => 'invalid-isbn',
            'published_date' => '2026-10-27',
            'genres' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'genres',
        ]);
    }

    public function test_store_validation_fails_when_isbn_is_not_unique(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        Book::factory()->create(['isbn' => '9784000000000']);

        $bookData = [
            'title' => '重複テスト本',
            'author' => '著者B',
            'isbn' => '9784000000000',
            'published_date' => '2026/10/27',
            'genres' => [$genre->id],
        ];

        $response = $this->postJson('/api/v1/books', $bookData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['isbn']);
    }

    public function test_show_returns_book_with_relations(): void
    {
        $genre = Genre::factory()->create();

        $book = Book::factory()->create(['title' => 'マイブック']);
        $book->genres()->attach($genre->id);
        $reviewer = User::factory()->create(['name' => 'レビューした人']);

        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $reviewer->id,
            'comment' => '最高でした',
            'rating' => 5,
        ]);

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertOk();

        $response->assertJsonPath('data.title', 'マイブック');
        $response->assertJsonPath('data.genres.0.id', $genre->id);
        $response->assertJsonPath('data.reviews.0.user_name', 'レビューした人');
        $response->assertJsonPath('data.reviews.0.comment', '最高でした');
    }

    public function test_update_modifies_book_successfully_when_owner(): void
    {
        $user = User::factory()->create();
        $genreOld = Genre::factory()->create();
        $genreNew = Genre::factory()->create();

        $book = Book::factory()->create(['title' => '古いタイトル', 'isbn' => '9784111111111', 'user_id' => $user->id]);
        $book->genres()->attach($genreOld->id);

        Sanctum::actingAs($user);

        $updateData = [
            'title' => '新しいタイトル',
            'author' => '新しい著者',
            'isbn' => '9784111111111',
            'published_date' => '2026/10/27',
            'genres' => [$genreNew->id],
        ];

        $response = $this->putJson("/api/v1/books/{$book->id}", $updateData);

        $response->assertOk();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '新しいタイトル',
        ]);

        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genreNew->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $genreOld->id]);
    }

    public function test_update_validation_fails_when_isbn_collides_with_other_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        Book::factory()->create(['isbn' => '9784111111111']);
        $myBook = Book::factory()->create(['isbn' => '9784222222222', 'user_id' => $user->id]);

        $updateData = [
            'title' => 'タイトル更新',
            'author' => '著者',
            'isbn' => '9784111111111',
            'published_date' => '2026/10/27',
            'genres' => [$genre->id],
        ];

        $response = $this->putJson("/api/v1/books/{$myBook->id}", $updateData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['isbn']);
    }

    public function test_update_fails_when_not_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '乗っ取りタイトル',
            'author' => '偽著者',
            'isbn' => '9784222222222',
            'published_date' => '2026/10/27',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'この操作を行う権限がありません']);
    }


    public function test_destroy_deletes_book_successfully__when_owner(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($book);
    }

    public function test_destroy_fails_when_not_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'この操作を行う権限がありません']);
    }
}


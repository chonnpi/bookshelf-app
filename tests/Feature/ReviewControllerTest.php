<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_saves_review_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $reviewData = [
            'rating' => 5,
            'comment' => '最高に面白い本でした！',
        ];

        $response = $this->actingAs($user)
            ->post(route('reviews.store', $book), $reviewData);

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => '最高に面白い本でした！',
        ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを投稿しました！');
    }

    public function test_guest_cannot_save_review_and_is_redirected_to_login(): void
    {
        $book = Book::factory()->create();

        $reviewData = [
            'rating' => 5,
            'comment' => '最高に面白い本でした！',
        ];

        $response = $this->post(route('reviews.store', $book), $reviewData);
        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('reviews', [
            'book_id' => $book->id,
            'comment' => '最高に面白い本でした！',
        ]);
    }

    public function test_store_review_fails_when_rating_is_required(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $invalidData = [
            'rating' => '',
            'comment' => '評価を入れ忘れた場合',
        ];

        $response = $this->actingAs($user)
            ->post(route('reviews.store', $book), $invalidData);

        $response->assertSessionHasErrors(['rating']);

        $this->assertDatabaseMissing('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_edit_returns_view_for_owner(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'テスト用の既存コメント',
        ]);

        $response = $this->actingAs($user)->get(route('reviews.edit', $review));

        $response->assertOk();
        $response->assertViewIs('reviews.edit');
        $response->assertViewHas('review', $review);

        $response->assertSee('value="5"', false);
        $response->assertSee('テスト用の既存コメント');
    }

    public function test_edit_redirects_non_owner_with_error(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('reviews.edit', $review));

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('error', '自分が投稿したレビュー以外は編集できません');
    }

    public function test_edit_redirects_guest_to_login(): void
    {
        $review = Review::factory()->create();

        $response = $this->get(route('reviews.edit', $review));
        $response->assertRedirect(route('login'));
    }

    public function test_update_modifies_review_for_owner(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id, 'comment' => '古いコメント']);

        $updateData = [
            'rating' => 4,
            'comment' => '新しいコメント内容',
        ];

        $response = $this->actingAs($user)
            ->put(route('reviews.update', $review), $updateData);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => '新しいコメント内容',
            'rating' => 4,
        ]);

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('success', 'レビューを更新しました！');
    }

    public function test_update_redirects_guest_to_login(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'comment' => '元のコメント',
        ]);

        $response = $this->put(route('reviews.update', $review), [
            'rating' => 4,
            'comment' => '未ログインの人が書き換えようとしたコメント',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => '元のコメント',
        ]);
    }

    public function test_update_redirects_non_owner_with_error(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $owner->id, 'comment' => '元のコメント']);

        $response = $this->actingAs($otherUser)
            ->put(route('reviews.update', $review), [
                'rating' => 5,
                'comment' => '他人が勝手に書き換えようとしたコメント',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'comment' => '元のコメント',
        ]);
        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('error', '自分が投稿したレビュー以外は更新できません');
    }

    public function test_update_fails_when_rating_is_invalid(): void
    {
        $user = User::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => '元のコメント',
        ]);

        $invalidData = [
            'rating' => 0,
            'comment' => '更新後のコメント',
        ];

        $response = $this->actingAs($user)
            ->put(route('reviews.update', $review), $invalidData);

        $response->assertSessionHasErrors(['rating']);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
            'comment' => '元のコメント',
        ]);
    }

    public function test_destroy_deletes_review_for_owner(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->delete(route('reviews.destroy', $review));

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('success', 'レビューを削除しました！');
    }

    public function test_destroy_redirects_guest_to_login(): void
    {
        $owner = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $owner->id]);

        $response = $this->delete(route('reviews.destroy', $review));
        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
        ]);
    }

    public function test_destroy_redirects_non_owner_with_error(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
        ]);

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('error', '自分が投稿したレビュー以外は削除できません');
    }

    public function test_like_toggles_review_like_status(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('books.show', $review->book_id))
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $review->book_id));
        $response->assertSessionHas('success', 'レビューにいいねしました');

        $this->assertTrue($user->likedReviews()->where('review_id', $review->id)->exists());

        $response = $this->actingAs($user)
            ->post(route('reviews.like', $review));

        $response->assertSessionHas('success', 'いいねを取り消しました');
        $this->assertFalse($user->likedReviews()->where('review_id', $review->id)->exists());
    }
}

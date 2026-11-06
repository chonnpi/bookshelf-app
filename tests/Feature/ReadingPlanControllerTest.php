<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function index_displays_only_authenticated_users_plans()
    {
        $book = Book::factory()->create();

        $myPlan = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress, // 'in_progress'
        ]);

        $otherPlan = ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.index'));

        $response->assertStatus(200)
            ->assertViewHas('readingPlans')
            ->assertSee($myPlan->book->title);

        $readingPlans = $response->viewData('readingPlans');
        $this->assertFalse($readingPlans->contains($otherPlan));
    }

    /** @test */
    public function index_filters_plans_by_status()
    {
        $myPlanInProgress = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'status' => ReadingPlanStatus::InProgress, // 'in_progress'
        ]);
        $myPlanCompleted = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'status' => ReadingPlanStatus::Completed, // 'completed'
        ]);

        // クエリパラメータ ?status=completed でリクエスト
        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.index', ['status' => ReadingPlanStatus::Completed->value]));

        $readingPlans = $response->viewData('readingPlans');
        $this->assertTrue($readingPlans->contains($myPlanCompleted));
        $this->assertFalse($readingPlans->contains($myPlanInProgress));
    }

    public function test_create_returns_view_with_all_books_for_authenticated_user(): void
    {
        // 1. テスト用の書籍データを3冊作成しておく
        $books = Book::factory()->count(3)->create();

        // 2. ログインした状態で create 画面（GET）にアクセス
        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.create'));

        // 3. アサーション（検証）
        $response->assertStatus(200); // 200 OK であること
        $response->assertViewIs('reading-plans.create'); // 正しいBladeビューが表示されていること
        $response->assertViewHas('books'); // ビューに $books が渡されていること

        // 4. ビューに渡された書籍データの件数が、データベースに作成した3件と完全に一致するか検証
        $viewBooks = $response->viewData('books');
        $this->assertCount(3, $viewBooks);
    }

    /** @test */
    public function store_creates_new_reading_plan_and_redirects()
    {
        $book = Book::factory()->create();
        $data = [
            'book_id' => $book->id,
            'target_date' => '2026-12-31',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('reading-plans.store'), $data);

        $response->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を新しく作成しました！');

        // データベースには 'in_progress' が入っているか検証
        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $this->user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress->value, // 'in_progress'
        ]);
    }

    /** @test */
    public function test_complete_updates_status_to_completed(): void
    {
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $book->id,
            'status' => 'in_progress',
        ]);

        // 💡 routeヘルパーにモデル丸ごとではなく、明示的に ['readingPlan' => $plan->id] を連想配列で渡す
        $response = $this->actingAs($this->user)
            ->post(route('reading-plans.complete', ['readingPlan' => $plan->id]));

        $response->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '状態を読了に変更しました！');

        // データベースのステータスが更新されているか検証
        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'status' => 'completed',
        ]);
    }

    /**
     * @test
     * 正常系：自分が作成した読書計画であれば、編集画面が正しく表示されること（認可成功）
     */
    public function test_edit_returns_view_for_reading_plan_owner(): void
    {
        $book = Book::factory()->create();
        // 💡 ログインユーザー（$this->user）のIDを紐づけて計画を作成
        $plan = ReadingPlan::factory()->create([
            'user_id' => $this->user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.edit', ['readingPlan' => $plan->id]));

        $response->assertStatus(200); // 403にならず、正常に200が返ること
        $response->assertViewIs('reading-plans.edit');
        $response->assertViewHas('readingPlan');
    }

    /**
     * @test
     * 異常系：他人が作成した読書計画の編集画面を開こうとした場合、403エラーになること（認可失敗）
     */
    public function test_edit_prevents_non_owner_from_accessing_view(): void
    {
        // 他のユーザーと、そのユーザーに紐づく計画を作成
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $otherPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
        ]);

        // 💡 自分（$this->user）として他人の計画の編集画面へアクセス
        $response = $this->actingAs($this->user)
            ->get(route('reading-plans.edit', ['readingPlan' => $otherPlan->id]));

        // 認可ポリシー（Policy）によって拒否され、403 Forbidden になることを検証
        $response->assertStatus(403);
    }

    public function test_user_cannot_complete_others_reading_plan(): void
    {
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();
        $otherPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => 'in_progress',
        ]);

        // 💡 こちらも同様に他人のプランIDを明示的に指定してPOSTする
        $response = $this->actingAs($this->user)
            ->post(route('reading-plans.complete', ['readingPlan' => $otherPlan->id]));

        // 自分が登録した計画ではないため、403 認可エラーになること
        $response->assertStatus(403);
    }

    /** @test */
    public function destroy_deletes_reading_plan()
    {
        $plan = ReadingPlan::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan));

        $response->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を削除しました！');

        $this->assertDatabaseMissing('reading_plans', ['id' => $plan->id]);
    }
}

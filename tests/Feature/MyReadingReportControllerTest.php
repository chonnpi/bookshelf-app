<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReadingReportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 正常系：マイ読書レポート画面が正しく集計データ（サマリー、評価分布、高評価本、ジャンル）をビューに渡すこと
     */
    public function test_index_displays_correct_reading_report_statistics(): void
    {
        // 1. ログインユーザーと他人のユーザーを作成
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 2. テスト用のジャンルと書籍を作成
        $genreNovel = Genre::factory()->create(['name' => '小説']);
        $genreTech = Genre::factory()->create(['name' => '技術書']);

        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();
        $bookC = Book::factory()->create(); // 他人用

        $bookA->genres()->attach($genreNovel);
        $bookB->genres()->attach($genreTech);
        $bookC->genres()->attach($genreNovel);

        // 3. ログインユーザーのレビュー（読書実績）を作成
        // bookA に星 5 を投稿
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $bookA->id,
            'rating' => 5,
        ]);
        // bookA にさらにもう1件レビューを投稿（同じ本を再読・複数レビューしたサマリーの重複カウント検証用）
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $bookA->id,
            'rating' => 3,
        ]);
        // bookB に星 4 を投稿
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $bookB->id,
            'rating' => 4,
        ]);

        // 4. 他人のレビューを作成（自分のレポートに混ざらないことを検証するため）
        Review::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $bookC->id,
            'rating' => 5,
        ]);

        // 5. ログイン状態でレポート画面にアクセス
        // ※ルート名が定義されている場合は route('reports.index') に変更してください
        $response = $this->actingAs($user)->get('/reports');

        // 6. 基本的なアサーション
        $response->assertOk();
        $response->assertViewIs('reports.index');
        $response->assertViewHas('stats');

        $stats = $response->viewData('stats');

        // 7. 『summary（サマリー）』の計算結果の検証
        // レビュー合計＝3件、読んだ本＝2冊（重複除外）、平均評価＝(5+3+4)/3 = 4.0
        $this->assertEquals(3, $stats['summary']['total_reviews']);
        $this->assertEquals(2, $stats['summary']['books_read']);
        $this->assertEquals(4.0, $stats['summary']['average_rating']);

        // 8. 『rating_distribution（評価分布）』の検証
        // 星1: 0件, 星2: 0件, 星3: 1件, 星4: 1件, 星5: 1件
        $distribution = $stats['rating_distribution']->toArray();
        $this->assertEquals([0, 0, 1, 1, 1], $distribution);

        // 9. 『top_rated_books（高評価の書籍5選）』の検証
        // 星4以上のレビューが、評価の高い順・最新順に最大5件並んでいること
        $this->assertCount(2, $stats['top_rated_books']); // 星5と星4の2件
        $this->assertEquals($bookA->title, $stats['top_rated_books'][0]['title']); // 最初は星5のbookA
        $this->assertEquals(5, $stats['top_rated_books'][0]['rating']);
        $this->assertEquals($bookB->title, $stats['top_rated_books'][1]['title']); // 次は星4のbookB
        $this->assertEquals(4, $stats['top_rated_books'][1]['rating']);

        // 10. 『genre_ratings（ジャンル別読書傾向）』の検証
        // 他人のデータが混ざらず、自分の読んだジャンル統計が返っていること
        $this->assertCount(2, $stats['genre_ratings']); // 小説と技術書

        // 小説ジャンルの集計チェック (bookAに2つのレビュー、5点と3点。平均評価 = 4.0)
        $novelStats = collect($stats['genre_ratings'])->firstWhere('name', '小説');
        $this->assertNotNull($novelStats);
        $this->assertEquals(2, $novelStats['count']);
        $this->assertEquals(4.0, $novelStats['average_rating']);
    }

    /**
     * @test
     * ゲスト（未ログイン）制限の検証
     */
    public function test_guest_cannot_access_report_page(): void
    {
        // ログインせずにアクセスした場合はログイン画面（または指定の場所）にリダイレクトされること
        $response = $this->get('/reports');
        $response->assertRedirect(route('login'));
    }
}

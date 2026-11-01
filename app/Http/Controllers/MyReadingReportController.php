<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyReadingReportController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $myReviewsQuery = $user->myReviews();

        $summary = [
            'total_reviews' => (clone $myReviewsQuery)->count(),
            'books_read' => (clone $myReviewsQuery)->distinct('book_id')->count('book_id'),
            'average_rating' => (clone $myReviewsQuery)->avg('rating') ?? 0,
        ];

        $distributionPlaceholder = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];

        $distributionData = $user->myReviews()
            ->groupBy('rating')
            ->selectRaw('rating, count(*) as count')
            ->get();

        foreach ($distributionData as $data) {
            $index = (int) $data->rating - 1;
            if (isset($distributionPlaceholder[$index])) {
                $distributionPlaceholder[$index] = $data->count;
            }
        }
        $ratingDistribution = collect($distributionPlaceholder);

        $topBooksData = $user->myReviews()
            ->with('book') // Review -> Book (BelongsTo)
            ->where('rating', '>=', 4)
            ->orderBy('rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $topRatedBooks = [];
        foreach ($topBooksData as $review) {
            $topRatedBooks[] = [
                'id' => $review->book->id ?? null,
                'title' => $review->book->title ?? '不明な書籍',
                'author' => $review->book->author ?? '不明な著者',
                'rating' => (int) $review->rating,
            ];
        }

        $genreRatings = Genre::whereHas('books.reviews', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->withCount([
                'books as count' => function ($query) use ($user) {
                    $query->join('reviews', 'books.id', '=', 'reviews.book_id')
                        ->where('reviews.user_id', $user->id);
                },
            ])
            ->withAvg([
                'books as average_rating' => function ($query) use ($user) {
                    $query->join('reviews', 'books.id', '=', 'reviews.book_id')
                        ->where('reviews.user_id', $user->id);
                },
            ], 'reviews.rating')
            ->orderBy('average_rating', 'desc')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        $stats = [
            'summary' => $summary,
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $topRatedBooks,
            'genre_ratings' => $genreRatings,
        ];

        return view('reports.index', compact('stats'));
    }
}

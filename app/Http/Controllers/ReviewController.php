<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Book $book)
    {
        $validated = $request->validated();

        try {
            $book->reviews()->create([
                'user_id' => auth()->id(),
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
            ]);

            return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました！');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'レビューの投稿に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    public function like(Review $review)
    {
        $user = auth()->user();

        if ($user->likedReviews()->where('review_id', $review->id)->exists()) {
            $user->likedReviews()->detach($review->id);
            $message = 'いいねを取り消しました';
        } else {
            $user->likedReviews()->attach($review->id);
            $message = 'レビューにいいねしました';
        }

        return redirect()->back()->with('success', $message);
    }

    public function edit(Review $review)
    {
        try {

            $this->authorize('update', $review);

            return view('reviews.edit', compact('review'));

        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.show', $review->book_id)
                ->with('error', '自分が投稿したレビュー以外は編集できません。');
        }
    }

    public function update(ReviewRequest $request, Review $review)
    {
        try {
            $this->authorize('update', $review);

            $validated = $request->validated();

            $reviewData = Arr::only($validated, [
                'rating',
                'comment',
            ]);
            $review->update($reviewData);

            return redirect()->route('books.show', $review->book_id)
                ->with('success', 'レビューを更新しました！');
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.show', $review->book_id)
                ->with('error', '自分が投稿したレビュー以外は更新できません。');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'レビューの更新に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    public function destroy(Review $review)
    {
        try {
            $this->authorize('delete', $review);

            $review->delete();

            return redirect()->route('books.show', $review->book_id)
                ->with('success', 'レビューを削除しました！');
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.show', $review->book_id)
                ->with('error', '自分が投稿したレビュー以外は削除できません');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'レビューの削除に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;


class FavoriteController extends Controller
{
    public function toggle(Book $book): RedirectResponse
    {
        $user = auth()->user();

        if ($user->favoriteBooks()->where('book_id', $book->id)->exists()) {
            $user->favoriteBooks()->detach($book->id);
            $message = 'お気に入りを解除しました';
        } else {
            $user->favoriteBooks()->attach($book->id);
            $message = 'お気に入りに登録しました';
        }

        return redirect()->back()->with('success', $message);
    }

    public function index(): View
    {
        $user = auth()->user();

        $books = $user->favoriteBooks()
            ->latest()
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $books = Book::with('genres')
            ->latest()
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BookRequest $request)
    {
        $validated = $request->validated();

        $bookData = Arr::only($validated, [
            'title',
            'author',
            'isbn',
            'published_date',
            'description',
            'image_url',
        ]);

        $bookData['user_id'] = auth()->id();

        try {
            DB::transaction(function () use ($bookData, $validated) {
                $book = Book::create($bookData);

                if (! empty($validated['genres'])) {
                    $book->genres()->sync($validated['genres']);
                }
            });

            return redirect()->route('books.index')->with('success', '書籍を登録しました！');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', '書籍の登録に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);

        return view('books.show', compact('book'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book)
    {
        try {
            $this->authorize('update', $book);
            $genres = Genre::all();

            return view('books.edit', compact('book', 'genres'));
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.index')
                ->with('error', '自分が登録した書籍以外は編集できません');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BookRequest $request, Book $book)
    {
        try {
            $this->authorize('update', $book);

            $validated = $request->validated();

            $bookData = Arr::only($validated, [
                'title',
                'author',
                'isbn',
                'published_date',
                'description',
                'image_url',
            ]);

            DB::transaction(function () use ($book, $bookData, $validated) {

                $book->update($bookData);

                $book->genres()->sync($validated['genres'] ?? []);
            });

            return redirect()->route('books.show', $book)->with('success', '書籍を更新しました！');
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.index')
                ->with('error', '自分が登録した書籍以外は更新できません。');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', '書籍の更新に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book)
    {
        try {
            $this->authorize('delete', $book);
            $book->delete();

            return redirect()->route('books.index')->with('success', '書籍を削除しました！');
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('books.index')
                ->with('error', '自分が登録した書籍以外は削除できません');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', '書籍の削除に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }
}

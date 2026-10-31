<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('author', 'like', '%'.$keyword.'%');
            });
        }
        if ($request->filled('genre')) {
            $genreId = $request->input('genre');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $sort = $request->input('sort', 'newest');

        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;

            case 'title':
                $query->orderBy('title', 'asc');
                break;

            case 'rating':

                $query->orderByRaw('CASE WHEN reviews_avg_rating IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('reviews_avg_rating', 'desc');
                break;

            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $books = $query->paginate(10)->withQueryString();

        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function fetchByIsbn(string $isbn): JsonResponse
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return response()->json([
                'error' => '正しい13桁のISBNコードを入力してください。',
            ], 400);
        }
        try {
            $baseUrl = config('services.google.books_api_url');
            $apiKey = config('services.google.books_api_key');
            $safeIsbn = urlencode("isbn:{$isbn}");
            $url = "{$baseUrl}/volumes?q={$safeIsbn}&key={$apiKey}";
            $response = Http::get($url);
            if ($response->failed()) {
                return response()->json([
                    'error' => 'Googleエラー('.$response->status().'): '.$response->body(),
                ], 502);
            }

            $data = $response->json();

            if (! isset($data['items']) || count($data['items']) === 0) {
                return response()->json([
                    'error' => '該当する書籍情報が見つかりませんでした。',
                ], 404);
            }

            $volumeInfo = $data['items'][0]['volumeInfo'];
            $publishedDate = $volumeInfo['publishedDate'] ?? '';
            $formattedDate = '';

            if (! empty($publishedDate)) {
                $digits = preg_replace('/\D/', '', $publishedDate);
                if (strlen($digits) === 8) {
                    $year = substr($digits, 0, 4);
                    $month = substr($digits, 4, 2);
                    $day = substr($digits, 6, 2);

                    if (checkdate((int) $month, (int) $day, (int) $year)) {
                        $formattedDate = "{$year}-{$month}-{$day}";
                    }
                }
            }

            return response()->json([
                'title' => $volumeInfo['title'] ?? '',
                'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
                'description' => $volumeInfo['description'] ?? '',
                'published_date' => $formattedDate,
                'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'サーバー内部でエラーが発生しました。時間をおいて再度お試しください。',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BookRequest $request): RedirectResponse
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
    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);

        return view('books.show', compact('book'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): View|RedirectResponse
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
    public function update(BookRequest $request, Book $book): RedirectResponse
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
                ->with('error', '自分が登録した書籍以外は更新できません');
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
    public function destroy(Book $book): RedirectResponse
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

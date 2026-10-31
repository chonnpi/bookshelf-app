<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')
            ->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(GenreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            Genre::create($validated);

            return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました！');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'ジャンルの登録に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()
            ->latest()
            ->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(GenreRequest $request, Genre $genre): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $genre->update($validated);

            return redirect()
                ->route('genres.index')
                ->with('success', 'ジャンルを更新しました！');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'ジャンルの更新に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        try {

            if ($genre->books()->exists()) {
                return redirect()
                    ->route('genres.index')
                    ->with('error', 'このジャンルには書籍が登録されているため削除できません');
            }
            $genre->delete();

            return redirect()
                ->route('genres.index')
                ->with('success', 'ジャンルを削除しました！');
        } catch (\Exception $e) {
            return redirect()
                ->route('genres.index')
                ->with('error', 'ジャンルの削除に失敗しました。もう一度お試しいただくか、時間を置いてからやり直してください');
        }
    }
}

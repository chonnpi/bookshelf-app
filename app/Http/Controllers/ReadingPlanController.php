<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\Request;

class ReadingPlanController extends Controller
{
    public function index(Request $request)
    {
        $currentStatus = $request->query('status');
        $readingPlans = auth()->user()->readingPlans()
            ->with('book')
            ->when($currentStatus, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    public function complete(Request $request, ReadingPlan $readingPlan)
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update(['status' => 'completed']);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '状態を読了に変更しました！');
    }

    public function create()
    {
        $books = Book::all();

        return view('reading-plans.create', compact('books'));
    }

    public function store(ReadingPlanRequest $request)
    {
        $validated = $request->validated();

        auth()->user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を新しく作成しました！');
    }

    public function edit(ReadingPlan $readingPlan)
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', compact('readingPlan'));
    }

    public function update(ReadingPlanRequest $request, ReadingPlan $readingPlan)
    {
        $this->authorize('update', $readingPlan);

        $validated = $request->validated();

        $readingPlan->update($validated);

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました！');
    }

    public function destroy(ReadingPlan $readingPlan)
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました！');
    }
}

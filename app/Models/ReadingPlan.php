<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'status' => ReadingPlanStatus::class,
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Unread);
    }

    public function scopeReading(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Reading);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Completed);
    }
}

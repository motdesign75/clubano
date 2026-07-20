<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'title',
        'description',
        'plan_start',
        'plan_end',
        'follow_up_at',
        'actual_start',
        'actual_end',
        'status',
        'percent_done',
        'assignee_id',
        'created_by',
        'priority',
        'type',
        'related_type',
        'related_id',
        'completed_at',
    ];

    protected $casts = [
        'plan_start'   => 'date',
        'plan_end'     => 'date',
        'follow_up_at' => 'date',
        'actual_start' => 'date',
        'actual_end'   => 'date',
        'percent_done' => 'integer',
        'priority'     => 'integer',
        'completed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo(null, 'related_type', 'related_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress', 'blocked']);
    }

    public function scopeAssignedTo(Builder $query, int|string|null $userId): Builder
    {
        return $query->when($userId, fn (Builder $builder) => $builder->where('assignee_id', $userId));
    }

    public function scopeDue(Builder $query, ?string $date = null): Builder
    {
        $date ??= now()->toDateString();

        return $query->whereNotNull('plan_end')->whereDate('plan_end', '<=', $date);
    }

    public function scopeFollowUps(Builder $query, ?string $date = null): Builder
    {
        $date ??= now()->toDateString();

        return $query->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', $date);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}

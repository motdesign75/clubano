<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'filename',
        'status',
        'row_count',
        'imported_count',
        'undone_at',
    ];

    protected $casts = [
        'undone_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'import_run_id');
    }

    public function isUndoable(): bool
    {
        return $this->status === 'completed' && $this->undone_at === null;
    }
}

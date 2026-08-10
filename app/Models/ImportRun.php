<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'import_type',
        'created_by',
        'filename',
        'status',
        'row_count',
        'imported_count',
        'skipped_count',
        'summary',
        'undone_at',
    ];

    protected $casts = [
        'undone_at' => 'datetime',
        'summary' => 'array',
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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'import_run_id');
    }

    public function isUndoable(): bool
    {
        return $this->status === 'completed' && $this->undone_at === null;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->import_type === 'contacts' ? 'Kontakte' : 'Mitglieder';
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAccessGrant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'granted_by',
        'scope',
        'reason',
        'starts_at',
        'expires_at',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at?->isFuture()
            && (! $this->starts_at || $this->starts_at->isPast());
    }

    public function getScopeLabelAttribute(): string
    {
        return match ($this->scope) {
            'documents' => 'Dokumente und Metadaten',
            'finance' => 'Finanzen und Metadaten',
            'full' => 'Erweiterter Support',
            default => 'Metadaten und Einstellungen',
        };
    }
}

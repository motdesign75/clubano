<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyDataExport extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'requested_by',
        'status',
        'filename',
        'disk',
        'path',
        'size',
        'prepared_at',
        'expires_at',
        'downloaded_at',
        'failure_reason',
    ];

    protected $casts = [
        'prepared_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_READY => 'Bereit',
            self::STATUS_FAILED => 'Fehlgeschlagen',
            default => 'Wird vorbereitet',
        };
    }

    public function getHumanSizeAttribute(): string
    {
        if (! $this->size) {
            return 'offen';
        }

        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format(max(1, $this->size / 1024), 0, ',', '.') . ' KB';
    }
}

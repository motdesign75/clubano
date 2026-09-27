<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AppNewsItem extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'updated_by',
        'title',
        'teaser',
        'body',
        'status',
        'published_at',
        'push_enabled',
        'push_sent_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'push_enabled' => 'boolean',
        'push_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (AppNewsItem $item) {
            if (Auth::check()) {
                $item->tenant_id ??= Auth::user()->tenant_id;
                $item->created_by ??= Auth::id();
                $item->updated_by ??= Auth::id();
            }
        });

        static::updating(function (AppNewsItem $item) {
            if (Auth::check()) {
                $item->updated_by = Auth::id();
            }
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_PUBLISHED => 'Veröffentlicht',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }
}

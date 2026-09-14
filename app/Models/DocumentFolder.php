<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DocumentFolder extends Model
{
    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (DocumentFolder $folder) {
            if (Auth::check()) {
                $folder->tenant_id ??= Auth::user()->tenant_id;
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function getDepthAttribute(): int
    {
        return $this->parent_id ? 1 : 0;
    }

    public function getIndentedNameAttribute(): string
    {
        return ($this->parent_id ? '— ' : '') . $this->name;
    }
}

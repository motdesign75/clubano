<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EventCategory extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function (EventCategory $category) {
            if (Auth::check()) {
                $category->tenant_id = Auth::user()->tenant_id;
            }

            if (! $category->slug) {
                $category->slug = Str::slug($category->name);
            }

            if (! $category->color) {
                $category->color = '#2563EB';
            }
        });

        static::updating(function (EventCategory $category) {
            if (! $category->slug) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}

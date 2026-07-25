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
        'icon',
        'default_target_tag_id',
        'default_visibility',
        'attendance_enabled_default',
        'response_required_default',
        'counts_toward_required_hours_default',
        'reminders_enabled_default',
    ];

    protected $casts = [
        'attendance_enabled_default' => 'boolean',
        'response_required_default' => 'boolean',
        'counts_toward_required_hours_default' => 'boolean',
        'reminders_enabled_default' => 'boolean',
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

    public function defaultTargetTag()
    {
        return $this->belongsTo(Tag::class, 'default_target_tag_id');
    }
}

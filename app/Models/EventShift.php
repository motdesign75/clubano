<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EventShift extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'title',
        'role',
        'starts_at',
        'ends_at',
        'required_people',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $appends = [
        'confirmed_assignments_count',
        'open_slots',
        'coverage_status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($shift) {
            if (Auth::check() && !$shift->tenant_id) {
                $shift->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function assignments()
    {
        return $this->hasMany(EventShiftAssignment::class)->latest();
    }

    public function confirmedAssignments()
    {
        return $this->hasMany(EventShiftAssignment::class)->where('status', 'confirmed');
    }

    public function getConfirmedAssignmentsCountAttribute(): int
    {
        if ($this->relationLoaded('assignments')) {
            return $this->assignments->where('status', 'confirmed')->count();
        }

        return $this->confirmedAssignments()->count();
    }

    public function getOpenSlotsAttribute(): int
    {
        return max((int) $this->required_people - $this->confirmed_assignments_count, 0);
    }

    public function getCoverageStatusAttribute(): string
    {
        if ($this->confirmed_assignments_count < $this->required_people) {
            return 'understaffed';
        }

        if ($this->confirmed_assignments_count === $this->required_people) {
            return 'full';
        }

        return 'overstaffed';
    }
}

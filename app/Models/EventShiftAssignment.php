<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EventShiftAssignment extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_shift_id',
        'member_id',
        'helper_name',
        'helper_email',
        'helper_phone',
        'status',
        'notes',
    ];

    protected $appends = [
        'display_name',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($assignment) {
            if (Auth::check() && !$assignment->tenant_id) {
                $assignment->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function shift()
    {
        return $this->belongsTo(EventShift::class, 'event_shift_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->member?->full_name
            ?: $this->helper_name
            ?: 'Unbenannte Helferperson';
    }
}

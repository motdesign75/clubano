<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendance extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'member_id',
        'attended',
        'hours',
        'counts_toward_required_hours',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'hours' => 'decimal:2',
        'counts_toward_required_hours' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

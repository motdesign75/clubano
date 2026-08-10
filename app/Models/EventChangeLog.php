<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EventChangeLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'action',
        'summary',
        'before_state',
        'after_state',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

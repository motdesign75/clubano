<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EventWaitlistEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_id', 'member_id', 'status', 'joined_at', 'notified_at'];
    protected $casts = ['joined_at' => 'datetime', 'notified_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

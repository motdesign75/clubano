<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorAnnouncementDelivery extends Model
{
    protected $fillable = [
        'operator_announcement_id',
        'tenant_id',
        'user_id',
        'recipient_name',
        'email',
        'status',
        'error',
    ];

    public function announcement()
    {
        return $this->belongsTo(OperatorAnnouncement::class, 'operator_announcement_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

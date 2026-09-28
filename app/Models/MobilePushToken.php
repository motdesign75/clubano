<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilePushToken extends Model
{
    protected $fillable = [
        'tenant_id',
        'mobile_app_user_id',
        'member_id',
        'token',
        'provider',
        'platform',
        'device_name',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function appUser()
    {
        return $this->belongsTo(MobileAppUser::class, 'mobile_app_user_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MobileAppUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public const STATUS_MANUAL = 'manual';
    public const STATUS_INVITED = 'invited';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'username',
        'password',
        'is_active',
        'sync_status',
        'invitation_token',
        'invitation_sent_at',
        'accepted_at',
        'synced_at',
        'sync_ignored_at',
        'sync_error',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'invitation_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'synced_at' => 'datetime',
        'sync_ignored_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pushTokens()
    {
        return $this->hasMany(MobilePushToken::class);
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }
}

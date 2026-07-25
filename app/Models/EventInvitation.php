<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventInvitation extends Model
{
    public const STATUS_INVITED = 'invited';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_MAYBE = 'maybe';
    public const STATUS_NO_RESPONSE = 'no_response';
    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
        self::STATUS_MAYBE,
        self::STATUS_NO_RESPONSE,
        self::STATUS_EXCUSED,
    ];

    protected $fillable = [
        'tenant_id',
        'event_id',
        'member_id',
        'response_token',
        'status',
        'note',
        'responded_at',
        'recorded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventInvitation $invitation) {
            if (! $invitation->response_token) {
                $invitation->response_token = self::newResponseToken();
            }
        });
    }

    public static function newResponseToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::query()->where('response_token', $token)->exists());

        return $token;
    }

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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACCEPTED => 'Zusage',
            self::STATUS_DECLINED => 'Absage',
            self::STATUS_MAYBE => 'Vielleicht',
            self::STATUS_NO_RESPONSE => 'Keine Rückmeldung',
            self::STATUS_EXCUSED => 'Entschuldigt',
            default => 'Eingeladen',
        };
    }
}

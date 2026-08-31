<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperatorAnnouncementDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'operator_announcement_id',
        'tenant_id',
        'user_id',
        'recipient_name',
        'email',
        'status',
        'error',
        'tracking_token',
        'open_count',
        'first_opened_at',
        'last_opened_at',
        'click_count',
        'first_clicked_at',
        'last_clicked_at',
        'last_clicked_url',
    ];

    protected $casts = [
        'open_count' => 'integer',
        'click_count' => 'integer',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $delivery) {
            if (blank($delivery->tracking_token)) {
                $delivery->tracking_token = (string) Str::uuid();
            }
        });
    }

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

    public function registerOpen(): void
    {
        $now = now();

        $this->forceFill([
            'open_count' => (int) $this->open_count + 1,
            'first_opened_at' => $this->first_opened_at ?: $now,
            'last_opened_at' => $now,
        ])->save();
    }

    public function registerClick(?string $url = null): void
    {
        $now = now();

        $this->forceFill([
            'click_count' => (int) $this->click_count + 1,
            'first_clicked_at' => $this->first_clicked_at ?: $now,
            'last_clicked_at' => $now,
            'last_clicked_url' => $url ?: $this->last_clicked_url,
        ])->save();
    }
}

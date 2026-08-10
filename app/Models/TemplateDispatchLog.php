<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateDispatchLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'created_by',
        'channel',
        'action',
        'recipient_type',
        'member_id',
        'contact_id',
        'recipient_name',
        'recipient_reference',
        'subject',
        'message_excerpt',
        'tracking_token',
        'open_count',
        'first_opened_at',
        'last_opened_at',
        'click_count',
        'first_clicked_at',
        'last_clicked_at',
        'dispatched_at',
        'meta',
    ];

    protected $casts = [
        'open_count' => 'integer',
        'click_count' => 'integer',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if (blank($log->tracking_token) && $log->channel === 'mail') {
                $log->tracking_token = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
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
        $meta = $this->meta ?? [];

        if ($url) {
            $meta['last_clicked_url'] = $url;
        }

        $this->forceFill([
            'click_count' => (int) $this->click_count + 1,
            'first_clicked_at' => $this->first_clicked_at ?: $now,
            'last_clicked_at' => $now,
            'meta' => $meta,
        ])->save();
    }
}

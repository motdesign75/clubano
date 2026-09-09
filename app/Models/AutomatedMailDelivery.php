<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatedMailDelivery extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DRY_RUN = 'dry_run';

    protected $fillable = [
        'tenant_id',
        'automated_mail_setting_id',
        'member_id',
        'occasion',
        'occasion_date',
        'recipient_email',
        'recipient_name',
        'subject',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'occasion_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function setting()
    {
        return $this->belongsTo(AutomatedMailSetting::class, 'automated_mail_setting_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

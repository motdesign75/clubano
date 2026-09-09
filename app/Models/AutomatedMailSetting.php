<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatedMailSetting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const OCCASION_BIRTHDAY = 'birthday';

    protected $fillable = [
        'tenant_id',
        'occasion',
        'enabled',
        'subject',
        'body_html',
        'days_before',
        'send_time',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'days_before' => 'integer',
    ];

    public static function occasionLabels(): array
    {
        return [
            self::OCCASION_BIRTHDAY => 'Geburtstag',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deliveries()
    {
        return $this->hasMany(AutomatedMailDelivery::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtocolEntry extends Model
{
    public const TYPE_INFORMATION = 'information';
    public const TYPE_DISCUSSION = 'discussion';
    public const TYPE_RESOLUTION = 'resolution';
    public const TYPE_TASK = 'task';
    public const TYPE_DATE = 'date';
    public const TYPE_FOLLOW_UP = 'follow_up';

    protected $fillable = [
        'tenant_id',
        'protocol_id',
        'type',
        'title',
        'content',
        'responsible_name',
        'due_date',
        'scheduled_date',
        'visible_in_protocol',
        'position',
    ];

    protected $casts = [
        'due_date' => 'date',
        'scheduled_date' => 'date',
        'visible_in_protocol' => 'boolean',
        'position' => 'integer',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_INFORMATION => 'Information',
            self::TYPE_DISCUSSION => 'Diskussion',
            self::TYPE_RESOLUTION => 'Beschluss',
            self::TYPE_TASK => 'Aufgabe',
            self::TYPE_DATE => 'Termin',
            self::TYPE_FOLLOW_UP => 'Wiedervorlage',
        ];
    }

    public static function typeLabelFor(?string $type): string
    {
        return self::typeOptions()[$type] ?? 'Protokollpunkt';
    }

    public static function typeToneFor(?string $type): string
    {
        return match ($type) {
            self::TYPE_RESOLUTION => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            self::TYPE_TASK => 'border-amber-200 bg-amber-50 text-amber-900',
            self::TYPE_DATE => 'border-sky-200 bg-sky-50 text-sky-900',
            self::TYPE_FOLLOW_UP => 'border-rose-200 bg-rose-50 text-rose-900',
            self::TYPE_DISCUSSION => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            default => 'border-slate-200 bg-slate-50 text-slate-900',
        };
    }

    public function protocol()
    {
        return $this->belongsTo(Protocol::class);
    }
}

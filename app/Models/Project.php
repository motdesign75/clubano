<?php

// app/Models/Project.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Carbon;

class Project extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'owner_id',
    ];

    // Nur Datum anzeigen/speichern
    protected $casts = [
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    // akzeptiert: Y-m-d, Y-m-d H:i, Y-m-d\TH:i, usw.
    public function setStartsAtAttribute($value): void
    {
        $this->attributes['starts_at'] = $this->toDateStringOrNull($value);
    }

    public function setEndsAtAttribute($value): void
    {
        $this->attributes['ends_at'] = $this->toDateStringOrNull($value);
    }

    protected function toDateStringOrNull($value): ?string
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value, config('app.timezone', 'Europe/Berlin'))->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function owner()      { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks()      { return $this->hasMany(Task::class); }
    public function documents()  { return $this->hasMany(ProjectDocument::class); }
}

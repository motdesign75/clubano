<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SepaRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'sequence_type',
        'collection_date',
        'transaction_count',
        'control_sum',
        'file_name',
        'file_path',
        'exported_at',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'exported_at' => 'datetime',
        'control_sum' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SepaRunItem::class);
    }

    public function sequenceTypeLabel(): string
    {
        return match ($this->sequence_type) {
            'FRST' => 'Erstlastschrift',
            'RCUR' => 'Folgelastschrift',
            'FNAL' => 'Letzte Lastschrift',
            default => 'Einmallastschrift',
        };
    }
}

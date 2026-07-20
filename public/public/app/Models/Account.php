<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'type',
        'tenant_id',
    ];

    /**
     * Beziehung: Konto gehört zu einem Verein (Mandant)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Filter für aktuellen Mandanten
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }
}

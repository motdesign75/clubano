<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomMemberValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'custom_member_field_id',
        'value',
    ];

    /**
     * Beziehung: Wert gehört zu einem Mitglied
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Beziehung: Wert gehört zu einem benutzerdefinierten Feld
     */
    public function field()
    {
        return $this->belongsTo(CustomMemberField::class, 'custom_member_field_id');
    }

    /**
     * Scope: Nur Werte des aktuellen Mandanten (über Member)
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->whereHas('member', function ($q) {
            $q->where('tenant_id', auth()->user()->tenant_id);
        });
    }
}

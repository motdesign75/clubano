<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;

class CustomMemberField extends Model
{
    use HasFactory;

    protected $table = 'custom_member_fields';

    protected $fillable = [
        'tenant_id',
        'name',
        'label',
        'slug',
        'type',
        'options',
        'required',
        'visible',
        'order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'visible' => 'boolean',
    ];

    /**
     * Global Scope + automatischer Tenant beim Erstellen
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($field) {
            if (Auth::check()) {
                $field->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Beziehung zu gespeicherten Werten (z. B. bei Mitgliedern).
     */
    public function values()
    {
        return $this->hasMany(CustomMemberValue::class, 'custom_member_field_id');
    }

    /**
     * Optional: Lokaler Scope für eigenen Tenant
     */
    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }
}

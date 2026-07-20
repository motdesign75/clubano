<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Scopes\CurrentTenantScope;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'amount',
        'interval',
    ];

    protected $casts = [
       'amount' => 'float',
    ];

    /**
     * Global Scope für Mandanten einfügen und tenant_id automatisch setzen
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($membership) {
            if (Auth::check()) {
                $membership->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Beziehung: Eine Mitgliedschaft kann viele Mitglieder haben.
     */
    public function members()
    {
        return $this->hasMany(Member::class);
    }
}

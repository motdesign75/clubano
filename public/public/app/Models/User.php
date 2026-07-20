<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Die Felder, die massenweise zuweisbar sind.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
    ];

    /**
     * Diese Felder werden bei Serialisierung verborgen.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // 'two_factor_recovery_codes',
        // 'two_factor_secret',
    ];

    /**
     * Accessors, die automatisch angehängt werden sollen.
     *
     * @var array<int, string>
     */
    protected $appends = [
        // 'profile_photo_url',
    ];

    /**
     * Typ-Casts für bestimmte Felder.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Beziehung zum aktuellen Verein (Mandant).
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'fee', // ← hier war vorher 'amount', das ist falsch
        'billing_cycle',
    ];

    /**
     * Automatisches Casting von fee auf float,
     * damit Laravel intern korrekt mit Dezimalwerten arbeitet.
     */
    protected $casts = [
        'fee' => 'float',
    ];

    /**
     * Beziehung: Eine Mitgliedschaft kann viele Mitglieder haben.
     */
    public function members()
    {
        return $this->hasMany(Member::class);
    }
}

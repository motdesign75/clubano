<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomMemberField extends Model
{
    use HasFactory;

    protected $table = 'custom_member_fields';

    protected $fillable = [
        'tenant_id',
        'name',
        'label',      // <-- wichtig: damit das Label gespeichert wird
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
     * Beziehung zu gespeicherten Werten (z. B. bei Mitgliedern).
     */
    public function values()
    {
        return $this->hasMany(CustomMemberValue::class, 'custom_member_field_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToTenant;

    // Erlaubte Felder beim Mass Assignment
    protected $fillable = [
        'tenant_id',
        'name',
        'color', // neu hinzugefügt für Farbinformation
    ];

    /**
     * Die Mitglieder, die diesem Tag zugewiesen sind.
     */
    public function members()
    {
        return $this->belongsToMany(Member::class);
    }
}

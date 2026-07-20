<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvitationCode extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'code'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}


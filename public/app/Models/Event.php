<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'start',
        'end',
        'is_public',
        'tenant_id',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'is_public' => 'boolean',
    ];
}

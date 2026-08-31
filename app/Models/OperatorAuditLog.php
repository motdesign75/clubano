<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'actor_email',
        'target_tenant_id',
        'target_tenant_name',
        'target_tenant_email',
        'action',
        'label',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withoutGlobalScopes();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id')->withoutGlobalScopes();
    }
}

<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BudgetPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'year',
        'title',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($plan) {
            if (Auth::check() && !$plan->tenant_id) {
                $plan->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetPlanItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    public function isReleased(): bool
    {
        return $this->status === 'freigegeben';
    }
}

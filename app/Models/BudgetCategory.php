<?php

namespace App\Models;

use App\Scopes\CurrentTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BudgetCategory extends Model
{
    use HasFactory;

    public const DEFAULTS = [
        ['name' => 'Mitgliedschaft', 'color' => 'blue'],
        ['name' => 'Veranstaltungen', 'color' => 'amber'],
        ['name' => 'Gastronomie', 'color' => 'emerald'],
        ['name' => 'Sponsoring', 'color' => 'purple'],
        ['name' => 'Jugend', 'color' => 'cyan'],
        ['name' => 'Sportbetrieb', 'color' => 'lime'],
        ['name' => 'Verwaltung', 'color' => 'slate'],
        ['name' => 'Oeffentlichkeitsarbeit', 'color' => 'rose'],
        ['name' => 'Vereinsheim', 'color' => 'indigo'],
        ['name' => 'Sonstiges', 'color' => 'zinc'],
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CurrentTenantScope);

        static::creating(function ($category) {
            if (Auth::check() && blank($category->tenant_id)) {
                $category->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function budgetItems()
    {
        return $this->hasMany(BudgetPlanItem::class);
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', auth()->user()->tenant_id);
    }

    public static function ensureDefaultsForTenant(string $tenantId): void
    {
        foreach (self::DEFAULTS as $index => $default) {
            static::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $default['name'],
                ],
                [
                    'color' => $default['color'],
                    'sort_order' => ($index + 1) * 10,
                    'active' => true,
                ]
            );
        }
    }
}

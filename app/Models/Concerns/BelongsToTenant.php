<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // tenant_id beim Anlegen automatisch setzen
        static::creating(function ($model) {
            if (Auth::check() && empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        // Global Scope: Abfragen auf den eigenen Tenant beschränken
        static::addGlobalScope('tenant', function (Builder $builder) {
            // In CLI/Jobs kann Auth::check() false sein – dann kein Scope
            if (Auth::check()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    Auth::user()->tenant_id
                );
            }
        });
    }
}

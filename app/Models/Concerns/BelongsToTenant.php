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
            $user = Auth::user();

            if ($user && filled($user->tenant_id) && empty($model->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });

        // Global Scope: Abfragen auf den eigenen Tenant beschränken
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() && blank($user->tenant_id)) {
                return;
            }

            if (blank($user->tenant_id)) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(
                $builder->getModel()->getTable() . '.tenant_id',
                $user->tenant_id
            );
        });
    }
}

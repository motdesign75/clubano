<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CurrentTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
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

        $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
    }
}

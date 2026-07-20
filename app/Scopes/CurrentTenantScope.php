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
        if (Auth::check()) {
            $builder->where('tenant_id', Auth::user()->tenant_id);
        }
    }
}

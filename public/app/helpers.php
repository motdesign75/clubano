<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('currentTenantId')) {
    function currentTenantId()
    {
        return Auth::user()?->tenant_id;
    }
}

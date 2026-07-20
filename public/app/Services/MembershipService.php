<?php

namespace App\Services;

use App\Models\Membership;
use Illuminate\Support\Collection;

class MembershipService
{
    public function getForTenant(): Collection
    {
        return Membership::where('tenant_id', app('currentTenant')->id)->get();
    }
}

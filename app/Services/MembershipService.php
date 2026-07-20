<?php

namespace App\Services;

use App\Models\Membership;

class MembershipService
{
    /**
     * Gibt alle Mitgliedschaften des aktuellen Tenants zurück.
     */
    public function getForTenant()
    {
        return Membership::where('tenant_id', auth()->user()->tenant_id)->get();
    }

    /**
     * Gibt eine bestimmte Mitgliedschaft zurück (oder null).
     */
    public function findById(int $id)
    {
        return Membership::where('tenant_id', auth()->user()->tenant_id)
                         ->where('id', $id)
                         ->first();
    }
}

<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function before(User $user, string $ability)
    {
        // Optional: Superadmin o.Ä. whitelisten
        // if ($user->is_superadmin) return true;
    }

    public function viewAny(User $user): bool
    {
        return true; // Tenant-Scope begrenzt Daten ohnehin
    }

    public function view(User $user, Contact $contact): bool
    {
        return (string)$user->tenant_id === (string)$contact->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->isStaff()
            && (string)$user->tenant_id === (string)$contact->tenant_id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->isStaff()
            && (string)$user->tenant_id === (string)$contact->tenant_id;
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $user->isStaff()
            && (string)$user->tenant_id === (string)$contact->tenant_id;
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return false;
    }
}

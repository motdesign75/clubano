<?php

namespace App\Http\Controllers;

use App\Models\User;

class RoleController extends Controller
{
    public function edit()
    {
        $profiles = User::permissionProfiles();

        return view('roles.edit', compact('profiles'));
    }

    public function update()
    {
        return redirect()
            ->route('roles.edit')
            ->with('success', 'Das Rollenmodell ist jetzt bewusst fest und einfach gehalten. Benutzerrollen aenderst du direkt in der Benutzerverwaltung.');
    }
}

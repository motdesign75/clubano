<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function edit()
    {
        // Beispielrechte – später dynamisch aus Datenbank oder config
        $permissions = [
            'Mitglieder' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
            ],
            'Veranstaltungen' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => false,
            ],
            'Beiträge' => [
                'read' => true,
                'create' => true,
                'update' => false,
                'delete' => false,
            ],
        ];

        return view('roles.edit', compact('permissions'));
    }

    public function update(Request $request)
    {
        // Später: Speichern in DB oder config
        // dd($request->all()); // zu Debugzwecken

        return redirect()->route('roles.edit')->with('success', 'Rollen erfolgreich aktualisiert.');
    }
}

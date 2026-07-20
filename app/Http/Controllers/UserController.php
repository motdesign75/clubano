<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected function ensureManageable(User $user): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Benutzer können nur von Vereinsadmins verwaltet werden.');
        }

        if ((string) $user->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff erlaubt.');
        }

        if ($user->isSuperAdmin() && ! auth()->user()?->isSuperAdmin()) {
            abort(403, 'Superadmin-Konten können nur vom Superadmin verwaltet werden.');
        }
    }

    /**
     * Zeigt alle Benutzer eines Tenants – inkl. inaktiver, falls vorhanden.
     */
    public function index()
    {
        $users = User::where('tenant_id', auth()->user()->tenant_id)
                     ->orderByRaw('last_login_at IS NULL, last_login_at DESC')
                     ->orderBy('name')
                     ->get();

        return view('users.index', compact('users'));
    }

    /**
     * Zeigt das Formular zum Erstellen eines Benutzers.
     */
    public function create()
    {
        $roleOptions = User::roleOptionsFor(auth()->user());

        return view('users.create', compact('roleOptions'));
    }

    public function edit(User $user)
    {
        $this->ensureManageable($user);

        $roleOptions = User::roleOptionsFor(auth()->user());

        return view('users.edit', compact('user', 'roleOptions'));
    }

    /**
     * Speichert einen neuen Benutzer.
     */
    public function store(Request $request)
    {
        $allowedRoles = User::manageableRolesFor(auth()->user());

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['role'] = User::normalizeRole($validated['role']);

        // Optional: Standardmäßig als aktiv markieren
        // $validated['active'] = true;

        User::create($validated);

        return redirect()->route('users.index')->with('success', 'Benutzer erfolgreich erstellt.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManageable($user);

        $allowedRoles = User::manageableRolesFor(auth()->user());

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        if ($user->id === auth()->id() && $user->isSuperAdmin() && ($validated['role'] ?? null) !== 'SAdmin') {
            return redirect()
                ->route('users.edit', $user)
                ->with('error', 'Du kannst deinem aktuell eingeloggten Superadmin-Account die Rolle nicht selbst entziehen.');
        }

        $validated['role'] = User::normalizeRole($validated['role']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'Benutzer erfolgreich aktualisiert.');
    }

    /**
     * Löscht einen Benutzer (nur im eigenen Tenant erlaubt).
     */
    public function destroy(User $user)
    {
        // Sicherheit: nicht eigenen Account löschen
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Du kannst deinen eigenen Account nicht löschen.');
        }

        $this->ensureManageable($user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Benutzer gelöscht.');
    }
}

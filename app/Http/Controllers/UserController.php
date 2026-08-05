<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
        $tenantId = auth()->user()->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
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

    public function inviteMembers()
    {
        $tenantId = auth()->user()->tenant_id;
        $roleOptions = User::roleOptionsFor(auth()->user());
        $existingEmails = User::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->all();

        $allMembers = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $members = $allMembers
            ->filter(fn (Member $member) => filled(trim((string) $member->email))
                && ! in_array(mb_strtolower(trim($member->email)), $existingEmails, true))
            ->values();

        $unavailableMembers = $allMembers
            ->reject(fn (Member $member) => $members->contains('id', $member->id))
            ->map(function (Member $member) use ($existingEmails) {
                $email = trim((string) $member->email);

                $member->invite_blocked_reason = blank($email)
                    ? 'Keine E-Mail-Adresse hinterlegt'
                    : (in_array(mb_strtolower($email), $existingEmails, true)
                        ? 'Benutzerzugang besteht bereits'
                        : 'Nicht einladbar');

                return $member;
            })
            ->values();

        return view('users.invite-members', compact('members', 'unavailableMembers', 'roleOptions'));
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
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['role'] = User::normalizeRole($validated['role']);

        User::create($validated);

        return redirect()->route('users.index')->with('success', 'Benutzer erfolgreich erstellt.');
    }

    public function storeMemberInvites(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $allowedRoles = User::manageableRolesFor(auth()->user());

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'integer',
                Rule::exists('members', 'id')->where('tenant_id', $tenantId),
            ],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ], [
            'member_ids.required' => 'Bitte wähle mindestens ein Mitglied aus.',
        ]);

        $role = User::normalizeRole($validated['role']);
        $members = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->whereIn('id', $validated['member_ids'])
            ->get();

        $invited = 0;
        $skipped = 0;

        foreach ($members as $member) {
            $email = trim((string) $member->email);

            if (blank($email)) {
                $skipped++;
                continue;
            }

            $existingUser = User::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($existingUser) {
                $skipped++;
                continue;
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
                $skipped++;
                continue;
            }

            $name = trim($member->full_name) ?: ($member->organization ?: $member->email);

            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(48)),
                'role' => $role,
            ]);

            Password::sendResetLink(['email' => $email]);
            $invited++;
        }

        $message = $invited === 1
            ? '1 Mitglied wurde als Benutzer eingeladen.'
            : "{$invited} Mitglieder wurden als Benutzer eingeladen.";

        if ($skipped > 0) {
            $message .= " {$skipped} Einträge wurden übersprungen, weil keine E-Mail vorhanden ist oder bereits ein Benutzer existiert.";
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManageable($user);

        $allowedRoles = User::manageableRolesFor(auth()->user());

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($user->id),
            ],
            'password' => ['nullable', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        if ($user->id === auth()->id() && $user->isSuperAdmin() && ($validated['role'] ?? null) !== 'SAdmin') {
            return redirect()
                ->route('users.edit', $user)
                ->with('error', 'Du kannst deinem aktuell eingeloggten Superadmin-Account die Rolle nicht selbst entziehen.');
        }

        $emailOwner = User::query()
            ->where('id', '!=', $user->id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($validated['email']))])
            ->first();

        if ($emailOwner) {
            return redirect()
                ->route('users.edit', $user)
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Diese E-Mail-Adresse kann aktuell nicht für diesen Benutzer verwendet werden.');
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

        return redirect()->route('users.index')->with('success', 'Benutzer erfolgreich gelöscht.');
    }
}

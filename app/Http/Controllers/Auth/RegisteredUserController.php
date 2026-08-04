<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        session([
            'registration.form_started_at' => now()->timestamp,
        ]);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guardAgainstSpamRegistration($request);

        // 🛡️ Eingaben validieren
        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'min:3', 'max:255'],
            'contact_name' => ['required', 'string', 'min:3', 'max:255'],
            'role_in_club' => ['required', 'string', 'max:120'],
            'club_city' => ['nullable', 'string', 'max:120'],
            'club_website' => ['nullable', 'url', 'max:255'],
            'intended_use' => ['nullable', 'array'],
            'intended_use.*' => ['string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
            'nickname' => ['nullable', 'max:0'],
        ]);

        // 🧩 Neuen Verein (Tenant) automatisch erstellen
        $tenant = Tenant::create([
            'name' => $validated['tenant_name'],
            'slug' => Str::slug($validated['tenant_name']) . '-' . Str::random(4),
            'email' => $validated['email'],
            'city' => $validated['club_city'] ?? null,
            'invite_code' => Str::uuid(),
            'verification_status' => 'pending',
            'registration_contact_name' => $validated['contact_name'],
            'registration_role' => $validated['role_in_club'],
            'registration_website' => $validated['club_website'] ?? null,
            'registration_intent' => collect($validated['intended_use'] ?? [])->filter()->implode(', '),
            'registration_ip' => $request->ip(),
        ]);

        // 👤 Neuen Benutzer anlegen und Tenant zuweisen
        $user = User::create([
            'name' => $validated['contact_name'],
            'email' => $validated['email'],
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make($validated['password']),
        ]);

        // 📣 Event & Login
        event(new Registered($user));
        Auth::login($user);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->forget('registration.form_started_at');

        return redirect()->route('verification.notice');
    }

    private function guardAgainstSpamRegistration(Request $request): void
    {
        if (filled((string) $request->input('nickname'))) {
            throw ValidationException::withMessages([
                'email' => 'Die Registrierung konnte nicht verarbeitet werden.',
            ]);
        }

        $startedAt = (int) $request->session()->get('registration.form_started_at', 0);

        if ($startedAt === 0 || now()->timestamp - $startedAt < config('clubano.registration.min_fill_seconds', 4)) {
            throw ValidationException::withMessages([
                'email' => 'Die Registrierung wurde zu schnell abgeschickt. Bitte versuche es erneut.',
            ]);
        }

        $emailDomain = Str::lower((string) Str::after((string) $request->input('email'), '@'));
        $blockedDomains = collect(config('clubano.registration.blocked_email_domains', []))
            ->map(fn ($domain) => Str::lower((string) $domain))
            ->filter()
            ->all();

        if ($emailDomain !== '' && in_array($emailDomain, $blockedDomains, true)) {
            throw ValidationException::withMessages([
                'email' => 'Bitte verwende eine echte Vereins- oder persoenliche E-Mail-Adresse.',
            ]);
        }

        $name = Str::lower((string) $request->input('tenant_name'));
        $blockedFragments = collect(config('clubano.registration.blocked_name_fragments', []))
            ->map(fn ($fragment) => Str::lower((string) $fragment))
            ->filter()
            ->all();

        foreach ($blockedFragments as $fragment) {
            if (Str::contains($name, $fragment)) {
                throw ValidationException::withMessages([
                    'name' => 'Der Name sieht nicht nach einer echten Registrierung aus. Bitte pruefe deine Angaben.',
                ]);
            }
        }

        $tenantName = trim((string) $request->input('tenant_name'));
        $contactName = trim((string) $request->input('contact_name'));

        if ($tenantName !== '' && $contactName !== '' && Str::lower($tenantName) === Str::lower($contactName)) {
            throw ValidationException::withMessages([
                'tenant_name' => 'Bitte trage den Vereinsnamen ein, nicht nur den Namen der Kontaktperson.',
            ]);
        }
    }
}

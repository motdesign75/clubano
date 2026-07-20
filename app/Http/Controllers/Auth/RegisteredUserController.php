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
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);

        // 🧩 Neuen Verein (Tenant) automatisch erstellen
        $tenant = Tenant::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'email' => $request->email,
            'invite_code' => Str::uuid(),
        ]);

        // 👤 Neuen Benutzer anlegen und Tenant zuweisen
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'tenant_id' => $tenant->id,
            'password' => Hash::make($request->password),
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
        if (filled((string) $request->input('website'))) {
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

        $name = Str::lower((string) $request->input('name'));
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
    }
}

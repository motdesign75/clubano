<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-semibold text-slate-950">Mein Clubano aktivieren</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            {{ $appUser->tenant?->name }} hat dich zur Mitglieder-App eingeladen. Dieser Zugang gilt nur für die App und nicht für die Clubano-Verwaltung.
        </p>
    </div>

    <form method="POST" action="{{ route('mobile-app-invitations.store', $appUser->invitation_token) }}" class="space-y-5">
        @csrf

        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Dein App-Login ist <strong>{{ $appUser->username }}</strong>. Lege jetzt dein persönliches App-Passwort fest.
        </div>

        <div>
            <x-input-label for="password" value="App-Passwort" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="App-Passwort wiederholen" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>

        <button class="inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
            App-Zugang aktivieren
        </button>
    </form>
</x-guest-layout>

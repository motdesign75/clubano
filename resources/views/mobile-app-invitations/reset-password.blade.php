<x-guest-layout>
    <h1 class="text-xl font-semibold">Mein Clubano: Neues Passwort</h1>
    @if(session('status'))
        <p class="mt-4 text-sm text-emerald-800">{{ session('status') }}</p>
    @else
        <form method="POST" action="{{ route('mobile-password.update') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="username" value="{{ $username }}">
            <div>
                <x-input-label for="password" value="Neues App-Passwort" />
                <x-text-input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Passwort wiederholen" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full" />
            </div>
            <x-primary-button>Passwort speichern</x-primary-button>
        </form>
    @endif
</x-guest-layout>

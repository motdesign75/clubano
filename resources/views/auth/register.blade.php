<x-guest-layout>

    <div class="space-y-6">

        <!-- Headline -->
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                Account anlegen
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                Erstellen Sie Ihren Clubano-Zugang für Ihren Verein. Die Testphase startet erst nach der Bestätigung Ihrer E-Mail-Adresse.
            </p>
        </div>

        <!-- Form -->
        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <input type="text" name="website" value="{{ old('website') }}" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <!-- Name -->
            <div class="space-y-1">
                <label for="name" class="text-xs font-medium text-gray-500">
                    Name
                </label>
                <input id="name" name="name" type="text" required autofocus
                    value="{{ old('name') }}"
                    placeholder="Max Mustermann"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs" />
            </div>

            <!-- E-Mail -->
            <div class="space-y-1">
                <label for="email" class="text-xs font-medium text-gray-500">
                    E-Mail-Adresse
                </label>
                <input id="email" name="email" type="email" required
                    value="{{ old('email') }}"
                    placeholder="z. B. vorstand@verein.de"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
            </div>

            <!-- Passwort -->
            <div class="space-y-1">
                <label for="password" class="text-xs font-medium text-gray-500">
                    Passwort
                </label>
                <input id="password" name="password" type="password" required
                    placeholder="••••••••"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs" />
            </div>

            <!-- Passwort bestätigen -->
            <div class="space-y-1">
                <label for="password_confirmation" class="text-xs font-medium text-gray-500">
                    Passwort bestätigen
                </label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    placeholder="••••••••"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs" />
            </div>

            <!-- AGB -->
            <div class="flex items-start space-x-2 text-xs text-gray-600">
                <input id="terms" name="terms" type="checkbox" value="1" required class="mt-1 border-gray-300 rounded" @checked(old('terms'))>
                <label for="terms">
                    Ich akzeptiere die
                    <a href="https://clubano.de/allgemeine-geschaeftsbedingungen/" class="text-blue-600 hover:underline">AGB</a>
                    und habe die
                    <a href="https://clubano.de/datenschutzerklaerung/" class="text-blue-600 hover:underline">Datenschutzerklärung</a>
                    gelesen.
                </label>
            </div>
            <x-input-error :messages="$errors->get('terms')" class="mt-1 text-xs" />

            <!-- Submit -->
            <button type="submit"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition transform hover:-translate-y-0.5">
                Account erstellen
            </button>

            <p class="text-center text-xs text-gray-500">
                14 Tage kostenlos testen. Erst ansehen, dann später über ein Abo entscheiden.
            </p>

        </form>

        <!-- Login Link -->
        <div class="text-center text-sm text-gray-500 pt-2">
            Bereits registriert?
            <a href="{{ route('login') }}"
               class="text-blue-600 font-medium hover:underline">
                Jetzt einloggen
            </a>
        </div>

    </div>

</x-guest-layout>

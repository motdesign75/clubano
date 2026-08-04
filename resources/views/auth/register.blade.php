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

            <input type="text" name="nickname" value="{{ old('nickname') }}" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <!-- Verein -->
            <div class="space-y-1">
                <label for="tenant_name" class="text-xs font-medium text-gray-500">
                    Vereinsname
                </label>
                <input id="tenant_name" name="tenant_name" type="text" required autofocus
                    value="{{ old('tenant_name') }}"
                    placeholder="z. B. SV Musterstadt e.V."
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('tenant_name')" class="mt-1 text-xs" />
            </div>

            <!-- Kontaktperson -->
            <div class="space-y-1">
                <label for="contact_name" class="text-xs font-medium text-gray-500">
                    Ansprechpartner
                </label>
                <input id="contact_name" name="contact_name" type="text" required
                    value="{{ old('contact_name') }}"
                    placeholder="Vorname Nachname"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('contact_name')" class="mt-1 text-xs" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="role_in_club" class="text-xs font-medium text-gray-500">
                        Funktion im Verein
                    </label>
                    <input id="role_in_club" name="role_in_club" type="text" required
                        value="{{ old('role_in_club') }}"
                        placeholder="z. B. Vorstand, Kasse"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                    <x-input-error :messages="$errors->get('role_in_club')" class="mt-1 text-xs" />
                </div>

                <div class="space-y-1">
                    <label for="club_city" class="text-xs font-medium text-gray-500">
                        Ort des Vereins
                    </label>
                    <input id="club_city" name="club_city" type="text"
                        value="{{ old('club_city') }}"
                        placeholder="z. B. Musterstadt"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                    <x-input-error :messages="$errors->get('club_city')" class="mt-1 text-xs" />
                </div>
            </div>

            <div class="space-y-1">
                <label for="club_website" class="text-xs font-medium text-gray-500">
                    Vereinswebsite oder Social-Media-Seite
                </label>
                <input id="club_website" name="club_website" type="url"
                    value="{{ old('club_website') }}"
                    placeholder="https://www.verein.de"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm" />
                <x-input-error :messages="$errors->get('club_website')" class="mt-1 text-xs" />
            </div>

            <fieldset class="space-y-2">
                <legend class="text-xs font-medium text-gray-500">Wofür interessiert sich der Verein?</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach(['Mitgliederverwaltung', 'Finanzen', 'Termine', 'Protokolle', 'Import/Umstieg', 'Kommunikation'] as $option)
                        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-600">
                            <input type="checkbox" name="intended_use[]" value="{{ $option }}" class="rounded border-gray-300 text-blue-600" @checked(in_array($option, old('intended_use', []), true))>
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('intended_use')" class="mt-1 text-xs" />
            </fieldset>

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

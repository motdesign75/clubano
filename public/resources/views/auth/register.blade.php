<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center items-center bg-gray-50 px-4 py-12">

        {{-- Logo --}}
        <div class="mb-8">
            <img src="{{ asset('images/logo_clubano.svg') }}" alt="Clubano Logo" class="h-16 w-auto mx-auto">
        </div>

        {{-- Formularbox --}}
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-lg space-y-6 ring-1 ring-gray-200">

            <h1 class="text-2xl font-extrabold text-center text-gray-900">Account anlegen</h1>
            <p class="text-sm text-gray-600 text-center">
                Legen Sie hier mit Ihrer E-Mail-Adresse und Ihrem Passwort einen Account an
            </p>

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                {{-- Name --}}
                <div>
                    <input id="name" name="name" type="text" required autofocus autocomplete="name"
                        value="{{ old('name') }}"
                        placeholder="Name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#2954A3] focus:outline-none" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                {{-- E-Mail --}}
                <div>
                    <input id="email" name="email" type="email" required autocomplete="username"
                        value="{{ old('email') }}"
                        placeholder="E-Mail-Adresse"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#2954A3] focus:outline-none" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                {{-- Passwort --}}
                <div>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        placeholder="Passwort"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#2954A3] focus:outline-none" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                {{-- Passwort bestätigen --}}
                <div>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        placeholder="Passwort bestätigen"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#2954A3] focus:outline-none" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                {{-- AGB-Checkbox --}}
                <div class="flex items-start space-x-2 text-sm text-gray-600">
                    <input id="terms" type="checkbox" required class="mt-1 border-gray-300 rounded">
                    <label for="terms">
                        Hiermit sage ich, dass ich die
                        <a href="#" class="text-blue-600 hover:underline">Allgemeinen Geschäftsbedingungen</a>
                        gelesen habe und damit einverstanden bin. Informationen zur Verarbeitung meiner personenbezogenen Daten kann ich der
                        <a href="#" class="text-blue-600 hover:underline">Datenschutzerklärung</a> entnehmen.
                    </label>
                </div>

                {{-- Button --}}
                <button type="submit"
                    class="w-full bg-[#2954A3] hover:bg-[#1E3F7F] text-white font-semibold py-3 rounded-xl transition">
                    Weiter
                </button>
            </form>

            {{-- Link zum Login --}}
            <p class="text-sm text-center text-gray-700">
                Sie haben bereits einen Account?
                <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Einloggen</a>
            </p>
        </div>
    </div>
</x-guest-layout>

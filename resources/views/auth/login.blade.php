<x-guest-layout>

    <div class="space-y-6">

        <!-- Headline -->
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                Willkommen bei Clubano
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                Die digitale Lösung für Ihren Verein
            </p>
        </div>

        <!-- Status -->
        @if (session('status'))
            <div class="text-sm text-green-700 bg-green-100 border border-green-300 rounded-lg p-3 text-center">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="text-sm text-amber-800 bg-amber-100 border border-amber-300 rounded-lg p-3 text-center">
                {{ session('error') }}
            </div>
        @endif

        <!-- Form -->
        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <!-- Email -->
            <div class="space-y-1">
                <label for="email" class="text-xs font-medium text-gray-500">
                    E-Mail-Adresse
                </label>
                <input id="email" name="email" type="email" required autofocus
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm"
                    placeholder="z. B. vorstand@verein.de"
                    value="{{ old('email') }}">
                <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
            </div>

            <!-- Password -->
            <div class="space-y-1">
                <label for="password" class="text-xs font-medium text-gray-500">
                    Passwort
                </label>
                <input id="password" name="password" type="password" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-sm"
                    placeholder="••••••••">
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs" />

                <div class="text-right">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-xs text-gray-500 hover:text-blue-600 transition">
                            Passwort vergessen?
                        </a>
                    @endif
                </div>
            </div>

            <!-- Remember -->
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="remember"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                    <span class="text-gray-600 text-xs">
                        Angemeldet bleiben
                    </span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition transform hover:-translate-y-0.5">
                Anmelden
            </button>
        </form>

        <!-- Divider -->
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-xs">
                <span class="px-2 bg-white text-gray-400">Oder</span>
            </div>
        </div>

        <!-- Microsoft Login -->
        <a href="#"
           class="w-full flex items-center justify-center py-3 border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <img src="https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg"
                 alt="Microsoft" class="h-5 mr-2">
            <span class="text-sm font-medium text-gray-700">
                Mit Microsoft anmelden
            </span>
        </a>

        <!-- Register -->
        <div class="text-center text-sm text-gray-500 pt-2">
            Noch kein Account?
            <a href="{{ route('register') }}"
               class="text-blue-600 font-medium hover:underline">
                Jetzt registrieren
            </a>
        </div>

    </div>

</x-guest-layout>

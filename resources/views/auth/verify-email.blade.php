<x-guest-layout>
    <div class="space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                E-Mail-Adresse bestätigen
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                Wir haben dir soeben eine Bestätigungs-E-Mail geschickt. Bitte öffne den Link darin. Direkt danach startet deine kostenlose Testphase.
            </p>
        </div>

        @if (session('status') === 'verification-link-sent')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                Wir haben dir einen neuen Bestätigungslink per E-Mail gesendet.
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
            <p>
                Ohne bestätigte E-Mail-Adresse bleibt der Zugang eingeschränkt. Das schützt euch vor fehlerhaften Registrierungen und hilft uns, echte Vereine zuverlässig von Spam zu trennen. Nach der Bestätigung könnt ihr Clubano sofort in Ruhe testen.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <x-primary-button>
                    Bestätigungs-E-Mail erneut senden
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="text-sm font-medium text-slate-500 underline hover:text-slate-800">
                    Abmelden
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>

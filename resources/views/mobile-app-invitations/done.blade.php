<x-guest-layout>
    <div class="text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl font-semibold text-emerald-700">
            ✓
        </div>
        <h1 class="mt-5 text-2xl font-semibold text-slate-950">App-Zugang aktiviert</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">
            Du kannst dich jetzt in der App Mein Clubano mit <strong>{{ $appUser->username }}</strong> und deinem neuen Passwort anmelden.
        </p>
        <a href="{{ config('services.mobile_app.ios_store_url') }}"
           class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
            Mein Clubano im App Store herunterladen
        </a>
        <p class="mt-3 text-sm leading-6 text-slate-500">
            Für iPhone und iPad. Im App Store heißt die App „Clubano“. Öffne sie nach der Installation und melde dich mit den oben genannten Zugangsdaten an.
        </p>
    </div>
</x-guest-layout>

<section>
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
            <x-heroicon-o-user class="h-5 w-5" />
        </span>
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Persönliche Daten</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Aktualisiere deinen Namen und deine E-Mail-Adresse.</p>
        </div>
    </div>

    @php($user = auth()->user())

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-800">Name</label>
            <input id="name"
                   name="name"
                   type="text"
                   class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                   value="{{ old('name', $user->name) }}"
                   required
                   autofocus
                   autocomplete="name">
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-slate-800">E-Mail-Adresse</label>
            <input id="email"
                   name="email"
                   type="email"
                   class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                   value="{{ old('email', $user->email) }}"
                   required
                   autocomplete="username">
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    <div class="font-semibold">Deine E-Mail-Adresse ist noch nicht bestätigt.</div>
                    <button form="send-verification" class="mt-1 font-semibold text-amber-950 underline underline-offset-2">
                        Bestätigungslink erneut senden
                    </button>
                </div>
            @endif
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                <x-heroicon-o-check class="h-5 w-5" />
                Profil speichern
            </button>
        </div>
    </form>
</section>

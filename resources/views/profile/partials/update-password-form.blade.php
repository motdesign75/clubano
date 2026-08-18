<section>
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
            <x-heroicon-o-lock-closed class="h-5 w-5" />
        </span>
        <div>
            <h2 class="text-lg font-semibold text-slate-950">Passwort ändern</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Nutze ein langes Passwort, das du nicht an anderer Stelle verwendest.</p>
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-semibold text-slate-800">Aktuelles Passwort</label>
            <input id="update_password_current_password"
                   name="current_password"
                   type="password"
                   class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                   autocomplete="current-password">
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="update_password_password" class="block text-sm font-semibold text-slate-800">Neues Passwort</label>
                <input id="update_password_password"
                       name="password"
                       type="password"
                       class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                       autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="update_password_password_confirmation" class="block text-sm font-semibold text-slate-800">Neues Passwort wiederholen</label>
                <input id="update_password_password_confirmation"
                       name="password_confirmation"
                       type="password"
                       class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                       autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                <x-heroicon-o-check class="h-5 w-5" />
                Passwort speichern
            </button>
        </div>
    </form>
</section>

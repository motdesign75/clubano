<section class="space-y-5">
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
        </span>
        <div>
            <h2 class="text-lg font-semibold text-rose-950">Benutzerkonto löschen</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Damit löschst du deinen persönlichen Zugang. Vereinsdaten, Mitglieder, Dokumente und Finanzen werden dadurch nicht automatisch gelöscht.
            </p>
        </div>
    </div>

    <button type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
        Benutzerkonto löschen
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-xl font-semibold text-slate-950">Benutzerkonto wirklich löschen?</h2>

            <p class="mt-3 text-sm leading-6 text-slate-600">
                Du wirst abgemeldet und dein persönlicher Zugang wird entfernt. Gib dein Passwort ein, um diesen Schritt zu bestätigen.
            </p>

            <div class="mt-6">
                <label for="password" class="block text-sm font-semibold text-slate-800">Passwort</label>
                <input id="password"
                       name="password"
                       type="password"
                       class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300"
                       placeholder="Dein Passwort"
                       autocomplete="current-password">

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button"
                        x-on:click="$dispatch('close')"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Abbrechen
                </button>

                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-700 px-4 text-sm font-semibold text-white transition hover:bg-rose-800">
                    Benutzerkonto endgültig löschen
                </button>
            </div>
        </form>
    </x-modal>
</section>

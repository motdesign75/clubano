@extends('layouts.app')

@section('title', 'Betreiberkonto')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="border-b border-slate-200 pb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Clubano Betrieb</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">Betreiberkonto</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
            Dieses Konto gehört zur Plattform, nicht zu einem Verein. Hier änderst du deine Zugangsdaten für das Admin-Cockpit.
        </p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Identität</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Name und E-Mail-Adresse deines Betreiberzugangs.</p>
            </div>

            @if(session('status') === 'account-updated')
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    Betreiberkonto wurde aktualisiert.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.account.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="text-sm font-medium text-slate-700">Name</label>
                    <input id="name"
                           name="name"
                           type="text"
                           value="{{ old('name', $user->name) }}"
                           required
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('name')
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="email" class="text-sm font-medium text-slate-700">E-Mail</label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email', $user->email) }}"
                           required
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('email')
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Rolle: <span class="font-semibold text-slate-950">{{ $user->roleLabel() }}</span><br>
                    Verein: <span class="font-semibold text-slate-950">kein Verein</span>
                </div>

                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Konto speichern
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Kennwort</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Ändere dein Passwort regelmäßig und nutze ein langes, einzigartiges Kennwort.</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="update_password_current_password" class="text-sm font-medium text-slate-700">Aktuelles Kennwort</label>
                    <input id="update_password_current_password"
                           name="current_password"
                           type="password"
                           autocomplete="current-password"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($errors->updatePassword->get('current_password') as $message)
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @endforeach
                </div>

                <div>
                    <label for="update_password_password" class="text-sm font-medium text-slate-700">Neues Kennwort</label>
                    <input id="update_password_password"
                           name="password"
                           type="password"
                           autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($errors->updatePassword->get('password') as $message)
                        <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                    @endforeach
                </div>

                <div>
                    <label for="update_password_password_confirmation" class="text-sm font-medium text-slate-700">Neues Kennwort bestätigen</label>
                    <input id="update_password_password_confirmation"
                           name="password_confirmation"
                           type="password"
                           autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">
                        Kennwort ändern
                    </button>

                    @if(session('status') === 'password-updated')
                        <span class="text-sm font-medium text-emerald-700">Kennwort wurde geändert.</span>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

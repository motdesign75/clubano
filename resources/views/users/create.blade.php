@extends('layouts.app')

@section('title', 'Benutzer anlegen')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_58%,#1f2937_100%)] p-6 sm:p-8">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white">Benutzer anlegen</h1>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                Nutze diese manuelle Anlage nur, wenn die Person nicht aus der Mitgliederliste eingeladen werden soll.
            </p>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Bitte prüfe deine Eingaben.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('users.store') }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr),360px]">
        @csrf

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <x-heroicon-o-user-plus class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Zugangsdaten</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Diese Person kann sich danach direkt anmelden.</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-800">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-800">E-Mail-Adresse</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-800">Passwort</label>
                        <input id="password" name="password" type="password" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-slate-800">Passwort wiederholen</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <label for="role" class="block text-sm font-semibold text-slate-800">Rolle</label>
                <select id="role" name="role" required class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    @foreach($roleOptions as $option)
                        <option value="{{ $option['value'] }}" @selected(old('role') === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>

                <div class="mt-5 space-y-3">
                    @foreach($roleOptions as $option)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="text-sm font-semibold text-slate-900">{{ $option['label'] }}</div>
                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ $option['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row lg:flex-col-reverse">
                <a href="{{ route('users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Zurück zu Benutzern
                </a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    <x-heroicon-o-check class="h-5 w-5" />
                    Benutzer speichern
                </button>
            </div>
        </aside>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Benutzer')

@section('content')
@php
    $currentUser = auth()->user();
    $totalUsers = $users->count();
    $adminCount = $users->filter(fn ($user) => $user->isAdmin())->count();
    $neverLoggedInCount = $users->whereNull('last_login_at')->count();
    $activeLast30Days = $users->filter(fn ($user) => $user->last_login_at?->gte(now()->subDays(30)))->count();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),360px] lg:p-10">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Verein verwalten</div>
                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Benutzer</h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                    Hier steuerst du, wer in Clubano mitarbeiten darf. Jede Person bekommt eine klare Rolle, damit sensible Bereiche geschützt bleiben.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('users.invite-members') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                        <x-heroicon-o-user-plus class="h-5 w-5" />
                        Mitglieder einladen
                    </a>
                    <a href="{{ route('users.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/18 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/12">
                        <x-heroicon-o-plus class="h-5 w-5" />
                        Benutzer manuell anlegen
                    </a>
                </div>
            </div>

            <aside class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Schnell prüfen</div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-slate-950">
                            <x-heroicon-o-user-group class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Zugänge</span>
                            <span class="block truncate text-xs text-white/55">Benutzer insgesamt</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $totalUsers }}</span>
                    </div>

                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white/70">
                            <x-heroicon-o-shield-check class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Admins</span>
                            <span class="block truncate text-xs text-white/55">mit Verwaltungsrechten</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $adminCount }}</span>
                    </div>

                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white/70">
                            <x-heroicon-o-clock class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Aktiv</span>
                            <span class="block truncate text-xs text-white/55">Anmeldung in 30 Tagen</span>
                        </span>
                        <span class="text-2xl font-semibold tracking-tight text-white">{{ $activeLast30Days }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    @if($neverLoggedInCount > 0)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-amber-950">{{ $neverLoggedInCount }} Benutzer ohne Anmeldung</h2>
                    <p class="mt-1 text-sm leading-6 text-amber-800">Diese Personen haben ihren Zugang vermutlich noch nicht genutzt.</p>
                </div>
                <a href="{{ route('users.invite-members') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-amber-300 bg-white px-4 text-sm font-semibold text-amber-900 transition hover:bg-amber-50">
                    Einladungen prüfen
                </a>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Übersicht</div>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Alle Benutzer</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Prüfe regelmäßig, ob Rollen noch passen. Wer keine Finanzaufgaben hat, sollte auch keinen Finanzzugriff benötigen.
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($users as $user)
                @php
                    $isCurrent = $currentUser && $currentUser->id === $user->id;
                    $loginLabel = $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'noch nie angemeldet';
                @endphp

                <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr),180px,220px,180px] xl:items-center {{ $isCurrent ? 'bg-emerald-50/70' : '' }}">
                    <div class="flex min-w-0 items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $isCurrent ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                            <x-heroicon-o-user class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="truncate text-lg font-semibold text-slate-950">{{ $user->name }}</div>
                                @if($isCurrent)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Du</span>
                                @endif
                            </div>
                            <div class="mt-1 break-all text-sm text-slate-500">{{ $user->email }}</div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rolle</div>
                        <div class="mt-1 text-sm font-semibold text-slate-950">{{ $user->roleLabel() }}</div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Letzte Anmeldung</div>
                        <div class="mt-1 text-sm font-semibold text-slate-950">{{ $loginLabel }}</div>
                        @if($user->last_login_ip)
                            <div class="mt-1 text-xs text-slate-500">IP {{ $user->last_login_ip }}</div>
                        @endif
                    </div>

                    <div class="flex flex-wrap justify-start gap-2 xl:justify-end">
                        <a href="{{ route('users.edit', $user) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Bearbeiten
                        </a>

                        @if(! $isCurrent)
                            <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Diesen Benutzerzugang wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                    Löschen
                                </button>
                            </form>
                        @else
                            <span class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-100 px-3 text-sm font-semibold text-emerald-800">
                                Aktuell angemeldet
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-8 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                            <x-heroicon-o-user-group class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-950">Noch keine Benutzer</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Lege einen Benutzer an oder lade Mitglieder zur Mitarbeit ein.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

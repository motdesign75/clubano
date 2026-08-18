@extends('layouts.app')

@section('title', 'Mein Profil')

@section('content')
@php
    $tenant = $user->tenant;
    $emailVerified = ! ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) || $user->hasVerifiedEmail();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),360px] lg:p-10">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Persönlich</div>
                <h1 class="mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Mein Profil</h1>
                <p class="mt-4 max-w-2xl text-sm leading-6 text-white/68">
                    Hier pflegst du deine persönlichen Zugangsdaten. Vereinsdaten, Mitglieder und Finanzen werden an anderen Stellen verwaltet.
                </p>

                <div class="mt-7 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                        {{ $user->roleLabel() }}
                    </span>
                    @if($tenant)
                        <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white/80">
                            {{ $tenant->name }}
                        </span>
                    @endif
                </div>
            </div>

            <aside class="rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/55">Kontostatus</div>
                <div class="mt-4 space-y-3">
                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-slate-950">
                            <x-heroicon-o-user class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">Benutzer</span>
                            <span class="block truncate text-xs text-white/55">{{ $user->email }}</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-[40px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $emailVerified ? 'bg-white text-slate-950' : 'bg-white/10 text-white/70' }}">
                            <x-heroicon-o-envelope class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white">E-Mail</span>
                            <span class="block truncate text-xs text-white/55">Bestätigung des Zugangs</span>
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $emailVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $emailVerified ? 'bestätigt' : 'offen' }}
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if(session('status') === 'profile-updated')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            Profil wurde gespeichert.
        </div>
    @endif

    @if(session('status') === 'password-updated')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            Passwort wurde geändert.
        </div>
    @endif

    @if(session('status') === 'verification-link-sent')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr),380px]">
        <section class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @include('profile.partials.update-password-form')
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Sicherheit</h2>
                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Rolle</div>
                        <div class="mt-1 text-sm font-semibold text-slate-950">{{ $user->roleLabel() }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Letzte Anmeldung</div>
                        <div class="mt-1 text-sm font-semibold text-slate-950">
                            {{ $user->last_login_at?->format('d.m.Y H:i') ?? 'noch nicht erfasst' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
                @include('profile.partials.delete-user-form')
            </section>
        </aside>
    </div>
</div>
@endsection

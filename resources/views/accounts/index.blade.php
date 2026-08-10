@extends('layouts.app')

@section('title', 'Kontenübersicht')

@section('content')
@php
    $balanceCount = $balanceAccounts->count();
    $chartCount = $chartAccounts->count();
    $inactiveCount = $inactiveAccounts->count();
    $totalAccounts = $balanceCount + $chartCount + $inactiveCount;
    $balanceVolume = $balanceAccounts->sum(fn ($account) => $account->balance_current ?? $account->balance_start ?? 0);
    $balanceVolumeClass = $balanceVolume < 0 ? 'text-rose-700' : 'text-slate-600';
    $importedCount = $balanceAccounts->concat($chartAccounts)->concat($inactiveAccounts)->filter(fn ($account) => filled($account->chart_name) || filled($account->import_source))->count();
    $accountBalance = fn ($account) => $account->balance_current ?? $account->balance_start ?? 0;
    $accountBalanceClass = fn ($account, string $default = 'text-slate-950') => $accountBalance($account) < 0 ? 'text-rose-700' : $default;
    $accountSearch = fn ($account) => collect([
        $account->number,
        $account->name,
        $account->type,
        $account->tax_area,
        $account->chart_name,
        $account->tax_key,
        $account->datev_automatic ? 'DATEV Automatik' : null,
        $account->is_postable ? 'buchbar' : 'nicht buchbar',
        $account->iban,
        $account->bic,
    ])->filter()->implode(' ');
@endphp

<div x-data="accountManager(@js($tab))" x-init="init()" class="mx-auto max-w-6xl space-y-8">

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Finanzen</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Konten klar geordnet</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        Bank, Kasse und Buchhaltung an einem Ort. Weniger Suchen, schneller erkennen, welches Konto gerade gebraucht wird.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:justify-end">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4">
                    <div class="text-2xl font-semibold">{{ $totalAccounts }}</div>
                    <div class="text-sm text-slate-300">Konten insgesamt</div>
                </div>

                <button
                    @click="create()"
                    class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100"
                >
                    Neues Konto
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="border-t border-emerald-200 bg-emerald-50 px-6 py-4 text-sm font-semibold text-emerald-800 md:px-8">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="border-t border-rose-200 bg-rose-50 px-6 py-4 text-sm font-semibold text-rose-800 md:px-8">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="border-t border-rose-200 bg-rose-50 px-6 py-4 text-sm text-rose-800 md:px-8">
                <div class="font-semibold">Der Kontenrahmen konnte nicht verarbeitet werden.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:grid-cols-3 md:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Bank & Kasse</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $balanceCount }}</div>
                <div class="mt-1 text-sm {{ $balanceVolumeClass }}">{{ number_format($balanceVolume, 2, ',', '.') }} € Bestand</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Buchhaltung</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $chartCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Einnahmen und Ausgaben sauber getrennt</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Inaktiv</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $inactiveCount }}</div>
                <div class="mt-1 text-sm text-slate-600">Derzeit ausgeblendete Konten</div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500">Einfach starten</div>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">Clubano-Standardrahmen verwenden</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Für kleine Vereine, die ohne Buchhaltungsballast starten möchten. Bank, Kasse, Beiträge, Spenden, Veranstaltungserlöse und typische Kosten sind sofort angelegt.
            </p>
            <form method="POST" action="{{ route('accounts.simple-chart') }}" class="mt-5">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Standardrahmen anlegen
                </button>
            </form>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500">Kontenrahmen importieren</div>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">CSV aus DATEV, WISO oder bestehender Software</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Clubano erkennt Kontonummer, Bezeichnung, Steuerschlüssel, Buchbarkeit und DATEV-Automatik. Bestehende Konten werden aktualisiert, nicht blind dupliziert.
            </p>
            @if($totalAccounts > 0)
                <div class="mt-5 rounded-3xl border-2 border-rose-300 bg-rose-50 p-5 text-rose-950 shadow-sm">
                    <div class="text-xs font-black uppercase tracking-[0.28em] text-rose-700">Wichtige Warnung</div>
                    <h3 class="mt-2 text-lg font-black">Du nutzt bereits {{ $totalAccounts }} Konten.</h3>
                    <p class="mt-2 text-sm leading-6">
                        Ein neuer Kontenrahmen kann bestehende Konten aktualisieren und die Kontenübersicht deutlich verändern. Bitte nur fortfahren, wenn du sicher bist, dass die Datei zum Verein passt.
                    </p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm leading-6">
                        <li>Bestehende Konten mit gleicher Nummer und gleichem Namen werden aktualisiert.</li>
                        <li>Nicht buchbare Konten können automatisch ausgeblendet werden.</li>
                        <li>Vor einem großen Import sollte ein Datenbank-Backup vorhanden sein.</li>
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('accounts.import-chart') }}"
                  enctype="multipart/form-data"
                  class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto]"
                  @if($totalAccounts > 0) onsubmit="return confirm('Achtung: Es sind bereits Konten vorhanden. Der Import kann bestehende Konten aktualisieren. Wirklich fortfahren?');" @endif>
                @csrf
                <div class="grid gap-3">
                    <input type="text" name="chart_name" value="{{ old('chart_name', 'SKR / eigener Kontenrahmen') }}"
                           class="w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                           placeholder="Name des Kontenrahmens">
                    <input type="file" name="chart_file" accept=".csv,.txt"
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-800">
                    @if($totalAccounts > 0)
                        <label class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-white px-4 py-3 text-sm font-semibold text-rose-900">
                            <input type="checkbox"
                                   name="confirm_existing_chart_import"
                                   value="1"
                                   required
                                   class="mt-1 rounded border-rose-300 text-rose-700 focus:ring-rose-500"
                                   @checked(old('confirm_existing_chart_import'))>
                            <span>Ich habe verstanden, dass bereits Konten vorhanden sind und dieser Import bestehende Konten aktualisieren kann.</span>
                        </label>
                    @endif
                </div>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-full border border-slate-300 px-5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                    Importieren
                </button>
            </form>
            @if($importedCount > 0)
                <p class="mt-3 text-xs font-medium text-emerald-700">{{ $importedCount }} Konten stammen bereits aus einem Kontenrahmen.</p>
            @endif
        </div>
    </section>

    <section class="space-y-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="inline-flex w-full flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 xl:w-auto">
                <button
                    @click="tab = 'balance'"
                    :class="tab === 'balance' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
                >
                    Bank & Kasse
                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="tab === 'balance' ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-500'">{{ $balanceCount }}</span>
                </button>
                <button
                    @click="tab = 'erloes'"
                    :class="tab === 'erloes' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
                >
                    Buchhaltung
                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="tab === 'erloes' ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-500'">{{ $chartCount }}</span>
                </button>
                <button
                    @click="tab = 'inaktiv'"
                    :class="tab === 'inaktiv' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
                >
                    Inaktiv
                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="tab === 'inaktiv' ? 'bg-white/10 text-white' : 'bg-slate-100 text-slate-500'">{{ $inactiveCount }}</span>
                </button>
            </div>

            <p class="text-sm text-slate-500" x-show="tab === 'balance'">Konten mit echtem Geldbestand wie Bank oder Kasse.</p>
            <p class="text-sm text-slate-500" x-show="tab === 'erloes'">Konten für Einnahmen und Ausgaben in der Buchhaltung.</p>
            <p class="text-sm text-slate-500" x-show="tab === 'inaktiv'">Konten, die gerade nicht aktiv genutzt werden.</p>

            <div class="grid w-full gap-3 xl:w-auto xl:grid-cols-[320px_auto] xl:items-center">
                <div class="relative">
                    <input
                        type="search"
                        x-model.debounce.150ms="search"
                        class="w-full rounded-2xl border-slate-200 bg-white py-3 pl-4 pr-11 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                        placeholder="Konto suchen, z. B. 1200, Bank, DATEV"
                        aria-label="Konten suchen"
                    >
                    <button
                        type="button"
                        x-show="search.length > 0"
                        x-cloak
                        @click="search = ''"
                        class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Suche löschen"
                    >
                        &times;
                    </button>
                </div>
                <div class="inline-flex w-full rounded-2xl border border-slate-200 bg-white p-1 xl:w-auto">
                <button
                    type="button"
                    @click="setViewMode('cards')"
                    :class="viewMode === 'cards' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                    class="flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition xl:flex-none"
                >
                    Kacheln
                </button>
                <button
                    type="button"
                    @click="setViewMode('compact')"
                    :class="viewMode === 'compact' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                    class="flex-1 rounded-xl px-4 py-2 text-sm font-semibold transition xl:flex-none"
                >
                    Kompakt
                </button>
                </div>
            </div>
        </div>

        <div x-show="tab === 'balance' && viewMode === 'cards'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($balanceAccounts as $account)
                <div x-show="matches(@js($accountSearch($account)))" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">{{ $account->name }}</h2>
                                <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-500">
                                    <span>{{ $account->type }}</span>
                                    @if($account->number)
                                        <span>·</span>
                                        <span>{{ $account->number }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if($account->iban)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">IBAN hinterlegt</span>
                                @endif
                                @if($account->tax_area)
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">{{ str_replace('_', ' ', $account->tax_area) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                            <button
                                @click='edit(@json($account))'
                                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                Bearbeiten
                            </button>
                            <form method="POST" action="{{ route('accounts.hide', $account) }}" onsubmit="return confirm('Dieses Konto aus der aktiven Übersicht ausblenden?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800">
                                    Ausblenden
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Aktueller Bestand</div>
                        <div class="mt-2 text-2xl font-semibold {{ $accountBalanceClass($account) }}">
                            {{ number_format($accountBalance($account), 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Noch keine Bank- oder Kassenkonten angelegt.
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('accounts.bulk-visibility') }}" x-show="tab === 'balance' && viewMode === 'compact'" x-transition class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="hide">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950">Bank & Kasse kompakt</h2>
                    <p class="mt-1 text-xs text-slate-500">Wähle mehrere Konten aus und blende sie gemeinsam aus.</p>
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Ausgewählte ausblenden
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="px-4 py-3">Konto</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Bereich</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($balanceAccounts as $account)
                            <tr x-show="matches(@js($accountSearch($account)))" class="hover:bg-slate-50">
                                <td class="px-4 py-3"><input type="checkbox" name="account_ids[]" value="{{ $account->id }}" class="rounded border-slate-300 text-slate-950"></td>
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950">{{ $account->number ?: 'ohne Nummer' }}</div><div class="text-slate-600">{{ $account->name }}</div></td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->tax_area ? str_replace('_', ' ', $account->tax_area) : 'Keine Angabe' }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $accountBalanceClass($account, 'text-slate-900') }}">{{ number_format($accountBalance($account), 2, ',', '.') }} €</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Noch keine Bank- oder Kassenkonten angelegt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div x-show="tab === 'erloes' && viewMode === 'cards'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($chartAccounts as $account)
                <div x-show="matches(@js($accountSearch($account)))" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">{{ $account->name }}</h2>
                                <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-500">
                                    <span>{{ $account->type }}</span>
                                    @if($account->number)
                                        <span>·</span>
                                        <span>{{ $account->number }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if($account->tax_area)
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">{{ str_replace('_', ' ', $account->tax_area) }}</span>
                                @endif
                                @if($account->chart_name)
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $account->chart_name }}</span>
                                @endif
                                @unless($account->is_postable)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Nicht buchbar</span>
                                @endunless
                                @if($account->tax_key)
                                    <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700">{{ $account->tax_key }}</span>
                                @endif
                                @if($account->datev_automatic)
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">DATEV-Automatik</span>
                                @endif
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Buchhaltungskonto</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                            <button
                                @click='edit(@json($account))'
                                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                Bearbeiten
                            </button>
                            <form method="POST" action="{{ route('accounts.hide', $account) }}" onsubmit="return confirm('Dieses Konto aus der aktiven Übersicht ausblenden?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800">
                                    Ausblenden
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Saldo</div>
                        <div class="mt-2 text-2xl font-semibold {{ $accountBalanceClass($account) }}">
                            {{ number_format($accountBalance($account), 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Noch keine Buchhaltungskonten angelegt.
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('accounts.bulk-visibility') }}" x-show="tab === 'erloes' && viewMode === 'compact'" x-transition class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="hide">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950">Buchhaltung kompakt</h2>
                    <p class="mt-1 text-xs text-slate-500">Ideal für große importierte Kontenrahmen.</p>
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Ausgewählte ausblenden
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="px-4 py-3">Konto</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Kontenrahmen</th>
                            <th class="px-4 py-3">DATEV</th>
                            <th class="px-4 py-3">Buchbar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($chartAccounts as $account)
                            <tr x-show="matches(@js($accountSearch($account)))" class="hover:bg-slate-50">
                                <td class="px-4 py-3"><input type="checkbox" name="account_ids[]" value="{{ $account->id }}" class="rounded border-slate-300 text-slate-950"></td>
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950">{{ $account->number ?: 'ohne Nummer' }}</div><div class="max-w-xl text-slate-600">{{ $account->name }}</div></td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->chart_name ?: 'Eigene Konten' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ collect([$account->tax_key, $account->datev_automatic ? 'Automatik' : null])->filter()->implode(' · ') ?: 'Keine' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $account->is_postable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $account->is_postable ? 'Ja' : 'Nein' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Noch keine Buchhaltungskonten angelegt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div x-show="tab === 'inaktiv' && viewMode === 'cards'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($inactiveAccounts as $account)
                <div x-show="matches(@js($accountSearch($account)))" class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $account->name }}</h2>
                            <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-500">
                                <span>{{ $account->type }}</span>
                                @if($account->number)
                                    <span>·</span>
                                    <span>{{ $account->number }}</span>
                                @endif
                            </div>
                            <div class="mt-4 text-sm text-slate-600">
                                Letzter Bestand: <span class="font-semibold {{ $accountBalanceClass($account, 'text-slate-700') }}">{{ number_format($accountBalance($account), 2, ',', '.') }} €</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                            <form method="POST" action="{{ route('accounts.restore', $account) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                    Einblenden
                                </button>
                            </form>
                            <button
                                @click='edit(@json($account))'
                                class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-100"
                            >
                                Bearbeiten
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Es gibt aktuell keine inaktiven Konten.
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('accounts.bulk-visibility') }}" x-show="tab === 'inaktiv' && viewMode === 'compact'" x-transition class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="restore">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950">Inaktive Konten kompakt</h2>
                    <p class="mt-1 text-xs text-slate-500">Ausgeblendete Konten bleiben erhalten und können jederzeit zurückgeholt werden.</p>
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Ausgewählte einblenden
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="px-4 py-3">Konto</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Kontenrahmen</th>
                            <th class="px-4 py-3">Buchbar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($inactiveAccounts as $account)
                            <tr x-show="matches(@js($accountSearch($account)))" class="hover:bg-slate-50">
                                <td class="px-4 py-3"><input type="checkbox" name="account_ids[]" value="{{ $account->id }}" class="rounded border-slate-300 text-slate-950"></td>
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950">{{ $account->number ?: 'ohne Nummer' }}</div><div class="max-w-xl text-slate-600">{{ $account->name }}</div></td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->type }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->chart_name ?: 'Eigene Konten' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $account->is_postable ? 'Ja' : 'Nein' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Es gibt aktuell keine inaktiven Konten.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </section>

    {{-- MODAL --}}
    <div x-show="open"
         x-transition
         class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-40"
         x-cloak>

        <div class="relative w-full max-w-2xl rounded-[28px] bg-white p-6 shadow-lg sm:p-7">

            <button @click="close()" class="absolute right-5 top-5 text-2xl text-slate-400 transition hover:text-slate-700">&times;</button>

            <div class="mb-6 pr-10">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Kontodaten</p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950" x-text="account.id ? 'Konto bearbeiten' : 'Neues Konto anlegen'"></h2>
                <p class="mt-2 text-sm text-slate-500">
                    Nur die Felder ausfüllen, die für dieses Konto wirklich gebraucht werden.
                </p>
            </div>

            <form @submit.prevent="submitForm">
                <div class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-700">Kontoname</label>
                            <input type="text" x-model="account.name" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Typ</label>
                            <select x-model="account.type" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                            <option value="bank">Bankkonto</option>
                            <option value="kasse">Kasse</option>
                            <option value="einnahme">Einnahme</option>
                            <option value="ausgabe">Ausgabe</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Steuerlicher Bereich</label>
                            <select x-model="account.tax_area" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                            <option value="">Bitte wählen</option>
                            <option value="ideell">Ideeller Bereich</option>
                            <option value="zweckbetrieb">Zweckbetrieb</option>
                            <option value="vermoegensverwaltung">Vermögensverwaltung</option>
                            <option value="wirtschaftlich">Wirtschaftlicher Geschäftsbetrieb</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-700">IBAN</label>
                            <input type="text" x-model="account.iban" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" placeholder="Nur bei Bankkonten nötig">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700">Anfangsbestand (€)</label>
                            <input type="number" step="0.01" x-model="account.balance_start" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                        </div>

                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div>
                                <div class="text-sm font-medium text-slate-800">Konto aktiv</div>
                                <div class="text-xs text-slate-500">Inaktive Konten erscheinen nur im Archivbereich.</div>
                            </div>
                            <label class="inline-flex cursor-pointer items-center">
                                <input type="checkbox" x-model="account.active" class="h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            @click="close()"
                            class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- SCRIPT bleibt unverändert --}}
<script>
    function accountManager(initialTab) {
        return {
            tab: initialTab || 'balance',
            viewMode: 'cards',
            search: '',
            open: false,
            account: {},
            init() {
                if (!['balance', 'erloes', 'inaktiv'].includes(this.tab)) {
                    this.tab = 'balance';
                }
                this.viewMode = window.localStorage.getItem('clubano.accounts.viewMode') || 'cards';
                if (!['cards', 'compact'].includes(this.viewMode)) {
                    this.viewMode = 'cards';
                }
            },
            setViewMode(mode) {
                this.viewMode = mode;
                window.localStorage.setItem('clubano.accounts.viewMode', mode);
            },
            matches(value) {
                const term = this.normalize(this.search).trim();

                if (term === '') {
                    return true;
                }

                return this.normalize(value).includes(term);
            },
            normalize(value) {
                return value
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            },
            create() {
                this.account = {
                    name: '',
                    type: 'bank',
                    tax_area: '',
                    iban: '',
                    bic: '',
                    description: '',
                    balance_start: 0,
                    balance_date: '',
                    active: true,
                    online: false
                };
                this.open = true;
            },
            edit(data) {
                this.account = {
                    ...data,
                    active: Boolean(Number(data.active)),
                    online: Boolean(Number(data.online))
                };
                this.open = true;
            },
            close() {
                this.open = false;
            },
            submitForm() {
                const isNew = !this.account.id;
                const url = isNew ? '/accounts' : `/accounts/${this.account.id}`;
                const method = 'POST';

                const payload = {
                    ...this.account,
                    active: this.account.active ? 1 : 0,
                    online: this.account.online ? 1 : 0
                };

                if (!isNew) {
                    payload._method = 'PUT';
                }

                fetch(url, {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => {
                    if (response.ok) {
                        this.close();
                        window.location.reload();
                    } else {
                        alert('Fehler beim Speichern');
                    }
                });
            }
        }
    }
</script>
@endsection

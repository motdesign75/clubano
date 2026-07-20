@extends('layouts.app')

@section('title', 'Kontenübersicht')

@section('content')
@php
    $balanceCount = $balanceAccounts->count();
    $chartCount = $chartAccounts->count();
    $inactiveCount = $inactiveAccounts->count();
    $totalAccounts = $balanceCount + $chartCount + $inactiveCount;
    $balanceVolume = $balanceAccounts->sum(fn ($account) => $account->balance_current ?? $account->balance_start ?? 0);
@endphp

<div x-data="accountManager()" x-init="init()" class="mx-auto max-w-6xl space-y-8">

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

        <div class="grid gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:grid-cols-3 md:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Bank & Kasse</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $balanceCount }}</div>
                <div class="mt-1 text-sm text-slate-600">{{ number_format($balanceVolume, 2, ',', '.') }} € Bestand</div>
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

    <section class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="inline-flex w-full flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 lg:w-auto">
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
        </div>

        <div x-show="tab === 'balance'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($balanceAccounts as $account)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
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

                        <button
                            @click='edit(@json($account))'
                            class="shrink-0 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Bearbeiten
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Aktueller Bestand</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ number_format($account->balance_current ?? $account->balance_start ?? 0, 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Noch keine Bank- oder Kassenkonten angelegt.
                </div>
            @endforelse
        </div>

        <div x-show="tab === 'erloes'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($chartAccounts as $account)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
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
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Buchhaltungskonto</span>
                            </div>
                        </div>

                        <button
                            @click='edit(@json($account))'
                            class="shrink-0 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Bearbeiten
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Saldo</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-950">
                            {{ number_format($account->balance_current ?? $account->balance_start ?? 0, 2, ',', '.') }} €
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Noch keine Buchhaltungskonten angelegt.
                </div>
            @endforelse
        </div>

        <div x-show="tab === 'inaktiv'" x-transition class="grid gap-4 md:grid-cols-2">
            @forelse ($inactiveAccounts as $account)
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
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
                                Letzter Bestand: {{ number_format($account->balance_current ?? $account->balance_start ?? 0, 2, ',', '.') }} €
                            </div>
                        </div>

                        <button
                            @click='edit(@json($account))'
                            class="shrink-0 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-100"
                        >
                            Bearbeiten
                        </button>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 md:col-span-2">
                    Es gibt aktuell keine inaktiven Konten.
                </div>
            @endforelse
        </div>
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
    function accountManager() {
        return {
            tab: 'balance',
            open: false,
            account: {},
            init() {
                this.tab = 'balance';
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

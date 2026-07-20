@extends('layouts.app')

@section('title', $mode === 'edit' ? 'Haushaltsplan bearbeiten' : 'Haushaltsplan anlegen')

@section('content')
@php
    $accountOptions = $accounts->map(fn ($account) => [
        'id' => $account->id,
        'label' => trim(($account->number ? $account->number . ' · ' : '') . $account->name),
        'type' => $account->type === 'einnahme' ? 'income' : 'expense',
    ])->values();
    $initialItems = old('items', $items);
@endphp

<div class="mx-auto max-w-6xl space-y-8" x-data="budgetPlanForm(@js($accountOptions), @js($initialItems))" x-init="init()">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300">Haushaltsplan</p>
                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ $mode === 'edit' ? 'Plan in Ruhe nachziehen' : 'Ein neues Haushaltsjahr vorbereiten' }}
                    </h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                        {{ $mode === 'edit' ? 'Passe Zahlen und Schwerpunkte an, ohne den Gesamtblick zu verlieren.' : 'Lege die Jahresrichtung fest. Einnahmen und Ausgaben werden spaeter direkt mit den echten Buchungen verglichen.' }}
                    </p>
                </div>
            </div>

            @if($sourcePlan)
                <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm text-slate-200">
                    Vorlage aus {{ $sourcePlan->year }} uebernommen
                </div>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ $mode === 'edit' ? route('budgets.update', $plan) : route('budgets.store') }}" class="space-y-8">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
            <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Jahr</label>
                        <input type="number" name="year" value="{{ old('year', $plan->year) }}" min="2000" max="2100"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                        @error('year')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Status</label>
                        <select name="status" class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                            <option value="entwurf" @selected(old('status', $plan->status) === 'entwurf')>Entwurf</option>
                            <option value="freigegeben" @selected(old('status', $plan->status) === 'freigegeben')>Freigegeben</option>
                        </select>
                        @error('status')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Titel</label>
                        <input type="text" name="title" value="{{ old('title', $plan->title) }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                        @error('title')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Notiz fuer den Vorstand oder die Kasse</label>
                        <textarea name="notes" rows="4"
                                  class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">{{ old('notes', $plan->notes) }}</textarea>
                        @error('notes')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <aside class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">So bleibt es einfach</p>
                <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                    <p>Pro Position nur ein Konto. Dadurch bleibt der spaetere Ist-Vergleich sauber.</p>
                    <p>Betrag und Rhythmus reichen. Den Jahreswert rechnet Clubano automatisch hoch.</p>
                    <p>Freigeben lohnt sich erst, wenn die Zahlen stabil sind.</p>
                </div>
            </aside>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Planpositionen</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Betrag im echten Rhythmus eingeben. Clubano rechnet den Jahreswert automatisch daraus hoch.</p>
                </div>

                <button type="button"
                        @click="addItem()"
                        class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Position hinzufuegen
                </button>
            </div>

            @error('items')<p class="mt-4 text-sm text-rose-600">{{ $message }}</p>@enderror

            <div class="mt-6 space-y-4">
                <template x-for="(item, index) in items" :key="index">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Position</div>
                                    <div class="mt-1 text-lg font-semibold text-slate-950" x-text="item.account_id ? selectedAccountLabel(item) : `Neue Position ${index + 1}`"></div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                        <span x-text="item.account_id ? selectedCycleSummary(item) : 'Bitte Konto, Betrag und Rhythmus festlegen'"></span>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="item.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'" x-text="item.type === 'income' ? 'Einnahme' : 'Ausgabe'"></span>
                                    </div>
                                </div>

                                <button type="button"
                                        @click="removeItem(index)"
                                        class="inline-flex items-center justify-center rounded-full border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 lg:shrink-0">
                                    Entfernen
                                </button>
                            </div>

                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1.6fr)_9rem_10rem_11rem]">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Konto</label>
                                    <select :name="`items[${index}][account_id]`" x-model="item.account_id" @change="syncTypeFromAccount(item)"
                                            class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                                        <option value="">Bitte waehlen</option>
                                        <template x-for="account in accountOptions" :key="account.id">
                                            <option :value="String(account.id)"
                                                    :selected="String(item.account_id) === String(account.id)"
                                                    x-text="account.label"></option>
                                        </template>
                                    </select>
                                    @error('items.*.account_id')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Betrag</label>
                                    <input :name="`items[${index}][period_amount]`" x-model="item.period_amount" @input="recalculate(item)" type="number" min="0" step="0.01"
                                           class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" required>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Rhythmus</label>
                                    <select :name="`items[${index}][planning_cycle]`" x-model="item.planning_cycle" @change="recalculate(item)"
                                            class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                                        <option value="monthly">Monatlich</option>
                                        <option value="quarterly">Vierteljaehrlich</option>
                                        <option value="half_yearly">Halbjaehrlich</option>
                                        <option value="yearly">Jaehrlich</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Jahreswert</label>
                                    <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-950 px-4 py-3 text-white">
                                        <div class="text-xl font-semibold tracking-tight" x-text="formatCurrency(annualAmount(item))"></div>
                                        <input type="hidden" :name="`items[${index}][planned_amount]`" :value="annualAmount(item).toFixed(2)">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Notiz</label>
                                <input :name="`items[${index}][notes]`" x-model="item.notes" type="text"
                                       class="mt-2 w-full rounded-2xl border-slate-200 bg-white text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                       placeholder="Optional, z. B. Sommerfest oder Versicherung">
                            </div>

                            <input type="hidden" :name="`items[${index}][type]`" x-model="item.type">
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <div class="sticky bottom-4 z-10 -mx-2 rounded-[28px] border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:shadow-none sm:backdrop-blur-0">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ $mode === 'edit' ? route('budgets.show', $plan) : route('budgets.index') }}"
               class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Zurueck
            </a>

            <button type="submit"
                    class="inline-flex items-center justify-center rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                {{ $mode === 'edit' ? 'Haushaltsplan speichern' : 'Haushaltsplan anlegen' }}
            </button>
        </div>
        </div>
    </form>
</div>

<script>
    function budgetPlanForm(accountOptions, initialItems) {
        return {
            accountOptions,
            cycleFactors: {
                monthly: 12,
                quarterly: 4,
                half_yearly: 2,
                yearly: 1,
            },
            items: (initialItems.length ? initialItems : [{
                account_id: '',
                type: 'income',
                period_amount: '',
                planning_cycle: 'monthly',
                planned_amount: '',
                notes: '',
            }]).map((item) => ({
                account_id: item.account_id !== null && item.account_id !== undefined && item.account_id !== '' ? String(item.account_id) : '',
                type: item.type ?? 'income',
                period_amount: item.period_amount ?? item.planned_amount ?? '',
                planning_cycle: item.planning_cycle ?? 'monthly',
                planned_amount: item.planned_amount ?? '',
                notes: item.notes ?? '',
            })),
            addItem() {
                this.items.push({
                    account_id: '',
                    type: 'income',
                    period_amount: '',
                    planning_cycle: 'monthly',
                    planned_amount: '',
                    notes: '',
                });
            },
            removeItem(index) {
                if (this.items.length === 1) {
                    this.items = [{
                        account_id: '',
                        type: 'income',
                        period_amount: '',
                        planning_cycle: 'monthly',
                        planned_amount: '',
                        notes: '',
                    }];
                    return;
                }

                this.items.splice(index, 1);
            },
            syncTypeFromAccount(item) {
                item.account_id = item.account_id !== null && item.account_id !== undefined && item.account_id !== '' ? String(item.account_id) : '';
                const selected = this.accountOptions.find((account) => String(account.id) === String(item.account_id));

                if (selected) {
                    item.type = selected.type;
                }
            },
            annualAmount(item) {
                const factor = this.cycleFactors[item.planning_cycle] ?? 1;
                const base = parseFloat(item.period_amount || 0);

                return Math.round(base * factor * 100) / 100;
            },
            recalculate(item) {
                item.planned_amount = this.annualAmount(item).toFixed(2);
            },
            formatCurrency(value) {
                return new Intl.NumberFormat('de-DE', {
                    style: 'currency',
                    currency: 'EUR',
                }).format(Number(value || 0));
            },
            planningCycleLabel(item) {
                const labels = {
                    monthly: 'monatlich',
                    quarterly: 'vierteljaehrlich',
                    half_yearly: 'halbjaehrlich',
                    yearly: 'jaehrlich',
                };

                return labels[item.planning_cycle] ?? 'jaehrlich';
            },
            selectedAccountLabel(item) {
                const selected = this.accountOptions.find((account) => String(account.id) === String(item.account_id));

                return selected ? selected.label : 'Neue Position';
            },
            selectedCycleSummary(item) {
                const base = parseFloat(item.period_amount || 0);

                if (!base) {
                    return 'Noch kein Betrag hinterlegt';
                }

                return `${this.formatCurrency(base)} ${this.planningCycleLabel(item)}`;
            },
            init() {
                this.items = this.items.map((item) => {
                    const normalized = {
                        ...item,
                        account_id: item.account_id !== null && item.account_id !== undefined && item.account_id !== '' ? String(item.account_id) : '',
                    };

                    this.syncTypeFromAccount(normalized);
                    this.recalculate(normalized);

                    return normalized;
                });
            },
        };
    }
</script>
@endsection

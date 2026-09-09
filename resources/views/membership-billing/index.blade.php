@extends('layouts.app')

@section('title', 'Mitgliederabrechnung')

@section('content')
    @php
        $dunningLevels = [
            ['label' => '1. Erinnerung', 'days' => $tenant->dunning_first_after_days ?? 14],
            ['label' => '2. Mahnung', 'days' => $tenant->dunning_second_after_days ?? 28],
            ['label' => 'Letzte Mahnung', 'days' => $tenant->dunning_final_after_days ?? 42],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-[28px] bg-slate-950 px-6 py-7 text-white shadow-sm sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-white/50">Mitgliederabrechnung</div>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Wer muss abgerechnet werden?</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                        Beitragsmodelle, fällige Mitglieder, Entwürfe und Mahnungen an einem Ort. Clubano bereitet nur Entwürfe vor, der Versand bleibt deine Entscheidung.
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="membership_billing">
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-white px-5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 sm:w-auto">
                            Fällige Entwürfe vorbereiten
                        </button>
                    </form>
                    <a href="{{ route('memberships.index') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-white/20 bg-white/10 px-5 text-sm font-semibold text-white transition hover:bg-white/15 sm:w-auto">
                        Beitragsmodelle
                    </a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <a href="#faellig" class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Jetzt dran</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight text-amber-950">{{ $stats['due_members'] }}</div>
                <p class="mt-2 text-sm text-amber-800">Mitglieder sind abrechnungsreif.</p>
            </a>
            <a href="#fehlende-modelle" class="rounded-3xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-700">Fehlt</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight text-rose-950">{{ $stats['missing_membership'] }}</div>
                <p class="mt-2 text-sm text-rose-800">Mitglieder ohne Beitragsmodell.</p>
            </a>
            <a href="#fehlende-zahlungsart" class="rounded-3xl border border-sky-200 bg-sky-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Zahlungsart</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight text-sky-950">{{ $stats['missing_payment_method'] }}</div>
                <p class="mt-2 text-sm text-sky-800">Mitglieder ohne Zahlungsart.</p>
            </a>
            <a href="{{ route('invoices.index', ['status' => 'entwurf']) }}" class="rounded-3xl border border-indigo-200 bg-indigo-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700">Vorbereitet</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight text-indigo-950">{{ $stats['drafts'] }}</div>
                <p class="mt-2 text-sm text-indigo-800">Beitragsentwürfe warten auf Prüfung.</p>
            </a>
            <a href="#mahnwesen" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Mahnwesen</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight {{ $tenant->dunning_enabled ? 'text-emerald-700' : 'text-slate-950' }}">
                    {{ $tenant->dunning_enabled ? 'Ein' : 'Aus' }}
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ $stats['overdue_invoices'] }} überfällige Beitragsrechnung{{ $stats['overdue_invoices'] === 1 ? '' : 'en' }}.</p>
            </a>
        </section>

        <section id="faellig" class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Arbeitsliste</div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Fällige Mitglieder</h2>
                    </div>
                    <form method="POST" action="{{ route('invoices.generateMemberships') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="membership_billing">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Entwürfe vorbereiten
                        </button>
                    </form>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($dueRows as $row)
                        @php($member = $row['member'])
                        <article class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                            <div>
                                <a href="{{ route('members.show', $member) }}" class="text-base font-semibold text-slate-950 hover:underline">
                                    {{ $member->full_name ?: ($member->organization ?: 'Unbenanntes Mitglied') }}
                                </a>
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $member->member_id ?: 'ohne Mitgliedsnummer' }} · {{ $row['membership']?->name ?? 'kein Modell' }}
                                </div>
                            </div>
                            <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Nächste Rechnung</div>
                                <div class="mt-1 font-semibold">{{ optional($row['next_date'])->format('d.m.Y') }}</div>
                            </div>
                            <a href="{{ route('members.show', $member) }}" class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Prüfen
                            </a>
                        </article>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">
                            Aktuell ist kein Mitglied abrechnungsreif.
                        </div>
                    @endforelse
                </div>
            </div>

            <div id="fehlende-modelle" class="rounded-3xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Vorher klären</div>
                        <h2 class="mt-2 text-xl font-semibold text-amber-950">Ohne Beitragsmodell</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-800">Diese Mitglieder tauchen in keiner Beitragsrechnung auf, bis ein Modell zugeordnet ist.</p>
                    </div>
                    <div class="rounded-2xl bg-white px-4 py-2 text-2xl font-semibold text-amber-900 shadow-sm">{{ $stats['missing_membership'] }}</div>
                </div>

                @if($missingRows->isEmpty())
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        Alle abrechnungsrelevanten Mitglieder haben ein Beitragsmodell.
                    </div>
                @else
                    <form method="POST" action="{{ route('membership-billing.assign-members') }}" class="mt-5 space-y-4">
                        @csrf

                        <div class="grid gap-3">
                            <div>
                                <label for="bulk-membership-id" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Beitragsmodell</label>
                                <select id="bulk-membership-id" name="membership_id" class="w-full rounded-2xl border-amber-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                    <option value="">Nicht ändern</option>
                                    @foreach($memberships as $membership)
                                        <option value="{{ $membership->id }}" @selected((string) old('membership_id') === (string) $membership->id)>
                                            {{ $membership->name }} · {{ number_format($membership->amount ?? 0, 2, ',', '.') }} € / {{ $membership->interval }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="bulk-payment-method" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Zahlungsart</label>
                                <select id="bulk-payment-method" name="payment_method" class="w-full rounded-2xl border-amber-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                    <option value="">Nicht ändern</option>
                                    <option value="ueberweisung" @selected(old('payment_method') === 'ueberweisung')>Rechnung / Überweisung</option>
                                    <option value="bar" @selected(old('payment_method') === 'bar')>Barzahlung</option>
                                </select>
                            </div>

                            <div>
                                <label for="bulk-next-invoice-on" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Nächste Beitragsrechnung</label>
                                <input id="bulk-next-invoice-on" type="date" name="next_membership_invoice_on" value="{{ old('next_membership_invoice_on', now()->toDateString()) }}" class="w-full rounded-2xl border-amber-200 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-200">
                                <p class="mt-1 text-xs leading-5 text-amber-800">Wird nur gesetzt, wenn du ein Beitragsmodell auswählst.</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-white p-3 shadow-sm">
                            <label class="flex items-center gap-3 text-sm font-semibold text-amber-950">
                                <input id="billing-missing-check-all" type="checkbox" class="rounded border-amber-300 text-amber-700 focus:ring-amber-300" checked>
                                Alle angezeigten Mitglieder auswählen
                            </label>
                        </div>

                        <div class="max-h-[32rem] space-y-2 overflow-y-auto pr-1">
                            @foreach($missingRows as $row)
                                @php($member = $row['member'])
                                <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm transition hover:bg-amber-50">
                                    <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="billing-missing-checkbox mt-1 rounded border-amber-300 text-amber-700 focus:ring-amber-300" checked>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-slate-950">{{ $member->full_name ?: ($member->organization ?: 'Unbenanntes Mitglied') }}</span>
                                        <span class="mt-1 block text-slate-500">{{ $member->member_id ?: 'ohne Mitgliedsnummer' }} · {{ $member->paymentMethodLabel() }}</span>
                                    </span>
                                    <a href="{{ route('members.edit', $member) }}" class="text-xs font-semibold text-amber-800 hover:underline">Prüfen</a>
                                </label>
                            @endforeach
                        </div>

                        <div class="sticky bottom-4 rounded-2xl border border-amber-200 bg-white/95 p-3 shadow-lg backdrop-blur">
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full px-5 text-sm font-semibold text-white shadow-sm transition" style="background-color: #92400e;">
                                Auswahl aktualisieren
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const checkAll = document.getElementById('billing-missing-check-all');
                const boxes = Array.from(document.querySelectorAll('.billing-missing-checkbox'));

                if (!checkAll || boxes.length === 0) {
                    return;
                }

                const syncParent = () => {
                    const checkedCount = boxes.filter((box) => box.checked).length;
                    checkAll.checked = checkedCount === boxes.length;
                    checkAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                };

                checkAll.addEventListener('change', () => {
                    boxes.forEach((box) => {
                        box.checked = checkAll.checked;
                    });
                });

                boxes.forEach((box) => box.addEventListener('change', syncParent));
                syncParent();
            });
        </script>

        <section id="fehlende-zahlungsart" class="rounded-3xl border border-sky-200 bg-sky-50/70 p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Sammelaktion</div>
                    <h2 class="mt-2 text-xl font-semibold text-sky-950">Zahlungsart fehlt</h2>
                    <p class="mt-2 text-sm leading-6 text-sky-800">
                        Wenn ihr klassisch per Rechnung abrechnet, markierst du die Mitglieder und setzt die Zahlungsart auf Rechnung / Überweisung.
                    </p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-2 text-2xl font-semibold text-sky-900 shadow-sm">{{ $stats['missing_payment_method'] }}</div>
            </div>

            @if($missingPaymentRows->isEmpty())
                <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    Bei allen abrechnungsrelevanten Mitgliedern ist eine Zahlungsart hinterlegt.
                </div>
            @else
                <form method="POST" action="{{ route('membership-billing.assign-members') }}" class="mt-5 space-y-4">
                    @csrf
                    <div class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <label for="payment-only-method" class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-sky-800">Zahlungsart setzen</label>
                            <select id="payment-only-method" name="payment_method" class="w-full rounded-2xl border-sky-200 bg-white text-sm shadow-sm focus:border-sky-500 focus:ring-sky-200" required>
                                <option value="ueberweisung">Rechnung / Überweisung</option>
                                <option value="bar">Barzahlung</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-full bg-sky-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800">
                            Zahlungsart zuweisen
                        </button>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-white p-3 shadow-sm">
                        <label class="flex items-center gap-3 text-sm font-semibold text-sky-950">
                            <input id="billing-payment-check-all" type="checkbox" class="rounded border-sky-300 text-sky-700 focus:ring-sky-300" checked>
                            Alle angezeigten Mitglieder auswählen
                        </label>
                    </div>

                    <div class="grid max-h-[28rem] gap-2 overflow-y-auto pr-1 md:grid-cols-2">
                        @foreach($missingPaymentRows as $row)
                            @php($member = $row['member'])
                            <label class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-white px-4 py-3 text-sm transition hover:bg-sky-50">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="billing-payment-checkbox mt-1 rounded border-sky-300 text-sky-700 focus:ring-sky-300" checked>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-slate-950">{{ $member->full_name ?: ($member->organization ?: 'Unbenanntes Mitglied') }}</span>
                                    <span class="mt-1 block text-slate-500">{{ $member->member_id ?: 'ohne Mitgliedsnummer' }} · {{ $row['membership']?->name ?? 'kein Beitragsmodell' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </form>
            @endif
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const checkAll = document.getElementById('billing-payment-check-all');
                const boxes = Array.from(document.querySelectorAll('.billing-payment-checkbox'));

                if (!checkAll || boxes.length === 0) {
                    return;
                }

                const syncParent = () => {
                    const checkedCount = boxes.filter((box) => box.checked).length;
                    checkAll.checked = checkedCount === boxes.length;
                    checkAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                };

                checkAll.addEventListener('change', () => {
                    boxes.forEach((box) => {
                        box.checked = checkAll.checked;
                    });
                });

                boxes.forEach((box) => box.addEventListener('change', syncParent));
                syncParent();
            });
        </script>

        <section id="mahnwesen" class="grid gap-6 xl:grid-cols-[0.9fr_1.2fr]">
            <form method="POST" action="{{ route('membership-billing.settings') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PATCH')
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Regeln je Verein</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Mahnwesen</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Jeder Verein entscheidet selbst. Solange das Mahnwesen aus ist, blockiert Clubano den Mahnversand.
                </p>

                <div class="mt-5 space-y-4">
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                        <input type="checkbox" name="membership_billing_reminders_enabled" value="1" class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-500" @checked($tenant->membership_billing_reminders_enabled)>
                        <span>
                            <span class="block font-semibold text-slate-950">In Sidebar erinnern</span>
                            <span class="mt-1 block text-sm text-slate-500">Zeigt einen Zähler, sobald Abrechnung anliegt.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                        <input type="checkbox" name="dunning_enabled" value="1" class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-500" @checked($tenant->dunning_enabled)>
                        <span>
                            <span class="block font-semibold text-slate-950">Mahnwesen aktivieren</span>
                            <span class="mt-1 block text-sm text-slate-500">Erlaubt manuelle Mahnungen für überfällige Rechnungen.</span>
                        </span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach($dunningLevels as $index => $level)
                            @php($field = ['dunning_first_after_days', 'dunning_second_after_days', 'dunning_final_after_days'][$index])
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $level['label'] }}</label>
                                <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                                    <input type="number" min="1" max="365" name="{{ $field }}" value="{{ old($field, $level['days']) }}" class="w-full border-0 p-0 text-sm font-semibold focus:ring-0">
                                    <span class="text-xs text-slate-400">Tage</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-full bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Einstellungen speichern
                </button>
            </form>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Offene Beiträge</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Überfällige Beitragsrechnungen</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format((float) $stats['overdue_total'], 2, ',', '.') }} € offen aus Mitgliedsbeiträgen.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($overdueInvoices as $invoice)
                        <article class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                            <div>
                                <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-slate-950 hover:underline">{{ $invoice->invoice_number }}</a>
                                <div class="mt-1 text-sm text-slate-500">{{ $invoice->getRecipientDisplayName() }} · fällig {{ optional($invoice->due_date)->format('d.m.Y') }}</div>
                            </div>
                            <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">
                                {{ number_format($invoice->getRemainingAmount(), 2, ',', '.') }} €
                            </div>
                            @if($tenant->dunning_enabled)
                                <a href="{{ route('invoices.reminder.preview', $invoice) }}" class="inline-flex min-h-10 items-center justify-center rounded-full bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700">
                                    Mahnung vorbereiten
                                </a>
                            @else
                                <span class="inline-flex min-h-10 items-center justify-center rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-400">
                                    Mahnwesen aus
                                </span>
                            @endif
                        </article>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">
                            Keine überfälligen Beitragsrechnungen. Sehr angenehm.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection

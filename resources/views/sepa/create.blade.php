@extends('layouts.app')

@section('title', 'SEPA-Lastschriftlauf')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-5 sm:px-6 sm:py-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Finanzen</div>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900 sm:text-3xl">SEPA-Lastschriftlauf</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">
                    Hier erzeugt ihr einen XML-Export fuer offene Rechnungen von Mitgliedern mit hinterlegtem SEPA-Mandat.
                </p>
            </div>
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Zurueck zu den Rechnungen
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                <div class="font-semibold">Bitte prueft den Lastschriftlauf noch einmal.</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Offene SEPA-Rechnungen</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $eligibleInvoices->count() }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Exportsumme</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($totalAmount, 2, ',', '.') }} €</div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Creditor Setup</div>
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <div>{{ $tenant->name }}</div>
                    <div>Gläubiger-ID: {{ $tenant->creditor_identifier ?: 'fehlt' }}</div>
                    <div>IBAN: {{ $tenant->iban ?: 'fehlt' }}</div>
                    <div>BIC: {{ $tenant->bic ?: 'fehlt' }}</div>
                </div>
            </div>
        </div>

        @if (blank($tenant->creditor_identifier) || blank($tenant->iban) || blank($tenant->bic))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                Fuer einen echten SEPA-Lauf fehlen noch Vereinsdaten. Bitte hinterlegt zuerst Gläubiger-ID, IBAN und BIC im
                <a href="{{ route('tenant.edit') }}" class="font-semibold underline">Vereinsprofil</a>.
            </div>
        @endif

        <form method="POST" action="{{ route('sepa.export') }}" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_220px] md:items-end">
                    <div>
                        <label for="collection_date" class="mb-1 block text-sm font-medium text-slate-700">Einzugstermin</label>
                        <input type="date" id="collection_date" name="collection_date" value="{{ old('collection_date', $collectionDate) }}" class="w-full rounded-2xl border-slate-300">
                    </div>
                    <div>
                        <label for="sequence_type" class="mb-1 block text-sm font-medium text-slate-700">Sequenztyp</label>
                        <select id="sequence_type" name="sequence_type" class="w-full rounded-2xl border-slate-300">
                            <option value="OOFF" @selected(old('sequence_type', 'OOFF') === 'OOFF')>OOFF - Einmallastschrift</option>
                            <option value="FRST" @selected(old('sequence_type') === 'FRST')>FRST - Erstlastschrift</option>
                            <option value="RCUR" @selected(old('sequence_type') === 'RCUR')>RCUR - Folgelastschrift</option>
                            <option value="FNAL" @selected(old('sequence_type') === 'FNAL')>FNAL - Letzte Lastschrift</option>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700" @disabled($eligibleInvoices->isEmpty() || blank($tenant->creditor_identifier) || blank($tenant->iban) || blank($tenant->bic))>
                        XML exportieren
                    </button>
                </div>
            </div>

            <div class="space-y-4">
                @forelse ($eligibleInvoices as $invoice)
                    <label class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex gap-4">
                            <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="mt-1 rounded border-slate-300 text-emerald-600" {{ $invoice->wasSepaExported() ? '' : 'checked' }}>
                            <div class="space-y-2">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $invoice->invoice_number }}</div>
                                    <div class="mt-1 text-lg font-semibold text-slate-900">{{ $invoice->member->full_name }}</div>
                                    <div class="text-sm text-slate-500">{{ $invoice->member->paymentMethodLabel() }} · Mandat {{ $invoice->member->sepa_mandate_reference }}</div>
                                    @if($invoice->wasSepaExported())
                                        <div class="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                            Bereits exportiert am {{ optional($invoice->sepa_exported_at)->format('d.m.Y H:i') }} ({{ $invoice->sepa_sequence_type ?: '—' }})
                                        </div>
                                    @endif
                                </div>
                                <div class="grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <div>IBAN: {{ $invoice->member->iban }}</div>
                                    <div>Unterschrift: {{ optional($invoice->member->sepa_signed_at)->format('d.m.Y') ?: '—' }}</div>
                                    <div>Faelligkeit: {{ optional($invoice->due_date)->format('d.m.Y') ?: '—' }}</div>
                                    <div>Ort: {{ $invoice->recipient_zip }} {{ $invoice->recipient_city }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Betrag</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</div>
                        </div>
                    </label>
                @empty
                    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm">
                        Aktuell gibt es keine offenen Rechnungen mit vollstaendigen SEPA-Daten.
                    </div>
                @endforelse
            </div>
        </form>

        <section class="space-y-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Historie</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Bisherige Lastschriftläufe</h2>
            </div>

            @forelse ($recentRuns as $run)
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">{{ $run->sequenceTypeLabel() }}</div>
                            <div class="mt-1 text-sm text-slate-500">
                                Exportiert am {{ optional($run->exported_at)->format('d.m.Y H:i') ?: '—' }}
                                @if($run->creator)
                                    · von {{ $run->creator->name }}
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-slate-500">
                                Einzugstermin {{ optional($run->collection_date)->format('d.m.Y') ?: '—' }}
                                · {{ $run->transaction_count }} Posten
                                · {{ number_format((float) $run->control_sum, 2, ',', '.') }} €
                            </div>
                        </div>
                        <div class="text-sm text-slate-500">{{ $run->file_name }}</div>
                    </div>

                    @if($run->items->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-[0.18em] text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2">Rechnung</th>
                                        <th class="px-3 py-2">Mitglied</th>
                                        <th class="px-3 py-2">Mandat</th>
                                        <th class="px-3 py-2 text-right">Betrag</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($run->items as $item)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-700">{{ $item->invoice_number }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $item->member_name ?: '—' }}</td>
                                            <td class="px-3 py-2 text-slate-500">{{ $item->mandate_reference ?: '—' }}</td>
                                            <td class="px-3 py-2 text-right font-medium text-slate-900">{{ number_format((float) $item->amount, 2, ',', '.') }} €</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('sepa.download', $run) }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            XML erneut herunterladen
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500 shadow-sm">
                    Noch keine Lastschriftläufe protokolliert.
                </div>
            @endforelse
        </section>
    </div>
@endsection

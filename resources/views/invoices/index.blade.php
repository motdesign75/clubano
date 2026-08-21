@extends('layouts.app')

@section('content')
    @php
        $statusChips = [
            '' => 'Alle',
            'open' => 'Offen',
            'overdue' => 'Ueberfaellig',
            'entwurf' => 'Entwurf',
            'paid' => 'Bezahlt',
            'storniert' => 'Storniert',
        ];

        $documentTypeChips = [
            '' => 'Alle Dokumente',
            'invoice' => 'Rechnungen',
            'offer' => 'Angebote',
        ];
    @endphp

    <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
        <section class="rounded-[28px] bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-7">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Rechnungen & Angebote</div>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Dokumentenzentrale</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        Alles Wichtige an einem Ort: offen, in Arbeit, bezahlt oder storniert. Erst Überblick, dann saubere Einzelarbeit.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('sepa.create') }}" class="inline-flex w-full items-center justify-center rounded-full border border-emerald-300/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:bg-emerald-500/20 sm:w-auto">
                    SEPA-Lastschriftlauf
                    </a>
                    <a href="{{ route('memberships.index') }}" class="inline-flex w-full items-center justify-center rounded-full border border-white/15 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto">
                    Beiträge vorbereiten
                    </a>

                    <a href="{{ route('invoices.create') }}" class="inline-flex w-full items-center justify-center rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-100 sm:w-auto">
                    Neue Rechnung
                    </a>
                    <a href="{{ route('invoices.create', ['type' => 'offer']) }}" class="inline-flex w-full items-center justify-center rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto">
                    Neues Angebot
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Alle</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['all'] }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Offen</div>
                <div class="mt-2 text-2xl font-semibold text-amber-900">{{ $stats['open'] }}</div>
                <div class="mt-1 text-xs text-amber-700/70">{{ number_format((float) $stats['due_total'], 2, ',', '.') }} € offen</div>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Ueberfaellig</div>
                <div class="mt-2 text-2xl font-semibold text-rose-900">{{ $stats['overdue'] }}</div>
                <div class="mt-1 text-xs text-rose-700/70">{{ number_format((float) $stats['overdue_total'], 2, ',', '.') }} € ueberfaellig</div>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700">Entwurf</div>
                <div class="mt-2 text-2xl font-semibold text-indigo-900">{{ $stats['draft'] }}</div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Bezahlt</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ $stats['paid'] }}</div>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Storniert</div>
                <div class="mt-2 text-2xl font-semibold text-rose-900">{{ $stats['cancelled'] }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3.5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Angebote</div>
                <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $stats['offers'] }}</div>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-semibold text-slate-900">Schneller Blick</div>
            <div class="flex flex-wrap gap-3">
                @foreach($statusChips as $value => $label)
                    <a href="{{ route('invoices.index', array_merge(request()->except('status'), ['status' => $value])) }}"
                       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $status === $value ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('invoices.index') }}" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr_0.8fr_auto]">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Suche</label>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Nummer, Empfaenger, E-Mail, Ort ..." class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Dokumentart</label>
                    <select name="document_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                        @foreach($documentTypeChips as $value => $label)
                            <option value="{{ $value }}" @selected($documentType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Empfaengertyp</label>
                    <select name="recipient_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                        <option value="">Alle</option>
                        <option value="member" @selected($recipientType === 'member')>Mitglied</option>
                        <option value="contact" @selected($recipientType === 'contact')>Kontakt</option>
                        <option value="free" @selected($recipientType === 'free')>Freie Adresse</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Filtern
                    </button>
                    <a href="{{ route('invoices.index') }}" class="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Zuruecksetzen
                    </a>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('invoices.bulk-cancel') }}">
            @csrf

            <div class="mb-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Markierte Rechnungen bearbeiten</div>
                        <div class="mt-1 text-sm text-slate-500">Entwürfe kannst du löschen und neu beginnen. Für Stornos ist ein Grund Pflicht.</div>
                    </div>
                    <div class="grid gap-3 lg:min-w-[520px] lg:grid-cols-[1fr_auto_auto]">
                        <input type="text" name="cancellation_reason" value="{{ old('cancellation_reason') }}" placeholder="Stornogrund für markierte Rechnungen" class="rounded-full border border-slate-300 px-4 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                        <button type="submit" formaction="{{ route('invoices.bulk-destroy-drafts') }}" onclick="return confirm('Markierte Entwürfe wirklich löschen? Nur Entwürfe ohne Zahlungen werden entfernt. Danach kannst du die Beitragsrechnungen neu vorbereiten.');" class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                            Entwürfe löschen
                        </button>
                        <button type="submit" onclick="return confirm('Markierte Rechnungen wirklich gesammelt stornieren?');" class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                            Markierte stornieren
                        </button>
                    </div>
                </div>
                @error('cancellation_reason')
                    <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ $message }}</div>
                @enderror
            </div>

        <div class="space-y-4 md:hidden">
            @forelse ($invoices as $invoice)
                @php
                    $status = $invoice->status;
                    $isDraft = $invoice->isDraft();
                    $isOverdue = $invoice->isOverdue();
                    $displayStatusLabel = $invoice->isOffer() && $status === 'open' ? 'Angebot' : ucfirst($status);
                    $displayStatusClasses = match (true) {
                        $status === 'paid' => 'bg-emerald-100 text-emerald-800',
                        $invoice->isOffer() && $status === 'open' => 'bg-sky-100 text-sky-800',
                        $status === 'open' => 'bg-amber-100 text-amber-800',
                        $status === 'entwurf' => 'bg-slate-100 text-slate-700',
                        $status === 'storniert' => 'bg-rose-100 text-rose-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <article class="rounded-3xl border p-5 shadow-sm {{ $isDraft ? 'border-indigo-200 bg-indigo-50/70 shadow-indigo-100/60' : 'border-slate-200 bg-white' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                            <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                {{ $invoice->getDocumentLabel() }} · {{ ucfirst($invoice->recipient_type ?? 'member') }}
                            </div>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $invoice->invoice_number }}</h2>
                            @if($isDraft)
                                <div class="mt-2 inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                    In Arbeit
                                </div>
                            @endif
                            </div>
                        </div>

                        <span class="{{ $displayStatusClasses }} inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                            {{ $displayStatusLabel }}
                        </span>
                    </div>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Empfaenger</dt>
                            <dd class="mt-1 font-medium text-slate-900">{{ $invoice->getRecipientDisplayName() }}</dd>
                            <div class="mt-1 text-slate-500">{{ $invoice->recipient_zip }} {{ $invoice->recipient_city }}</div>
                            @if($invoice->recipient_email)
                                <div class="mt-1 text-xs text-slate-500">{{ $invoice->recipient_email }}</div>
                            @endif
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Datum</dt>
                                <dd class="mt-1 text-slate-800">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Faellig</dt>
                                <dd class="mt-1 {{ $isOverdue ? 'font-semibold text-rose-700' : 'text-slate-800' }}">
                                    {{ optional($invoice->due_date)->format('d.m.Y') ?: '—' }}
                                </dd>
                                @if($isOverdue)
                                    <div class="mt-1 text-xs font-medium text-rose-700">
                                        {{ $invoice->overdueDays() }} Tag{{ $invoice->overdueDays() === 1 ? '' : 'e' }} ueberfaellig
                                    </div>
                                @endif
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gesamt</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</dd>
                            </div>
                        </div>

                        @if($isDraft)
                            <div class="rounded-2xl border border-indigo-200 bg-white/80 px-4 py-3 text-sm text-indigo-900">
                                Dieser Entwurf ist noch nicht freigegeben und kann weiter bearbeitet werden.
                            </div>
                        @elseif($isOverdue)
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                Diese Rechnung ist ueberfaellig. Jetzt ist ein guter Zeitpunkt fuer Zahlungskontrolle oder Mahnung.
                            </div>
                        @endif
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-3 text-sm">
                        <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-slate-700 hover:text-slate-900">Details</a>
                        @if($invoice->isDraft())
                            <a href="{{ route('invoices.edit', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Bearbeiten</a>
                        @endif
                        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="font-medium text-blue-600 hover:text-blue-800">PDF</a>
                        @if($invoice->status !== 'paid' && $invoice->isInvoice())
                            <a href="{{ route('payments.create', $invoice) }}" class="font-medium text-emerald-600 hover:text-emerald-800">Zahlung</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm">
                    Noch keine Rechnungen vorhanden.
                </div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <th class="px-5 py-4">
                            <input type="checkbox" id="check-all-invoices" class="rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                        </th>
                        <th class="px-5 py-4">Rechnung</th>
                        <th class="px-5 py-4">Empfaenger</th>
                        <th class="px-5 py-4">Datum & Frist</th>
                        <th class="px-5 py-4 text-right">Gesamt</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse ($invoices as $invoice)
                        @php
                            $status = $invoice->status;
                            $isDraft = $invoice->isDraft();
                            $isOverdue = $invoice->isOverdue();
                            $displayStatusLabel = $invoice->isOffer() && $status === 'open' ? 'Angebot' : ucfirst($status);
                            $displayStatusClasses = match (true) {
                                $status === 'paid' => 'bg-emerald-100 text-emerald-800',
                                $invoice->isOffer() && $status === 'open' => 'bg-sky-100 text-sky-800',
                                $status === 'open' => 'bg-amber-100 text-amber-800',
                                $status === 'entwurf' => 'bg-slate-100 text-slate-700',
                                $status === 'storniert' => 'bg-rose-100 text-rose-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr class="{{ $isDraft ? 'bg-indigo-50/70 hover:bg-indigo-50' : 'hover:bg-slate-50/70' }}">
                            <td class="px-5 py-4 align-top">
                                <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" class="invoice-checkbox mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="font-semibold text-slate-900">{{ $invoice->invoice_number }}</div>
                                    @if($isDraft)
                                        <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-indigo-700">
                                            In Arbeit
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $invoice->getDocumentLabel() }} · {{ ucfirst($invoice->recipient_type ?? 'member') }}
                                </div>
                                @if($isDraft)
                                    <div class="mt-2 text-xs font-medium text-indigo-700">
                                        Bearbeitbar, noch nicht final freigegeben
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900">{{ $invoice->getRecipientDisplayName() }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $invoice->recipient_zip }} {{ $invoice->recipient_city }}
                                </div>
                                @if($invoice->recipient_email)
                                    <div class="mt-1 text-xs text-slate-500">{{ $invoice->recipient_email }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <div>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') }}</div>
                                <div class="mt-1 text-xs {{ $isOverdue ? 'font-semibold text-rose-700' : 'text-slate-500' }}">
                                    faellig {{ optional($invoice->due_date)->format('d.m.Y') ?: '—' }}
                                </div>
                                @if($isOverdue)
                                    <div class="mt-1 text-xs font-medium text-rose-700">
                                        {{ $invoice->overdueDays() }} Tag{{ $invoice->overdueDays() === 1 ? '' : 'e' }} ueberfaellig
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900">
                                {{ number_format($invoice->getTotal(), 2, ',', '.') }} €
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="{{ $displayStatusClasses }} inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                                        {{ $displayStatusLabel }}
                                    </span>
                                    @if($isOverdue)
                                        <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                            Ueberfaellig
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3 text-sm">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-slate-700 hover:text-slate-900">Details</a>
                                    @if($invoice->isDraft())
                                        <a href="{{ route('invoices.edit', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-800">Bearbeiten</a>
                                    @endif
                                    <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="font-medium text-blue-600 hover:text-blue-800">PDF</a>
                                    @if($invoice->status !== 'paid' && $invoice->isInvoice())
                                        <a href="{{ route('payments.create', $invoice) }}" class="font-medium text-emerald-600 hover:text-emerald-800">Zahlung</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">
                                Noch keine Rechnungen vorhanden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </form>
    </div>

    @push('scripts')
    <script>
        const checkAllInvoices = document.getElementById('check-all-invoices');

        if (checkAllInvoices) {
            checkAllInvoices.addEventListener('change', function () {
                document.querySelectorAll('.invoice-checkbox').forEach((checkbox) => {
                    checkbox.checked = this.checked;
                });
            });
        }
    </script>
    @endpush
@endsection

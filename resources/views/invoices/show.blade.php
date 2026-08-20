@extends('layouts.app')

@section('content')
    @php
        $documentLabel = $invoice->getDocumentLabel();
        $tenant = auth()->user()->tenant;
        $paymentQrReady = $invoice->isInvoice() && filled($tenant?->iban) && filled($tenant?->name);
        $dispatchLogs = $dispatchLogs ?? collect();
        $canSendNow = filled($invoice->recipient_email);
        $isDraft = $invoice->isDraft();
        $reminderCount = $reminderCount ?? 0;
        $lastReminderLog = $lastReminderLog ?? null;
        $nextReminderLabel = $nextReminderLabel ?? 'Zahlungserinnerung';
    @endphp
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">{{ $documentLabel }}</div>
            <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold">#{{ $invoice->invoice_number }}</h1>
                    <p class="mt-2 text-sm text-slate-300">
                        Empfaenger: {{ $invoice->getRecipientDisplayName() }}
                    </p>
                    @if($isDraft)
                        <div class="mt-3 inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-indigo-700">
                            Entwurf · In Arbeit
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3">
                    @if($invoice->isDraft())
                        <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-full border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">
                            Entwurf bearbeiten
                        </a>
                    @endif
                    @if($invoice->recipient_email)
                        <form action="{{ route('invoices.send', $invoice) }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-full border border-sky-300 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100">
                                Per E-Mail versenden
                            </button>
                        </form>
                    @endif
                    @if($invoice->status !== 'paid')
                        <form action="{{ route('invoices.status.update', $invoice) }}" method="POST" class="flex flex-wrap gap-2">
                            @csrf
                            @method('PATCH')
                            @if($invoice->status !== 'open')
                                <button type="submit" name="status" value="open" class="rounded-full border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                    Als offen markieren
                                </button>
                            @endif
                            @if($invoice->status !== 'entwurf')
                                <button type="submit" name="status" value="entwurf" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                                    Als Entwurf
                                </button>
                            @endif
                            @if($invoice->status !== 'storniert')
                                <button type="submit" name="status" value="storniert" class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                    Stornieren
                                </button>
                            @endif
                        </form>
                    @endif
                    <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" rel="noopener" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-slate-100">
                        PDF oeffnen
                    </a>
                    <a href="{{ route('invoices.index') }}" class="rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                        Zurueck
                    </a>
                </div>
            </div>
        </div>

        @if($isDraft)
            <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700">Arbeitsstand</div>
                        <h2 class="mt-2 text-xl font-semibold text-indigo-950">Dieses Dokument ist noch ein Entwurf</h2>
                        <p class="mt-2 text-sm text-indigo-900/80">
                            Inhalte, Positionen und Texte koennen noch angepasst werden. Erst nach Freigabe oder Versand wird daraus ein operatives Dokument.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                            Entwurf weiterbearbeiten
                        </a>
                        <form action="{{ route('invoices.status.update', $invoice) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" name="status" value="open" class="rounded-full border border-emerald-300 bg-white px-5 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                Als offen freigeben
                            </button>
                        </form>
                        <form action="{{ route('invoices.draft.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Diesen Entwurf wirklich löschen? Danach kannst du die Beitragsrechnung neu vorbereiten.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full border border-amber-300 bg-white px-5 py-3 text-sm font-semibold text-amber-800 transition hover:bg-amber-50">
                                Entwurf löschen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if($canSendNow && $dispatchLogs->isEmpty() && !$isDraft)
            <div class="rounded-3xl border border-sky-200 bg-sky-50 p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Naechster Schritt</div>
                        <h2 class="mt-2 text-xl font-semibold text-sky-950">{{ $documentLabel }} ist gespeichert, aber noch nicht versendet</h2>
                        <p class="mt-2 text-sm text-sky-900/80">
                            Wenn alles passt, kannst du das Dokument jetzt direkt per Mail mit PDF-Anhang an {{ $invoice->recipient_email }} schicken.
                        </p>
                    </div>
                    <form action="{{ route('invoices.send', $invoice) }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-full bg-sky-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-sky-700">
                            Jetzt per Mail versenden
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($invoice->isInvoice() && $invoice->isOpen() && $invoice->isOverdue())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Mahnwesen</div>
                        <h2 class="mt-2 text-xl font-semibold text-rose-950">
                            {{ $invoice->overdueDays() }} Tag{{ $invoice->overdueDays() === 1 ? '' : 'e' }} ueberfaellig
                        </h2>
                        <p class="mt-2 text-sm text-rose-900/80">
                            @if($reminderCount > 0)
                                Bereits versendet: {{ $reminderCount }} {{ $reminderCount === 1 ? 'Mahnstufe' : 'Mahnstufen' }}.
                                @if($lastReminderLog?->dispatched_at)
                                    Zuletzt am {{ $lastReminderLog->dispatched_at->format('d.m.Y H:i') }}.
                                @endif
                            @else
                                Bisher wurde noch keine Zahlungserinnerung versendet.
                            @endif
                        </p>
                    </div>

                    @if($canSendNow)
                        <a href="{{ route('invoices.reminder.preview', $invoice) }}" class="inline-flex rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                            {{ $nextReminderLabel }} vorbereiten
                        </a>
                    @else
                        <div class="rounded-2xl border border-rose-200 bg-white/70 px-4 py-3 text-sm text-rose-900">
                            Keine Empfaenger-E-Mail vorhanden.
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <section class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Empfaenger und Status</h2>

                    <div class="mt-5 space-y-4 text-sm text-slate-600">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $invoice->getRecipientDisplayName() }}</div>
                            @foreach ($invoice->getRecipientAddressLines() as $line)
                                <div class="mt-1">{{ $line }}</div>
                            @endforeach
                            @if ($invoice->recipient_email)
                                <div class="mt-2 text-slate-500">{{ $invoice->recipient_email }}</div>
                            @endif
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Rechnungsdatum</div>
                                <div class="mt-1 font-medium text-slate-900">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Faelligkeit</div>
                                <div class="mt-1 font-medium {{ $invoice->isOverdue() ? 'text-rose-700' : 'text-slate-900' }}">
                                    {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y') : '—' }}
                                </div>
                                @if($invoice->isOverdue())
                                    <div class="mt-1 text-xs font-medium text-rose-700">
                                        {{ $invoice->overdueDays() }} Tag{{ $invoice->overdueDays() === 1 ? '' : 'e' }} ueberfaellig
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Empfaengertyp</div>
                                <div class="mt-1 font-medium text-slate-900">{{ ucfirst($invoice->recipient_type ?? 'member') }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Ertragskonto</div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ $invoice->incomeAccount?->name ? (($invoice->incomeAccount->number ? $invoice->incomeAccount->number . ' - ' : '') . $invoice->incomeAccount->name) : 'Noch nicht festgelegt' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Versand</div>
                                <div class="mt-1 font-medium text-slate-900">
                                    @if(($dispatchLogs ?? collect())->isNotEmpty())
                                        {{ $dispatchLogs->count() }}x per Mail versendet
                                    @elseif($invoice->recipient_email)
                                        Bereit zum Versand
                                    @else
                                        Keine E-Mail hinterlegt
                                    @endif
                                </div>
                                @if($reminderCount > 0)
                                    <div class="mt-1 text-xs font-medium text-rose-700">
                                        {{ $reminderCount }} {{ $reminderCount === 1 ? 'Mahnstufe' : 'Mahnstufen' }} versendet
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status</div>
                                <div class="mt-1">
                                    @php $status = $invoice->status; @endphp
                                    <span class="@if($status === 'paid') bg-emerald-100 text-emerald-800 @elseif($status === 'open') bg-amber-100 text-amber-800 @elseif($status === 'entwurf') bg-slate-100 text-slate-700 @elseif($status === 'storniert') bg-rose-100 text-rose-700 @else bg-slate-100 text-slate-700 @endif inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                                        {{ ucfirst($status) }}
                                    </span>
                                    @if($invoice->isOverdue())
                                        <span class="ml-2 inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                            Ueberfaellig
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if($invoice->status !== 'paid')
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Naechster Schritt</div>
                                    <div class="mt-1 font-medium text-slate-900">
                                        {{ $invoice->status === 'entwurf' ? 'Vor Versand als offen markieren' : ($invoice->status === 'storniert' ? 'Bei Bedarf wieder oeffnen' : ($invoice->isOffer() ? 'Angebot abstimmen oder spaeter in eine Rechnung uebernehmen' : ($invoice->isOverdue() ? 'Zahlung pruefen oder Mahnung vorbereiten' : 'Zahlung erfassen oder per SEPA exportieren'))) }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Texte der Rechnung</h2>
                    <div class="mt-5 space-y-5 text-sm text-slate-700">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Einleitung</div>
                            <div class="mt-2 whitespace-pre-line rounded-2xl bg-slate-50 px-4 py-4">{{ $invoice->intro_text }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Zahlungshinweis</div>
                            <div class="mt-2 whitespace-pre-line rounded-2xl bg-slate-50 px-4 py-4">{{ $invoice->payment_text }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Abschluss</div>
                            <div class="mt-2 whitespace-pre-line rounded-2xl bg-slate-50 px-4 py-4">{{ $invoice->closing_text }}</div>
                        </div>
                    </div>
                </div>

                @if($invoice->isInvoice())
                    <div class="rounded-3xl border {{ $paymentQrReady ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-6 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] {{ $paymentQrReady ? 'text-emerald-700' : 'text-amber-700' }}">QR-Zahlung</div>
                        <h2 class="mt-2 text-lg font-semibold {{ $paymentQrReady ? 'text-emerald-950' : 'text-amber-950' }}">
                            {{ $paymentQrReady ? 'SEPA-QR ist im PDF aktiv' : 'Vereinskonto fehlt fuer den QR-Code' }}
                        </h2>
                        <p class="mt-2 text-sm {{ $paymentQrReady ? 'text-emerald-800' : 'text-amber-800' }}">
                            @if($paymentQrReady)
                                Rechnungsempfaenger koennen den QR-Code im PDF mit ihrer Banking-App scannen. Betrag, Empfaenger, IBAN und Verwendungszweck werden vorausgefuellt.
                            @else
                                Hinterlege in den Vereinsdaten mindestens Name und IBAN, damit Clubano den Ueberweisungs-QR automatisch in die Rechnung setzt.
                            @endif
                        </p>
                    </div>
                @endif

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Versandhistorie</h2>

                    <div class="mt-5 space-y-3">
                        @forelse(($dispatchLogs ?? collect()) as $log)
                            @php
                                $isReminderLog = $log->action === 'invoice_reminder_sent';
                                $reminderLabel = $isReminderLog
                                    ? ($log->meta['reminder_label'] ?? 'Mahnung')
                                    : 'Rechnung versendet';
                            @endphp
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-semibold text-slate-900">{{ $log->recipient_name }}</div>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $isReminderLog ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700' }}">
                                                {{ $reminderLabel }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500">{{ $log->recipient_reference ?: 'Keine Adresse hinterlegt' }}</div>
                                        @if($log->subject)
                                            <div class="mt-2 text-xs text-slate-500">{{ $log->subject }}</div>
                                        @endif
                                    </div>
                                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Mail</span>
                                </div>
                                <div class="mt-3 text-sm text-slate-600">
                                    {{ optional($log->dispatched_at)->format('d.m.Y H:i') ?: '–' }}
                                    @if($log->creator)
                                        · von {{ $log->creator->name }}
                                    @endif
                                </div>
                                @if(($log->open_count ?? 0) > 0 || ($log->click_count ?? 0) > 0)
                                    <div class="mt-2 flex flex-wrap gap-3 text-xs font-medium text-slate-500">
                                        @if(($log->open_count ?? 0) > 0)
                                            <span>{{ $log->open_count }}x geoeffnet</span>
                                        @endif
                                        @if(($log->click_count ?? 0) > 0)
                                            <span>{{ $log->click_count }}x geklickt</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                                Dieses Dokument wurde bisher noch nicht per Mail versendet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Positionen</h2>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Beschreibung</th>
                                    <th class="px-4 py-3 text-right">Menge</th>
                                    <th class="px-4 py-3">Einheit</th>
                                    <th class="px-4 py-3 text-right">Einzelpreis</th>
                                    <th class="px-4 py-3 text-right">Summe</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-slate-900">{{ $item->description }}</div>
                                            @if(!blank($item->details))
                                                <div class="mt-1 whitespace-pre-line text-sm text-slate-500">{{ $item->details }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $item->unit ?: '—' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-600">{{ number_format($item->unit_price, 2, ',', '.') }} €</td>
                                        <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 ml-auto max-w-sm space-y-3 text-sm">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Zwischensumme</span>
                            <span>{{ number_format($invoice->getSubtotal(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Rabatt</span>
                            <span>-{{ number_format($invoice->getDiscountAmount(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-600">
                            <span>USt. {{ number_format($invoice->tax_rate ?? 0, 2, ',', '.') }} %</span>
                            <span>{{ number_format($invoice->getTaxAmount(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900">
                            <span>Gesamt</span>
                            <span>{{ number_format($invoice->getTotal(), 2, ',', '.') }} €</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <iframe
                        src="{{ route('invoices.pdf', $invoice) }}#toolbar=1&navpanes=0&scrollbar=1"
                        class="h-[820px] w-full rounded-2xl"
                        title="PDF Vorschau {{ $documentLabel }} {{ $invoice->invoice_number }}">
                    </iframe>
                </div>
            </section>
        </div>
    </div>
@endsection

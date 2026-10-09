@php
    $startLabel = \Carbon\Carbon::parse($start)->format('d.m.Y');
    $endLabel = \Carbon\Carbon::parse($end)->format('d.m.Y');
    $periodLabel = $startLabel . ' bis ' . $endLabel;
    $issueGroups = [
        [
            'label' => 'Offene Buchungen',
            'count' => $pendingTransactions->count(),
            'items' => $pendingTransactions,
            'hint' => 'Entwürfe und nicht abgeschlossene Buchungen sollten vor der Prüfung abgeschlossen oder bewusst zurückgestellt werden.',
        ],
        [
            'label' => 'Fehlende Belege',
            'count' => $missingReceiptTransactions->count(),
            'items' => $missingReceiptTransactions,
            'hint' => 'Für diese Buchungen fehlt ein Beleg, Eigenbeleg, Vertrag oder eine Clubano-Rechnung.',
        ],
        [
            'label' => 'Belege nicht geprüft',
            'count' => $uncheckedReceiptTransactions->count(),
            'items' => $uncheckedReceiptTransactions,
            'hint' => 'Der Beleg ist noch nicht als geprüft markiert.',
        ],
        [
            'label' => 'Buchungen nicht geprüft',
            'count' => $uncheckedReviewTransactions->count(),
            'items' => $uncheckedReviewTransactions,
            'hint' => 'Die Buchung ist noch nicht im Journal gegengezeichnet.',
        ],
    ];
@endphp

<div class="{{ ($isPdf ?? false) ? '' : 'mx-auto max-w-7xl space-y-8' }}">
    <section class="{{ ($isPdf ?? false) ? 'audit-card' : 'overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm' }}">
        <div class="{{ ($isPdf ?? false) ? '' : 'flex flex-col gap-6 bg-slate-950 px-6 py-7 text-white md:px-8 lg:flex-row lg:items-end lg:justify-between' }}">
            <div class="max-w-3xl space-y-3">
                <p class="{{ ($isPdf ?? false) ? 'muted' : 'text-xs font-semibold uppercase tracking-[0.32em] text-slate-300' }}">Finanzen</p>
                <div class="space-y-2">
                    <h1 class="{{ ($isPdf ?? false) ? '' : 'text-3xl font-semibold tracking-tight sm:text-4xl' }}">Kassenprüfung</h1>
                    <p class="{{ ($isPdf ?? false) ? 'muted' : 'max-w-2xl text-sm leading-6 text-slate-300 sm:text-base' }}">
                        Prüfvorbereitung für Bank, Kasse, Belege und offene Posten. Zeitraum: {{ $periodLabel }}.
                    </p>
                </div>
            </div>

            <div class="{{ ($isPdf ?? false) ? '' : 'rounded-2xl border border-white/10 bg-white/5 px-5 py-4' }}">
                <div class="{{ ($isPdf ?? false) ? '' : 'text-2xl font-semibold' }}">{{ $issueCount }}</div>
                <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm text-slate-300' }}">offene Prüfpunkte</div>
            </div>
        </div>

        @unless($isPdf ?? false)
            <form class="screen-only grid gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] md:items-end md:px-8">
                <div>
                    <label for="start" class="mb-1 block text-sm font-medium text-slate-600">Von</label>
                    <input id="start" type="date" name="start" value="{{ $start }}" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
                <div>
                    <label for="end" class="mb-1 block text-sm font-medium text-slate-600">Bis</label>
                    <input id="end" type="date" name="end" value="{{ $end }}" class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                </div>
                <button class="inline-flex items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Aktualisieren
                </button>
                <a href="{{ route('transactions.audit.pdf', request()->only(['start', 'end'])) }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-950">
                    PDF
                </a>
            </form>
        @endunless
    </section>

    <div class="{{ ($isPdf ?? false) ? '' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-6' }}">
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-slate-500' }}">Anfangsbestand</div>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-2 text-2xl font-semibold text-slate-950' }}">{{ number_format($totalOpening, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">Bank und Kasse vor dem Zeitraum</div>
        </div>
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-slate-500' }}">Endbestand</div>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-2 text-2xl font-semibold text-slate-950' }}">{{ number_format($totalClosing, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">Bank und Kasse zum Stichtag</div>
        </div>
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-emerald-700' }}">Einnahmen</div>
            <div class="{{ ($isPdf ?? false) ? 'amount-positive' : 'mt-2 text-2xl font-semibold text-emerald-700' }}">{{ number_format($totalIncome, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-emerald-700/80' }}">Erlöse im Zeitraum</div>
        </div>
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-rose-700' }}">Ausgaben</div>
            <div class="{{ ($isPdf ?? false) ? 'amount-negative' : 'mt-2 text-2xl font-semibold text-rose-700' }}">{{ number_format($totalExpense, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-rose-700/80' }}">Aufwendungen im Zeitraum</div>
        </div>
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border ' . ($saldo >= 0 ? 'border-slate-200 bg-white' : 'border-amber-200 bg-amber-50/60') . ' p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-slate-500' }}">Ergebnis</div>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-2 text-2xl font-semibold ' . ($saldo >= 0 ? 'text-slate-950' : 'text-amber-700') }}">{{ number_format($saldo, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">Einnahmen minus Ausgaben</div>
        </div>
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-sm font-medium text-slate-500' }}">Umbuchungen</div>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-2 text-2xl font-semibold text-slate-950' }}">{{ number_format($transferTotal, 2, ',', '.') }} €</div>
            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">Zwischen Bank und Kasse</div>
        </div>
    </div>

    <section class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
        <div class="{{ ($isPdf ?? false) ? '' : 'flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between' }}">
            <div>
                <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-slate-900' }}">Bank und Kasse</h2>
                <p class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-sm text-slate-500' }}">Bestände werden aus Anfangsbestand plus Buchungen bis zum Stichtag berechnet.</p>
            </div>
            @unless($isPdf ?? false)
                <a href="{{ route('accounts.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Konten öffnen</a>
            @endunless
        </div>
        <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 overflow-hidden rounded-2xl border border-slate-200' }}">
            <table class="{{ ($isPdf ?? false) ? 'audit-grid' : 'min-w-full divide-y divide-slate-200 text-sm' }}">
                <thead class="{{ ($isPdf ?? false) ? '' : 'bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500' }}">
                    <tr>
                        <th class="{{ ($isPdf ?? false) ? '' : 'px-4 py-3' }}">Konto</th>
                        <th class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right' }}">Anfang</th>
                        <th class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right' }}">Zugang</th>
                        <th class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right' }}">Abgang</th>
                        <th class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right' }}">Ende</th>
                    </tr>
                </thead>
                <tbody class="{{ ($isPdf ?? false) ? '' : 'divide-y divide-slate-100 bg-white' }}">
                    @forelse($accountRows as $row)
                        <tr>
                            <td class="{{ ($isPdf ?? false) ? '' : 'px-4 py-3' }}">
                                <div class="font-medium text-slate-900">{{ $row['account']->number }} · {{ $row['account']->name }}</div>
                                <div class="{{ ($isPdf ?? false) ? 'muted' : 'text-xs text-slate-500' }}">{{ $row['transaction_count'] }} Buchung(en)</div>
                            </td>
                            <td class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right font-mono' }}">{{ number_format($row['opening'], 2, ',', '.') }} €</td>
                            <td class="{{ ($isPdf ?? false) ? 'text-right amount-positive' : 'px-4 py-3 text-right font-mono text-emerald-700' }}">{{ number_format($row['in'], 2, ',', '.') }} €</td>
                            <td class="{{ ($isPdf ?? false) ? 'text-right amount-negative' : 'px-4 py-3 text-right font-mono text-rose-700' }}">{{ number_format($row['out'], 2, ',', '.') }} €</td>
                            <td class="{{ ($isPdf ?? false) ? 'text-right' : 'px-4 py-3 text-right font-mono font-semibold text-slate-950' }}">{{ number_format($row['closing'], 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="{{ ($isPdf ?? false) ? 'muted' : 'px-4 py-8 text-center text-slate-500' }}">Noch keine aktiven Bank- oder Kassenkonten vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="{{ ($isPdf ?? false) ? '' : 'grid gap-4 lg:grid-cols-2' }}">
        @foreach($issueGroups as $group)
            <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border ' . ($group['count'] > 0 ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white') . ' p-5 shadow-sm' }}">
                <div class="{{ ($isPdf ?? false) ? '' : 'flex items-start justify-between gap-3' }}">
                    <div>
                        <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-slate-900' }}">{{ $group['label'] }}</h2>
                        <p class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-sm text-slate-500' }}">{{ $group['hint'] }}</p>
                    </div>
                    <div class="{{ ($isPdf ?? false) ? '' : 'rounded-full bg-white px-3 py-1 font-mono text-lg font-semibold text-slate-900' }}">{{ $group['count'] }}</div>
                </div>

                <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 space-y-2' }}">
                    @forelse($group['items']->take(5) as $transaction)
                        <a href="{{ ($isPdf ?? false) ? '#' : route('transactions.edit', $transaction) }}" class="{{ ($isPdf ?? false) ? '' : 'block rounded-xl border border-white bg-white px-3 py-2 text-sm hover:border-amber-200' }}">
                            <div class="{{ ($isPdf ?? false) ? '' : 'flex items-center justify-between gap-3' }}">
                                <span class="font-medium text-slate-900">{{ $transaction->date?->format('d.m.Y') }} · {{ $transaction->description }}</span>
                                <span class="{{ ($isPdf ?? false) ? '' : 'font-mono text-slate-700' }}">{{ number_format((float) $transaction->amount, 2, ',', '.') }} €</span>
                            </div>
                            <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">
                                {{ $transaction->account_from?->number }} {{ $transaction->account_from?->name }} → {{ $transaction->account_to?->number }} {{ $transaction->account_to?->name }}
                            </div>
                        </a>
                    @empty
                        <div class="{{ ($isPdf ?? false) ? 'muted' : 'rounded-xl border border-dashed border-slate-300 bg-white px-3 py-5 text-sm text-slate-500' }}">
                            Kein offener Punkt in dieser Kategorie.
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </section>

    @if(($duplicateTransactionGroups ?? collect())->isNotEmpty())
        <section class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm' }}">
            <div class="{{ ($isPdf ?? false) ? '' : 'flex items-start justify-between gap-3' }}">
                <div>
                    <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-amber-950' }}">Mögliche Doppelbuchungen</h2>
                    <p class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-sm text-amber-800' }}">
                        Gleicher Tag, gleicher Betrag und gleiche Konten. Diese Gruppen sollten vor der Kassenprüfung fachlich geprüft werden.
                    </p>
                </div>
                <div class="{{ ($isPdf ?? false) ? '' : 'rounded-full bg-white px-3 py-1 font-mono text-lg font-semibold text-amber-900' }}">{{ $duplicateTransactionGroups->count() }}</div>
            </div>

            <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 space-y-3' }}">
                @foreach($duplicateTransactionGroups->take(6) as $group)
                    <div class="{{ ($isPdf ?? false) ? '' : 'rounded-2xl border border-amber-200 bg-white p-4' }}">
                        <div class="{{ ($isPdf ?? false) ? '' : 'flex items-start justify-between gap-3' }}">
                            <div>
                                <div class="font-semibold text-slate-900">
                                    {{ optional($group['date'])->format('d.m.Y') ?: 'Ohne Datum' }} · {{ number_format($group['amount'], 2, ',', '.') }} €
                                </div>
                                <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">
                                    {{ $group['account_from']->name ?? '—' }} → {{ $group['account_to']->name ?? '—' }}
                                </div>
                            </div>
                            <span class="{{ ($isPdf ?? false) ? '' : 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800' }}">{{ $group['count'] }}x</span>
                        </div>
                        <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-2 text-xs text-slate-500' }}">
                            @foreach($group['transactions']->take(4) as $transaction)
                                {{ $transaction->receipt_number ?: '#' . $transaction->id }}: {{ $transaction->description }}@if(!$loop->last) · @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="{{ ($isPdf ?? false) ? '' : 'grid gap-4 lg:grid-cols-2' }}">
        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
            <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-slate-900' }}">Offene Ausgangsrechnungen zum Stichtag</h2>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 space-y-2' }}">
                @forelse($openInvoices->take(8) as $invoice)
                    <a href="{{ ($isPdf ?? false) ? '#' : route('invoices.show', $invoice) }}" class="{{ ($isPdf ?? false) ? '' : 'block rounded-xl border border-slate-200 px-3 py-2 text-sm hover:border-slate-400' }}">
                        <div class="{{ ($isPdf ?? false) ? '' : 'flex items-center justify-between gap-3' }}">
                            <span class="font-medium text-slate-900">{{ $invoice->invoice_number }} · {{ $invoice->recipient_name }}</span>
                            <span class="{{ ($isPdf ?? false) ? '' : 'font-mono text-slate-900' }}">{{ number_format($invoice->getRemainingAmount(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">Fällig {{ optional($invoice->due_date)->format('d.m.Y') ?: 'ohne Datum' }}</div>
                    </a>
                @empty
                    <div class="{{ ($isPdf ?? false) ? 'muted' : 'rounded-xl border border-dashed border-slate-300 px-3 py-5 text-sm text-slate-500' }}">Keine offenen Ausgangsrechnungen zum Stichtag.</div>
                @endforelse
            </div>
        </div>

        <div class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
            <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-slate-900' }}">Offene Eingangsrechnungen zum Stichtag</h2>
            <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 space-y-2' }}">
                @forelse($openPayables->take(8) as $document)
                    <a href="{{ ($isPdf ?? false) ? '#' : route('documents.show', $document) }}" class="{{ ($isPdf ?? false) ? '' : 'block rounded-xl border border-slate-200 px-3 py-2 text-sm hover:border-slate-400' }}">
                        <div class="{{ ($isPdf ?? false) ? '' : 'flex items-center justify-between gap-3' }}">
                            <span class="font-medium text-slate-900">{{ $document->title }}</span>
                            <span class="{{ ($isPdf ?? false) ? '' : 'font-mono text-slate-900' }}">{{ number_format($document->payableRemainingAmount(), 2, ',', '.') }} €</span>
                        </div>
                        <div class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-xs text-slate-500' }}">
                            {{ $document->derivedPayableStatusLabel() }} · fällig {{ optional($document->payable_due_date)->format('d.m.Y') ?: 'ohne Datum' }}
                        </div>
                    </a>
                @empty
                    <div class="{{ ($isPdf ?? false) ? 'muted' : 'rounded-xl border border-dashed border-slate-300 px-3 py-5 text-sm text-slate-500' }}">Keine offenen Eingangsrechnungen zum Stichtag.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="{{ ($isPdf ?? false) ? 'audit-card' : 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' }}">
        <div class="{{ ($isPdf ?? false) ? '' : 'flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between' }}">
            <div>
                <h2 class="{{ ($isPdf ?? false) ? '' : 'text-lg font-semibold text-slate-900' }}">Korrekturen und Stornos</h2>
                <p class="{{ ($isPdf ?? false) ? 'muted' : 'mt-1 text-sm text-slate-500' }}">Alles, was im Prüfzeitraum als Storno oder Korrektur erkennbar ist.</p>
            </div>
            @unless($isPdf ?? false)
                <a href="{{ route('transactions.journal', ['filter' => 'storno']) }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Im Journal öffnen</a>
            @endunless
        </div>
        <div class="{{ ($isPdf ?? false) ? '' : 'mt-4 space-y-2' }}">
            @forelse($correctionTransactions->take(8) as $transaction)
                <a href="{{ ($isPdf ?? false) ? '#' : route('transactions.edit', $transaction) }}" class="{{ ($isPdf ?? false) ? '' : 'block rounded-xl border border-slate-200 px-3 py-2 text-sm hover:border-slate-400' }}">
                    <div class="{{ ($isPdf ?? false) ? '' : 'flex items-center justify-between gap-3' }}">
                        <span class="font-medium text-slate-900">{{ $transaction->date?->format('d.m.Y') }} · {{ $transaction->description }}</span>
                        <span class="{{ ($isPdf ?? false) ? '' : 'font-mono text-slate-900' }}">{{ number_format((float) $transaction->amount, 2, ',', '.') }} €</span>
                    </div>
                </a>
            @empty
                <div class="{{ ($isPdf ?? false) ? 'muted' : 'rounded-xl border border-dashed border-slate-300 px-3 py-5 text-sm text-slate-500' }}">Keine Korrekturen oder Stornos im Zeitraum.</div>
            @endforelse
        </div>
    </section>

    @unless($isPdf ?? false)
        <section class="rounded-2xl border border-blue-200 bg-blue-50/70 p-5 text-sm text-blue-900">
            Diese Seite bereitet die Kassenprüfung vor. Die fachliche Bestätigung erfolgt weiterhin durch die Prüferinnen und Prüfer, aber offene Buchungen, fehlende Belege und ungeprüfte Journalpunkte sind hier an einer Stelle sichtbar.
        </section>
    @endunless
</div>

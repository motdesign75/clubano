@extends('layouts.app')

@section('title', 'Neue Buchung')

@section('content')
@php
    $prefill = $prefill ?? [];
    $guidedContext = $guidedContext ?? null;

    $contextCards = [
        'bar-einnahme' => [
            'label' => 'Bareinnahme',
            'description' => 'Zum Beispiel Theke, Spende oder Barzahlung vor Ort.',
            'classes' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        ],
        'bar-ausgabe' => [
            'label' => 'Barausgabe',
            'description' => 'Zum Beispiel Einkauf, Erstattung oder kleiner Vereinsbedarf.',
            'classes' => 'border-rose-200 bg-rose-50 text-rose-800',
        ],
        'bank-zu-kasse' => [
            'label' => 'Bank -> Kasse',
            'description' => 'Barabhebung oder Geld aus dem Bankkonto in die Kasse legen.',
            'classes' => 'border-sky-200 bg-sky-50 text-sky-800',
        ],
        'kasse-zu-bank' => [
            'label' => 'Kasse -> Bank',
            'description' => 'Tageseinnahmen einzahlen oder Bargeld zurück aufs Konto bringen.',
            'classes' => 'border-violet-200 bg-violet-50 text-violet-800',
        ],
        'expert' => [
            'label' => 'Expertenmodus',
            'description' => 'Freie Kontenzuordnung für Sonderfälle und bestehende Arbeitsweisen.',
            'classes' => 'border-slate-200 bg-slate-50 text-slate-700',
        ],
    ];

    $contextMessages = [
        'bar-einnahme' => 'Clubano führt dich durch eine Bareinnahme. Du wählst nur noch das passende Einnahmekonto; die Kasse ist bereits als Ziel gesetzt.',
        'bar-ausgabe' => 'Clubano führt dich durch eine Barausgabe. Du wählst nur noch das passende Ausgabekonto; die Kasse ist bereits als Quelle gesetzt.',
        'bank-zu-kasse' => 'Clubano erfasst hier eine Umbuchung von der Bank in die Kasse, zum Beispiel für eine Barabhebung.',
        'kasse-zu-bank' => 'Clubano erfasst hier eine Umbuchung von der Kasse zur Bank, zum Beispiel für eine Einzahlung.',
    ];

    $activeContext = $guidedContext ?: 'expert';

    $selectedFromId = (string) old('account_from_id', $prefill['account_from_id'] ?? '');
    $selectedToId = (string) old('account_to_id', $prefill['account_to_id'] ?? '');

    $selectedCashAccount = $cashAccounts->firstWhere('id', (int) ($prefill['account_to_id'] ?? $prefill['account_from_id']));
    if ($guidedContext === 'bar-ausgabe' || $guidedContext === 'kasse-zu-bank') {
        $selectedCashAccount = $cashAccounts->firstWhere('id', (int) ($prefill['account_from_id'] ?? null)) ?: $selectedCashAccount;
    }
    $selectedCashAccount = $selectedCashAccount ?: $cashAccounts->first();

    $selectedBankAccount = $bankAccounts->firstWhere('id', (int) ($prefill['account_from_id'] ?? $prefill['account_to_id']));
    if ($guidedContext === 'kasse-zu-bank') {
        $selectedBankAccount = $bankAccounts->firstWhere('id', (int) ($prefill['account_to_id'] ?? null)) ?: $selectedBankAccount;
    }
    $selectedBankAccount = $selectedBankAccount ?: $bankAccounts->first();

    $amountValue = old('amount', $prefill['amount'] ?? '');
    $taxAreaValue = old('tax_area', $prefill['tax_area'] ?? '');
    $statusValue = old('status', 'entwurf');
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Neue Buchung</h1>
        <p class="max-w-3xl text-sm text-slate-500">
            Wähle zuerst die Art der Buchung. Clubano legt dir dann die richtigen Konten und die passende Richtung vor, damit niemand mehr im Kopf mit Soll und Haben jonglieren muss.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-5">
        @foreach($contextCards as $contextKey => $card)
            @php
                $routeParams = ['context' => $contextKey === 'expert' ? null : $contextKey];
                if ($selectedCashAccount && in_array($contextKey, ['bar-einnahme', 'bank-zu-kasse'], true)) {
                    $routeParams['account_to_id'] = $selectedCashAccount->id;
                }
                if ($selectedCashAccount && in_array($contextKey, ['bar-ausgabe', 'kasse-zu-bank'], true)) {
                    $routeParams['account_from_id'] = $selectedCashAccount->id;
                }
                if ($selectedBankAccount && $contextKey === 'bank-zu-kasse') {
                    $routeParams['account_from_id'] = $selectedBankAccount->id;
                }
                if ($selectedBankAccount && $contextKey === 'kasse-zu-bank') {
                    $routeParams['account_to_id'] = $selectedBankAccount->id;
                }
                if ($taxAreaValue !== '') {
                    $routeParams['tax_area'] = $taxAreaValue;
                }
                $isActive = $activeContext === $contextKey;
            @endphp
            <a href="{{ route('transactions.create', array_filter($routeParams, fn ($value) => $value !== null && $value !== '')) }}"
               class="rounded-2xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow {{ $isActive ? $card['classes'] : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                <div class="text-sm font-semibold">{{ $card['label'] }}</div>
                <div class="mt-1 text-xs leading-5 {{ $isActive ? 'text-inherit opacity-90' : 'text-slate-500' }}">
                    {{ $card['description'] }}
                </div>
            </a>
        @endforeach
    </div>

    <form method="POST"
          action="{{ route('transactions.store') }}"
          enctype="multipart/form-data"
          class="space-y-6">
        @csrf

        @if($guidedContext)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                {{ $contextMessages[$guidedContext] ?? '' }}
            </div>
        @endif

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.9fr)]">
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <label for="date" class="mb-1 block text-sm font-medium text-slate-600">Datum *</label>
                            <input id="date"
                                   type="date"
                                   name="date"
                                   value="{{ old('date', $prefill['date'] ?? now()->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   required>
                            @error('date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="amount" class="mb-1 block text-sm font-medium text-slate-600">Betrag (€) *</label>
                            <input id="amount"
                                   type="number"
                                   name="amount"
                                   value="{{ $amountValue }}"
                                   step="0.01"
                                   min="0.01"
                                   class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="0,00"
                                   required>
                            <p class="mt-1 text-xs text-slate-500">Bitte immer als positiven Betrag eingeben.</p>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label for="description" class="mb-1 block text-sm font-medium text-slate-600">Beschreibung *</label>
                            <textarea id="description"
                                      name="description"
                                      rows="3"
                                      class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      required>{{ old('description', $prefill['description'] ?? '') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Zum Beispiel „Kuchenverkauf Sommerfest“ oder „Getränkeeinkauf Vereinsheim“.</p>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tax_area" class="mb-1 block text-sm font-medium text-slate-600">Steuerbereich *</label>
                            <select id="tax_area"
                                    name="tax_area"
                                    class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    required>
                                <option value="">Bitte wählen</option>
                                <option value="ideell" @selected($taxAreaValue === 'ideell')>Ideeller Bereich</option>
                                <option value="zweckbetrieb" @selected($taxAreaValue === 'zweckbetrieb')>Zweckbetrieb</option>
                                <option value="vermoegensverwaltung" @selected($taxAreaValue === 'vermoegensverwaltung')>Vermögensverwaltung</option>
                                <option value="wirtschaftlich" @selected($taxAreaValue === 'wirtschaftlich')>Wirtschaftlicher Betrieb</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Für Beiträge und Spenden meistens ideell, für Veranstaltungen oft Zweckbetrieb.</p>
                            @error('tax_area')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="status" class="mb-1 block text-sm font-medium text-slate-600">Status beim Speichern *</label>
                            <select id="status"
                                    name="status"
                                    class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    required>
                                <option value="entwurf" @selected($statusValue === 'entwurf')>Offen / noch korrigierbar</option>
                                <option value="abgeschlossen" @selected($statusValue === 'abgeschlossen')>Direkt abschließen</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Offene Buchungen kannst du später markieren und gesammelt abschließen.</p>
                            @error('status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($guidedContext === 'bar-einnahme')
                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_from_id',
                                'id' => 'account_from_id',
                                'label' => 'Einnahmekonto *',
                                'accounts' => $incomeAccounts,
                                'selectedId' => $selectedFromId,
                                'placeholder' => 'Einnahmekonto suchen',
                            ])
                            @error('account_from_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <input type="hidden" name="account_to_id" value="{{ old('account_to_id', $selectedCashAccount?->id) }}">
                        @elseif($guidedContext === 'bar-ausgabe')
                            <input type="hidden" name="account_from_id" value="{{ old('account_from_id', $selectedCashAccount?->id) }}">
                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_to_id',
                                'id' => 'account_to_id',
                                'label' => 'Ausgabekonto *',
                                'accounts' => $expenseAccounts,
                                'selectedId' => $selectedToId,
                                'placeholder' => 'Ausgabekonto suchen',
                            ])
                            @error('account_to_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        @elseif($guidedContext === 'bank-zu-kasse')
                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_from_id',
                                'id' => 'account_from_id',
                                'label' => 'Bankkonto *',
                                'accounts' => $bankAccounts,
                                'selectedId' => $selectedFromId,
                                'placeholder' => 'Bankkonto suchen',
                            ])
                            @error('account_from_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <input type="hidden" name="account_to_id" value="{{ old('account_to_id', $selectedCashAccount?->id) }}">
                        @elseif($guidedContext === 'kasse-zu-bank')
                            <input type="hidden" name="account_from_id" value="{{ old('account_from_id', $selectedCashAccount?->id) }}">
                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_to_id',
                                'id' => 'account_to_id',
                                'label' => 'Bankkonto *',
                                'accounts' => $bankAccounts,
                                'selectedId' => $selectedToId,
                                'placeholder' => 'Bankkonto suchen',
                            ])
                            @error('account_to_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        @else
                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_from_id',
                                'id' => 'account_from_id',
                                'label' => 'Von Konto *',
                                'accounts' => $accounts,
                                'selectedId' => $selectedFromId,
                                'placeholder' => 'Von-Konto suchen, z. B. 1200 oder Bank',
                            ])
                            @error('account_from_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            @include('transactions.partials.account-search-select', [
                                'name' => 'account_to_id',
                                'id' => 'account_to_id',
                                'label' => 'Nach Konto *',
                                'accounts' => $accounts,
                                'selectedId' => $selectedToId,
                                'placeholder' => 'Nach-Konto suchen, z. B. 8006 oder Beitrag',
                            ])
                            @error('account_to_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        @endif

                        <div class="lg:col-span-2">
                            <label for="receipt_file" class="mb-1 block text-sm font-medium text-slate-600">Beleg hochladen</label>
                            <input id="receipt_file"
                                   type="file"
                                   name="receipt_file"
                                   accept="image/*,.pdf"
                                   capture="environment"
                                   class="w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-slate-500">Optional, aber empfohlen. Auf dem Handy öffnet sich direkt die Kamera oder Dateiauswahl.</p>
                            @error('receipt_file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <input type="hidden" name="receipt_kind" value="none">
                            <label class="flex items-start gap-3">
                                <input type="checkbox"
                                       name="receipt_kind"
                                       value="vertrag"
                                       @checked(old('receipt_kind') === 'vertrag')
                                       class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-800">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Vertrag oder Dauerbeleg liegt vor</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">
                                        Für Miete, Versicherungen oder feste Dienstleister, bei denen nicht jeden Monat ein neuer Beleg kommt.
                                    </span>
                                </span>
                            </label>

                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div>
                                    <label for="contract_reference" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Vertrag / Grundlage</label>
                                    <input id="contract_reference"
                                           name="contract_reference"
                                           type="text"
                                           value="{{ old('contract_reference') }}"
                                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                                           placeholder="z. B. Mietvertrag Vereinsheim">
                                    @error('contract_reference')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="contract_location" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Ablageort</label>
                                    <input id="contract_location"
                                           name="contract_location"
                                           type="text"
                                           value="{{ old('contract_location') }}"
                                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900"
                                           placeholder="z. B. Dokumente / Verträge">
                                </div>

                                <div>
                                    <label for="contract_date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Vertragsdatum</label>
                                    <input id="contract_date"
                                           name="contract_date"
                                           type="date"
                                           value="{{ old('contract_date') }}"
                                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm 2xl:sticky 2xl:top-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Buchungsbild</div>
                    <div class="mt-3 space-y-3 text-sm text-slate-600">
                        @if($guidedContext === 'bar-einnahme')
                            <div>Quelle: <span class="font-medium text-slate-900">Einnahmekonto</span></div>
                            <div>Ziel: <span class="font-medium text-slate-900">{{ $selectedCashAccount?->name ?? 'Kasse wählen' }}</span></div>
                            <div class="rounded-xl bg-emerald-50 px-3 py-2 text-emerald-800">Wirkung: Kassenbestand steigt.</div>
                        @elseif($guidedContext === 'bar-ausgabe')
                            <div>Quelle: <span class="font-medium text-slate-900">{{ $selectedCashAccount?->name ?? 'Kasse wählen' }}</span></div>
                            <div>Ziel: <span class="font-medium text-slate-900">Ausgabekonto</span></div>
                            <div class="rounded-xl bg-rose-50 px-3 py-2 text-rose-800">Wirkung: Kassenbestand sinkt.</div>
                        @elseif($guidedContext === 'bank-zu-kasse')
                            <div>Quelle: <span class="font-medium text-slate-900">Bankkonto</span></div>
                            <div>Ziel: <span class="font-medium text-slate-900">{{ $selectedCashAccount?->name ?? 'Kasse wählen' }}</span></div>
                            <div class="rounded-xl bg-sky-50 px-3 py-2 text-sky-800">Wirkung: Bankbestand sinkt, Kassenbestand steigt.</div>
                        @elseif($guidedContext === 'kasse-zu-bank')
                            <div>Quelle: <span class="font-medium text-slate-900">{{ $selectedCashAccount?->name ?? 'Kasse wählen' }}</span></div>
                            <div>Ziel: <span class="font-medium text-slate-900">Bankkonto</span></div>
                            <div class="rounded-xl bg-violet-50 px-3 py-2 text-violet-800">Wirkung: Kassenbestand sinkt, Bankbestand steigt.</div>
                        @else
                            <div>Im Expertenmodus wählst du beide Konten frei.</div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2 text-slate-700">Gut für Sonderfälle, Umbuchungen und Altlogik.</div>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Hilfreich im Alltag</div>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600">
                        <li>Barverkauf oder Spende: <span class="font-medium text-slate-900">Bareinnahme</span></li>
                        <li>Einkauf aus der Kasse: <span class="font-medium text-slate-900">Barausgabe</span></li>
                        <li>Geld abheben: <span class="font-medium text-slate-900">Bank -> Kasse</span></li>
                        <li>Tageseinnahmen einzahlen: <span class="font-medium text-slate-900">Kasse -> Bank</span></li>
                    </ul>
                </div>
            </aside>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ $guidedContext ? route('transactions.cashbook') : route('transactions.index') }}"
               class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto">
                Zurück
            </a>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800 sm:w-auto">
                Buchung speichern
            </button>
        </div>
    </form>
</div>
@endsection

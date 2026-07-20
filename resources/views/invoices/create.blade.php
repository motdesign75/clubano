@extends('layouts.app')
@section('help-key', 'invoices.create')

@section('content')
    @php
        $editing = isset($invoice);
        $formInvoice = $invoice ?? null;
        $isOffer = ($documentType ?? 'invoice') === 'offer';
        $defaultStatus = old('status', $formInvoice->status ?? 'open');
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-5 sm:py-6">
        <div class="rounded-3xl bg-slate-950 px-5 py-5 text-white shadow-sm sm:px-6 sm:py-6">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Rechnungen & Angebote</div>
            <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">
                {{ $editing ? (($isOffer ? 'Angebot' : 'Rechnung') . ' bearbeiten') : ('Neues ' . ($isOffer ? 'Angebot' : 'Rechnung') . ' erstellen') }}
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                {{ $editing ? 'Entwurf anpassen, pruefen und anschliessend speichern oder direkt versenden.' : 'Erst Empfaenger, dann Positionen, dann entscheiden ob du nur speichern oder direkt versenden willst.' }}
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-800">
                <div class="font-semibold">Bitte pruefe deine Eingaben.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('invoices.update', $formInvoice) : route('invoices.store') }}" x-data="invoiceForm()" class="space-y-6">
            @csrf
            @if($editing)
                @method('PATCH')
            @endif
            <input type="hidden" name="document_type" value="{{ $isOffer ? 'offer' : 'invoice' }}">

            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">1. Empfaenger festlegen</h2>
                                <p class="mt-1 text-sm text-slate-500">Waehle, woher die Rechnungsadresse kommen soll.</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            @foreach (['member' => 'Mitglied', 'contact' => 'Kontakt', 'free' => 'Freie Adresse'] as $type => $label)
                                <label class="cursor-pointer rounded-2xl border p-4 transition"
                                       :class="recipientType === '{{ $type }}' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700'">
                                    <input type="radio" class="hidden" name="recipient_type" value="{{ $type }}" x-model="recipientType">
                                    <div class="text-sm font-semibold">{{ $label }}</div>
                                    <div class="mt-1 text-xs" :class="recipientType === '{{ $type }}' ? 'text-slate-300' : 'text-slate-500'">
                                        {{ $type === 'member' ? 'Mit vorhandener Mitgliederadresse arbeiten' : ($type === 'contact' ? 'Kontakt aus dem Adressbuch nutzen' : 'Adresse frei erfassen') }}
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2" x-show="recipientType === 'member'" x-cloak>
                            <div class="md:col-span-2">
                                <label for="member_id" class="mb-1 block text-sm font-medium text-slate-700">Mitglied</label>
                                <select name="member_id" id="member_id" x-model="memberId" @change="fillFromMember()" class="w-full rounded-2xl border-slate-300">
                                    <option value="">Bitte auswaehlen</option>
                                    @foreach ($members as $member)
                                        <option
                                            value="{{ $member->id }}"
                                            data-name="{{ $member->full_name }}"
                                            data-company="{{ $member->organization }}"
                                            data-salutation="{{ $member->salutation }}"
                                            data-email="{{ $member->email }}"
                                            data-street="{{ trim(($member->care_of ? $member->care_of . ' / ' : '') . $member->street . ($member->address_addition ? ' ' . $member->address_addition : '')) }}"
                                            data-zip="{{ $member->zip }}"
                                            data-city="{{ $member->city }}"
                                            data-country="{{ $member->country ?: 'Deutschland' }}"
                                            @selected(old('member_id', $formInvoice?->member_id) == $member->id)
                                        >
                                            {{ $member->last_name }}, {{ $member->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2" x-show="recipientType === 'contact'" x-cloak>
                            <div class="md:col-span-2">
                                <label for="contact_id" class="mb-1 block text-sm font-medium text-slate-700">Kontakt</label>
                                <select name="contact_id" id="contact_id" x-model="contactId" @change="fillFromContact()" class="w-full rounded-2xl border-slate-300">
                                    <option value="">Bitte auswaehlen</option>
                                    @foreach ($contacts as $contact)
                                        <option
                                            value="{{ $contact->id }}"
                                            data-name="{{ $contact->full_name ?: $contact->display_name }}"
                                            data-company="{{ $contact->organization ?: $contact->company }}"
                                            data-salutation="{{ $contact->salutation }}"
                                            data-email="{{ $contact->primary_email }}"
                                            data-street="{{ trim(($contact->care_of ? $contact->care_of . ' / ' : '') . ($contact->street ?: '') . ($contact->address_addition ? ' ' . $contact->address_addition : '')) }}"
                                            data-zip="{{ $contact->zip ?: $contact->postal_code }}"
                                            data-city="{{ $contact->city }}"
                                            data-country="{{ $contact->country ?: 'Deutschland' }}"
                                            @selected(old('contact_id', $formInvoice?->contact_id) == $contact->id)
                                        >
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="recipient_name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                                <input type="text" name="recipient_name" id="recipient_name" x-model="recipient.name" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="recipient_company" class="mb-1 block text-sm font-medium text-slate-700">Organisation / Firma</label>
                                <input type="text" name="recipient_company" id="recipient_company" x-model="recipient.company" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="recipient_email" class="mb-1 block text-sm font-medium text-slate-700">E-Mail</label>
                                <input type="email" name="recipient_email" id="recipient_email" x-model="recipient.email" class="w-full rounded-2xl border-slate-300">
                                <p class="mt-2 text-sm text-slate-500">Wird fuer den direkten Versand nach dem Speichern verwendet.</p>
                            </div>

                            <div>
                                <label for="recipient_salutation" class="mb-1 block text-sm font-medium text-slate-700">Anrede</label>
                                <input type="text" name="recipient_salutation" id="recipient_salutation" x-model="recipient.salutation" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div class="md:col-span-2">
                                <label for="recipient_street" class="mb-1 block text-sm font-medium text-slate-700">Strasse</label>
                                <input type="text" name="recipient_street" id="recipient_street" x-model="recipient.street" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="recipient_zip" class="mb-1 block text-sm font-medium text-slate-700">PLZ</label>
                                <input type="text" name="recipient_zip" id="recipient_zip" x-model="recipient.zip" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="recipient_city" class="mb-1 block text-sm font-medium text-slate-700">Ort</label>
                                <input type="text" name="recipient_city" id="recipient_city" x-model="recipient.city" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div class="md:col-span-2">
                                <label for="recipient_country" class="mb-1 block text-sm font-medium text-slate-700">Land</label>
                                <input type="text" name="recipient_country" id="recipient_country" x-model="recipient.country" class="w-full rounded-2xl border-slate-300">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">2. Dokument rahmen</h2>
                            <p class="mt-1 text-sm text-slate-500">Nur die Daten, die fuer Datum, Faelligkeit und Status wichtig sind.</p>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="invoice_date" class="mb-1 block text-sm font-medium text-slate-700">{{ $isOffer ? 'Angebotsdatum' : 'Rechnungsdatum' }}</label>
                                <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date', optional($formInvoice?->invoice_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="w-full rounded-2xl border-slate-300" required>
                            </div>

                            <div>
                                <label for="due_date" class="mb-1 block text-sm font-medium text-slate-700">{{ $isOffer ? 'Gueltig bis' : 'Faellig am' }}</label>
                                <input type="date" name="due_date" id="due_date" value="{{ old('due_date', optional($formInvoice?->due_date)->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d')) }}" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="discount" class="mb-1 block text-sm font-medium text-slate-700">Rabatt (%)</label>
                                <input type="number" step="0.01" name="discount" id="discount" x-model.number="pricing.discount" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div>
                                <label for="tax_rate" class="mb-1 block text-sm font-medium text-slate-700">USt. (%)</label>
                                <input type="number" step="0.01" name="tax_rate" id="tax_rate" x-model.number="pricing.taxRate" class="w-full rounded-2xl border-slate-300">
                            </div>

                            <div class="md:col-span-2">
                                <label for="income_account_id" class="mb-1 block text-sm font-medium text-slate-700">Ertragskonto</label>
                                <select name="income_account_id" id="income_account_id" class="w-full rounded-2xl border-slate-300" required>
                                    <option value="">Bitte auswaehlen</option>
                                    @foreach($incomeAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('income_account_id', $formInvoice?->income_account_id ?? $suggestedIncomeAccount?->id) == $account->id)>
                                            {{ $account->number ? $account->number . ' - ' : '' }}{{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-sm text-slate-500">{{ $incomeAccountHint ?? 'Bitte waehle das passende Ertragskonto.' }}</p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Startstatus</label>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="cursor-pointer rounded-2xl border p-4 transition {{ $defaultStatus === 'open' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                                    <input type="radio" class="hidden" name="status" value="open" @checked($defaultStatus === 'open')>
                                    <div class="text-sm font-semibold">{{ $isOffer ? 'Versendet / offen' : 'Offen' }}</div>
                                    <div class="mt-1 text-xs {{ $defaultStatus === 'open' ? 'text-slate-300' : 'text-slate-500' }}">
                                        Bereit fuer Zahlung oder direkten Versand.
                                    </div>
                                </label>
                                <label class="cursor-pointer rounded-2xl border p-4 transition {{ $defaultStatus === 'entwurf' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                                    <input type="radio" class="hidden" name="status" value="entwurf" @checked($defaultStatus === 'entwurf')>
                                    <div class="text-sm font-semibold">Entwurf</div>
                                    <div class="mt-1 text-xs {{ $defaultStatus === 'entwurf' ? 'text-slate-300' : 'text-slate-500' }}">
                                        Erst intern pruefen, spaeter versenden.
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">3. Positionen</h2>
                                <p class="mt-1 text-sm text-slate-500">Klar, ruhig und mobil gut benutzbar. Jede Position ist ein eigener, sauberer Arbeitsbereich.</p>
                            </div>

                            <button type="button" id="add-item" @click="addItem()" class="w-full rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 sm:w-auto">
                                Position hinzufuegen
                            </button>
                        </div>

                        <div class="mt-5 flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <div>
                                <span class="font-semibold text-slate-900" x-text="items.length"></span>
                                <span x-text="items.length === 1 ? 'Position' : 'Positionen'"></span>
                            </div>
                            <div class="text-right">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-400">Zwischensumme</div>
                                <div class="font-semibold text-slate-900" x-text="formatCurrency(subtotal())"></div>
                            </div>
                        </div>

                        <div id="items-wrapper" class="mt-5 space-y-4">
                            <template x-for="(item, index) in items" :key="item.key">
                                <div class="item-row overflow-hidden rounded-3xl border border-slate-200 bg-slate-50/70 shadow-sm">
                                    <div class="flex flex-col gap-4 border-b border-slate-200 bg-white/80 px-4 py-4 sm:px-5 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" x-text="'Position ' + (index + 1)"></div>
                                            <div class="mt-2 text-sm text-slate-500">Kurz beschreiben, was berechnet wird. Details nur dann, wenn sie der Rechnung wirklich helfen.</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700" x-text="formatCurrency(itemTotal(item))"></div>
                                            <button type="button" class="remove-row inline-flex h-10 items-center justify-center rounded-full border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-600 transition hover:bg-rose-50" title="Position entfernen" @click="removeItem(index)">
                                                Entfernen
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid gap-5 p-4 sm:p-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(340px,1fr)]">
                                        <div class="min-w-0">
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Beschreibung</label>
                                            <input type="text" :name="`items[${index}][description]`" x-model="item.description" class="w-full rounded-2xl border-slate-300" placeholder="z. B. Platzmiete April 2026" required>

                                            <label class="mb-1 mt-3 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Details</label>
                                            <textarea :name="`items[${index}][details]`" x-model="item.details" rows="5" class="w-full rounded-2xl border-slate-300" placeholder="Optional: Zeitraum, Leistungsumfang, Hinweise ..."></textarea>
                                        </div>

                                        <div class="space-y-4">
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Menge</label>
                                                    <input type="number" step="0.01" min="0" :name="`items[${index}][quantity]`" x-model.number="item.quantity" class="w-full rounded-2xl border-slate-300" placeholder="1,00" required>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Einheit</label>
                                                    <input type="text" :name="`items[${index}][unit]`" x-model="item.unit" class="w-full rounded-2xl border-slate-300" placeholder="Stk., Std., Monat">
                                                </div>
                                            </div>

                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Einzelpreis</label>
                                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" class="w-full rounded-2xl border-slate-300" placeholder="0,00" required>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Positionssumme</label>
                                                    <div class="flex h-[46px] items-center rounded-2xl border border-slate-200 bg-white px-4 text-base font-semibold text-slate-900" x-text="formatCurrency(itemTotal(item))"></div>
                                                </div>
                                            </div>

                                            <div class="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Berechnung</div>
                                                <div class="mt-2 text-sm text-slate-600">
                                                    <span x-text="formatNumber(item.quantity)"></span>
                                                    <span x-show="item.unit" x-text="item.unit"></span>
                                                    <span>x</span>
                                                    <span x-text="formatCurrency(item.unit_price)"></span>
                                                </div>
                                            </div>

                                            <div class="mt-3 rounded-2xl bg-slate-900 px-4 py-4 text-white">
                                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Wirkung auf Gesamtbetrag</div>
                                                <div class="mt-2 text-2xl font-semibold" x-text="formatCurrency(itemTotal(item))"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <details class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Texte anpassen</h2>
                                <p class="mt-1 text-sm text-slate-500">Nur aufklappen, wenn du Einleitung, Zahlungshinweis oder Abschluss individuell aendern willst.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                                Aufklappen
                            </span>
                        </summary>

                        <div class="mt-6 grid gap-4">
                            <div>
                                <label for="intro_text" class="mb-1 block text-sm font-medium text-slate-700">Einleitung</label>
                                <textarea name="intro_text" id="intro_text" rows="5" class="w-full rounded-2xl border-slate-300">{{ old('intro_text', $formInvoice?->intro_text) }}</textarea>
                            </div>
                            <div>
                                <label for="payment_text" class="mb-1 block text-sm font-medium text-slate-700">Zahlungshinweis</label>
                                <textarea name="payment_text" id="payment_text" rows="3" class="w-full rounded-2xl border-slate-300">{{ old('payment_text', $formInvoice?->payment_text) }}</textarea>
                            </div>
                            <div>
                                <label for="closing_text" class="mb-1 block text-sm font-medium text-slate-700">Abschluss</label>
                                <textarea name="closing_text" id="closing_text" rows="3" class="w-full rounded-2xl border-slate-300">{{ old('closing_text', $formInvoice?->closing_text) }}</textarea>
                            </div>
                        </div>
                    </details>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Empfaenger-Vorschau</h2>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <div class="font-semibold text-slate-900" x-text="recipient.name || 'Noch kein Name gesetzt'"></div>
                            <template x-if="recipient.company">
                                <div class="mt-1" x-text="recipient.company"></div>
                            </template>
                            <template x-if="recipient.street">
                                <div class="mt-3" x-text="recipient.street"></div>
                            </template>
                            <div class="mt-1">
                                <span x-text="recipient.zip"></span>
                                <span x-text="recipient.city"></span>
                            </div>
                            <template x-if="recipient.country">
                                <div class="mt-1" x-text="recipient.country"></div>
                            </template>
                            <template x-if="recipient.email">
                                <div class="mt-3 text-slate-500" x-text="recipient.email"></div>
                            </template>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Was soll jetzt passieren?</h2>
                        <p class="mt-2 text-sm text-slate-500">Damit du nach dem Speichern nicht erst suchen musst.</p>

                        <div class="mt-5 space-y-3">
                            <button type="submit" name="submit_action" value="send" class="w-full rounded-full bg-sky-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-sky-700">
                                {{ $editing ? 'Aenderungen speichern und direkt per Mail versenden' : 'Speichern und direkt per Mail versenden' }}
                            </button>
                            <button type="submit" name="submit_action" value="save" class="w-full rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                {{ $editing ? 'Aenderungen speichern' : 'Nur speichern' }}
                            </button>
                            <a href="{{ $editing ? route('invoices.show', $formInvoice) : route('invoices.index') }}" class="block rounded-full border border-slate-200 px-5 py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                {{ $editing ? 'Zurueck zum Entwurf' : 'Zurueck zur Uebersicht' }}
                            </a>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Live-Summe</h2>
                        <p class="mt-2 text-sm text-slate-500">Damit du beim Erfassen sofort siehst, was am Ende wirklich rausgeht.</p>

                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Zwischensumme</span>
                                <span x-text="formatCurrency(subtotal())"></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Rabatt</span>
                                <span x-text="'-' + formatCurrency(discountAmount())"></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span>USt.</span>
                                <span x-text="formatCurrency(taxAmount())"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900">
                                <span>Gesamt</span>
                                <span x-text="formatCurrency(grandTotal())"></span>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>

    <script>
        function invoiceForm() {
            return {
                recipientType: @js(old('recipient_type', $formInvoice?->recipient_type ?? 'member')),
                memberId: @js(old('member_id', $formInvoice?->member_id)),
                contactId: @js(old('contact_id', $formInvoice?->contact_id)),
                recipient: {
                    name: @js(old('recipient_name', $formInvoice?->recipient_name)),
                    company: @js(old('recipient_company', $formInvoice?->recipient_company)),
                    salutation: @js(old('recipient_salutation', $formInvoice?->recipient_salutation)),
                    email: @js(old('recipient_email', $formInvoice?->recipient_email)),
                    street: @js(old('recipient_street', $formInvoice?->recipient_street)),
                    zip: @js(old('recipient_zip', $formInvoice?->recipient_zip)),
                    city: @js(old('recipient_city', $formInvoice?->recipient_city)),
                    country: @js(old('recipient_country', $formInvoice?->recipient_country ?? 'Deutschland')),
                },
                pricing: {
                    discount: Number(@js(old('discount', $formInvoice?->discount ?? 0))) || 0,
                    taxRate: Number(@js(old('tax_rate', $formInvoice?->tax_rate ?? 0))) || 0,
                },
                items: @js(
                    collect(old('items', isset($formInvoice)
                        ? $formInvoice->items->map(fn ($item) => [
                            'description' => $item->description,
                            'details' => $item->details,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'unit_price' => $item->unit_price,
                        ])->all()
                        : [['description' => '', 'details' => '', 'quantity' => 1, 'unit' => '', 'unit_price' => 0]]
                    ))
                        ->values()
                        ->map(fn ($item, $index) => [
                            'key' => 'item-' . $index . '-' . substr(md5((string) $index . json_encode($item)), 0, 6),
                            'description' => $item['description'] ?? '',
                            'details' => $item['details'] ?? '',
                            'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1,
                            'unit' => $item['unit'] ?? '',
                            'unit_price' => is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0,
                        ])
                ),
                nextItemKey: {{ count(old('items', isset($formInvoice) ? $formInvoice->items->map(fn ($item) => ['description' => $item->description])->all() : [['description' => '']])) }},
                fillFromOption(selectId) {
                    const select = document.getElementById(selectId);
                    const option = select?.selectedOptions?.[0];

                    if (!option || !option.value) return;

                    this.recipient.name = option.dataset.name || '';
                    this.recipient.company = option.dataset.company || '';
                    this.recipient.salutation = option.dataset.salutation || '';
                    this.recipient.email = option.dataset.email || '';
                    this.recipient.street = option.dataset.street || '';
                    this.recipient.zip = option.dataset.zip || '';
                    this.recipient.city = option.dataset.city || '';
                    this.recipient.country = option.dataset.country || 'Deutschland';
                },
                fillFromMember() {
                    this.fillFromOption('member_id');
                },
                fillFromContact() {
                    this.fillFromOption('contact_id');
                },
                addItem() {
                    this.items.push({
                        key: `item-${this.nextItemKey++}`,
                        description: '',
                        details: '',
                        quantity: 1,
                        unit: '',
                        unit_price: 0,
                    });
                },
                removeItem(index) {
                    if (this.items.length <= 1) {
                        return;
                    }

                    this.items.splice(index, 1);
                },
                itemTotal(item) {
                    const quantity = Number(item.quantity) || 0;
                    const unitPrice = Number(item.unit_price) || 0;

                    return quantity * unitPrice;
                },
                subtotal() {
                    return this.items.reduce((sum, item) => sum + this.itemTotal(item), 0);
                },
                discountAmount() {
                    return this.subtotal() * ((Number(this.pricing.discount) || 0) / 100);
                },
                taxAmount() {
                    const taxable = this.subtotal() - this.discountAmount();
                    return taxable * ((Number(this.pricing.taxRate) || 0) / 100);
                },
                grandTotal() {
                    return this.subtotal() - this.discountAmount() + this.taxAmount();
                },
                formatCurrency(value) {
                    return new Intl.NumberFormat('de-DE', {
                        style: 'currency',
                        currency: 'EUR',
                    }).format(Number(value) || 0);
                },
                formatNumber(value) {
                    return new Intl.NumberFormat('de-DE', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    }).format(Number(value) || 0);
                }
            }
        }

    </script>
@endsection

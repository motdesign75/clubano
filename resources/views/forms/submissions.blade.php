@extends('layouts.app')

@section('title', 'Formularantworten')

@section('content')
@php
    $canManageForms = auth()->user()?->canManageForms() ?? false;

    $fieldLabels = $form->fields
        ->pluck('label', 'slug')
        ->mapWithKeys(fn ($label, $slug) => [mb_strtolower((string) $slug) => $label]);

    $systemLabels = collect([
        'first_name' => 'Vorname',
        'last_name' => 'Nachname',
        'booker_first_name' => 'Vorname Ansprechpartner',
        'booker_last_name' => 'Nachname Ansprechpartner',
        'name' => 'Name',
        'full_name' => 'Name',
        'organization' => 'Firma / Organisation',
        'company' => 'Firma',
        'email' => 'E-Mail',
        'booker_email' => 'E-Mail Ansprechpartner',
        'phone' => 'Telefon',
        'mobile' => 'Mobil',
        'booker_phone' => 'Telefon Ansprechpartner',
        'notes' => 'Notiz',
        'message' => 'Nachricht',
        'participant_notes' => 'Hinweise zu Teilnehmern',
        'participant_count' => 'Teilnehmerzahl',
        'street' => 'Straße und Hausnummer',
        'address' => 'Adresse',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'country' => 'Land',
        'use_booker_as_participant' => 'Ansprechpartner nimmt selbst teil',
        'payment_required' => 'Zahlungspflichtig',
        'privacy' => 'Datenschutz akzeptiert',
        'terms' => 'Bedingungen akzeptiert',
    ]);

    $labelFor = function (string $key) use ($fieldLabels, $systemLabels): string {
        $normalized = mb_strtolower(trim($key));

        if ($fieldLabels->has($normalized)) {
            return (string) $fieldLabels->get($normalized);
        }

        if ($systemLabels->has($normalized)) {
            return (string) $systemLabels->get($normalized);
        }

        return \Illuminate\Support\Str::of($key)
            ->replace(['_', '-'], ' ')
            ->lower()
            ->ucfirst()
            ->toString();
    };

    $booleanKeys = collect([
        'use_booker_as_participant',
        'payment_required',
        'privacy',
        'terms',
    ]);

    $formatValue = function ($value, ?string $key = null) use ($booleanKeys): string {
        $normalizedKey = mb_strtolower(trim((string) $key));

        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            $flat = collect($value)->flatten()->filter(fn ($item) => filled($item));

            return $flat->isEmpty() ? '-' : $flat->implode(', ');
        }

        if ($booleanKeys->contains($normalizedKey) && is_numeric($value) && in_array((string) $value, ['0', '1'], true)) {
            return (string) $value === '1' ? 'Ja' : 'Nein';
        }

        if (is_string($value) && in_array(mb_strtolower($value), ['true', 'false', 'yes', 'no'], true)) {
            return in_array(mb_strtolower($value), ['true', 'yes'], true) ? 'Ja' : 'Nein';
        }

        return blank($value) ? '-' : (string) $value;
    };

    $submissionsOnPage = collect($submissions->items());
    $activeCount = $submissionsOnPage->where('status', '!=', 'cancelled')->count();
    $cancelledCount = $submissionsOnPage->where('status', 'cancelled')->count();
    $bookingCount = $submissionsOnPage->filter(fn ($submission) => filled($submission->event_booking_id))->count();

    $statusLabel = fn ($status) => match ($status) {
        'cancelled' => 'Storniert',
        default => 'Aktiv',
    };

    $bookingStatusLabel = fn ($status) => match ($status) {
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Storniert',
        default => 'Vorgemerkt',
    };

    $paymentStatusLabel = fn ($status) => match ($status) {
        'paid' => 'Bezahlt',
        'cancelled' => 'Zahlung storniert',
        'not_required' => 'Keine Zahlung nötig',
        default => 'Zahlung offen',
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Formulare / Antworten</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Antworten prüfen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    {{ $form->title }} · Eingänge prüfen, Teilnehmer sehen und Daten bewusst übernehmen.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ route('forms.index') }}"
                   class="inline-flex items-center justify-center rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    Zurück zu Formularen
                </a>
                @if($canManageForms)
                    <a href="{{ route('forms.export', $form) }}"
                       class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100">
                        CSV exportieren
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Antworten gesamt</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $submissions->total() }}</div>
        </div>
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
            <div class="text-sm font-medium text-emerald-700">Aktiv auf dieser Seite</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ $activeCount }}</div>
        </div>
        <div class="rounded-3xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm">
            <div class="text-sm font-medium text-rose-700">Storniert</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-rose-900">{{ $cancelledCount }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Mit Buchung</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $bookingCount }}</div>
        </div>
    </section>

    <section class="space-y-4">
        @forelse($submissions as $submission)
            @php
                $answers = $submission->answers ?? [];
                $suggestedName = trim((string) ($answers['first_name'] ?? '') . ' ' . (string) ($answers['last_name'] ?? '')) ?: ($submission->full_name ?: 'Antwort');
                $suggestedOrganization = trim((string) ($answers['organization'] ?? ''));
                $canConvertToEventParticipant = $form->event && !$submission->eventBooking;
            @endphp

            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-xl font-semibold text-slate-950">{{ $submission->full_name ?: $suggestedName }}</h2>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $submission->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $statusLabel($submission->status) }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-500">
                                <span>{{ $submission->email ?: 'keine E-Mail' }}</span>
                                @if($submission->phone)
                                    <span>{{ $submission->phone }}</span>
                                @endif
                                <span>{{ $submission->created_at->format('d.m.Y H:i') }}</span>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                @if($submission->eventBooking)
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                        Buchung {{ $submission->eventBooking->booking_reference }}
                                    </span>
                                    <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-700">
                                        {{ $bookingStatusLabel($submission->eventBooking->booking_status) }}
                                    </span>
                                    <span class="inline-flex rounded-full px-3 py-1 font-semibold {{ $submission->eventBooking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($submission->eventBooking->payment_status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($submission->eventBooking->payment_status === 'not_required' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700')) }}">
                                        {{ $paymentStatusLabel($submission->eventBooking->payment_status) }}
                                    </span>
                                @endif

                                @if($submission->member)
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 font-semibold text-blue-700">
                                        Mitglied: {{ $submission->member->full_name ?: $submission->member->organization ?: $submission->member->email }}
                                    </span>
                                @endif

                                @if($submission->contact)
                                    <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 font-semibold text-sky-700">
                                        Kontakt: {{ $submission->contact->display_name }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 xl:items-end">
                            @if($submission->cancelled_at)
                                <div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                    Storniert am {{ $submission->cancelled_at->format('d.m.Y H:i') }}
                                </div>
                            @endif

                            @if($canManageForms)
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    @if($submission->status !== 'cancelled')
                                        <form method="POST" action="{{ route('forms.submissions.cancel', [$form, $submission]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex w-full items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100"
                                                    onclick="return confirm('Antwort und ggf. Kursanmeldung wirklich stornieren?');">
                                                Stornieren
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('forms.submissions.destroy', [$form, $submission]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                                                onclick="return confirm('Antwort wirklich löschen? Zugehörige Kurs-Teilnehmer werden ebenfalls entfernt. Verknüpfte Kontakte oder Mitglieder bleiben bestehen.');">
                                            Löschen
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid gap-0 xl:grid-cols-[1.05fr_0.95fr]">
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        @if($submission->eventBooking && $submission->eventBooking->participants->isNotEmpty())
                            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Teilnehmer</h3>
                                    <p class="mt-1 text-xs text-slate-500">{{ $submission->eventBooking->participants->count() }} Person{{ $submission->eventBooking->participants->count() === 1 ? '' : 'en' }} in dieser Buchung</p>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    @foreach($submission->eventBooking->participants as $participant)
                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                            <div class="font-semibold text-slate-950">{{ $participant->full_name }}</div>
                                            @if($participant->email || $participant->phone)
                                                <div class="mt-1 text-xs leading-5 text-slate-500">
                                                    {{ $participant->email ?: 'keine E-Mail' }}{{ $participant->phone ? ' · ' . $participant->phone : '' }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <section>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Antwortdaten</h3>
                                <p class="mt-1 text-xs text-slate-500">Deutsch benannt und so sortiert, wie das Formular die Daten geliefert hat.</p>
                            </div>

                            <dl class="mt-4 grid gap-3 md:grid-cols-2">
                                @forelse($answers as $key => $value)
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <dt class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $labelFor((string) $key) }}</dt>
                                        <dd class="mt-2 whitespace-pre-line text-sm font-medium leading-6 text-slate-900">{{ $formatValue($value, (string) $key) }}</dd>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 md:col-span-2">
                                        Diese Antwort enthält keine zusätzlichen Formularfelder.
                                    </div>
                                @endforelse
                            </dl>
                        </section>
                    </div>

                    @if($canManageForms && $submission->status !== 'cancelled')
                        <aside class="border-t border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6 xl:border-l xl:border-t-0">
                            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-950">Nächster Schritt</h3>
                                        <p class="mt-1 text-sm leading-6 text-slate-500">
                                            Nichts wird automatisch übernommen. Prüfe die Antwort und entscheide bewusst.
                                        </p>
                                    </div>
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                        {{ $suggestedOrganization ?: $suggestedName }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-3 {{ $canConvertToEventParticipant ? '2xl:grid-cols-3' : '2xl:grid-cols-2' }}">
                                    <form method="POST" action="{{ route('forms.submissions.convert-member', [$form, $submission]) }}" class="rounded-2xl border border-slate-200 bg-white p-4">
                                        @csrf
                                        <div class="text-sm font-semibold text-slate-950">Mitglied</div>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">Für Beitritte oder Personen in der Mitgliederverwaltung.</p>
                                        <label class="mt-3 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Eintritt</label>
                                        <input type="date" name="entry_date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                        <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                            <span>Daten geprüft.</span>
                                        </label>
                                        <button type="submit" @disabled($submission->member_id) class="mt-3 inline-flex w-full justify-center rounded-full bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                                            {{ $submission->member_id ? 'Bereits Mitglied' : 'Mitglied anlegen' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('forms.submissions.convert-contact', [$form, $submission]) }}" class="rounded-2xl border border-slate-200 bg-white p-4">
                                        @csrf
                                        <div class="text-sm font-semibold text-slate-950">Kontakt</div>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">Für Firmen, Sponsoren, Eltern oder externe Ansprechpartner.</p>
                                        <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                            <span>Daten geprüft.</span>
                                        </label>
                                        <button type="submit" @disabled($submission->contact_id) class="mt-3 inline-flex w-full justify-center rounded-full bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                                            {{ $submission->contact_id ? 'Bereits Kontakt' : 'Kontakt anlegen' }}
                                        </button>
                                    </form>

                                    @if($canConvertToEventParticipant)
                                        <form method="POST" action="{{ route('forms.submissions.convert-participant', [$form, $submission]) }}" class="rounded-2xl border border-slate-200 bg-white p-4" x-data="{
                                            participantType: 'guest',
                                            externalPrice: {{ json_encode((float) ($form->event?->price_per_person ?? 0)) }},
                                            memberPrice: {{ json_encode((float) ($form->event?->member_price_per_person ?? 0)) }},
                                            priceAmount: 0,
                                            paymentRequired: false,
                                            paymentStatus: 'not_required',
                                            defaultPrice() {
                                                return this.participantType === 'member' ? this.memberPrice : this.externalPrice;
                                            },
                                            syncPayment() {
                                                this.priceAmount = Number(this.defaultPrice()).toFixed(2);
                                                this.paymentRequired = Number(this.priceAmount) > 0;
                                                this.paymentStatus = this.paymentRequired ? 'open' : 'not_required';
                                            }
                                        }" x-init="syncPayment()">
                                            @csrf
                                            <div class="text-sm font-semibold text-slate-950">Teilnehmer</div>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">Für geprüfte Anmeldungen zur Teilnehmerliste.</p>
                                            <div class="mt-3 grid gap-2">
                                                <select name="participant_type" x-model="participantType" @change="syncPayment()" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <option value="guest">Freier Teilnehmer</option>
                                                    <option value="member">Bestehendes Mitglied</option>
                                                    <option value="contact">Bestehender Kontakt</option>
                                                </select>
                                                <select name="member_id" x-show="participantType === 'member'" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <option value="">Mitglied auswählen</option>
                                                    @foreach($manualParticipantMembers as $member)
                                                        <option value="{{ $member->id }}">{{ $member->full_name ?: $member->organization ?: $member->email }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="contact_id" x-show="participantType === 'contact'" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <option value="">Kontakt auswählen</option>
                                                    @foreach($manualParticipantContacts as $contact)
                                                        <option value="{{ $contact->id }}">{{ $contact->display_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                                <label class="flex items-start gap-2 text-xs text-slate-600 sm:col-span-2">
                                                    <input type="checkbox" name="payment_required" value="1" x-model="paymentRequired" @change="if (!paymentRequired) { paymentStatus = 'not_required' } else if (paymentStatus === 'not_required') { paymentStatus = 'open' }" class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                                    <span>Zahlungspflichtig</span>
                                                </label>
                                                <input type="number" step="0.01" min="0" name="price_amount" x-model="priceAmount" @input="paymentRequired = Number(priceAmount) > 0; if (!paymentRequired) { paymentStatus = 'not_required' } else if (paymentStatus === 'not_required') { paymentStatus = 'open' }" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                <select name="payment_status" x-model="paymentStatus" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    <option value="not_required">Kostenfrei</option>
                                                    <option value="open">Offen</option>
                                                    <option value="paid">Bezahlt</option>
                                                    <option value="cancelled">Storniert</option>
                                                </select>
                                            </div>
                                            <input type="text" name="payment_reason" placeholder="Grund/Preisnotiz, optional" class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                                <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                                <span>Daten geprüft.</span>
                                            </label>
                                            <button type="submit" class="mt-3 inline-flex w-full justify-center rounded-full bg-emerald-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                                                Teilnehmer anlegen
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </aside>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                <h2 class="text-xl font-semibold text-slate-900">Noch keine Antworten vorhanden</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Sobald jemand das Formular ausfüllt, erscheint der Eingang hier.
                </p>
            </div>
        @endforelse
    </section>

    {{ $submissions->links() }}
</div>
@endsection

@extends('layouts.app')

@section('title', 'Formularantworten')

@section('content')
@php
    $canManageForms = auth()->user()?->canManageForms() ?? false;
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Antworten: {{ $form->title }}</h1>
            <p class="text-sm text-gray-500">Alle eingegangenen Antworten dieses Formulars.</p>
        </div>

        @if($canManageForms)
            <a href="{{ route('forms.export', $form) }}"
               class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-emerald-700">
                CSV exportieren
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="space-y-4">
        @forelse($submissions as $submission)
            <div class="rounded-xl bg-white p-6 shadow ring-1 ring-slate-200/70">
                <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-medium text-gray-800">{{ $submission->full_name ?: 'Antwort' }}</div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $submission->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $submission->status === 'cancelled' ? 'Storniert' : 'Aktiv' }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ $submission->email ?: 'keine E-Mail' }}{{ $submission->phone ? ' · ' . $submission->phone : '' }}
                        </div>

                        @if($submission->eventBooking)
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">
                                    Buchung {{ $submission->eventBooking->booking_reference }}
                                </span>
                                <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 font-semibold text-indigo-700">
                                    {{ match($submission->eventBooking->booking_status) {
                                        'confirmed' => 'Bestätigt',
                                        'cancelled' => 'Storniert',
                                        default => 'Vorgemerkt',
                                    } }}
                                </span>
                                <span class="inline-flex rounded-full px-2.5 py-1 font-semibold {{ $submission->eventBooking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($submission->eventBooking->payment_status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($submission->eventBooking->payment_status === 'not_required' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700')) }}">
                                    {{ match($submission->eventBooking->payment_status) {
                                        'paid' => 'Bezahlt',
                                        'cancelled' => 'Zahlung storniert',
                                        'not_required' => 'Keine Zahlung nötig',
                                        default => 'Zahlung offen',
                                    } }}
                                </span>
                            </div>
                        @endif
                        @if($submission->member || $submission->contact)
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                @if($submission->member)
                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 font-semibold text-blue-700">
                                        Mitglied: {{ $submission->member->full_name ?: $submission->member->organization ?: $submission->member->email }}
                                    </span>
                                @endif
                                @if($submission->contact)
                                    <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 font-semibold text-sky-700">
                                        Kontakt: {{ $submission->contact->display_name }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3 lg:items-end">
                        <div class="text-sm text-gray-500">
                            {{ $submission->created_at->format('d.m.Y H:i') }}
                            @if($submission->cancelled_at)
                                <div class="mt-1 text-xs text-rose-600">storniert am {{ $submission->cancelled_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </div>

                        @if($canManageForms)
                            <div class="flex flex-col gap-2 sm:flex-row">
                                @if($submission->status !== 'cancelled')
                                    <form method="POST" action="{{ route('forms.submissions.cancel', [$form, $submission]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100"
                                                onclick="return confirm('Antwort und ggf. Kursanmeldung wirklich stornieren?');">
                                            Stornieren
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('forms.submissions.destroy', [$form, $submission]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                                            onclick="return confirm('Antwort wirklich löschen? Zugehörige Kurs-Teilnehmer werden ebenfalls entfernt. Verknüpfte Kontakte oder Mitglieder bleiben bestehen.');">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                @if($canManageForms && $submission->status !== 'cancelled')
                    @php
                        $answers = $submission->answers ?? [];
                        $suggestedName = trim((string) ($answers['first_name'] ?? '') . ' ' . (string) ($answers['last_name'] ?? '')) ?: ($submission->full_name ?: 'Antwort');
                        $suggestedOrganization = trim((string) ($answers['organization'] ?? ''));
                        $canConvertToEventParticipant = $form->event && !$submission->eventBooking;
                    @endphp
                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Antwort bewusst übernehmen</div>
                                <p class="mt-1 text-sm text-slate-500">Clubano übernimmt nichts automatisch. Prüfe die Daten und entscheide, ob daraus ein Mitglied, Kontakt oder Teilnehmer werden soll.</p>
                            </div>
                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $suggestedOrganization ?: $suggestedName }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 {{ $canConvertToEventParticipant ? 'xl:grid-cols-3' : 'xl:grid-cols-2' }}">
                            <form method="POST" action="{{ route('forms.submissions.convert-member', [$form, $submission]) }}" class="rounded-xl bg-white p-4 shadow-sm">
                                @csrf
                                <div class="text-sm font-semibold text-slate-950">Als Mitglied übernehmen</div>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Für Beitritte oder Personen, die künftig in der Mitgliederverwaltung geführt werden.</p>
                                <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500">Eintritt</label>
                                <input type="date" name="entry_date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                    <span>Daten geprüft und bewusst übernehmen.</span>
                                </label>
                                <button type="submit" @disabled($submission->member_id) class="mt-3 inline-flex w-full justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                                    {{ $submission->member_id ? 'Bereits Mitglied' : 'Mitglied anlegen' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('forms.submissions.convert-contact', [$form, $submission]) }}" class="rounded-xl bg-white p-4 shadow-sm">
                                @csrf
                                <div class="text-sm font-semibold text-slate-950">Als Kontakt übernehmen</div>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Für Sponsoren, Firmen, Interessenten, Eltern, Lieferanten oder externe Ansprechpartner.</p>
                                <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                    <span>Daten geprüft und bewusst übernehmen.</span>
                                </label>
                                <button type="submit" @disabled($submission->contact_id) class="mt-3 inline-flex w-full justify-center rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                                    {{ $submission->contact_id ? 'Bereits Kontakt' : 'Kontakt anlegen' }}
                                </button>
                            </form>

                            @if($canConvertToEventParticipant)
                                <form method="POST" action="{{ route('forms.submissions.convert-participant', [$form, $submission]) }}" class="rounded-xl bg-white p-4 shadow-sm" x-data="{ participantType: 'guest' }">
                                    @csrf
                                    <div class="text-sm font-semibold text-slate-950">Als Teilnehmer übernehmen</div>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">Für Anmeldungen, die erst geprüft und dann in die Teilnehmerliste übernommen werden sollen.</p>
                                    <div class="mt-3 grid gap-2">
                                        <select name="participant_type" x-model="participantType" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <option value="guest">Freier Teilnehmer aus Antwort</option>
                                            <option value="member">Mit bestehendem Mitglied verknüpfen</option>
                                            <option value="contact">Mit bestehendem Kontakt verknüpfen</option>
                                        </select>
                                        <select name="member_id" x-show="participantType === 'member'" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <option value="">Mitglied auswählen, falls gewählt</option>
                                            @foreach($manualParticipantMembers as $member)
                                                <option value="{{ $member->id }}">{{ $member->full_name ?: $member->organization ?: $member->email }}</option>
                                            @endforeach
                                        </select>
                                        <select name="contact_id" x-show="participantType === 'contact'" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <option value="">Kontakt auswählen, falls gewählt</option>
                                            @foreach($manualParticipantContacts as $contact)
                                                <option value="{{ $contact->id }}">{{ $contact->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <label class="flex items-start gap-2 text-xs text-slate-600 sm:col-span-2">
                                            <input type="checkbox" name="payment_required" value="1" @checked((float) ($form->event?->price_per_person ?? 0) > 0) class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                            <span>Zahlungspflichtig</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" name="price_amount" value="{{ number_format((float) ($form->event?->price_per_person ?? 0), 2, '.', '') }}" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                        <select name="payment_status" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                            <option value="not_required">Kostenfrei</option>
                                            <option value="open" @selected((float) ($form->event?->price_per_person ?? 0) > 0)>Offen</option>
                                            <option value="paid">Bezahlt</option>
                                            <option value="cancelled">Storniert</option>
                                        </select>
                                    </div>
                                    <input type="text" name="payment_reason" placeholder="Grund/Preisnotiz, optional" class="mt-2 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                    <label class="mt-3 flex items-start gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                        <span>Daten geprüft und bewusst übernehmen.</span>
                                    </label>
                                    <button type="submit" class="mt-3 inline-flex w-full justify-center rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                        Teilnehmer anlegen
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                @if($submission->eventBooking && $submission->eventBooking->participants->isNotEmpty())
                    <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-slate-800">Teilnehmer</div>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            @foreach($submission->eventBooking->participants as $participant)
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700">
                                    <div class="font-medium text-slate-900">{{ $participant->full_name }}</div>
                                    @if($participant->email || $participant->phone)
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ $participant->email ?: 'keine E-Mail' }}{{ $participant->phone ? ' · ' . $participant->phone : '' }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <dl class="grid gap-4 md:grid-cols-2">
                    @foreach($submission->answers as $key => $value)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ $key }}</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                @if(is_bool($value))
                                    {{ $value ? 'Ja' : 'Nein' }}
                                @elseif(is_array($value))
                                    {{ implode(', ', $value) }}
                                @elseif(blank($value))
                                    —
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @empty
            <div class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow">
                Noch keine Antworten vorhanden.
            </div>
        @endforelse
    </div>

    {{ $submissions->links() }}
</div>
@endsection

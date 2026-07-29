<div class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Teilnehmerliste</h2>
            <p class="mt-1 text-sm text-slate-500">
                @if($bookingSubmissionCount > 0)
                    {{ $bookingSubmissionCount }} Buchung{{ $bookingSubmissionCount === 1 ? '' : 'en' }}, {{ $participantCount }} Teilnehmer insgesamt.
                @elseif($event->activeBookingForm)
                    Online-Anmeldung ist aktiv, bisher aber ohne Teilnehmer.
                @else
                    Teilnehmer können auch ohne öffentliches Formular manuell nachgetragen werden.
                @endif
            </p>
        </div>

        @if($event->activeBookingForm || $bookingSubmissionCount > 0)
            <div class="flex flex-wrap gap-3">
                @if($event->activeBookingForm)
                    <a href="{{ route('forms.submissions', $event->activeBookingForm) }}"
                       class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Vollständige Antworten
                    </a>
                @endif

                <a href="{{ route('events.participants.export', $event) }}"
                   class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Teilnehmer exportieren
                </a>
            </div>
        @endif
    </div>

    @if($event->activeBookingForm || $bookingSubmissionCount > 0)
        <form method="GET" action="{{ route('events.participants.print', $event) }}" target="_blank" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-950">Ausgabe vorbereiten</h3>
                    <p class="mt-1 text-sm text-slate-500">Lege fest, ob Firmen/Organisationen oder Personen im Ausdruck im Vordergrund stehen.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,16rem),auto,auto] sm:items-end">
                    <div>
                        <label for="participant_display_mode" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Anzeige</label>
                        <select id="participant_display_mode" name="display" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                            <option value="person">Vor- und Nachname</option>
                            <option value="organization">Firma / Organisation</option>
                        </select>
                    </div>

                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        Drucken
                    </button>

                    <button type="submit" formaction="{{ route('events.participants.pdf', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        PDF öffnen
                    </button>
                </div>
            </div>
        </form>
    @endif

    @if($canManageManualParticipants ?? false)
    <div id="teilnehmer-nachtragen" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5" x-data="{ type: 'member', listDisplay: 'person', guestMode: 'person', paymentRequired: {{ (float) $event->price_per_person > 0 ? 'true' : 'false' }} }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-950">Teilnehmer nachtragen</h3>
                <p class="mt-1 text-sm text-slate-500">Für telefonische Anmeldungen, Abendkasse, Gäste, Sponsoren oder Teilnehmer ohne Online-Zugang.</p>
            </div>
            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">Intern</span>
        </div>

        <form method="POST" action="{{ route('events.manual-participants.store', $event) }}" class="mt-5 space-y-5">
            @csrf

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                    <input type="radio" name="participant_type" value="member" x-model="type" class="border-slate-300 text-slate-950 focus:ring-slate-400">
                    Mitglied
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                    <input type="radio" name="participant_type" value="contact" x-model="type" class="border-slate-300 text-slate-950 focus:ring-slate-400">
                    Kontakt
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                    <input type="radio" name="participant_type" value="guest" x-model="type" class="border-slate-300 text-slate-950 focus:ring-slate-400">
                    Freier Gast
                </label>
            </div>

            <div x-show="type === 'member' || type === 'contact'" class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Anzeige der Auswahl</p>
                    <p class="text-xs text-slate-500">Wähle, ob Organisationen oder Personennamen im Vordergrund stehen.</p>
                </div>
                <div class="grid grid-cols-2 rounded-lg bg-slate-100 p-1 text-sm font-semibold text-slate-600">
                    <button type="button" x-on:click="listDisplay = 'person'" :class="listDisplay === 'person' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" class="rounded-md px-3 py-2">Vor-/Nachname</button>
                    <button type="button" x-on:click="listDisplay = 'organization'" :class="listDisplay === 'organization' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" class="rounded-md px-3 py-2">Firma/Organisation</button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div x-show="type === 'member'">
                    @php
                        $selectedMemberIds = collect(old('member_ids', []))->map(fn ($id) => (string) $id)->all();
                    @endphp
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-slate-600">Mitglieder auswählen</label>
                        <span class="text-xs font-medium text-slate-400">{{ $manualParticipantMembers->count() }} verfügbar</span>
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        @forelse($manualParticipantMembers as $member)
                            @php
                                $memberName = $member->full_name ?: 'Mitglied ohne Namen';
                                $memberOrganization = trim((string) $member->organization);
                            @endphp
                            <label class="flex cursor-pointer items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" @checked(in_array((string) $member->id, $selectedMemberIds, true)) class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                <span class="min-w-0">
                                    <span x-show="listDisplay === 'person'" class="block">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $memberName }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $memberOrganization ?: ($member->email ?: $member->mobile ?: $member->landline ?: 'Keine Kontaktinfo hinterlegt') }}</span>
                                    </span>
                                    <span x-show="listDisplay === 'organization'" class="block">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $memberOrganization ?: $memberName }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $memberOrganization ? $memberName : ($member->email ?: $member->mobile ?: $member->landline ?: 'Keine Kontaktinfo hinterlegt') }}</span>
                                    </span>
                                </span>
                            </label>
                        @empty
                            <div class="px-4 py-5 text-sm text-slate-500">Keine aktiven Mitglieder verfügbar.</div>
                        @endforelse
                    </div>
                </div>

                <div x-show="type === 'contact'">
                    @php
                        $selectedContactIds = collect(old('contact_ids', []))->map(fn ($id) => (string) $id)->all();
                    @endphp
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="block text-sm font-medium text-slate-600">Kontakte auswählen</label>
                        <span class="text-xs font-medium text-slate-400">{{ $manualParticipantContacts->count() }} verfügbar</span>
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        @forelse($manualParticipantContacts as $contact)
                            @php
                                $contactName = $contact->full_name ?: 'Kontakt ohne Namen';
                                $contactOrganization = trim((string) ($contact->organization ?: $contact->company));
                            @endphp
                            <label class="flex cursor-pointer items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 hover:bg-slate-50">
                                <input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" @checked(in_array((string) $contact->id, $selectedContactIds, true)) class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                                <span class="min-w-0">
                                    <span x-show="listDisplay === 'person'" class="block">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $contactName }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $contactOrganization ?: ($contact->primary_email ?: $contact->primary_phone ?: 'Keine Kontaktinfo hinterlegt') }}</span>
                                    </span>
                                    <span x-show="listDisplay === 'organization'" class="block">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $contactOrganization ?: $contactName }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $contactOrganization ? $contactName : ($contact->primary_email ?: $contact->primary_phone ?: 'Keine Kontaktinfo hinterlegt') }}</span>
                                    </span>
                                </span>
                            </label>
                        @empty
                            <div class="px-4 py-5 text-sm text-slate-500">Keine aktiven Kontakte verfügbar.</div>
                        @endforelse
                    </div>
                </div>

                <div x-show="type === 'guest'" class="space-y-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                            <input type="radio" name="guest_mode" value="person" x-model="guestMode" class="border-slate-300 text-slate-950 focus:ring-slate-400">
                            Person
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                            <input type="radio" name="guest_mode" value="organization" x-model="guestMode" class="border-slate-300 text-slate-950 focus:ring-slate-400">
                            Firma oder Organisation
                        </label>
                    </div>

                    <div x-show="guestMode === 'person'" class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-600">Vorname</label>
                            <input type="text" name="first_name" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-600">Nachname</label>
                            <input type="text" name="last_name" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        </div>
                    </div>

                    <div x-show="guestMode === 'organization'">
                        <label class="mb-1 block text-sm font-medium text-slate-600">Firma oder Organisation</label>
                        <input type="text" name="organization_name" placeholder="z. B. Muster GmbH, Förderverein, Gastverein" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-600">E-Mail überschreiben</label>
                        <input type="email" name="email" placeholder="optional" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-600">Telefon überschreiben</label>
                        <input type="text" name="phone" placeholder="optional" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem_14rem_14rem] lg:items-end">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <input type="checkbox" name="payment_required" value="1" x-model="paymentRequired" class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                    <span>
                        <span class="block font-medium text-slate-950">Teilnehmer muss zahlen</span>
                        <span class="mt-1 block text-slate-500">Wenn aus, wird der Teilnehmer als kostenfrei geführt.</span>
                    </span>
                </label>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Preis</label>
                    <input type="number" step="0.01" min="0" name="price_amount" value="{{ number_format((float) $event->price_per_person, 2, '.', '') }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Zahlstatus</label>
                    <select name="payment_status" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        <option value="not_required">Keine Zahlung nötig</option>
                        <option value="open" @selected((float) $event->price_per_person > 0)>Offen</option>
                        <option value="paid">Bezahlt</option>
                        <option value="cancelled">Storniert</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Herkunft</label>
                    <select name="source" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                        <option value="manual">Manuell</option>
                        <option value="phone">Telefonisch</option>
                        <option value="email">E-Mail</option>
                        <option value="abendkasse">Abendkasse</option>
                        <option value="imported">Importiert</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Grund / Preisnotiz</label>
                    <input type="text" name="payment_reason" placeholder="z. B. Helfer, Ehrengast, Sponsor, ermäßigt" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Interne Notiz</label>
                    <input type="text" name="note" placeholder="optional" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800">
                    Teilnehmer speichern
                </button>
            </div>
        </form>
    </div>
    @endif

    @if($bookingSubmissionCount > 0)
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-500">Buchungen</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $bookingSubmissionCount }}</div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-500">Teilnehmer</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $participantCount }}</div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-500">Umsatz geplant</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ number_format((float) $bookingRevenue, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}
                </div>
            </div>
        </div>

        <form id="participants-bulk-free-form" method="POST" action="{{ route('events.participants.mark-free', $event) }}" class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4">
            @csrf
            @method('PATCH')

            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-blue-950">Mehrere Teilnehmer korrigieren</h3>
                    <p class="mt-1 text-sm text-blue-800">Markiere unten Teilnehmer und setze sie gemeinsam auf kostenfrei.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,18rem),auto] sm:items-end">
                    <div>
                        <label for="bulk_payment_reason" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-blue-700">Grund</label>
                        <input id="bulk_payment_reason" type="text" name="payment_reason" placeholder="z. B. Ehrengast, Sponsor, Helfer" class="w-full rounded-lg border-blue-200 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-200">
                    </div>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">
                        Ausgewählte kostenfrei setzen
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-slate-600">
                        <th class="px-4 py-3 font-semibold">Buchung</th>
                        <th class="px-4 py-3 font-semibold">Ansprechpartner</th>
                        <th class="px-4 py-3 font-semibold">Teilnehmer</th>
                        <th class="px-4 py-3 font-semibold">Preis</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Workflow</th>
                        <th class="px-4 py-3 font-semibold">Zusatzangaben</th>
                        <th class="px-4 py-3 font-semibold">Eingang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($eventBookings as $booking)
                        @php
                            $submission = $booking->submission;
                            $additionalAnswers = collect($submission?->answers ?? [])
                                ->reject(fn ($value, $key) => in_array($key, ['first_name', 'last_name', 'full_name', 'email', 'phone', 'mobile', 'participant_count', 'participant_notes'], true))
                                ->filter(fn ($value) => !blank($value));
                        @endphp

                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $booking->booking_reference }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $booking->participant_count }} Person{{ $booking->participant_count === 1 ? '' : 'en' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $booking->booker_name ?: 'Unbekannt' }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $booking->booker_email ?: 'keine E-Mail' }}</div>
                                @if($booking->booker_phone)
                                    <div class="mt-1 text-sm text-slate-600">{{ $booking->booker_phone }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="space-y-1">
                                    @foreach($booking->participants as $participant)
                                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                                            <label class="flex cursor-pointer items-start gap-2">
                                                <input form="participants-bulk-free-form" type="checkbox" name="participant_ids[]" value="{{ $participant->id }}" class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-400">
                                                <span class="font-medium text-slate-800">{{ $participant->display_name }}</span>
                                            </label>
                                            <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                                <span class="rounded-full bg-white px-2 py-0.5 font-semibold text-slate-600">{{ $participant->type_label }}</span>
                                                <span class="rounded-full {{ $participant->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($participant->payment_status === 'not_required' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }} px-2 py-0.5 font-semibold">
                                                    {{ $participant->payment_status_label }}
                                                </span>
                                                <span class="rounded-full bg-white px-2 py-0.5 font-semibold text-slate-600">
                                                    {{ number_format((float) $participant->price_amount, 2, ',', '.') }} {{ strtoupper($booking->currency ?: 'EUR') }}
                                                </span>
                                            </div>
                                            @if($participant->email || $participant->phone)
                                                <div class="mt-1 text-xs text-slate-500">
                                                    {{ $participant->email ?: 'keine E-Mail' }}
                                                    @if($participant->phone)
                                                        · {{ $participant->phone }}
                                                    @endif
                                                </div>
                                            @endif
                                            @if($participant->payment_reason || $participant->source || $participant->note)
                                                <div class="mt-1 text-xs text-slate-500">
                                                    {{ $participant->payment_reason ?: ucfirst((string) $participant->source) }}
                                                    @if($participant->note)
                                                        · {{ $participant->note }}
                                                    @endif
                                                </div>
                                            @endif
                                            <details class="group mt-2 rounded-lg border border-slate-200 bg-white">
                                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-xs font-semibold text-slate-600">
                                                    Teilnehmer bearbeiten
                                                    <x-heroicon-o-chevron-down class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                                                </summary>
                                                <form method="POST" action="{{ route('events.participants.update', [$event, $booking, $participant]) }}" class="space-y-3 border-t border-slate-100 p-3">
                                                    @csrf
                                                    @method('PATCH')

                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Vorname</label>
                                                            <input type="text" name="first_name" value="{{ old('first_name', $participant->first_name) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nachname</label>
                                                            <input type="text" name="last_name" value="{{ old('last_name', $participant->last_name) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Firma / Organisation</label>
                                                        <input type="text" name="organization_name" value="{{ old('organization_name', $participant->organization_name) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                    </div>

                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">E-Mail</label>
                                                            <input type="email" name="email" value="{{ old('email', $participant->email) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Telefon</label>
                                                            <input type="text" name="phone" value="{{ old('phone', $participant->phone) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                        </div>
                                                    </div>

                                                    <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                                        <input type="checkbox" name="payment_required" value="1" class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-400" @checked(old('payment_required', $participant->payment_required))>
                                                        <span>
                                                            <span class="block font-semibold text-slate-900">Teilnehmer muss zahlen</span>
                                                            <span class="block text-slate-500">Wenn aus, wird Betrag und Status auf kostenfrei gesetzt.</span>
                                                        </span>
                                                    </label>

                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Betrag</label>
                                                            <input type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount', number_format((float) $participant->price_amount, 2, '.', '')) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Zahlstatus</label>
                                                            <select name="payment_status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                                <option value="not_required" @selected(old('payment_status', $participant->payment_status) === 'not_required')>Keine Zahlung nötig</option>
                                                                <option value="open" @selected(old('payment_status', $participant->payment_status) === 'open')>Offen</option>
                                                                <option value="paid" @selected(old('payment_status', $participant->payment_status) === 'paid')>Bezahlt</option>
                                                                <option value="cancelled" @selected(old('payment_status', $participant->payment_status) === 'cancelled')>Storniert</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Grund / Hinweis</label>
                                                            <input type="text" name="payment_reason" value="{{ old('payment_reason', $participant->payment_reason) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300" placeholder="z. B. Sponsor, Ehrengast">
                                                        </div>
                                                        <div>
                                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Herkunft</label>
                                                            <select name="source" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">
                                                                <option value="manual" @selected(old('source', $participant->source) === 'manual')>Manuell</option>
                                                                <option value="phone" @selected(old('source', $participant->source) === 'phone')>Telefon</option>
                                                                <option value="email" @selected(old('source', $participant->source) === 'email')>E-Mail</option>
                                                                <option value="abendkasse" @selected(old('source', $participant->source) === 'abendkasse')>Abendkasse</option>
                                                                <option value="imported" @selected(old('source', $participant->source) === 'imported')>Importiert</option>
                                                                <option value="online" @selected(old('source', $participant->source) === 'online')>Online</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Notiz</label>
                                                        <textarea name="note" rows="2" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-300">{{ old('note', $participant->note) }}</textarea>
                                                    </div>

                                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                                        Teilnehmer speichern
                                                    </button>
                                                </form>
                                            </details>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div>{{ number_format((float) $booking->total_amount, 2, ',', '.') }} {{ strtoupper($booking->currency ?: 'EUR') }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ number_format((float) $booking->price_per_person, 2, ',', '.') }} {{ strtoupper($booking->currency ?: 'EUR') }} pro Person
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div class="space-y-2">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        {{ match($booking->booking_status) {
                                            'confirmed' => 'Bestätigt',
                                            'cancelled' => 'Storniert',
                                            default => 'Vorgemerkt',
                                        } }}
                                    </span>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $booking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($booking->payment_status === 'not_required' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ match($booking->payment_status) {
                                            'paid' => 'Bezahlt',
                                            'cancelled' => 'Zahlung storniert',
                                            'not_required' => 'Keine Zahlung nötig',
                                            default => 'Zahlung offen',
                                        } }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <form method="POST" action="{{ route('events.bookings.update', [$event, $booking]) }}" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    @csrf
                                    @method('PATCH')

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Buchungsstatus</label>
                                        <select name="booking_status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="pending" @selected($booking->booking_status === 'pending')>Vorgemerkt</option>
                                            <option value="confirmed" @selected($booking->booking_status === 'confirmed')>Bestätigt</option>
                                            <option value="cancelled" @selected($booking->booking_status === 'cancelled')>Storniert</option>
                                        </select>
                                    </div>

                                    @if((float) $booking->price_per_person > 0)
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Zahlstatus</label>
                                            <select name="payment_status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="open" @selected($booking->payment_status === 'open')>Offen</option>
                                                <option value="paid" @selected($booking->payment_status === 'paid')>Bezahlt</option>
                                                <option value="cancelled" @selected($booking->payment_status === 'cancelled')>Storniert</option>
                                            </select>
                                        </div>
                                    @else
                                        <input type="hidden" name="payment_status" value="not_required">
                                        <div class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-800">
                                            Für diese Buchung ist keine Zahlung erforderlich.
                                        </div>
                                    @endif

                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                        Status speichern
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($additionalAnswers->isEmpty())
                                    <span class="text-slate-400">—</span>
                                @else
                                    <div class="space-y-1">
                                        @foreach($additionalAnswers as $key => $value)
                                            <div>
                                                <span class="font-medium text-slate-700">
                                                    {{ optional($event->activeBookingForm->fields->firstWhere('slug', $key))->label ?? $key }}:
                                                </span>
                                                <span>
                                                    @if(is_bool($value))
                                                        {{ $value ? 'Ja' : 'Nein' }}
                                                    @elseif(is_array($value))
                                                        {{ implode(', ', $value) }}
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if(!blank($submission?->answers['participant_notes'] ?? null))
                                    <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                        <span class="font-semibold">Gruppenhinweis:</span>
                                        {{ $submission->answers['participant_notes'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $booking->created_at->format('d.m.Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $eventBookings->links() }}
        </div>
    @elseif($event->activeBookingForm)
        <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
            Noch keine Anmeldungen vorhanden.
        </div>
    @else
        <div class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
            Noch keine Teilnehmer vorhanden. Du kannst Teilnehmer oben manuell nachtragen oder die Buchbarkeit für Online-Anmeldungen einschalten.
        </div>
    @endif
</div>

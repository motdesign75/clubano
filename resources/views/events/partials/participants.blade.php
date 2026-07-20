<div class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Teilnehmerliste</h2>
            <p class="mt-1 text-sm text-slate-500">
                @if($event->activeBookingForm)
                    {{ $bookingSubmissionCount }} Buchung{{ $bookingSubmissionCount === 1 ? '' : 'en' }}, {{ $participantCount }} Teilnehmer insgesamt.
                @else
                    Für dieses Event gibt es noch kein aktives Buchungsformular.
                @endif
            </p>
        </div>

        @if($event->activeBookingForm)
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('forms.submissions', $event->activeBookingForm) }}"
                   class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Vollständige Antworten
                </a>

                <a href="{{ route('events.participants.export', $event) }}"
                   class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Teilnehmer exportieren
                </a>
            </div>
        @endif
    </div>

    @if($event->activeBookingForm && $bookingSubmissionCount > 0)
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
                                            <div class="font-medium text-slate-800">{{ $participant->full_name }}</div>
                                            @if($participant->email || $participant->phone)
                                                <div class="mt-1 text-xs text-slate-500">
                                                    {{ $participant->email ?: 'keine E-Mail' }}
                                                    @if($participant->phone)
                                                        · {{ $participant->phone }}
                                                    @endif
                                                </div>
                                            @endif
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
            Schalte die Buchbarkeit ein, damit Clubano automatisch Anmeldungen sammeln und hier anzeigen kann.
        </div>
    @endif
</div>

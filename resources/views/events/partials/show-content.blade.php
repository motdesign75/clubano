@php
    $canManageEvents = ! $isPublicPreview && (auth()->user()?->canManageEvents() ?? false);
    $eventHasEnded = $event->end?->isPast() ?? false;
    $externalPrice = (float) ($event->price_per_person ?? 0);
    $memberPrice = (float) ($event->member_price_per_person ?? 0);
    $hasMemberRate = $event->is_paid && $externalPrice > 0 && $memberPrice < $externalPrice;
@endphp

<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
        @if($event->image_url)
            <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-72 w-full object-cover sm:h-96">
        @endif

        <div class="p-6 sm:p-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-sm font-medium text-indigo-600">{{ $event->tenant->name ?? 'Clubano' }}</div>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $event->title }}</h1>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm text-slate-600">
                        @if($event->category)
                            <span class="rounded-full px-3 py-1 font-medium text-slate-800" style="background-color: {{ $event->category->color }}22;">
                                {{ $event->category->name }}
                            </span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $event->start->format('d.m.Y H:i') }} Uhr</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1">bis {{ $event->end->format('d.m.Y H:i') }} Uhr</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1">{{ $event->location ?: 'Ort folgt' }}</span>
                        <span class="rounded-full {{ $event->is_paid ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }} px-3 py-1 font-medium">
                            @if($event->is_paid)
                                @if($hasMemberRate)
                                    Mitglieder {{ $memberPrice > 0 ? number_format($memberPrice, 2, ',', '.').' '.strtoupper($event->currency ?: 'EUR') : 'kostenfrei' }} · Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}
                                @else
                                    {{ number_format($externalPrice, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }} pro Person
                                @endif
                            @else
                                Kostenfrei
                            @endif
                        </span>
                        @if($event->responsible_name)
                            <span class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-800">
                                Verantwortlich: {{ $event->responsible_name }}
                            </span>
                        @endif
                        @if(($event->conflict_count ?? 0) > 0)
                            <span class="rounded-full bg-rose-100 px-3 py-1 font-medium text-rose-800">
                                Konflikt mit {{ $event->conflict_count }} Termin{{ $event->conflict_count === 1 ? '' : 'en' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:items-end">
                    @if($event->booking_enabled && $event->activeBookingForm)
                        <a href="{{ route('forms.public.show', $event->activeBookingForm->slug) }}"
                           class="inline-flex rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-emerald-700">
                            Jetzt anmelden
                        </a>
                    @endif

                    @if($canManageEvents)
                        <a href="{{ route('events.participants.manage', $event) }}#teilnehmer-nachtragen"
                           class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                            Teilnehmer hinzufügen
                        </a>
                        <a href="{{ route('events.edit', $event) }}"
                           class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Termin bearbeiten
                        </a>
                    @endif
                </div>
            </div>

            @if($canManageEvents)
                <section class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Nächste Schritte</div>
                            <p class="mt-1 text-sm text-slate-600">Was möchtest du für diese Veranstaltung tun?</p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <a href="{{ route('events.participants.manage', $event) }}#teilnehmer-nachtragen" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                Teilnehmer hinzufügen
                            </a>
                            <a href="{{ route('events.participants.manage', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Teilnehmerliste
                            </a>
                            <a href="{{ route('events.schedule.manage', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Dienstplan
                            </a>
                            <a href="{{ route('events.edit', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Termin bearbeiten
                            </a>
                        </div>
                    </div>
                </section>
            @endif

            @if($event->description)
                <div class="mt-8 prose max-w-none prose-slate">
                    {!! $event->description !!}
                </div>
            @endif

            @if(!$isPublicPreview && ($event->conflict_count ?? 0) > 0)
                <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-6">
                    <h2 class="text-lg font-semibold text-rose-950">Termin-Konflikte</h2>
                    <p class="mt-2 text-sm text-rose-800">Dieser Termin überschneidet sich mit anderen Einträgen im Vereinskalender.</p>
                    <div class="mt-4 space-y-3">
                        @foreach($event->conflicting_events as $conflict)
                            <a href="{{ route('events.show', $conflict) }}" class="block rounded-2xl border border-rose-200 bg-white px-4 py-3 transition hover:bg-rose-50">
                                <div class="font-semibold text-slate-950">{{ $conflict->title }}</div>
                                <div class="mt-1 text-sm text-slate-600">{{ $conflict->start->format('d.m.Y H:i') }} - {{ $conflict->end->format('d.m.Y H:i') }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $conflict->location ?: 'Ort folgt' }}
                                    @if($conflict->responsible_name)
                                        · {{ $conflict->responsible_name }}
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!$isPublicPreview)
                <div class="mt-8 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Erstellt von</div>
                        <div class="mt-2 text-sm font-medium text-slate-900">{{ $event->creator?->name ?? 'unbekannt' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $event->created_at?->format('d.m.Y H:i') }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Zuletzt geändert von</div>
                        <div class="mt-2 text-sm font-medium text-slate-900">{{ $event->updater?->name ?? $event->creator?->name ?? 'unbekannt' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $event->updated_at?->format('d.m.Y H:i') }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Verantwortlich</div>
                        <div class="mt-2 text-sm font-medium text-slate-900">{{ $event->responsible_name ?: 'nicht gesetzt' }}</div>
                        <div class="mt-1 text-xs text-slate-500">Optional gepflegte Zuständigkeit</div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Aktivitätssteuerung</div>
                            <h2 class="mt-2 text-lg font-semibold text-slate-950">Für wen und wie?</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Diese Vorgaben steuern Rückmeldungen, Anwesenheit und spätere Auswertungen.</p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:min-w-[460px]">
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Zielgruppe</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950">{{ $event->targetTag?->name ?? 'Alle aktiven Mitglieder' }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rückmeldung</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950">{{ $event->response_required ? 'Erwartet' : 'Nicht erforderlich' }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Anwesenheit</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950">{{ $event->attendance_enabled ? 'Wird erfasst' : 'Optional' }}</div>
                            </div>
                            <div class="rounded-xl {{ $event->counts_toward_required_hours ? 'bg-emerald-50' : 'bg-slate-50' }} px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide {{ $event->counts_toward_required_hours ? 'text-emerald-700' : 'text-slate-500' }}">Pflichtstunden</div>
                                <div class="mt-1 text-sm font-semibold {{ $event->counts_toward_required_hours ? 'text-emerald-950' : 'text-slate-950' }}">{{ $event->counts_toward_required_hours ? 'Wird vorgeschlagen' : 'Nein' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(!$isPublicPreview && $event->changeLogs->isNotEmpty())
                <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-slate-900">Änderungsverlauf</h2>
                        <span class="text-sm text-slate-500">{{ $event->changeLogs->count() }} Einträge</span>
                    </div>
                    <div class="mt-5 space-y-4">
                        @foreach($event->changeLogs as $log)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-900">{{ $log->summary ?: ucfirst($log->action) }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->created_at?->format('d.m.Y H:i') }}</div>
                                </div>
                                <div class="mt-1 text-sm text-slate-600">
                                    {{ $log->user?->name ?? 'System' }} · Aktion: {{ $log->action }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($event->booking_enabled)
                <div class="mt-10 rounded-2xl border border-emerald-100 bg-emerald-50 p-6">
                    <h2 class="text-lg font-semibold text-emerald-900">Anmeldung</h2>

                    @if($event->activeBookingForm)
                        <p class="mt-2 text-sm leading-6 text-emerald-900/80">
                            Diese Veranstaltung ist buchbar. Über das Anmeldeformular können Interessierte sich selbst oder mehrere Personen gesammelt anmelden.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl bg-white/70 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Preis</div>
                                <div class="mt-1 text-base font-semibold text-emerald-950">
                                    @if($event->is_paid)
                                        @if($hasMemberRate)
                                            Mitglieder {{ $memberPrice > 0 ? number_format($memberPrice, 2, ',', '.').' '.strtoupper($event->currency ?: 'EUR') : 'kostenfrei' }}<br>
                                            Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }}
                                        @else
                                            {{ number_format($externalPrice, 2, ',', '.') }} {{ strtoupper($event->currency ?: 'EUR') }} pro Person
                                        @endif
                                    @else
                                        Kostenfrei
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Anmeldung</div>
                                <div class="mt-1 text-base font-semibold text-emerald-950">
                                    Bis zu {{ max(1, (int) ($event->max_participants_per_booking ?: 1)) }} Person{{ max(1, (int) ($event->max_participants_per_booking ?: 1)) === 1 ? '' : 'en' }} pro Buchung
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Abwicklung</div>
                                <div class="mt-1 text-base font-semibold text-emerald-950">
                                    {{ $event->is_paid ? 'Buchung mit offenem Zahlungsstatus' : 'Direkte kostenfreie Anmeldung' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="{{ route('forms.public.show', $event->activeBookingForm->slug) }}"
                               class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                Zum Buchungsformular
                            </a>

                            @if(!$isPublicPreview)
                                <a href="{{ route('forms.submissions', $event->activeBookingForm) }}"
                                   class="inline-flex rounded-lg border border-emerald-200 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-white">
                                    {{ $event->bookingSubmissions->count() }} Anmeldungen ansehen
                                </a>
                            @endif
                        </div>
                    @else
                        <p class="mt-2 text-sm text-amber-800">
                            Buchbarkeit ist aktiviert, aber dem Event ist noch kein aktives Anmeldeformular zugeordnet.
                        </p>
                    @endif
                </div>
            @endif

            @if(!$isPublicPreview)
                @if($canManageEvents)
                    @include('events.partials.invitations', [
                        'event' => $event,
                        'eventInvitations' => $eventInvitations,
                        'invitationStats' => $invitationStats,
                        'invitationStatuses' => $invitationStatuses,
                    ])

                    @if($eventHasEnded)
                        @include('events.partials.attendance', [
                            'event' => $event,
                            'attendanceMembers' => $attendanceMembers,
                            'attendancesByMember' => $attendancesByMember,
                            'attendanceStats' => $attendanceStats,
                        ])
                    @elseif($event->attendance_enabled)
                        <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Anwesenheit</div>
                                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Nach dem Termin erfassen</h2>
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                        Vor der Veranstaltung zählt erst, wer zugesagt hat. Die tatsächliche Anwesenheit wird nach dem Termin dokumentiert.
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    Verfügbar ab {{ $event->end->format('d.m.Y H:i') }} Uhr.
                                </div>
                            </div>
                        </section>
                    @endif
                @endif

                <section class="mt-10 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Arbeitsbereiche</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                Dienstplan und Teilnehmer bleiben mit dieser Veranstaltung verbunden, werden aber in eigenen Ansichten gepflegt.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-950">Dienstplan</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Schichten, Helfer und Aushänge für diese Veranstaltung.</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $scheduleStats['shift_count'] ?? 0 }} Schicht{{ ($scheduleStats['shift_count'] ?? 0) === 1 ? '' : 'en' }}
                                </span>
                            </div>

                            @if($canManageEvents)
                                <a href="{{ route('events.schedule.manage', $event) }}" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                    Dienstplan öffnen
                                </a>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-950">Teilnehmer</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-500">Teilnehmer suchen, hinzufügen, korrigieren und Listen ausgeben.</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $participantCount }} Teilnehmer
                                </span>
                            </div>

                            @if($canManageEvents)
                                <a href="{{ route('events.participants.manage', $event) }}#teilnehmer-nachtragen" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                    Teilnehmer hinzufügen
                                </a>
                                <a href="{{ route('events.participants.manage', $event) }}" class="mt-4 ml-2 inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    Liste öffnen
                                </a>
                            @endif
                        </div>
                    </div>
                </section>

                @if($canManageEvents)
                    <section class="mt-10 rounded-2xl border border-rose-200 bg-rose-50 p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-rose-950">Veranstaltung entfernen</h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-rose-800">
                                    Löschen entfernt die Veranstaltung aus Kalender und öffentlicher Liste. Nutze das nur, wenn der Termin wirklich nicht mehr gebraucht wird.
                                </p>
                            </div>
                            <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Veranstaltung wirklich löschen? Dieser Schritt kann nicht rückgängig gemacht werden.');" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-rose-300 bg-white px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100 sm:w-auto">
                                    Veranstaltung löschen
                                </button>
                            </form>
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </div>
</div>

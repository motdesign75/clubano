@php
    $coverageStyles = [
        'understaffed' => 'border-amber-200 bg-amber-50 text-amber-900',
        'full' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'overstaffed' => 'border-sky-200 bg-sky-50 text-sky-900',
    ];

    $coverageLabels = [
        'understaffed' => 'Es fehlen Helfer',
        'full' => 'Voll besetzt',
        'overstaffed' => 'Mehr als genug',
    ];

    $assignmentStatusLabels = [
        'planned' => 'Eingeplant',
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Abgesagt',
    ];

    $summaryStyle = $coverageStyles[$scheduleStats['coverage_status']] ?? $coverageStyles['understaffed'];
@endphp

<div class="{{ ($embeddedInEditor ?? false) ? '' : 'mt-10' }} space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-px overflow-hidden rounded-2xl bg-slate-200 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="bg-white p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Dienstplan</div>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Wer hilft wann?</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    Lege eine Schicht an, wähle mehrere Mitglieder aus und sieh sofort, wo noch Menschen fehlen.
                </p>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schichten</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $scheduleStats['shift_count'] }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Besetzt</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $scheduleStats['total_confirmed'] }} / {{ $scheduleStats['total_required'] }}</div>
                    </div>
                    <div class="rounded-xl border px-4 py-3 {{ $summaryStyle }}">
                        <div class="text-xs font-semibold uppercase tracking-wide">Status</div>
                        <div class="mt-1 text-sm font-semibold">
                            {{ $coverageLabels[$scheduleStats['coverage_status']] ?? 'Offen' }}
                            @if($scheduleStats['open_slots'] > 0)
                                · {{ $scheduleStats['open_slots'] }} offen
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 p-6">
                <div class="text-sm font-semibold text-slate-950">Ausgeben</div>
                <div class="mt-3 grid gap-2">
                    <a href="{{ route('events.schedule.print', $event) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Druckansicht
                    </a>
                    <a href="{{ route('events.schedule.member-pdf', $event) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                        Mitglieder-Aushang
                    </a>
                    <a href="{{ route('events.schedule.export', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        CSV exportieren
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <details {{ $eventShifts->isEmpty() ? 'open' : '' }}>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Neue Schicht anlegen</h3>
                    <p class="mt-1 text-sm text-slate-500">Beispiel: Aufbau, Theke, Einlass, Kasse oder Abbau.</p>
                </div>
                <span class="rounded-full border border-slate-200 px-3 py-1 text-sm font-semibold text-slate-600">Öffnen</span>
            </summary>

            <form action="{{ route('events.shifts.store', $event) }}" method="POST" class="mt-5 space-y-4">
                @csrf
                <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_8rem]">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Schichtname</label>
                        <input type="text" name="title" required class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="z. B. Theke">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Aufgabe</label>
                        <input type="text" name="role" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="optional">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Bedarf</label>
                        <input type="number" name="required_people" min="1" value="1" required class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Start</label>
                        <input type="datetime-local" step="900" name="starts_at" required value="{{ optional($event->start)->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ende</label>
                        <input type="datetime-local" step="900" name="ends_at" required value="{{ optional($event->end)->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Hinweis</label>
                        <input type="text" name="notes" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="z. B. Schlüssel vorher bei der Kasse abholen">
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                        Schicht anlegen
                    </button>
                </div>
            </form>
        </details>
    </section>

    <section class="space-y-4">
        @forelse($eventShifts as $shift)
            @php
                $shiftStyle = $coverageStyles[$shift->coverage_status] ?? $coverageStyles['understaffed'];
                $activeAssignmentMemberIds = $shift->assignments
                    ->where('status', '!=', 'cancelled')
                    ->pluck('member_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();
            @endphp

            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-px bg-slate-200 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <div class="bg-white p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-xl font-semibold text-slate-950">{{ $shift->title }}</h3>
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $shiftStyle }}">
                                        {{ $coverageLabels[$shift->coverage_status] ?? 'Offen' }}
                                    </span>
                                    @if($shift->role)
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $shift->role }}</span>
                                    @endif
                                </div>

                                <div class="mt-2 text-sm text-slate-600">
                                    {{ $shift->starts_at->format('d.m.Y') }} · {{ $shift->starts_at->format('H:i') }} bis {{ $shift->ends_at->format('H:i') }} Uhr
                                </div>
                                @if($shift->notes)
                                    <div class="mt-2 text-sm text-slate-500">{{ $shift->notes }}</div>
                                @endif
                            </div>

                            <div class="w-full max-w-xs shrink-0">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-700">Besetzung</span>
                                    <span class="font-semibold text-slate-950">{{ $shift->confirmed_assignments_count }} / {{ $shift->required_people }}</span>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full {{ $shift->coverage_status === 'understaffed' ? 'bg-amber-500' : ($shift->coverage_status === 'overstaffed' ? 'bg-sky-500' : 'bg-emerald-500') }}"
                                         style="width: {{ min(100, $shift->required_people > 0 ? round(($shift->confirmed_assignments_count / $shift->required_people) * 100) : 0) }}%"></div>
                                </div>
                                @if($shift->open_slots > 0)
                                    <div class="mt-2 text-sm font-semibold text-amber-700">{{ $shift->open_slots }} Platz{{ $shift->open_slots === 1 ? '' : 'e' }} fehlen noch</div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5">
                            <h4 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Eingeteilt</h4>
                            @if($shift->assignments->isEmpty())
                                <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                                    Noch niemand zugeordnet. Rechts Mitglieder auswählen und speichern.
                                </div>
                            @else
                                <div class="mt-3 grid gap-2 md:grid-cols-2">
                                    @foreach($shift->assignments as $assignment)
                                        <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-200 px-3 py-3">
                                            <div class="min-w-0">
                                                <div class="truncate font-semibold text-slate-950">{{ $assignment->display_name }}</div>
                                                <div class="mt-0.5 text-xs text-slate-500">
                                                    {{ $assignmentStatusLabels[$assignment->status] ?? ucfirst($assignment->status) }}
                                                    · {{ $assignment->member ? 'Mitglied' : 'extern' }}
                                                </div>
                                                @if($assignment->helper_email || $assignment->helper_phone)
                                                    <div class="mt-1 truncate text-xs text-slate-500">
                                                        {{ $assignment->helper_email ?: 'keine E-Mail' }}
                                                        @if($assignment->helper_phone)
                                                            · {{ $assignment->helper_phone }}
                                                        @endif
                                                    </div>
                                                @endif
                                                @if($assignment->notes)
                                                    <div class="mt-1 text-xs text-slate-600">{{ $assignment->notes }}</div>
                                                @endif
                                            </div>

                                            <form action="{{ route('events.shifts.assignments.destroy', [$event, $shift, $assignment]) }}" method="POST" onsubmit="return confirm('Zuordnung wirklich entfernen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                                    Entfernen
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <details class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Schichtdaten bearbeiten</summary>
                            <form action="{{ route('events.shifts.update', [$event, $shift]) }}" method="POST" class="mt-4 space-y-4">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_8rem]">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Schichtname</label>
                                        <input type="text" name="title" value="{{ $shift->title }}" required class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Aufgabe</label>
                                        <input type="text" name="role" value="{{ $shift->role }}" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Bedarf</label>
                                        <input type="number" name="required_people" min="1" value="{{ $shift->required_people }}" required class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Start</label>
                                        <input type="datetime-local" step="900" name="starts_at" value="{{ $shift->starts_at->format('Y-m-d\TH:i') }}" required class="w-full min-w-0 rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Ende</label>
                                        <input type="datetime-local" step="900" name="ends_at" value="{{ $shift->ends_at->format('Y-m-d\TH:i') }}" required class="w-full min-w-0 rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Hinweis</label>
                                        <input type="text" name="notes" value="{{ $shift->notes }}" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                    </div>
                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                        Speichern
                                    </button>
                                </div>
                            </form>

                            <form action="{{ route('events.shifts.destroy', [$event, $shift]) }}" method="POST" class="mt-3" onsubmit="return confirm('Schicht wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">
                                    Schicht löschen
                                </button>
                            </form>
                        </details>
                    </div>

                    <div class="bg-slate-50 p-5">
                        <div x-data="{ selected: [], search: '' }">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-950">Helfer zuordnen</h4>
                                    <p class="mt-1 text-sm text-slate-500">Mehrere Mitglieder anhaken und gemeinsam speichern.</p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200" x-text="selected.length + ' gewählt'">0 gewählt</span>
                            </div>

                            <form action="{{ route('events.shifts.assignments.store', [$event, $shift]) }}" method="POST" class="mt-4 space-y-4">
                                @csrf
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Mitglieder suchen</label>
                                    <input type="search" x-model.debounce.150ms="search" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="Name, E-Mail oder Mitgliedsnummer">
                                </div>

                                <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                                    @foreach($assignableMembers as $member)
                                        @php
                                            $isAssigned = in_array((int) $member->id, $activeAssignmentMemberIds, true);
                                            $memberSearch = Str::lower(trim($member->full_name.' '.$member->member_id.' '.$member->email));
                                        @endphp
                                        <label data-search="{{ $memberSearch }}"
                                               x-show="search === '' || $el.dataset.search.includes(search.toLowerCase())"
                                               class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 {{ $isAssigned ? 'border-slate-200 bg-white/60 opacity-60' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                            <input type="checkbox"
                                                   name="member_ids[]"
                                                   value="{{ $member->id }}"
                                                   class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-400"
                                                   x-model="selected"
                                                   @disabled($isAssigned)>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold text-slate-950">{{ $member->full_name }}</span>
                                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                                    @if($member->member_id)
                                                        Nr. {{ $member->member_id }}
                                                    @else
                                                        ohne Mitgliedsnummer
                                                    @endif
                                                    @if($member->email)
                                                        · {{ $member->email }}
                                                    @endif
                                                </span>
                                                @if($isAssigned)
                                                    <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">bereits eingeteilt</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                                        <select name="status" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                            <option value="confirmed">Bestätigt</option>
                                            <option value="planned">Eingeplant</option>
                                            <option value="cancelled">Abgesagt</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">Notiz für alle</label>
                                        <input type="text" name="notes" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="optional">
                                    </div>
                                </div>

                                <details class="rounded-xl border border-slate-200 bg-white px-3 py-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">Externe Person eintragen</summary>
                                    <div class="mt-3 space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                                            <input type="text" name="helper_name" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300" placeholder="z. B. Gasthelfer">
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-slate-700">E-Mail</label>
                                                <input type="email" name="helper_email" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-sm font-medium text-slate-700">Telefon</label>
                                                <input type="text" name="helper_phone" class="w-full rounded-xl border-slate-300 focus:border-slate-500 focus:ring-slate-300">
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                <button type="submit" class="inline-flex w-full min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Auswahl zuordnen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-sm text-slate-500">
                Für dieses Event gibt es noch keine Schichten. Öffne oben „Neue Schicht anlegen“ und starte mit der ersten Aufgabe.
            </div>
        @endforelse
    </section>
</div>

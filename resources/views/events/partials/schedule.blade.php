@php
    $coverageStyles = [
        'understaffed' => 'border-rose-200 bg-rose-50 text-rose-800',
        'full' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'overstaffed' => 'border-amber-200 bg-amber-50 text-amber-800',
    ];

    $coverageLabels = [
        'understaffed' => 'Noch offen',
        'full' => 'Ausreichend besetzt',
        'overstaffed' => 'Über Soll besetzt',
    ];

    $summaryStyle = $coverageStyles[$scheduleStats['coverage_status']] ?? $coverageStyles['understaffed'];
@endphp

<div class="{{ ($embeddedInEditor ?? false) ? '' : 'mt-10' }} rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">🗓️ Dienstplan</h2>
            <p class="mt-1 text-sm text-slate-500">
                Lege Schichten mit Soll-Besetzung an und ordne interne Helfer oder externe Personen direkt zu.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('events.schedule.print', $event) }}" target="_blank" class="inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Druckansicht
            </a>
            <a href="{{ route('events.schedule.member-pdf', $event) }}" target="_blank" class="inline-flex rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                Mitglieder-Aushang
            </a>
            <a href="{{ route('events.schedule.export', $event) }}" class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Dienstplan exportieren
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Schichten</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $scheduleStats['shift_count'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Soll</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $scheduleStats['total_required'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Bestätigt</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $scheduleStats['total_confirmed'] }}</div>
            </div>
            <div class="rounded-xl border px-4 py-3 {{ $summaryStyle }}">
                <div class="text-xs font-medium uppercase tracking-wide">Status</div>
                <div class="mt-1 text-sm font-semibold">
                    {{ $coverageLabels[$scheduleStats['coverage_status']] ?? 'Offen' }}
                    @if($scheduleStats['open_slots'] > 0)
                        · {{ $scheduleStats['open_slots'] }} offen
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5">
        <h3 class="text-base font-semibold text-slate-900">Neue Schicht anlegen</h3>

        <form action="{{ route('events.shifts.store', $event) }}" method="POST" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 lg:grid-cols-[2fr_1.2fr_0.8fr]">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Titel</label>
                    <input type="text" name="title" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="z. B. Theke, Einlass">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Rolle</label>
                    <input type="text" name="role" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="optional">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Bedarf</label>
                    <input type="number" name="required_people" min="1" value="1" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Von</label>
                    <input type="datetime-local" step="900" name="starts_at" required value="{{ optional($event->start)->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-slate-500">Datum und Uhrzeit des Schichtbeginns</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Bis</label>
                    <input type="datetime-local" step="900" name="ends_at" required value="{{ optional($event->end)->format('Y-m-d\TH:i') }}" class="w-full min-w-0 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-slate-500">Datum und Uhrzeit des Schichtendes</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1.8fr_auto] lg:items-end">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                    <input type="text" name="notes" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="z. B. Kassenübergabe um 18:00">
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                    Schicht anlegen
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 space-y-5">
        @forelse($eventShifts as $shift)
            @php
                $shiftStyle = $coverageStyles[$shift->coverage_status] ?? $coverageStyles['understaffed'];
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $shift->title }}</h3>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $shiftStyle }}">
                                {{ $coverageLabels[$shift->coverage_status] ?? 'Offen' }}
                            </span>
                            @if($shift->role)
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                    {{ $shift->role }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-2 text-sm text-slate-600">
                            {{ $shift->starts_at->format('d.m.Y H:i') }} bis {{ $shift->ends_at->format('H:i') }}
                            · {{ $shift->confirmed_assignments_count }} / {{ $shift->required_people }} besetzt
                            @if($shift->open_slots > 0)
                                · noch {{ $shift->open_slots }} offen
                            @endif
                        </div>
                        @if($shift->notes)
                            <div class="mt-2 text-sm text-slate-500">{{ $shift->notes }}</div>
                        @endif
                    </div>

                    <form action="{{ route('events.shifts.destroy', [$event, $shift]) }}" method="POST" onsubmit="return confirm('Schicht wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">
                            Schicht löschen
                        </button>
                    </form>
                </div>

                <form action="{{ route('events.shifts.update', [$event, $shift]) }}" method="POST" class="mt-5 space-y-4 rounded-2xl bg-slate-50 p-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 lg:grid-cols-[2fr_1.2fr_0.8fr]">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Titel</label>
                            <input type="text" name="title" value="{{ $shift->title }}" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Rolle</label>
                            <input type="text" name="role" value="{{ $shift->role }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Bedarf</label>
                            <input type="number" name="required_people" min="1" value="{{ $shift->required_people }}" required class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Von</label>
                            <input type="datetime-local" step="900" name="starts_at" value="{{ $shift->starts_at->format('Y-m-d\TH:i') }}" required class="w-full min-w-0 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Bis</label>
                            <input type="datetime-local" step="900" name="ends_at" value="{{ $shift->ends_at->format('Y-m-d\TH:i') }}" required class="w-full min-w-0 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[1.8fr_auto] lg:items-end">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                            <input type="text" name="notes" value="{{ $shift->notes }}" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                            Aktualisieren
                        </button>
                    </div>
                </form>

                <div class="mt-5 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Besetzung</h4>
                        @if($shift->assignments->isEmpty())
                            <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                                Noch niemand zugeordnet.
                            </div>
                        @else
                            <div class="mt-3 space-y-3">
                                @foreach($shift->assignments as $assignment)
                                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $assignment->display_name }}</div>
                                            <div class="mt-1 text-sm text-slate-500">
                                                {{ match($assignment->status) {
                                                    'planned' => 'Eingeplant',
                                                    'confirmed' => 'Bestätigt',
                                                    'cancelled' => 'Abgesagt',
                                                    default => ucfirst($assignment->status),
                                                } }}
                                                @if($assignment->member)
                                                    · Mitglied
                                                @else
                                                    · Externe Helferperson
                                                @endif
                                            </div>
                                            @if($assignment->helper_email || $assignment->helper_phone)
                                                <div class="mt-1 text-sm text-slate-500">
                                                    {{ $assignment->helper_email ?: '—' }}
                                                    @if($assignment->helper_phone)
                                                        · {{ $assignment->helper_phone }}
                                                    @endif
                                                </div>
                                            @endif
                                            @if($assignment->notes)
                                                <div class="mt-2 text-sm text-slate-600">{{ $assignment->notes }}</div>
                                            @endif
                                        </div>

                                        <form action="{{ route('events.shifts.assignments.destroy', [$event, $shift, $assignment]) }}" method="POST" onsubmit="return confirm('Zuordnung wirklich entfernen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm font-medium text-rose-600 hover:underline">
                                                Entfernen
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Helfer zuordnen</h4>

                        <form action="{{ route('events.shifts.assignments.store', [$event, $shift]) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Mitglied</label>
                                <select name="member_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Extern oder frei eintragen</option>
                                    @foreach($assignableMembers as $member)
                                        <option value="{{ $member->id }}">{{ $member->full_name }} @if($member->member_id)· Nr. {{ $member->member_id }}@endif</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Externer Name</label>
                                <input type="text" name="helper_name" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="nur wenn kein Mitglied gewählt ist">
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">E-Mail</label>
                                    <input type="email" name="helper_email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Telefon</label>
                                    <input type="text" name="helper_phone" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                                <select name="status" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="confirmed">Bestätigt</option>
                                    <option value="planned">Eingeplant</option>
                                    <option value="cancelled">Abgesagt</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Notiz</label>
                                <textarea name="notes" rows="3" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="z. B. Aufbau ab 17:30, Kassenschlüssel erhalten"></textarea>
                            </div>

                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                                Helfer hinzufügen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-sm text-slate-500">
                Für dieses Event gibt es noch keine Schichten. Lege oben die erste Schicht an, dann siehst du direkt, ob ihr ausreichend besetzt seid.
            </div>
        @endforelse
    </div>
</div>

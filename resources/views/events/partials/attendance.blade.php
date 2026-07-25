@php
    $eventDurationHours = max(0, round($event->start->diffInMinutes($event->end) / 60, 2));
@endphp

<section class="mt-10 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Anwesenheit</div>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Wer war da?</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Hake Mitglieder ab, trage die Stunden ein und entscheide bewusst, ob diese Anwesenheit zu den Pflichtstunden zählt.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-2 text-center sm:min-w-[360px]">
            <div class="rounded-xl bg-slate-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Anwesend</div>
                <div class="mt-1 text-xl font-semibold text-slate-950">{{ $attendanceStats['present'] }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stunden</div>
                <div class="mt-1 text-xl font-semibold text-slate-950">{{ number_format($attendanceStats['total_hours'], 2, ',', '.') }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Pflicht</div>
                <div class="mt-1 text-xl font-semibold text-emerald-950">{{ number_format($attendanceStats['counted_hours'], 2, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('events.attendance.update', $event) }}" class="mt-6">
        @csrf
        @method('PUT')

        <div class="overflow-hidden rounded-2xl border border-slate-200">
            <div class="hidden grid-cols-[minmax(0,1fr),120px,150px,160px] gap-4 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 md:grid">
                <div>Mitglied</div>
                <div>Anwesend</div>
                <div>Stunden</div>
                <div>Pflichtstunden</div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($attendanceMembers as $member)
                    @php
                        $attendance = $attendancesByMember->get($member->id);
                        $attended = (bool) optional($attendance)->attended;
                        $hours = optional($attendance)->hours ?? $eventDurationHours;
                        $counts = $attendance
                            ? (bool) $attendance->counts_toward_required_hours
                            : (bool) $event->counts_toward_required_hours;
                    @endphp
                    <div class="grid gap-4 px-4 py-4 md:grid-cols-[minmax(0,1fr),120px,150px,160px] md:items-center">
                        <div class="min-w-0">
                            <input type="hidden" name="attendances[{{ $loop->index }}][member_id]" value="{{ $member->id }}">
                            <div class="font-semibold text-slate-950">{{ $member->full_name }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                @if($member->member_id)
                                    <span>Nr. {{ $member->member_id }}</span>
                                @endif
                                <span>{{ number_format((float) $member->required_service_hours, 2, ',', '.') }} h Soll</span>
                            </div>
                        </div>

                        <label class="flex min-h-11 items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 md:justify-start md:border-0 md:px-0">
                            <span class="md:hidden">Anwesend</span>
                            <input type="hidden" name="attendances[{{ $loop->index }}][attended]" value="0">
                            <input type="checkbox" name="attendances[{{ $loop->index }}][attended]" value="1" @checked($attended) class="h-5 w-5 rounded border-slate-300 text-slate-950 focus:ring-slate-400">
                        </label>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden">Stunden</label>
                            <input type="number" step="0.25" min="0" max="999.99" name="attendances[{{ $loop->index }}][hours]" value="{{ old('attendances.' . $loop->index . '.hours', number_format((float) $hours, 2, '.', '')) }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                        </div>

                        <label class="flex min-h-11 items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800">
                            <span>Zählt</span>
                            <input type="hidden" name="attendances[{{ $loop->index }}][counts_toward_required_hours]" value="0">
                            <input type="checkbox" name="attendances[{{ $loop->index }}][counts_toward_required_hours]" value="1" @checked($counts) class="h-5 w-5 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-400">
                        </label>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Keine aktiven Mitglieder gefunden.</div>
                @endforelse
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-6 text-slate-500">Tipp: Für reine Teilnahme ohne Arbeit den Haken bei Pflichtstunden einfach aus lassen.</p>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Anwesenheit speichern
            </button>
        </div>
    </form>
</section>

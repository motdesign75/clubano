@extends('layouts.app')

@section('title', 'Anwesenheit auswerten')

@section('content')
<div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-slate-500">
                    <a href="{{ route('events.index') }}" class="font-semibold text-slate-700 hover:text-slate-950">Kalender</a>
                    <span aria-hidden="true" class="text-slate-300">/</span>
                    <span>Anwesenheit</span>
                </div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Anwesenheit auswerten</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Wer war dabei, welche Stunden zählen und wer hat sein Pflichtstunden-Soll noch offen.
                </p>
            </div>

            <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Zurück zum Kalender
            </a>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Anwesenheiten</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $reportStats['present_records'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Mitglieder</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $reportStats['members_with_attendance'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Stunden gesamt</div>
            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($reportStats['total_hours'], 2, ',', '.') }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-emerald-700">Pflichtstunden</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-950">{{ number_format($reportStats['counted_hours'], 2, ',', '.') }}</div>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-sm">
            <div class="text-sm font-medium text-amber-700">Noch offen</div>
            <div class="mt-1 text-2xl font-semibold text-amber-950">{{ $reportStats['open_members'] }}</div>
        </div>
    </section>

    <details class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Auswertung steuern</h2>
                <p class="mt-1 text-sm text-slate-500">Zeitraum wählen, ein Mitglied fokussieren oder nur offene Pflichtstunden anzeigen.</p>
            </div>
            <span class="rounded-md bg-slate-950 px-3 py-1.5 text-sm font-semibold text-white">Filter</span>
        </summary>

        <form method="GET" action="{{ route('events.attendance.report') }}" class="mt-5 grid gap-4 lg:grid-cols-[150px_150px_minmax(0,1fr)_auto] lg:items-end">
            <div>
                <label class="text-sm font-semibold text-slate-900">Von</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Bis</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
            </div>

            <div>
                <label class="text-sm font-semibold text-slate-900">Mitglied</label>
                <select name="member_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <option value="">Alle Mitglieder</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" @selected((string) $filters['member_id'] === (string) $member->id)>{{ $member->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-4 text-sm text-slate-700">
                    <input type="checkbox" name="only_open" value="1" @checked($filters['only_open']) class="rounded border-slate-300">
                    Nur offene
                </label>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Auswerten
                </button>
            </div>
        </form>
    </details>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr),380px]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Mitglieder und Pflichtstunden</h2>
                <p class="mt-1 text-sm text-slate-500">Offene Stunden stehen oben, erledigte Mitglieder werden ruhig eingeordnet.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($memberSummaries as $summary)
                    @php
                        $member = $summary['member'];
                        $isComplete = $summary['remaining_hours'] <= 0;
                    @endphp
                    <a href="{{ route('members.show', $member) }}" class="block px-5 py-4 hover:bg-slate-50">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr),120px,120px,120px] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate text-base font-semibold text-slate-950">{{ $member->full_name }}</div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isComplete ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $isComplete ? 'Erfüllt' : 'Offen' }}
                                    </span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full {{ $isComplete ? 'bg-emerald-500' : 'bg-slate-950' }}" style="width: {{ $summary['completion_percent'] }}%"></div>
                                </div>
                                <div class="mt-2 text-sm text-slate-500">{{ $summary['attendances_count'] }} Anwesenheiten im Zeitraum</div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Soll</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950">{{ number_format($summary['required_hours'], 2, ',', '.') }} h</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Gezählt</div>
                                <div class="mt-1 text-sm font-semibold text-emerald-800">{{ number_format($summary['counted_hours'], 2, ',', '.') }} h</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">Offen</div>
                                <div class="mt-1 text-sm font-semibold text-amber-800">{{ number_format($summary['remaining_hours'], 2, ',', '.') }} h</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Für diese Auswahl gibt es keine passenden Mitglieder.</div>
                @endforelse
            </div>
        </div>

        <aside class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Termine im Zeitraum</h2>
                <p class="mt-1 text-sm text-slate-500">Die Grundlage der Auswertung.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($eventSummaries as $summary)
                    @php($event = $summary['event'])
                    <a href="{{ $event ? route('events.show', $event) : '#' }}" class="block px-5 py-4 hover:bg-slate-50">
                        <div class="flex items-start gap-3">
                            <div class="w-14 shrink-0 rounded-lg bg-slate-50 px-2 py-2 text-center">
                                <div class="text-xs font-semibold uppercase text-slate-500">{{ $event?->start?->translatedFormat('D') ?? '-' }}</div>
                                <div class="text-lg font-semibold text-slate-950">{{ $event?->start?->format('d') ?? '-' }}</div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-slate-950">{{ $event?->title ?? 'Gelöschte Veranstaltung' }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $summary['present'] }} anwesend · {{ number_format($summary['counted_hours'], 2, ',', '.') }} h Pflicht</div>
                                <div class="mt-1 truncate text-xs text-slate-500">{{ $event?->location ?: 'Ort folgt' }}</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Noch keine Anwesenheiten im Zeitraum erfasst.</div>
                @endforelse
            </div>
        </aside>
    </section>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Auswertungen')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-3xl bg-slate-950 text-white shadow-sm">
        <div class="grid gap-8 p-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:p-8">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-200">Vereinsauswertungen</div>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal sm:text-5xl">Was passiert im Verein?</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300">
                    Mitglieder, Termine, Rückmeldungen, Pflichtstunden und offene Vorgänge in einer ruhigen Übersicht.
                </p>
            </div>

            <form method="GET" action="{{ route('statistics.index') }}" class="rounded-2xl border border-white/10 bg-white/10 p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <label for="date_from" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">Von</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-2 w-full rounded-xl border-white/20 bg-white text-sm text-slate-950 shadow-sm focus:border-blue-400 focus:ring-blue-400">
                    </div>
                    <div>
                        <label for="date_to" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">Bis</label>
                        <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-2 w-full rounded-xl border-white/20 bg-white text-sm text-slate-950 shadow-sm focus:border-blue-400 focus:ring-blue-400">
                    </div>
                    <div>
                        <label for="category_id" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">Terminart</label>
                        <select id="category_id" name="category_id" class="mt-2 w-full rounded-xl border-white/20 bg-white text-sm text-slate-950 shadow-sm focus:border-blue-400 focus:ring-blue-400">
                            <option value="">Alle Terminarten</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="member_id" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">Mitglied</label>
                        <select id="member_id" name="member_id" class="mt-2 w-full rounded-xl border-white/20 bg-white text-sm text-slate-950 shadow-sm focus:border-blue-400 focus:ring-blue-400">
                            <option value="">Alle Mitglieder</option>
                            @foreach($memberOptions as $member)
                                @php
                                    $memberName = $member->organization ?: trim(($member->first_name ?? '').' '.($member->last_name ?? '')) ?: $member->email;
                                @endphp
                                <option value="{{ $member->id }}" @selected((int) $filters['member_id'] === $member->id)>{{ $memberName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                    Zeitraum anwenden
                </button>
            </form>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($summaryCards as $card)
            @php
                $toneClasses = match($card['tone']) {
                    'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
                    'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
                    default => 'border-slate-200 bg-white text-slate-500',
                };
            @endphp
            <div class="rounded-2xl border {{ $toneClasses }} p-5 shadow-sm">
                <div class="text-sm font-medium">{{ $card['label'] }}</div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ $card['value'] }}</div>
                <div class="mt-2 text-sm">{{ $card['hint'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach($spotlights as $spotlight)
            <a href="{{ $spotlight['route'] }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-200 hover:bg-blue-50/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-500">{{ $spotlight['label'] }}</div>
                        <div class="mt-3 text-3xl font-semibold text-slate-950">{{ $spotlight['value'] }}</div>
                        <div class="mt-2 text-sm text-slate-500">{{ $spotlight['hint'] }}</div>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition group-hover:bg-blue-100 group-hover:text-blue-700">
                        Öffnen
                    </span>
                </div>
            </a>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Teilnahme und Rückmeldungen</h2>
                    <p class="mt-1 text-sm text-slate-500">Vom Einladen bis zur echten Anwesenheit, gefiltert nach Zeitraum, Terminart oder Mitglied.</p>
                </div>
                <a href="{{ route('events.attendance.report') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Anwesenheit öffnen</a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Eingeladen</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participationSummary['invited'] }}</div>
                </div>
                <div class="rounded-2xl bg-blue-50 px-4 py-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">Geantwortet</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participationSummary['response_rate'] ?? 0 }}%</div>
                    <div class="mt-1 text-xs text-blue-700">{{ $participationSummary['responses'] }} Rückmeldungen</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 px-4 py-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Zugesagt</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participationSummary['accepted'] }}</div>
                    <div class="mt-1 text-xs text-emerald-700">{{ $participationSummary['maybe'] }} vielleicht</div>
                </div>
                <div class="rounded-2xl bg-amber-50 px-4 py-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-600">Offen</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $participationSummary['open'] }}</div>
                    <div class="mt-1 text-xs text-amber-700">{{ $participationSummary['declined'] + $participationSummary['excused'] }} abgesagt/entschuldigt</div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-950">Tatsächlich anwesend</div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $participationSummary['attended'] }} erfasste Anwesenheiten
                            @if(! is_null($participationSummary['attendance_rate']))
                                · {{ $participationSummary['attendance_rate'] }}% der Zusagen wurden als anwesend erfasst
                            @endif
                        </p>
                    </div>
                    <div class="text-3xl font-semibold text-slate-950">{{ $participationSummary['attendance_rate'] ?? 0 }}%</div>
                </div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-white">
                    <div class="h-full rounded-full bg-emerald-600" style="width: {{ min(100, $participationSummary['attendance_rate'] ?? 0) }}%"></div>
                </div>
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Monatsentwicklung</h2>
            <p class="mt-1 text-sm text-slate-500">Wie Termine, Rückmeldungen und Anwesenheiten über das Jahr laufen.</p>

            <div class="mt-5 space-y-3">
                @forelse($monthlyParticipation as $month)
                    @php
                        $monthMax = max(1, $monthlyParticipation->max(fn ($item) => max($item['events'], $item['responses'], $item['attended'])));
                    @endphp
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-semibold text-slate-950">{{ $month['label'] }}</div>
                            <div class="text-xs text-slate-500">{{ $month['events'] }} Termine</div>
                        </div>
                        <div class="mt-3 grid gap-1.5">
                            <div class="h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full bg-blue-600" style="width: {{ round(($month['responses'] / $monthMax) * 100) }}%"></div>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full bg-emerald-600" style="width: {{ round(($month['attended'] / $monthMax) * 100) }}%"></div>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500">{{ $month['responses'] }} Rückmeldungen · {{ $month['attended'] }} anwesend</div>
                    </div>
                @empty
                    <div class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-500">Noch keine Monatswerte vorhanden.</div>
                @endforelse
            </div>
        </aside>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Termine nach Terminart</h2>
                        <p class="mt-1 text-sm text-slate-500">Welche Aktivitätsarten im gewählten Zeitraum wirklich genutzt werden.</p>
                    </div>
                    <a href="{{ route('events.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Kalender öffnen</a>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($eventCategoryStats as $category)
                        @php
                            $maxCategory = max(1, $eventCategoryStats->max('count'));
                        @endphp
                        <div>
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <div class="font-semibold text-slate-900">{{ $category['label'] }}</div>
                                <div class="text-slate-500">{{ $category['count'] }} Termine</div>
                            </div>
                            <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, round(($category['count'] / $maxCategory) * 100)) }}%"></div>
                            </div>
                            <div class="mt-1 text-xs text-slate-500">{{ $category['attendance_enabled'] }} mit Anwesenheitserfassung</div>
                        </div>
                    @empty
                        <div class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-500">Noch keine Termine im gewählten Zeitraum.</div>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <h2 class="text-lg font-semibold text-slate-950">Mitglieder nach Teilnahme</h2>
                    <p class="mt-1 text-sm text-slate-500">Wer Rückmeldungen gibt und wer tatsächlich teilnimmt.</p>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                        <div class="hidden grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(80px,1fr))] gap-3 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 lg:grid">
                            <div>Mitglied</div>
                            <div>Eingeladen</div>
                            <div>Zugesagt</div>
                            <div>Anwesend</div>
                            <div>Antwort</div>
                            <div>Teilnahme</div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse($memberParticipation as $member)
                                <div class="grid gap-3 px-4 py-4 text-sm lg:grid-cols-[minmax(0,1.4fr)_repeat(5,minmax(80px,1fr))] lg:items-center">
                                    <div class="font-semibold text-slate-950">{{ $member['name'] }}</div>
                                    <div><span class="lg:hidden text-slate-400">Eingeladen: </span>{{ $member['invited'] }}</div>
                                    <div><span class="lg:hidden text-slate-400">Zugesagt: </span>{{ $member['accepted'] }}</div>
                                    <div><span class="lg:hidden text-slate-400">Anwesend: </span>{{ $member['attended'] }}</div>
                                    <div><span class="lg:hidden text-slate-400">Antwort: </span>{{ $member['response_rate'] }}%</div>
                                    <div>
                                        <span class="lg:hidden text-slate-400">Teilnahme: </span>
                                        <span class="font-semibold text-emerald-700">{{ $member['attendance_rate'] }}%</span>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-sm text-slate-500">Noch keine Einladungen mit Mitgliedsbezug vorhanden.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Gruppen und Abteilungen</h2>
                    <p class="mt-1 text-sm text-slate-500">Die stärksten Markierungen in der Mitgliederbasis.</p>

                    <div class="mt-5 space-y-3">
                        @forelse($tagStats as $tag)
                            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                                <span class="font-semibold text-slate-900">{{ $tag['label'] }}</span>
                                <span class="text-sm font-semibold text-slate-500">{{ $tag['count'] }}</span>
                            </div>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-500">Noch keine Gruppen oder Abteilungen markiert.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Terminarten nach Teilnahme</h2>
                    <p class="mt-1 text-sm text-slate-500">Welche Aktivitäten Rückmeldungen und Anwesenheiten erzeugen.</p>

                    <div class="mt-5 space-y-3">
                        @forelse($categoryParticipation as $category)
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="truncate font-semibold text-slate-900">{{ $category['label'] }}</span>
                                    <span class="text-sm font-semibold text-slate-500">{{ $category['events'] }} Termine</span>
                                </div>
                                <div class="mt-1 text-sm text-slate-500">{{ $category['accepted'] }} Zusagen · {{ $category['attended'] }} anwesend</div>
                            </div>
                        @empty
                            <div class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-500">Noch keine Teilnahmewerte nach Terminart.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-950">Zuletzt passiert</h2>
                <p class="mt-1 text-sm text-slate-500">Neue Termine und Eingänge, die du direkt öffnen kannst.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recentActivity as $activity)
                    <a href="{{ $activity['route'] }}" class="block px-5 py-4 transition hover:bg-slate-50">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $activity['type'] }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-slate-950">{{ $activity['title'] }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $activity['meta'] }}</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-sm text-slate-500">Im gewählten Zeitraum ist noch nichts Sichtbares passiert.</div>
                @endforelse
            </div>
        </aside>
    </section>
</div>
@endsection

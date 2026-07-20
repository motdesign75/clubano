@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    \Carbon\Carbon::setLocale('de');

    $tenant = Auth::user()->tenant;
    $isSubscribed = (bool) ($tenant && $tenant->subscribed('default'));
    $onTrial = $tenant && $tenant->onTrial();
    $trialEndsAt = $tenant?->trial_ends_at;
    $hasComplimentaryAccess = $tenant?->hasComplimentaryAccess() ?? false;

    $today = now();
    $greeting = match (true) {
        $today->hour < 11 => 'Guten Morgen',
        $today->hour < 17 => 'Guten Tag',
        default => 'Guten Abend',
    };

    $timelineCount = $timeline->count();
    $primaryNextStep = $onboarding['nextStep'] ?? null;

    $quickActions = [
        ['label' => 'Mitglied anlegen', 'route' => route('members.create')],
        ['label' => 'Termin planen', 'route' => route('events.create')],
        ['label' => 'Formular bauen', 'route' => route('forms.create')],
    ];

    $focusCards = [
        [
            'title' => 'Mitglieder',
            'value' => $membersCount,
            'meta' => $entriesThisYearCount . ' neu in diesem Jahr',
            'route' => route('members.index'),
        ],
        [
            'title' => 'Termine',
            'value' => $upcomingEventsCount,
            'meta' => $timelineCount . ' in den nächsten 7 Tagen',
            'route' => route('events.index'),
        ],
        [
            'title' => 'Formulare',
            'value' => $formsCount,
            'meta' => $publicEventsCount . ' öffentliche Termine',
            'route' => route('forms.index'),
        ],
    ];

    $attentionItems = [];

    if ($timelineCount > 0) {
        $attentionItems[] = [
            'title' => $timelineCount . ' Termine in den nächsten 7 Tagen',
            'meta' => 'Die nächsten Veranstaltungen solltest du heute im Blick haben.',
            'route' => route('events.index'),
            'action' => 'Termine öffnen',
            'tone' => 'slate',
        ];
    }

    if ($entries->count() > 0) {
        $attentionItems[] = [
            'title' => $entries->count() . ' neue Eintritte in diesem Monat',
            'meta' => 'Prüfe kurz, ob alle neuen Mitglieder vollständig angelegt sind.',
            'route' => route('members.index'),
            'action' => 'Mitglieder öffnen',
            'tone' => 'emerald',
        ];
    }

    if ($formsCount > 0) {
        $attentionItems[] = [
            'title' => 'Formulare laufen bereits',
            'meta' => 'Schau nach, ob Antworten oder Anmeldungen auf dich warten.',
            'route' => route('forms.index'),
            'action' => 'Formulare öffnen',
            'tone' => 'indigo',
        ];
    }

    if (empty($attentionItems)) {
        $attentionItems[] = [
            'title' => 'Heute ist alles ruhig',
            'meta' => 'Ein guter Moment, um den Verein in Ruhe zu ordnen oder etwas Neues anzulegen.',
            'route' => route('dashboard'),
            'action' => 'Übersicht behalten',
            'tone' => 'slate',
        ];
    }
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
    <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <div class="grid gap-8 lg:grid-cols-[1.2fr,0.8fr] lg:items-start">
            <div class="space-y-5">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="rounded-full bg-slate-950 px-3 py-1 font-semibold text-white">
                        {{ $greeting }}, {{ Auth::user()->name }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">
                        {{ $tenant->name }}
                    </span>

                    @if($hasComplimentaryAccess)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            {{ $tenant->license_mode_label }}
                        </span>
                    @elseif($onTrial)
                        <span class="rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-700 ring-1 ring-amber-200">
                            Testphase bis {{ $trialEndsAt?->format('d.m.Y') }}
                        </span>
                    @elseif($isSubscribed)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            Lizenz aktiv
                        </span>
                    @endif
                </div>

                <div class="max-w-3xl">
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Was braucht heute deine Aufmerksamkeit?
                    </h1>
                    <p class="mt-3 max-w-xl text-base leading-7 text-slate-600">
                        Der nächste sinnvolle Schritt, die wichtigsten Signale und sonst nichts, was dich aufhält.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    @foreach($quickActions as $action)
                        <a href="{{ $action['route'] }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:border-slate-300 hover:bg-white">
                            <span>{{ $action['label'] }}</span>
                            <span aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>

                @if($onTrial && $trialEndsAt)
                    <div class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-4 text-sm text-amber-900">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Testphase aktiv</div>
                                <div class="mt-1 text-base font-semibold text-amber-950">
                                    Ihr könnt Clubano kostenlos bis zum {{ $trialEndsAt->format('d.m.Y') }} nutzen.
                                </div>
                                <p class="mt-1 text-sm leading-6 text-amber-800">
                                    Erst ausprobieren, dann später in Ruhe entscheiden, ob ihr ein Abo aktivieren möchtet.
                                </p>
                            </div>

                            <a href="{{ route('subscription.index') }}"
                               class="inline-flex items-center justify-center rounded-full border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100">
                                Lizenz ansehen
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Heute zuerst</div>

                @if($primaryNextStep)
                    <h2 class="mt-4 text-2xl font-semibold">{{ $primaryNextStep['title'] }}</h2>
                    <p class="mt-3 text-sm leading-6 text-white/75">{{ $primaryNextStep['meta'] }}</p>

                    @if($primaryNextStep['route'])
                        <a href="{{ $primaryNextStep['route'] }}"
                           class="mt-5 inline-flex rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                            Jetzt machen
                        </a>
                    @endif
                @else
                    <h2 class="mt-4 text-2xl font-semibold">Alles bereit</h2>
                    <p class="mt-3 text-sm leading-6 text-white/75">
                        Heute gibt es keinen offensichtlichen Engpass. Du kannst ruhig arbeiten und bewusst vorgehen.
                    </p>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach($focusCards as $card)
            <a href="{{ $card['route'] }}"
               class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="text-sm font-medium text-slate-500">{{ $card['title'] }}</div>
                <div class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $card['value'] }}</div>
                <div class="mt-2 text-sm text-slate-500">{{ $card['meta'] }}</div>
            </a>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Jetzt wichtig</h2>
                <p class="mt-1 text-sm text-slate-500">Nur die Themen, die im Alltag gerade wirklich Aufmerksamkeit brauchen.</p>
            </div>

            <div class="mt-6 space-y-3">
                @foreach($attentionItems as $item)
                    <a href="{{ $item['route'] }}"
                       class="flex items-start justify-between gap-4 rounded-2xl border px-4 py-4 transition hover:bg-slate-50
                            {{ $item['tone'] === 'emerald' ? 'border-emerald-200 bg-emerald-50/40' : '' }}
                            {{ $item['tone'] === 'indigo' ? 'border-indigo-200 bg-indigo-50/40' : '' }}
                            {{ $item['tone'] === 'slate' ? 'border-slate-200' : '' }}">
                        <div>
                            <div class="text-base font-semibold text-slate-900">{{ $item['title'] }}</div>
                            <div class="mt-1 text-sm leading-6 text-slate-600">{{ $item['meta'] }}</div>
                        </div>
                        <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">{{ $item['action'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Nächste Termine</h2>
                    <p class="mt-1 text-sm text-slate-500">Schnell scanbar, damit du direkt weißt, was ansteht.</p>
                </div>
                <a href="{{ route('events.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                    Alle Termine
                </a>
            </div>

            <div class="mt-6 space-y-2.5">
                @forelse($timeline as $event)
                    <a href="{{ route('events.show', $event) }}"
                       class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3.5 transition hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-500">
                                {{ \Carbon\Carbon::parse($event->start)->translatedFormat('D, d.m.Y · H:i') }} Uhr
                            </div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $event->title }}</div>
                            <div class="mt-1 truncate text-sm text-slate-600">
                                {{ $event->location ?: 'Ort folgt' }}
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ $event->is_public ? 'Öffentlich' : 'Intern' }}
                        </span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                        Keine Termine in den nächsten sieben Tagen. Gute Gelegenheit, etwas Neues zu planen.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if(isset($onboarding) && !$onboarding['isComplete'])
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-6 lg:grid-cols-[1.2fr,0.8fr] lg:items-start">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Startcenter</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                        Noch {{ $onboarding['totalCount'] - $onboarding['completedCount'] }} Schritte bis zum Start
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Für neue Vereine lohnt sich hier noch ein kurzer sauberer Aufbau. Danach wird Clubano im Alltag deutlich ruhiger.
                    </p>

                    <div class="mt-5 grid gap-3">
                        @foreach($onboarding['steps'] as $step)
                            <div class="rounded-2xl border px-4 py-4 {{ $step['completed'] ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50' }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-base font-semibold text-slate-900">{{ $step['title'] }}</div>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $step['description'] }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $step['completed'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $step['completed'] ? 'Erledigt' : 'Offen' }}
                                    </span>
                                </div>

                                @if(!$step['completed'] && $step['route'])
                                    <a href="{{ $step['route'] }}"
                                       class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-slate-200 hover:bg-slate-100">
                                        Jetzt machen
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-200">
                    <div class="flex items-center justify-between text-sm font-medium text-slate-600">
                        <span>Fortschritt</span>
                        <span>{{ $onboarding['completedCount'] }}/{{ $onboarding['totalCount'] }}</span>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-slate-950" style="width: {{ $onboarding['progressPercent'] }}%"></div>
                    </div>
                    <div class="mt-3 text-3xl font-semibold text-slate-950">{{ $onboarding['progressPercent'] }}%</div>
                </div>
            </div>
        </section>
    @endif

    <details class="group rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Entwicklung im Verein</h2>
                <p class="mt-1 text-sm text-slate-500">Mitgliederbewegung, Geburtstage, Jubiläen und längerfristige Entwicklung.</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $membersCount }} Mitglieder
                </span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $entriesThisYearCount }} neu
                </span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                    Aufklappen
                </span>
            </div>
        </summary>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr,0.9fr]">
            <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200">
                <div>
                    <h3 class="text-2xl font-semibold tracking-tight text-slate-900">Mitglieder wachsen oder gehen</h3>
                    <p class="mt-1 text-sm text-slate-500">Hier siehst du, wie sich der Verein entwickelt.</p>
                </div>

                <div class="mt-6">
                    @livewire('dashboard-member-chart')
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200">
                <div>
                    <h3 class="text-2xl font-semibold tracking-tight text-slate-900">Was im Verein passiert</h3>
                    <p class="mt-1 text-sm text-slate-500">Eintritte, Austritte, Geburtstage und Jubiläen auf einen Blick.</p>
                </div>

                <div class="mt-6">
                    @livewire('dashboard-member-stats')
                </div>
            </div>
        </div>
    </details>
</div>
@endsection

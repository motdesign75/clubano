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
    $documentsCount = $documentsCount ?? 0;
    $documentAttentionCount = $documentAttentionCount ?? 0;
    $canManageWork = auth()->user()?->isStaff() ?? false;

    $licenseLabel = match (true) {
        $hasComplimentaryAccess => $tenant->license_mode_label,
        $onTrial && $trialEndsAt => 'Testphase bis ' . $trialEndsAt->format('d.m.Y'),
        $isSubscribed => 'Lizenz aktiv',
        default => 'Lizenz prüfen',
    };

    $quickActions = $canManageWork
        ? [
            ['label' => 'Mitglied anlegen', 'route' => route('members.create'), 'icon' => 'user-plus'],
            ['label' => 'Termin planen', 'route' => route('events.create'), 'icon' => 'calendar'],
            ['label' => 'Formular erstellen', 'route' => route('forms.create'), 'icon' => 'document-plus'],
            ['label' => 'Dokument ablegen', 'route' => route('documents.create'), 'icon' => 'archive-box'],
        ]
        : [
            ['label' => 'Mitglieder ansehen', 'route' => route('members.index'), 'icon' => 'users'],
            ['label' => 'Termine ansehen', 'route' => route('events.index'), 'icon' => 'calendar'],
            ['label' => 'Dokumente ansehen', 'route' => route('documents.index'), 'icon' => 'archive-box'],
        ];

    $metrics = [
        [
            'label' => 'Mitglieder',
            'value' => $membersCount,
            'hint' => $entriesThisYearCount . ' neue Eintritte ' . now()->year,
            'route' => route('members.index'),
            'icon' => 'users',
        ],
        [
            'label' => 'Termine',
            'value' => $upcomingEventsCount,
            'hint' => $timelineCount . ' in den nächsten 7 Tagen',
            'route' => route('events.index'),
            'icon' => 'calendar-days',
        ],
        [
            'label' => 'Formulare',
            'value' => $formsCount,
            'hint' => $publicEventsCount . ' öffentliche Termine',
            'route' => route('forms.index'),
            'icon' => 'clipboard-document-list',
        ],
        [
            'label' => 'Dokumente',
            'value' => $documentsCount,
            'hint' => $documentAttentionCount . ' brauchen Aufmerksamkeit',
            'route' => route('documents.index'),
            'icon' => 'archive-box',
        ],
    ];

    $signals = [];

    if ($timelineCount > 0) {
        $signals[] = [
            'label' => 'Termine prüfen',
            'text' => $timelineCount . ' Termine in den nächsten 7 Tagen',
            'route' => route('events.index'),
        ];
    }

    if ($entries->count() > 0) {
        $signals[] = [
            'label' => 'Mitglieder prüfen',
            'text' => $entries->count() . ' neue Eintritte in diesem Monat',
            'route' => route('members.index'),
        ];
    }

    if ($formsCount > 0) {
        $signals[] = [
            'label' => 'Formulare prüfen',
            'text' => 'Antworten und Anmeldungen im Blick behalten',
            'route' => route('forms.index'),
        ];
    }

    if ($documentAttentionCount > 0) {
        $signals[] = [
            'label' => 'Dokumente prüfen',
            'text' => $documentAttentionCount . ' Dokumente haben Fristen oder Prüfbedarf',
            'route' => route('documents.index', ['due' => 'soon']),
        ];
    }

    if (empty($signals)) {
        $signals[] = [
            'label' => 'Alles ruhig',
            'text' => 'Heute gibt es keinen dringenden Hinweis.',
            'route' => route('dashboard'),
        ];
    }

    $heroEvent = $timeline->first();
    $heroTitle = $primaryNextStep['title'] ?? ($heroEvent?->title ?? 'Alles bereit');
    $heroMeta = $primaryNextStep['meta'] ?? ($heroEvent
        ? 'Als Nächstes steht ' . $heroEvent->title . ' an.'
        : 'Kein Engpass sichtbar. Du kannst bewusst und ruhig weiterarbeiten.');
    $heroRoute = $primaryNextStep['route'] ?? ($heroEvent ? route('events.show', $heroEvent) : route('dashboard'));
    $heroAction = $primaryNextStep ? 'Jetzt machen' : ($heroEvent ? 'Termin öffnen' : 'Übersicht behalten');

    $todaysBirthdays = $birthdays->filter(fn ($member) => $member->birthday?->format('m-d') === now()->format('m-d'))->values();
    $birthdayMember = $todaysBirthdays->first();
    $anniversaryMember = $anniversaries->first();
    $entryMember = $entries->first();

    $memberDisplayName = fn ($member) => trim(($member?->first_name ? $member->first_name . ' ' : '') . ($member?->last_name ?? '')) ?: ($member?->organization ?? 'Mitglied');

    $clubMoment = match (true) {
        (bool) $birthdayMember => [
            'eyebrow' => 'Clubano-Moment',
            'title' => $memberDisplayName($birthdayMember) . ' hat heute Geburtstag',
            'text' => 'Ein kurzer Glückwunsch wirkt oft stärker als jede perfekte Verwaltung.',
            'action' => 'Mitglied öffnen',
            'route' => route('members.show', $birthdayMember),
            'icon' => 'cake',
        ],
        (bool) $anniversaryMember => [
            'eyebrow' => 'Clubano-Moment',
            'title' => $memberDisplayName($anniversaryMember) . ' hat heute Vereinsjubiläum',
            'text' => 'Solche Momente machen sichtbar, wer den Verein über Jahre trägt.',
            'action' => 'Mitglied öffnen',
            'route' => route('members.show', $anniversaryMember),
            'icon' => 'sparkles',
        ],
        (bool) $entryMember => [
            'eyebrow' => 'Clubano-Moment',
            'title' => $memberDisplayName($entryMember) . ' ist neu dabei',
            'text' => 'Ein persönliches Willkommen entscheidet oft darüber, ob jemand wirklich ankommt.',
            'action' => 'Neue Mitglieder',
            'route' => route('members.index'),
            'icon' => 'heart',
        ],
        default => [
            'eyebrow' => 'Clubano-Moment',
            'title' => 'Heute ist ein guter Tag für ein Danke',
            'text' => 'Such dir eine Person im Verein aus, die selten im Mittelpunkt steht, aber viel möglich macht.',
            'action' => 'Mitglieder ansehen',
            'route' => route('members.index'),
            'icon' => 'heart',
        ],
    };
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
        <div class="grid min-h-[360px] gap-8 bg-[linear-gradient(135deg,#020617_0%,#0f3a3a_52%,#1f2937_100%)] p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr),420px] lg:p-10">
            <div class="flex min-w-0 flex-col justify-between gap-8">
                <div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-white/70">
                        <span class="font-semibold text-white">{{ $greeting }}, {{ Auth::user()->name }}</span>
                        <span aria-hidden="true" class="hidden text-white/30 sm:inline">/</span>
                        <span>{{ $tenant->name }}</span>
                        <span aria-hidden="true" class="hidden text-white/30 sm:inline">/</span>
                        <a href="{{ route('subscription.index') }}" class="font-medium text-white/85 hover:text-white">
                            {{ $licenseLabel }}
                        </a>
                    </div>

                    <h1 class="mt-7 max-w-3xl text-4xl font-semibold leading-tight tracking-tight text-white sm:text-5xl">
                        Heute zuerst.
                    </h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-white/72 sm:text-lg">
                        Clubano zeigt dir den nächsten sinnvollen Schritt, bevor du suchen musst.
                    </p>

                    <div class="mt-8 max-w-3xl rounded-xl border border-white/15 bg-white/8 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] backdrop-blur">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-white/50">{{ $clubMoment['eyebrow'] }}</div>
                                <div class="mt-3 flex items-start gap-3">
                                    <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-950">
                                        @if($clubMoment['icon'] === 'cake')
                                            <x-heroicon-o-cake class="h-5 w-5" />
                                        @elseif($clubMoment['icon'] === 'sparkles')
                                            <x-heroicon-o-sparkles class="h-5 w-5" />
                                        @else
                                            <x-heroicon-o-heart class="h-5 w-5" />
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xl font-semibold leading-snug tracking-tight text-white">{{ $clubMoment['title'] }}</p>
                                        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/65">{{ $clubMoment['text'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ $clubMoment['route'] }}"
                               class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-white/18 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
                                {{ $clubMoment['action'] }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ $heroRoute }}"
                       class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-white px-5 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                        <x-heroicon-o-sparkles class="h-5 w-5" />
                        {{ $heroAction }}
                    </a>
                    <div class="flex flex-wrap gap-2">
                        @foreach($quickActions as $action)
                            <a href="{{ $action['route'] }}"
                               class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/8 px-4 text-sm font-semibold text-white transition hover:bg-white/14">
                                @if($action['icon'] === 'user-plus')
                                    <x-heroicon-o-user-plus class="h-5 w-5" />
                                @elseif($action['icon'] === 'users')
                                    <x-heroicon-o-users class="h-5 w-5" />
                                @elseif($action['icon'] === 'calendar')
                                    <x-heroicon-o-calendar class="h-5 w-5" />
                                @elseif($action['icon'] === 'archive-box')
                                    <x-heroicon-o-archive-box class="h-5 w-5" />
                                @else
                                    <x-heroicon-o-document-plus class="h-5 w-5" />
                                @endif
                                <span>{{ $action['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <aside class="self-stretch rounded-xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                <div class="flex h-full flex-col">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-white/55">Heute im Verein</div>
                            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-white">{{ $heroTitle }}</h2>
                        </div>
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-slate-950">
                            <x-heroicon-o-bolt class="h-6 w-6" />
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-white/72">{{ $heroMeta }}</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        @foreach($metrics as $metric)
                            <a href="{{ $metric['route'] }}" class="grid grid-cols-[42px,minmax(0,1fr),auto] items-center gap-3 rounded-lg border border-white/12 px-3 py-3 transition hover:bg-white/8">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/12 text-white">
                                    @if($metric['icon'] === 'users')
                                        <x-heroicon-o-users class="h-5 w-5" />
                                    @elseif($metric['icon'] === 'calendar-days')
                                        <x-heroicon-o-calendar-days class="h-5 w-5" />
                                    @elseif($metric['icon'] === 'clipboard-document-list')
                                        <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                                    @else
                                        <x-heroicon-o-archive-box class="h-5 w-5" />
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-white">{{ $metric['label'] }}</span>
                                    <span class="block truncate text-xs text-white/58">{{ $metric['hint'] }}</span>
                                </span>
                                <span class="text-2xl font-semibold tracking-tight text-white">{{ $metric['value'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-auto pt-6">
                        <div class="h-px bg-white/12"></div>
                        <div class="mt-4 flex items-center justify-between gap-3 text-sm text-white/65">
                            <span>{{ now()->translatedFormat('l, d. F Y') }}</span>
                            <span>{{ count($signals) }} Hinweise</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if($onTrial && $trialEndsAt)
        <section class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex sm:items-center sm:justify-between sm:gap-4">
            <div>
                <span class="font-semibold">Testphase aktiv:</span>
                Clubano kann bis zum {{ $trialEndsAt->format('d.m.Y') }} kostenlos genutzt werden.
            </div>
            <a href="{{ route('subscription.index') }}" class="mt-2 inline-flex font-semibold text-amber-950 hover:text-amber-800 sm:mt-0">
                Lizenz ansehen
            </a>
        </section>
    @endif

    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr),360px]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nächster Schritt</div>
                    @if($primaryNextStep)
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $primaryNextStep['title'] }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $primaryNextStep['meta'] }}</p>
                    @else
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Alles bereit</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Kein Engpass sichtbar. Du kannst direkt mit Mitgliederpflege, Terminen oder Kommunikation weitermachen.
                        </p>
                    @endif
                </div>

                @if($primaryNextStep && $primaryNextStep['route'])
                    <a href="{{ $primaryNextStep['route'] }}"
                       class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Jetzt machen
                    </a>
                @endif
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($metrics as $metric)
                    <a href="{{ $metric['route'] }}" class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                        <div class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</div>
                        <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ $metric['value'] }}</div>
                        <div class="mt-1 text-sm leading-5 text-slate-500">{{ $metric['hint'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-950">Hinweise</h2>
                <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ count($signals) }}</span>
            </div>

            <div class="mt-4 divide-y divide-slate-100">
                @foreach($signals as $signal)
                    <a href="{{ $signal['route'] }}" class="block py-3 first:pt-0 last:pb-0">
                        <div class="text-sm font-semibold text-slate-900">{{ $signal['label'] }}</div>
                        <div class="mt-1 text-sm leading-5 text-slate-500">{{ $signal['text'] }}</div>
                    </a>
                @endforeach
            </div>
        </aside>
    </section>

    <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr),420px]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Nächste Termine</h2>
                    <p class="mt-1 text-sm text-slate-500">Die kommenden sieben Tage in Reihenfolge.</p>
                </div>
                <a href="{{ route('events.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                    Alle Termine
                </a>
            </div>

            <div class="mt-5 divide-y divide-slate-100">
                @forelse($timeline as $event)
                    <a href="{{ route('events.show', $event) }}" class="grid gap-3 py-4 first:pt-0 last:pb-0 sm:grid-cols-[150px,minmax(0,1fr),96px] sm:items-center">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ \Carbon\Carbon::parse($event->start)->translatedFormat('D, d.m.') }}
                            <span class="block font-normal text-slate-500">{{ \Carbon\Carbon::parse($event->start)->format('H:i') }} Uhr</span>
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-base font-semibold text-slate-950">{{ $event->title }}</div>
                            <div class="mt-1 truncate text-sm text-slate-500">{{ $event->location ?: 'Ort folgt' }}</div>
                        </div>
                        <div class="text-left text-xs font-semibold text-slate-500 sm:text-right">
                            {{ $event->is_public ? 'Öffentlich' : 'Intern' }}
                        </div>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                        Keine Termine in den nächsten sieben Tagen.
                    </div>
                @endforelse
            </div>
        </div>

        @if(isset($onboarding) && !$onboarding['isComplete'])
            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Startcenter</div>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">
                            {{ $onboarding['completedCount'] }}/{{ $onboarding['totalCount'] }} erledigt
                        </h2>
                    </div>
                    <div class="text-2xl font-semibold text-slate-950">{{ $onboarding['progressPercent'] }}%</div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-slate-950" style="width: {{ $onboarding['progressPercent'] }}%"></div>
                </div>

                <div class="mt-5 divide-y divide-slate-100">
                    @foreach($onboarding['steps'] as $step)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $step['title'] }}</div>
                                    <p class="mt-1 text-sm leading-5 text-slate-500">{{ $step['description'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold {{ $step['completed'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $step['completed'] ? 'Fertig' : 'Offen' }}
                                </span>
                            </div>

                            @if(!$step['completed'] && $step['route'])
                                <a href="{{ $step['route'] }}" class="mt-3 inline-flex text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                                    Öffnen
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </aside>
        @else
            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">Verein im Blick</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Der Grundaufbau ist erledigt. Nutze die Schnellaktionen oben für die tägliche Arbeit.
                </p>
            </aside>
        @endif
    </section>

    <details class="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <summary class="flex cursor-pointer list-none flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Entwicklung im Verein</h2>
                <p class="mt-1 text-sm text-slate-500">Mitgliederbewegung, Geburtstage, Jubiläen und längerfristige Entwicklung.</p>
            </div>
            <span class="inline-flex w-fit rounded-md bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 group-open:bg-slate-950 group-open:text-white">
                Details anzeigen
            </span>
        </summary>

        <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.3fr),minmax(320px,0.7fr)]">
            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-base font-semibold text-slate-950">Mitgliederentwicklung</h3>
                <div class="mt-4">
                    @livewire('dashboard-member-chart')
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-base font-semibold text-slate-950">Bewegungen</h3>
                <div class="mt-4">
                    @livewire('dashboard-member-stats')
                </div>
            </section>
        </div>
    </details>
</div>
@endsection

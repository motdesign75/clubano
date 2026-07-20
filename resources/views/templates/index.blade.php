@extends('layouts.app')

@section('content')
@php
    $templateCount = $templates->count();
    $mailTemplateCount = $templates->filter(fn ($template) => $template->supportsMail())->count();
    $letterTemplateCount = $templates->filter(fn ($template) => $template->supportsLetter())->count();

    $typeSections = [
        \App\Models\Template::TYPE_MAIL => [
            'title' => 'Mail',
            'description' => 'Vorlagen nur fuer E-Mails.',
            'badge' => 'bg-blue-100 text-blue-800',
        ],
        \App\Models\Template::TYPE_LETTER => [
            'title' => 'Brief',
            'description' => 'Vorlagen nur fuer Briefe und Ausdrucke.',
            'badge' => 'bg-amber-100 text-amber-800',
        ],
        \App\Models\Template::TYPE_MAIL_AND_LETTER => [
            'title' => 'Mail und Brief',
            'description' => 'Eine Vorlage fuer beide Wege.',
            'badge' => 'bg-emerald-100 text-emerald-800',
        ],
        \App\Models\Template::TYPE_PDF => [
            'title' => 'PDF und Dokumente',
            'description' => 'Vorlagen fuer Dokumente ohne direkten Versand.',
            'badge' => 'bg-violet-100 text-violet-800',
        ],
    ];

    $groupedTemplates = collect($typeSections)
        ->map(function ($meta, $type) use ($templates) {
            return [
                'meta' => $meta,
                'items' => $templates->where('type', $type)->values(),
            ];
        });

    $actionCards = [
        [
            'title' => 'Mail senden',
            'description' => 'Eine Vorlage waehlen und sofort an Mitglieder, Kontakte oder freie E-Mail-Adressen senden.',
            'route' => route('mail.create'),
            'tone' => 'bg-slate-950 text-white hover:bg-slate-900',
            'primary' => true,
        ],
        [
            'title' => 'Brief erstellen',
            'description' => 'Eine Briefvorlage nutzen und als PDF vorbereiten.',
            'route' => route('letters.create'),
            'tone' => 'bg-white text-slate-900 ring-1 ring-slate-200 hover:bg-slate-50',
            'primary' => false,
        ],
        [
            'title' => 'Neue Vorlage',
            'description' => 'Eine neue Vorlage fuer Mail, Brief oder Dokument anlegen.',
            'route' => route('templates.create'),
            'tone' => 'bg-white text-slate-900 ring-1 ring-slate-200 hover:bg-slate-50',
            'primary' => false,
        ],
        [
            'title' => 'Versand ansehen',
            'description' => 'Nachschauen, was zuletzt verschickt oder gedruckt wurde.',
            'route' => route('templates.dispatch-log'),
            'tone' => 'bg-white text-slate-900 ring-1 ring-slate-200 hover:bg-slate-50',
            'primary' => false,
        ],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Vorlagen</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Kommunikation ohne Umwege vorbereiten</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Vorlagen bauen, direkt senden und später nachvollziehen, was bereits rausgegangen ist.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                <div class="font-semibold text-white">{{ $templateCount }} Vorlagen</div>
                <div class="mt-0.5 text-xs text-slate-300">{{ $mailTemplateCount }} mailfaehig, {{ $letterTemplateCount }} briefnutzbar</div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 lg:grid-cols-[1.15fr_0.85fr]">
            @php($primaryAction = collect($actionCards)->firstWhere('primary', true))
            <a href="{{ $primaryAction['route'] }}"
               class="rounded-3xl px-5 py-5 transition {{ $primaryAction['tone'] }}">
                <div class="text-lg font-semibold">{{ $primaryAction['title'] }}</div>
                <p class="mt-2 max-w-xl text-sm leading-6 text-white/80">
                    {{ $primaryAction['description'] }}
                </p>
                <div class="mt-5 text-sm font-semibold">
                    Direkt starten →
                </div>
            </a>

            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                @foreach(collect($actionCards)->reject(fn ($card) => $card['primary']) as $card)
                    <a href="{{ $card['route'] }}"
                       class="rounded-3xl px-5 py-4 transition {{ $card['tone'] }}">
                        <div class="text-base font-semibold">{{ $card['title'] }}</div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ $card['description'] }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(($recentDispatches ?? collect())->isNotEmpty())
        <details x-data="{ channel: 'all', query: '' }" class="group rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Zuletzt passiert</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Die letzten Vorgänge</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $recentDispatches->count() }} Eintraege als Vorschau. Bei Bedarf aufklappen oder direkt ins ganze Protokoll springen.
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <a href="{{ route('templates.dispatch-log') }}"
                       class="text-sm font-semibold text-indigo-700 hover:text-indigo-800"
                       onclick="event.stopPropagation();">
                        Ganzes Protokoll
                    </a>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </summary>

            <div class="mt-5">
                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="inline-flex flex-wrap gap-2 rounded-2xl bg-white p-1 ring-1 ring-slate-200">
                        <button type="button"
                                @click="channel = 'all'"
                                :class="channel === 'all' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                class="rounded-xl px-3 py-2 text-sm font-semibold transition">
                            Alle
                        </button>
                        <button type="button"
                                @click="channel = 'mail'"
                                :class="channel === 'mail' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                class="rounded-xl px-3 py-2 text-sm font-semibold transition">
                            Mail
                        </button>
                        <button type="button"
                                @click="channel = 'letter'"
                                :class="channel === 'letter' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                class="rounded-xl px-3 py-2 text-sm font-semibold transition">
                            Brief
                        </button>
                    </div>

                    <div class="w-full sm:max-w-xs">
                        <label for="dispatch-search" class="sr-only">Verlauf durchsuchen</label>
                        <input id="dispatch-search"
                               x-model.trim="query"
                               type="search"
                               placeholder="Empfänger oder Vorlage suchen"
                               class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                @foreach($recentDispatches as $dispatch)
                    <div x-data='{
                            entryChannel: @json($dispatch->channel === "mail" ? "mail" : "letter"),
                            entrySearch: @json(\Illuminate\Support\Str::lower(trim(($dispatch->recipient_name ?? "") . " " . ($dispatch->recipient_reference ?? "") . " " . ($dispatch->template?->name ?? ""))))
                        }'
                         x-show="(channel === 'all' || channel === entryChannel) && (query === '' || entrySearch.includes(query.toLowerCase()))"
                         class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $dispatch->channel === 'mail' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $dispatch->channel === 'mail' ? 'Mail' : 'Brief' }}
                                </span>
                                <div class="text-sm font-semibold text-slate-900">{{ $dispatch->recipient_name }}</div>
                            </div>
                            <div class="mt-1 text-sm text-slate-500">{{ $dispatch->template?->name ?? 'Ohne Vorlage' }}</div>
                            @if($dispatch->recipient_reference)
                                <div class="mt-1 text-xs text-slate-500">{{ $dispatch->recipient_reference }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-xs text-slate-500 sm:text-right">
                            <div>{{ optional($dispatch->dispatched_at)->format('d.m.Y') }}</div>
                            <div class="mt-1">{{ optional($dispatch->dispatched_at)->format('H:i') }}</div>
                        </div>
                    </div>
                @endforeach
                </div>

                @if($recentDispatches->count() > 5)
                    <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-500">
                        Im grossen Versandprotokoll findest du weitere {{ $recentDispatches->count() - 5 }} Eintraege.
                    </div>
                @endif
            </div>
        </details>
    @endif

    <section class="space-y-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Deine Vorlagen</h2>
            <p class="mt-1 text-sm text-slate-500">Alle Vorlagen sind nach ihrem Zweck sortiert. So musst du nicht raten, wo du anfangen sollst.</p>
        </div>

        @if($templates->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                <div class="mx-auto max-w-md">
                    <h3 class="text-xl font-semibold text-slate-900">Noch keine Vorlagen vorhanden</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500">
                        Lege zuerst eine Vorlage an. Danach kannst du sie direkt fuer Mail oder Brief verwenden.
                    </p>
                    <a href="{{ route('templates.create') }}"
                       class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        Erste Vorlage anlegen
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-5">
                @foreach($groupedTemplates as $type => $group)
                    @continue($group['items']->isEmpty())

                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $group['meta']['badge'] }}">
                                    {{ $group['meta']['title'] }}
                                </div>
                                <p class="mt-3 text-sm text-slate-500">{{ $group['meta']['description'] }}</p>
                            </div>
                            <div class="text-sm font-semibold text-slate-500">
                                {{ $group['items']->count() }} Vorlage{{ $group['items']->count() === 1 ? '' : 'n' }}
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 xl:grid-cols-2">
                            @foreach($group['items'] as $t)
                                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-lg font-semibold text-slate-900">{{ $t->name }}</h3>
                                            @if($t->subject)
                                                <p class="mt-1 text-sm text-slate-500">{{ $t->subject }}</p>
                                            @endif
                                        </div>

                                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $typeSections[$t->type]['badge'] }}">
                                            {{ $t->typeLabel() }}
                                        </span>
                                    </div>

                                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                        @if($t->supportsMail())
                                            <a href="{{ route('mail.create', ['template' => $t->id]) }}"
                                               class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                                Als Mail nutzen
                                            </a>
                                        @endif

                                        @if($t->supportsLetter())
                                            <a href="{{ route('letters.create', ['template' => $t->id]) }}"
                                               class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                                Als Brief nutzen
                                            </a>
                                        @endif

                                        <a href="{{ route('templates.edit', $t->id) }}"
                                           class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Bearbeiten
                                        </a>

                                        <a href="{{ route('templates.preview', $t->id) }}"
                                           class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Vorschau
                                        </a>
                                    </div>

                                    <form method="POST" action="{{ route('templates.destroy', $t->id) }}" class="mt-3" onsubmit="return confirm('Vorlage löschen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            Loeschen
                                        </button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection

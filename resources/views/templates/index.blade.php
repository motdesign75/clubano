@extends('layouts.app')

@section('content')
@php
    $canManageTemplates = auth()->user()?->isStaff() ?? false;
    $templatesCollection = $templates->getCollection();
    $filteredTemplateCount = $templates->total();
    $hasActiveFilters = filled($search) || filled($type);

    $actionCards = [
        [
            'title' => 'Mail senden',
            'description' => 'Eine Vorlage wählen und sofort an Mitglieder, Kontakte oder freie E-Mail-Adressen senden.',
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
            'description' => 'Eine neue Vorlage für Mail, Brief oder Dokument anlegen.',
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

    $badgeClasses = [
        \App\Models\Template::TYPE_MAIL => 'bg-blue-100 text-blue-800',
        \App\Models\Template::TYPE_LETTER => 'bg-amber-100 text-amber-800',
        \App\Models\Template::TYPE_MAIL_AND_LETTER => 'bg-emerald-100 text-emerald-800',
        \App\Models\Template::TYPE_PDF => 'bg-violet-100 text-violet-800',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Vorlagen</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Kommunikation ohne Umwege vorbereiten</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Vorlagen bauen, direkt senden und später nachvollziehen, was bereits rausgegangen ist.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                <div class="font-semibold text-white">{{ $templateTotalCount }} Vorlagen</div>
                <div class="mt-0.5 text-xs text-slate-300">{{ $mailTemplateCount }} mailfähig, {{ $letterTemplateCount }} briefnutzbar</div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 lg:grid-cols-4">
            @foreach($actionCards as $card)
                <a href="{{ $card['route'] }}"
                   class="rounded-2xl px-5 py-4 transition {{ $card['primary'] ? 'lg:col-span-1 ' : '' }}{{ $card['tone'] }}">
                    <div class="text-base font-semibold">{{ $card['title'] }}</div>
                    <p class="mt-2 text-sm leading-6 {{ $card['primary'] ? 'text-white/80' : 'text-slate-600' }}">
                        {{ $card['description'] }}
                    </p>
                </a>
            @endforeach
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(($recentDispatches ?? collect())->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Zuletzt passiert</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Die letzten Vorgänge</h2>
                </div>
                <a href="{{ route('templates.dispatch-log') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">
                    Ganzes Protokoll
                </a>
            </div>

            <div class="mt-4 divide-y divide-slate-100">
                @foreach($recentDispatches as $dispatch)
                    <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $dispatch->channel === 'mail' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $dispatch->channel === 'mail' ? 'Mail' : 'Brief' }}
                                </span>
                                <div class="font-semibold text-slate-900">{{ $dispatch->recipient_name }}</div>
                            </div>
                            <div class="mt-1 text-sm text-slate-500">{{ $dispatch->template?->name ?? 'Ohne Vorlage' }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-slate-500 sm:text-right">
                            {{ optional($dispatch->dispatched_at)->format('d.m.Y H:i') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-3">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Vorlagen gesamt</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $templateTotalCount }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b-0 sm:border-r">
            <div class="text-sm font-medium text-slate-500">Mailfähig</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $mailTemplateCount }}</div>
        </div>
        <div class="px-5 py-4">
            <div class="text-sm font-medium text-slate-500">Aktueller Ausschnitt</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $filteredTemplateCount }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
        <form method="GET" action="{{ route('templates.index') }}" class="grid gap-3 lg:grid-cols-4 lg:items-end">
            <div class="lg:col-span-2">
                <label for="search" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Suche</label>
                <input id="search" name="search" type="search" value="{{ $search }}"
                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                       placeholder="Name, Betreff oder Inhalt">
            </div>

            <div>
                <label for="type" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Typ</label>
                <select id="type" name="type" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    <option value="">Alle Typen</option>
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Filtern
                </button>
                @if($hasActiveFilters)
                    <a href="{{ route('templates.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>
    </section>

    @if($templatesCollection->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <div class="mx-auto max-w-md">
                <h2 class="text-xl font-semibold text-slate-900">{{ $hasActiveFilters ? 'Keine passenden Vorlagen gefunden' : 'Noch keine Vorlagen vorhanden' }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    {{ $hasActiveFilters
                        ? 'Passe Suche oder Typfilter an, um den Ausschnitt wieder zu erweitern.'
                        : 'Lege zuerst eine Vorlage an. Danach kannst du sie direkt für Mail oder Brief verwenden.' }}
                </p>
                @if($canManageTemplates && ! $hasActiveFilters)
                    <a href="{{ route('templates.create') }}"
                       class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        Erste Vorlage anlegen
                    </a>
                @endif
            </div>
        </section>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Vorlagenliste</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $templates->firstItem() }}-{{ $templates->lastItem() }} von {{ $filteredTemplateCount }} Vorlagen.
                    </p>
                </div>
                <div class="text-sm text-slate-500">Sortiert nach Name</div>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($templatesCollection as $template)
                    <article class="px-5 py-4 transition hover:bg-slate-50/70 sm:px-6">
                        <div class="grid gap-4 lg:grid-cols-12 lg:items-center">
                            <div class="min-w-0 lg:col-span-5">
                                <a href="{{ route('templates.preview', $template) }}" class="block truncate text-base font-semibold text-slate-950 transition hover:text-indigo-700">
                                    {{ $template->name }}
                                </a>
                                <div class="mt-1 truncate text-sm text-slate-500">
                                    {{ $template->subject ?: 'Ohne Betreff' }}
                                </div>
                            </div>

                            <div class="lg:col-span-2">
                                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses[$template->type] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $template->typeLabel() }}
                                </span>
                            </div>

                            <div class="text-sm text-slate-500 lg:col-span-2">
                                {{ $template->updated_at?->format('d.m.Y') ?? '—' }}
                            </div>

                            <div class="flex flex-wrap gap-2 lg:col-span-3 lg:justify-end">
                                @if($template->supportsMail())
                                    <a href="{{ route('mail.create', ['template' => $template->id]) }}"
                                       class="inline-flex items-center justify-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                        Mail
                                    </a>
                                @endif

                                @if($template->supportsLetter())
                                    <a href="{{ route('letters.create', ['template' => $template->id]) }}"
                                       class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Brief
                                    </a>
                                @endif

                                @if($canManageTemplates)
                                    <a href="{{ route('templates.edit', $template) }}"
                                       class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                        Bearbeiten
                                    </a>
                                @endif

                                <a href="{{ route('templates.preview', $template) }}"
                                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Vorschau
                                </a>

                                @if($canManageTemplates)
                                    <form method="POST" action="{{ route('templates.destroy', $template) }}" onsubmit="return confirm('Vorlage wirklich löschen?');" class="inline-flex">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                            Löschen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($templates->hasPages())
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    {{ $templates->links() }}
                </div>
            @endif
        </section>
    @endif
</div>
@endsection

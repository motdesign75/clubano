@extends('layouts.app')

@section('title', 'Protokoll')

@section('content')
@php
    $canManageProtocols = auth()->user()?->canManageProtocols() ?? false;
    $attachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];

    if (is_string($attachments)) {
        $attachments = [$attachments];
    }
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokoll</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $protocol->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Ein klarer Blick auf Teilnehmer, Beschlüsse und das, was wirklich festgehalten wurde.
                </p>
            </div>

            @if($canManageProtocols)
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    <a href="{{ route('protocols.edit', $protocol) }}"
                       class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                        Bearbeiten
                    </a>
                    <a href="{{ route('protocols.mail.form', $protocol) }}"
                       class="inline-flex items-center justify-center rounded-full bg-white/10 px-5 py-3 text-sm font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/15">
                        Versenden
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="grid overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
        <div class="border-b border-slate-100 px-5 py-4 sm:border-r xl:border-b-0">
            <div class="text-sm font-medium text-slate-500">Datum</div>
            <div class="mt-2 text-xl font-semibold text-slate-950">{{ $protocol->created_at->format('d.m.Y') }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-b sm:border-r xl:border-b-0">
            <div class="text-sm font-medium text-slate-500">Typ</div>
            <div class="mt-2 text-xl font-semibold text-slate-950">{{ $protocol->type }}</div>
        </div>
        <div class="border-b border-slate-100 px-5 py-4 sm:border-r sm:border-b-0 xl:border-r">
            <div class="text-sm font-medium text-slate-500">Erstellt von</div>
            <div class="mt-2 text-xl font-semibold text-slate-950">{{ $protocol->user->name ?? 'Unbekannt' }}</div>
        </div>
        <div class="px-5 py-4">
            <div class="text-sm font-medium text-slate-500">Teilnehmer</div>
            <div class="mt-2 text-xl font-semibold text-slate-950">{{ $protocol->participants?->count() ?? 0 }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Erstellt:</span>
                {{ $protocol->created_at->format('d.m.Y H:i') }}
                @if($protocol->user)
                    · von {{ $protocol->user->name }}
                @endif
            </div>

            <div class="text-sm text-slate-600">
                <span class="font-semibold text-slate-900">Zuletzt bearbeitet:</span>
                {{ $protocol->updated_at->format('d.m.Y H:i') }}
                @if($protocol->updated_at->ne($protocol->created_at))
                    <span class="ml-2 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        aktualisiert
                    </span>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Protokoll</div>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Der eigentliche Inhalt</h2>
                    </div>
                </div>

                <div class="mt-6 prose prose-slate max-w-none prose-headings:font-semibold prose-p:leading-7">
                    {!! $protocol->content !!}
                </div>
            </article>

            @if($protocol->resolutions)
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Beschlüsse</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Was entschieden wurde</h2>
                    <div class="mt-4 border-t border-slate-100 pt-4 whitespace-pre-line text-sm leading-7 text-slate-800">
                        {{ $protocol->resolutions }}
                    </div>
                </article>
            @endif

            @if($protocol->next_meeting)
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Nächstes Treffen</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Was als Nächstes kommt</h2>
                    <div class="mt-4 border-t border-slate-100 pt-4 whitespace-pre-line text-sm leading-7 text-slate-800">
                        {{ $protocol->next_meeting }}
                    </div>
                </article>
            @endif
        </div>

        <aside class="space-y-6">
            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Rahmendaten</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Schnell lesen</h2>

                <div class="mt-5 space-y-4 text-sm">
                    @if($protocol->location)
                        <div>
                            <div class="font-semibold text-slate-900">Ort</div>
                            <div class="mt-1 text-slate-600">{{ $protocol->location }}</div>
                        </div>
                    @endif

                    @if($protocol->start_time)
                        <div>
                            <div class="font-semibold text-slate-900">Beginn</div>
                            <div class="mt-1 text-slate-600">{{ $protocol->start_time }}</div>
                        </div>
                    @endif

                    @if($protocol->end_time)
                        <div>
                            <div class="font-semibold text-slate-900">Ende</div>
                            <div class="mt-1 text-slate-600">{{ $protocol->end_time }}</div>
                        </div>
                    @endif
                </div>
            </article>

            @if($protocol->participants && $protocol->participants->count() > 0)
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Teilnehmer</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Wer dabei war</h2>

                    <div class="mt-5 space-y-2">
                        @foreach ($protocol->participants as $member)
                            <div class="border-t border-slate-100 py-3 text-sm font-medium text-slate-800 first:border-t-0 first:pt-0 last:pb-0">
                                {{ $member->full_name }}
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif

            @if(!empty($attachments))
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Anhänge</div>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Was mit dazu gehört</h2>

                    <div class="mt-5 space-y-2">
                        @foreach($attachments as $file)
                            <a href="{{ route('protocols.attachments.show', ['protocol' => $protocol, 'index' => $loop->index]) }}"
                               target="_blank"
                               class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 text-sm font-medium text-slate-700 transition first:border-t-0 first:pt-0 last:pb-0 hover:text-slate-950">
                                <span class="truncate">{{ basename($file) }}</span>
                                <span class="shrink-0 text-xs font-semibold text-indigo-700">Öffnen</span>
                            </a>
                        @endforeach
                    </div>
                </article>
            @endif

            <article class="rounded-2xl border border-slate-200 bg-white p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Versandhistorie</div>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">An wen es gegangen ist</h2>

                <div class="mt-5 space-y-3">
                    @forelse(($dispatchLogs ?? collect()) as $log)
                        <div class="border-t border-slate-100 py-3 first:border-t-0 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold text-slate-900">{{ $log->recipient_name }}</div>
                                    <div class="mt-1 text-sm text-slate-500">
                                        {{ $log->recipient_reference ?: 'Keine Adresse hinterlegt' }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                        Mail
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ $log->recipient_type === 'contact' ? 'Kontakt' : ($log->recipient_type === 'member' ? 'Mitglied' : 'Freie Adresse') }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 text-sm text-slate-600">
                                {{ optional($log->dispatched_at)->format('d.m.Y H:i') ?: '–' }}
                                @if($log->creator)
                                    · von {{ $log->creator->name }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="border-t border-slate-100 py-4 text-sm text-slate-500">
                            Dieses Protokoll wurde bisher noch nicht per Mail versendet.
                        </div>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('protocols.index') }}"
           class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Zurück zur Übersicht
        </a>

        @if($canManageProtocols)
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('protocols.edit', $protocol) }}"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Bearbeiten
                </a>
                <a href="{{ route('protocols.mail.form', $protocol) }}"
                   class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Versenden
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

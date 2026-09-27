@extends('layouts.app')

@section('content')
@php
    $hasFilter = filled($status);
    $badgeClasses = [
        \App\Models\AppNewsItem::STATUS_DRAFT => 'bg-amber-100 text-amber-800',
        \App\Models\AppNewsItem::STATUS_PUBLISHED => 'bg-emerald-100 text-emerald-800',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Kommunikation</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">App-News veröffentlichen</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Kurze, offizielle Mitteilungen für Mein Clubano. Ohne Chat, klar getrennt vom großen Clubano-Zugang.
                </p>
            </div>

            <a href="{{ route('app-news.create') }}"
               class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                Neue App-News
            </a>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Live in der App</div>
                <div class="mt-2 text-2xl font-semibold">{{ $publishedCount }}</div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Entwürfe</div>
                <div class="mt-2 text-2xl font-semibold">{{ $draftCount }}</div>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 sm:px-6">
        <form method="GET" action="{{ route('app-news.index') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <div>
                <label for="status" class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                    <option value="">Alle App-News</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Filtern
                </button>
                @if($hasFilter)
                    <a href="{{ route('app-news.index') }}" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Zurücksetzen
                    </a>
                @endif
            </div>
        </form>
    </section>

    @if($items->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <h2 class="text-xl font-semibold text-slate-900">Noch keine App-News vorhanden</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-500">
                Lege die erste Mitteilung an. Sobald sie veröffentlicht ist, erscheint sie im News-Bereich der App.
            </p>
            <a href="{{ route('app-news.create') }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                Erste App-News anlegen
            </a>
        </section>
    @else
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="divide-y divide-slate-100">
                @foreach($items as $item)
                    <article class="px-5 py-5 transition hover:bg-slate-50/70 sm:px-6">
                        <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
                            <div class="min-w-0 lg:col-span-7">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClasses[$item->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $item->statusLabel() }}
                                    </span>
                                    @if($item->push_enabled)
                                        <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                            Push vorgemerkt
                                        </span>
                                    @endif
                                </div>
                                <h2 class="mt-3 text-xl font-semibold tracking-tight text-slate-950">{{ $item->title }}</h2>
                                @if($item->teaser)
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $item->teaser }}</p>
                                @endif
                            </div>

                            <div class="text-sm text-slate-500 lg:col-span-3">
                                <div class="font-semibold text-slate-700">Veröffentlichung</div>
                                <div class="mt-1">{{ $item->published_at?->format('d.m.Y H:i') ?? 'Noch nicht veröffentlicht' }}</div>
                                <div class="mt-3 text-xs text-slate-400">Zuletzt bearbeitet {{ $item->updated_at?->format('d.m.Y H:i') }}</div>
                            </div>

                            <div class="flex flex-wrap gap-2 lg:col-span-2 lg:justify-end">
                                <a href="{{ route('app-news.edit', $item) }}"
                                   class="inline-flex items-center justify-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Bearbeiten
                                </a>
                                <form method="POST" action="{{ route('app-news.destroy', $item) }}" onsubmit="return confirm('Diese App-News wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{ $items->links() }}
    @endif
</div>
@endsection

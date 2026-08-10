@extends('layouts.app')

@section('title', 'Betreiber-Mitteilungen')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-blue-600">Clubano Betrieb</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">Betreiber-Mitteilungen</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Produktupdates und wichtige Betreiberhinweise an Vereinsadmins senden. Mitglieder werden hier bewusst nicht angeschrieben.
            </p>
        </div>
        <a href="{{ route('admin.announcements.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
            Neue Mitteilung
        </a>
    </div>

    @if(session('success'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-950">Versandprotokoll</h2>
            <p class="mt-1 text-sm text-slate-500">Nachvollziehbar, wann welche Betreiber-Mitteilung erstellt und versendet wurde.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($announcements as $announcement)
                <article class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_180px_160px_140px] lg:items-center">
                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold text-slate-950">{{ $announcement->subject }}</div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ $announcement->recipient_summary['filter'] ?? 'Empfängerfilter' }} · erstellt {{ $announcement->created_at->format('d.m.Y H:i') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Status</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800">{{ str_replace('_', ' ', $announcement->status) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Zustellungen</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800">{{ $announcement->deliveries_count }}</div>
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $announcement->sent_at ? $announcement->sent_at->format('d.m.Y H:i') : 'nicht versendet' }}
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center text-sm text-slate-500">
                    Noch keine Betreiber-Mitteilungen versendet.
                </div>
            @endforelse
        </div>
    </section>

    <div class="mt-6">
        {{ $announcements->links() }}
    </div>
</div>
@endsection

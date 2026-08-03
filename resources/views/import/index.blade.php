@extends('layouts.app')

@section('title', 'Import')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="max-w-3xl">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Datenübernahme</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Import-Cockpit</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                Mitglieder und Kontakte sauber übernehmen, vor dem Import prüfen und bei Bedarf rückgängig machen.
            </p>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <section class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('import.mitglieder') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-950 text-white">
                    <x-heroicon-o-users class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Mitglieder importieren</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Bestandsmitglieder aus WISO, Excel oder anderen Vereinsprogrammen übernehmen.</p>
                    <div class="mt-4 text-sm font-semibold text-slate-900">Import starten</div>
                </div>
            </div>
        </a>

        <a href="{{ route('import.kontakte') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <x-heroicon-o-identification class="h-6 w-6" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Kontakte importieren</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Sponsoren, Lieferanten, Behörden, Presse, Eltern oder Partner zentral erfassen.</p>
                    <div class="mt-4 text-sm font-semibold text-emerald-800">Import starten</div>
                </div>
            </div>
        </a>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-950">Letzte Importe</h2>
            <p class="mt-1 text-sm text-slate-500">Hier siehst du, was zuletzt übernommen wurde.</p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($recentImportRuns as $run)
                <div class="px-5 py-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-slate-950">{{ $run->type_label }}</span>
                                <span class="text-sm text-slate-500">{{ $run->imported_count }} importiert</span>
                                @if(($run->skipped_count ?? 0) > 0)
                                    <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $run->skipped_count }} übersprungen</span>
                                @endif
                                @if($run->status === 'undone')
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Rückgängig gemacht</span>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $run->created_at->format('d.m.Y H:i') }}
                                @if($run->creator)
                                    · {{ $run->creator->name }}
                                @endif
                            </div>
                        </div>

                        @if($run->isUndoable())
                            <form method="POST" action="{{ route('import.mitglieder.undo', $run) }}" onsubmit="return confirm('Diesen Import wirklich rückgängig machen?')">
                                @csrf
                                <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                    Rückgängig machen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">Noch keine Importe vorhanden.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection

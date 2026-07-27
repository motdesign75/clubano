@extends('layouts.app')

@section('title', 'Spenden')

@section('content')
@php
    $currentYear = now()->year;
    $statusOptions = [
        'all' => 'Alle',
        'draft' => 'Entwurf',
        'issued' => 'Erstellt',
        'sent' => 'Versendet',
        'cancelled' => 'Storniert',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Spenden</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Zuwendungen {{ $year }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Erfasse Geldspenden ruhig, nachvollziehbar und mit klarer Vorbereitung für die Zuwendungsbestätigung.
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('donations.settings') }}" class="inline-flex items-center justify-center rounded-full border border-white/15 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/10">
                    Einstellungen
                </a>
                <a href="{{ route('donations.create') }}" class="inline-flex items-center justify-center rounded-full bg-white px-4 py-2.5 text-sm font-medium text-slate-950 shadow-sm transition hover:bg-slate-100">
                    Neue Spende
                </a>
            </div>
        </div>
    </section>

    @if(! $readiness['can_issue'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="font-semibold">Zuwendungsbestätigungen sind noch gesperrt.</div>
                    <p class="mt-1">Spenden können erfasst werden. PDFs werden erst freigeschaltet, wenn der Freistellungsbescheid und die Pflichtangaben vollständig sind.</p>
                </div>
                <a href="{{ route('donations.settings') }}" class="inline-flex shrink-0 justify-center rounded-xl bg-amber-900 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Nachweis hinterlegen</a>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Spendensumme</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['total'], 2, ',', '.') }} €</div>
            <div class="mt-1 text-xs text-slate-500">Ohne stornierte Spenden</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Spenden</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $summary['count'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Im ausgewählten Jahr</div>
        </div>
        <div class="rounded-2xl border {{ $summary['drafts'] > 0 ? 'border-amber-200 bg-amber-50/70' : 'border-slate-200 bg-white' }} p-5 shadow-sm">
            <div class="text-sm font-medium {{ $summary['drafts'] > 0 ? 'text-amber-800' : 'text-slate-500' }}">Noch offen</div>
            <div class="mt-2 text-2xl font-semibold {{ $summary['drafts'] > 0 ? 'text-amber-900' : 'text-slate-950' }}">{{ $summary['drafts'] }}</div>
            <div class="mt-1 text-xs {{ $summary['drafts'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">Bestätigung noch nicht erstellt</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Bestätigungen</div>
            <div class="mt-2 text-2xl font-semibold text-slate-950">{{ $summary['issued'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Erstellt oder versendet</div>
        </div>
    </div>

    <form method="GET" action="{{ route('donations.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,10rem)_minmax(0,12rem)_auto_auto] lg:items-end">
            <div>
                <label for="search" class="mb-1 block text-sm font-medium text-slate-600">Suche</label>
                <input id="search" type="text" name="search" value="{{ $search }}" placeholder="Name, E-Mail, Zweck oder Nummer" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
            </div>
            <div>
                <label for="year" class="mb-1 block text-sm font-medium text-slate-600">Jahr</label>
                <select id="year" name="year" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @for($y = $currentYear + 1; $y >= $currentYear - 10; $y--)
                        <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-600">Status</label>
                <select id="status" name="status" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Anwenden</button>
            <a href="{{ route('donations.index') }}" class="inline-flex justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">Zurücksetzen</a>
        </div>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(0,1.5fr)_130px_140px_140px_140px] border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid">
            <div>Spender</div>
            <div>Datum</div>
            <div>Status</div>
            <div>Nummer</div>
            <div class="text-right">Betrag</div>
        </div>
        @forelse($donations as $donation)
            <a href="{{ route('donations.show', $donation) }}" class="grid gap-3 border-b border-slate-100 px-5 py-4 transition hover:bg-slate-50 lg:grid-cols-[minmax(0,1.5fr)_130px_140px_140px_140px] lg:items-center last:border-b-0">
                <div class="min-w-0">
                    <div class="truncate font-semibold text-slate-950">{{ $donation->donor_name }}</div>
                    <div class="mt-1 truncate text-sm text-slate-500">{{ $donation->purpose ?: 'Allgemeine Spende' }}</div>
                </div>
                <div class="text-sm text-slate-600">{{ $donation->donated_at->format('d.m.Y') }}</div>
                <div>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $donation->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($donation->status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                        {{ $donation->status_label }}
                    </span>
                </div>
                <div class="text-sm text-slate-500">{{ $donation->certificate_number ?: 'Noch offen' }}</div>
                <div class="font-semibold text-slate-950 lg:text-right">{{ number_format($donation->amount, 2, ',', '.') }} €</div>
            </a>
        @empty
            <div class="px-5 py-12 text-center">
                <div class="text-lg font-semibold text-slate-950">Noch keine Spenden erfasst</div>
                <p class="mt-2 text-sm text-slate-500">Starte mit der ersten Geldspende. Clubano führt dich durch die nötigen Angaben.</p>
                <a href="{{ route('donations.create') }}" class="mt-5 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Spende erfassen</a>
            </div>
        @endforelse
    </section>

    {{ $donations->links() }}
</div>
@endsection

@extends('layouts.app')

@section('title', 'Formulare')

@section('content')
@php
    $currentUser = auth()->user();
    $canManageForms = $currentUser?->canManageForms() ?? false;
    $activeFormsCount = $forms->where('is_active', true)->count();
    $totalSubmissionsCount = $forms->sum('submissions_count');
    $embedReadyCount = $forms->count();

    $typeLabels = [
        'general' => 'Allgemein',
        'contact' => 'Kontakt',
        'membership' => 'Beitritt',
        'event' => 'Anmeldung',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Formulare</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Formulare, die sofort nutzbar sind</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Erstellen, teilen und Antworten prüfen, ohne zwischen Link, Einbettung und Verwaltung springen zu müssen.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-200">
                    <div class="font-semibold text-white">{{ $activeFormsCount }} aktiv</div>
                    <div class="mt-0.5 text-xs text-slate-300">{{ $totalSubmissionsCount }} Antworten insgesamt</div>
                </div>

                @if($canManageForms)
                    <a href="{{ route('forms.create') }}"
                       class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-slate-100 sm:w-auto">
                        Neues Formular
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-[1.1fr_1.1fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Formulare gesamt</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $forms->count() }}</div>
        </div>
        <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="text-sm font-medium text-emerald-700">Aktiv</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ $activeFormsCount }}</div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm font-medium text-slate-500">Antworten gesamt</div>
            <div class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $totalSubmissionsCount }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ $embedReadyCount }} per Link oder Einbettung nutzbar</div>
        </div>
    </section>

    @if($forms->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
            <div class="mx-auto max-w-md">
                <h2 class="text-xl font-semibold text-slate-900">Noch keine Formulare vorhanden</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Lege dein erstes Formular an und stelle es direkt per Link oder Einbettung bereit.
                </p>
                @if($canManageForms)
                    <a href="{{ route('forms.create') }}"
                       class="mt-6 inline-flex items-center justify-center rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                        Erstes Formular erstellen
                    </a>
                @endif
            </div>
        </div>
    @else
        <section class="space-y-4 lg:hidden">
            @foreach($forms as $form)
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-slate-950">{{ $form->title }}</h2>
                            <div class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">/{{ $form->slug }}</div>
                        </div>

                        <span class="inline-flex shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $form->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                            {{ $form->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl bg-slate-50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Typ</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $typeLabels[$form->form_type] ?? $form->form_type }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Felder</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $form->fields_count }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-3 py-3">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Antworten</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $form->submissions_count }}</div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Bereit zum Teilen</div>
                        <div class="mt-2 break-all text-xs leading-5 text-slate-600">{{ route('forms.public.embed', $form->slug) }}</div>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <a href="{{ route('forms.submissions', $form) }}"
                           class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Antworten
                        </a>
                        @if($canManageForms)
                            <a href="{{ route('forms.edit', $form) }}"
                               class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Bearbeiten
                            </a>
                        @endif
                        <a href="{{ route('forms.public.show', $form->slug) }}" target="_blank"
                           class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Link oeffnen
                        </a>
                        @if($canManageForms)
                            <a href="{{ route('forms.edit', $form) }}#einbettung"
                               class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Einbetten
                            </a>
                        @endif
                    </div>

                    @if($canManageForms)
                        <form action="{{ route('forms.destroy', $form) }}" method="POST" class="mt-3" onsubmit="return confirm('Formular wirklich löschen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                Loeschen
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Formular</th>
                            <th class="px-6 py-4">Typ</th>
                            <th class="px-6 py-4">Felder</th>
                            <th class="px-6 py-4">Antworten</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Teilen</th>
                            <th class="px-6 py-4 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach($forms as $form)
                            <tr class="align-top transition hover:bg-slate-50/80">
                                <td class="px-6 py-5">
                                    <div class="font-semibold text-slate-950">{{ $form->title }}</div>
                                    <div class="mt-1 text-xs font-medium uppercase tracking-[0.16em] text-slate-400">/{{ $form->slug }}</div>
                                </td>
                                <td class="px-6 py-5">{{ $typeLabels[$form->form_type] ?? $form->form_type }}</td>
                                <td class="px-6 py-5 font-medium text-slate-900">{{ $form->fields_count }}</td>
                                <td class="px-6 py-5 font-medium text-slate-900">{{ $form->submissions_count }}</td>
                                <td class="px-6 py-5">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $form->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $form->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="max-w-xs break-all text-xs leading-5 text-slate-500">{{ route('forms.public.embed', $form->slug) }}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('forms.submissions', $form) }}"
                                           class="inline-flex items-center justify-center rounded-full bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                            Antworten
                                        </a>
                                        @if($canManageForms)
                                            <a href="{{ route('forms.edit', $form) }}"
                                               class="inline-flex items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                                Bearbeiten
                                            </a>
                                        @endif
                                        <a href="{{ route('forms.public.show', $form->slug) }}" target="_blank"
                                           class="inline-flex items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            Link
                                        </a>
                                        @if($canManageForms)
                                            <form action="{{ route('forms.destroy', $form) }}" method="POST" onsubmit="return confirm('Formular wirklich löschen?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                                    Loeschen
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection

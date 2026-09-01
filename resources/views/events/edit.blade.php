@extends('layouts.app')

@section('title', 'Termin bearbeiten')

@section('content')
@php
    $publicEventUrl = route('events.public.show', $event->id);
    $bookingUrl = $event->activeBookingForm ? route('forms.public.show', $event->activeBookingForm->slug) : null;
    $publicListUrl = route('events.public.index', $event->tenant->slug);
    $embedListUrl = route('events.public.embed', $event->tenant->slug);
    $selectedCategoryEmbed = $event->category ? $embedListUrl.'?category='.$event->category->slug : null;
    $bookingFields = $event->activeBookingForm?->fields?->sortBy('sort_order') ?? collect();
    $bookingCustomFields = $bookingFields->reject(fn ($field) => in_array($field->slug, $bookingSystemFieldSlugs, true))->values();
    $bookingStandardFields = $bookingFields->filter(fn ($field) => in_array($field->slug, $bookingSystemFieldSlugs, true))->values();
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-2xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">Event-Editor</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $event->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    Bearbeite hier nur den Termin selbst. Dienstplan und Teilnehmer sind eigene Arbeitsbereiche.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-4 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    Ansehen
                </a>
                <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-4 text-sm font-semibold text-white hover:bg-white/10">
                    Zum Kalender
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)] lg:items-start">
        <aside class="space-y-4 lg:sticky lg:top-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Arbeitsbereich</div>
                <h2 class="mt-2 text-base font-semibold text-slate-950">Termin bearbeiten</h2>

                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ $event->is_public ? 'Öffentlich' : 'Intern' }}</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Anmeldung</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ $event->booking_enabled ? 'Aktiv' : 'Aus' }}</div>
                    </div>
                </div>

                <button form="event-edit-form" type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    Speichern
                </button>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Ansehen
                    </a>
                    <a href="{{ route('events.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Kalender
                    </a>
                </div>
            </section>

            <nav aria-label="Terminbereiche" class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                <a href="#termin" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>Termin</span>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-slate-400" />
                </a>
                <a href="#oeffentlich" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>Öffentliche Ausgabe</span>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-slate-400" />
                </a>
                <a href="#anmeldefelder" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>Anmeldefelder</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $bookingCustomFields->count() }}</span>
                </a>
                <a href="{{ route('events.schedule.manage', $event) }}" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>Dienstplan</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $eventShiftCount }}</span>
                </a>
                <a href="{{ route('events.participants.manage', $event) }}" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>Teilnehmer</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $participantCount }}</span>
                </a>
                <a href="#loeschen" class="flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                    <span>Löschen</span>
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-rose-300" />
                </a>
            </nav>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Verknüpft</div>
                <div class="mt-3 space-y-3">
                    <a href="{{ route('events.schedule.manage', $event) }}" class="group block rounded-xl border border-slate-200 bg-slate-50 p-3 hover:bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-950">Dienstplan</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ $eventShiftCount }}</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Schichten und Helfer planen.</p>
                    </a>

                    <a href="{{ route('events.participants.manage', $event) }}" class="group block rounded-xl border border-slate-200 bg-slate-50 p-3 hover:bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-950">Teilnehmer</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ $participantCount }}</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Listen, Zahlungen und Export.</p>
                    </a>
                </div>
            </section>
        </aside>

        <main class="min-w-0 space-y-6">
            <section id="termin" class="scroll-mt-6 space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Termin und Anmeldung</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Hier entsteht der Termin selbst: Inhalt, Zeit, Sichtbarkeit, Rückmeldung und Buchung.</p>
                </div>

                <form id="event-edit-form" action="{{ route('events.update', $event) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    @method('PUT')

                    @include('events.partials.form-fields', ['event' => $event])

                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Abbrechen
                        </a>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </section>

            <section id="oeffentlich" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Öffentliche Ausgabe</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                            Vorschau, Buchungslink und Einbettung liegen bewusst an einer Stelle.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $publicEventUrl }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Vorschau
                        </a>
                        @if($bookingUrl)
                            <a href="{{ route('forms.submissions', $event->activeBookingForm) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Anmeldungen
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-900">Öffentlicher Link</label>
                        <input type="text" readonly value="{{ $publicEventUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-900">Buchungslink</label>
                        <input type="text" readonly value="{{ $bookingUrl ?? 'Wird aktiv, sobald Anmeldung eingeschaltet ist.' }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-900">Veranstaltungsliste</label>
                        <input type="text" readonly value="{{ $publicListUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-900">Embed-Liste</label>
                        <input type="text" readonly value="{{ $selectedCategoryEmbed ?? $embedListUrl }}" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="text-sm font-semibold text-slate-900">Iframe-Code</label>
                    <textarea readonly rows="3" class="mt-2 w-full rounded-lg border-slate-300 bg-slate-50 text-sm">{{ '<iframe src="' . ($selectedCategoryEmbed ?? $embedListUrl) . '" width="100%" height="980" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>' }}</textarea>
                </div>
            </section>

            <section id="anmeldefelder" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Anmeldefelder</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                            Stelle eigene Fragen für genau diese Veranstaltung, zum Beispiel Essenswunsch, T-Shirt-Größe oder Abteilung.
                        </p>
                    </div>
                    <div class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                        {{ $bookingCustomFields->count() }} Zusatzfelder
                    </div>
                </div>

                @if(!$event->booking_enabled)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        Schalte oben zuerst die Anmeldung ein. Danach kannst du hier eigene Felder für die Buchung anlegen.
                    </div>
                @elseif(!$event->activeBookingForm)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        Speichere den Termin einmal. Clubano legt danach automatisch das Anmeldeformular an.
                    </div>
                @else
                    <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                        <div class="space-y-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Immer enthalten</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse($bookingStandardFields as $field)
                                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                            {{ $field->label }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-slate-500">Die Standardfelder werden beim Speichern der Anmeldung angelegt.</span>
                                    @endforelse
                                </div>
                            </div>

                            @forelse($bookingCustomFields as $field)
                                <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                        <div>
                                            <div class="font-semibold text-slate-950">{{ $field->label }}</div>
                                            <div class="mt-1 text-sm text-slate-500">
                                                {{ $bookingFieldTypes[$field->field_type] ?? $field->field_type }}
                                                @if($field->is_required)
                                                    <span class="ml-2 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">Pflichtfeld</span>
                                                @endif
                                            </div>
                                        </div>
                                        <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-slate-400" />
                                    </summary>

                                    <div class="mt-4 border-t border-slate-100 pt-4">
                                        <form method="POST" action="{{ route('events.booking-fields.update', [$event, $field]) }}" class="grid gap-4 md:grid-cols-2">
                                            @csrf
                                            @method('PUT')

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Feldname</label>
                                                <input type="text" name="label" value="{{ old('label', $field->label) }}" class="w-full rounded-lg border-slate-300 text-sm">
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Art</label>
                                                <select name="field_type" class="w-full rounded-lg border-slate-300 text-sm">
                                                    @foreach($bookingFieldTypes as $type => $label)
                                                        <option value="{{ $type }}" @selected(old('field_type', $field->field_type) === $type)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Interner Kurzname</label>
                                                <input type="text" name="slug" value="{{ old('slug', $field->slug) }}" class="w-full rounded-lg border-slate-300 text-sm">
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Platzhalter</label>
                                                <input type="text" name="placeholder" value="{{ old('placeholder', $field->placeholder) }}" class="w-full rounded-lg border-slate-300 text-sm">
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Antwortmöglichkeiten</label>
                                                <textarea name="options" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Eine Option pro Zeile">{{ old('options', str_replace('|', "\n", (string) $field->options)) }}</textarea>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Hilfetext</label>
                                                <textarea name="help_text" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ old('help_text', $field->help_text) }}</textarea>
                                            </div>

                                            <div class="flex items-center gap-2 md:col-span-2">
                                                <input id="required-{{ $field->id }}" type="checkbox" name="is_required" value="1" @checked(old('is_required', $field->is_required)) class="rounded border-slate-300 text-slate-950">
                                                <label for="required-{{ $field->id }}" class="text-sm font-semibold text-slate-700">Pflichtfeld</label>
                                            </div>

                                            <div class="md:col-span-2">
                                                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                                    Speichern
                                                </button>
                                            </div>
                                        </form>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                                <form method="POST" action="{{ route('events.booking-fields.move', [$event, $field]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hoch</button>
                                                </form>

                                                <form method="POST" action="{{ route('events.booking-fields.move', [$event, $field]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Runter</button>
                                                </form>

                                                <form method="POST" action="{{ route('events.booking-fields.destroy', [$event, $field]) }}" onsubmit="return confirm('Anmeldefeld wirklich löschen? Bereits gespeicherte Antworten bleiben in alten Anmeldungen erhalten.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-rose-200 px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50">Löschen</button>
                                                </form>
                                        </div>
                                    </div>
                                </details>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-6 text-slate-500">
                                    Noch keine Zusatzfragen. Die Anmeldung funktioniert bereits mit den Standardfeldern.
                                </div>
                            @endforelse
                        </div>

                        <form method="POST" action="{{ route('events.booking-fields.store', $event) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Neue Zusatzfrage</div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-slate-700">Was soll abgefragt werden?</label>
                                    <input type="text" name="label" value="{{ old('label') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="z. B. Essenswunsch">
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-slate-700">Antwortart</label>
                                    <select name="field_type" class="w-full rounded-lg border-slate-300 text-sm">
                                        @foreach($bookingFieldTypes as $type => $label)
                                            <option value="{{ $type }}" @selected(old('field_type', 'text') === $type)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-slate-700">Antwortmöglichkeiten</label>
                                    <textarea name="options" rows="4" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Nur bei Auswahlfeldern:&#10;Vegetarisch&#10;Fleisch&#10;Keine Angabe">{{ old('options') }}</textarea>
                                </div>

                                <details class="rounded-lg border border-slate-200 bg-white">
                                    <summary class="cursor-pointer list-none px-3 py-2 text-sm font-semibold text-slate-700">Feinheiten</summary>
                                    <div class="space-y-3 border-t border-slate-100 p-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Interner Kurzname</label>
                                            <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="z_b_essenswunsch">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Platzhalter</label>
                                            <input type="text" name="placeholder" value="{{ old('placeholder') }}" class="w-full rounded-lg border-slate-300 text-sm">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Hilfetext</label>
                                            <textarea name="help_text" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ old('help_text') }}</textarea>
                                        </div>
                                    </div>
                                </details>

                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <input type="checkbox" name="is_required" value="1" @checked(old('is_required')) class="rounded border-slate-300 text-slate-950">
                                    Muss ausgefüllt werden
                                </label>

                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                    Feld hinzufügen
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </section>

            <section id="loeschen" class="scroll-mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-rose-950">Veranstaltung löschen</h2>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-rose-800">
                            Entfernt den Termin aus Kalender, öffentlicher Veranstaltungsliste und zugehöriger Organisation. Bitte nur löschen, wenn diese Veranstaltung wirklich nicht mehr benötigt wird.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Veranstaltung wirklich löschen? Dieser Schritt kann nicht rückgängig gemacht werden.');" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-rose-300 bg-white px-5 text-sm font-semibold text-rose-700 hover:bg-rose-100 sm:w-auto">
                            Veranstaltung löschen
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    tinymce.init({
        selector: '#description',
        license_key: 'gpl',
        height: 280,
        menubar: false,
        plugins: 'lists link table code fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | code fullscreen',
        content_style: 'body { font-family: Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.6; }'
    });

    document.getElementById('event-edit-form')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush

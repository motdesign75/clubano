@extends('layouts.app')

@section('title', 'Neues Formular')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" x-data="{ formType: '{{ old('form_type', $form->form_type ?? 'general') }}', confirmationMailEnabled: {{ old('confirmation_mail_enabled', $form->confirmation_mail_enabled) ? 'true' : 'false' }} }">
    <section class="rounded-[28px] bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-7">
        <div class="max-w-3xl">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Formulare</div>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Neues Formular anlegen</h1>
            <p class="mt-2 text-sm leading-6 text-slate-300">
                Erst Zweck und Titel festlegen, dann loslegen. Das passende Starter-Set an Feldern bereitet Clubano automatisch vor.
            </p>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('forms.store') }}" method="POST" class="space-y-6">
        @csrf

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-6">
                <div>
                    <div class="text-sm font-semibold text-slate-900">1. Wofuer ist das Formular?</div>
                    <div class="mt-1 text-sm text-slate-500">Waehle den Zweck. Clubano richtet die ersten Felder passend dazu vor.</div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @php
                        $typeMeta = [
                            'general' => ['label' => 'Allgemein', 'hint' => 'Fuer freie Anfragen und einfache Formulare.'],
                            'contact' => ['label' => 'Kontakt', 'hint' => 'Name, E-Mail und Nachricht direkt startklar.'],
                            'membership' => ['label' => 'Beitritt', 'hint' => 'Mit Starter-Set fuer neue Vereinsmitglieder.'],
                            'event' => ['label' => 'Event', 'hint' => 'Anmeldung mit Event-Bezug und Buchungslogik.'],
                        ];
                    @endphp

                    @foreach($formTypes as $key => $label)
                        <label class="block">
                            <input type="radio" name="form_type" value="{{ $key }}" class="peer sr-only" x-model="formType">
                            <span class="flex h-full min-h-[124px] flex-col rounded-2xl border border-slate-200 bg-white p-4 transition peer-checked:border-slate-950 peer-checked:bg-slate-950 peer-checked:text-white hover:border-slate-300">
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400 peer-checked:text-slate-300">{{ $typeMeta[$key]['label'] ?? $label }}</span>
                                <span class="mt-2 text-lg font-semibold text-slate-950 peer-checked:text-white">{{ $label }}</span>
                                <span class="mt-2 text-sm leading-6 text-slate-500 peer-checked:text-slate-300">{{ $typeMeta[$key]['hint'] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="grid gap-5 lg:grid-cols-[1.3fr_0.9fr]">
                <div class="space-y-5">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">2. Wie soll es heissen?</div>
                        <div class="mt-1 text-sm text-slate-500">Mehr brauchst du fuer den Start eigentlich nicht.</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Titel</label>
                        <input type="text"
                               name="title"
                               value="{{ old('title', $form->title) }}"
                               class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                               placeholder="z. B. Anmeldung Sommerfest 2026"
                               required>
                    </div>

                    <div x-show="formType === 'event'" x-cloak>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Event verknuepfen</label>
                        <select name="event_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                            <option value="">Event auswaehlen</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" @selected((string) old('event_id', $form->event_id) === (string) $event->id)>
                                    {{ $event->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Nur bei Event-Formularen noetig. Danach werden Buchungen und Teilnehmer sauber verbunden.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kurze Beschreibung <span class="text-slate-400">(optional)</span></label>
                        <textarea name="description"
                                  rows="3"
                                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                  placeholder="Ein bis zwei Saetze fuer den Einstieg in das Formular.">{{ old('description', $form->description) }}</textarea>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-semibold text-slate-900">Was danach passiert</div>
                    <ol class="mt-3 space-y-3 text-sm text-slate-600">
                        <li>1. Clubano legt das Formular an.</li>
                        <li>2. Ein passendes Starter-Set an Feldern wird vorbereitet.</li>
                        <li>3. Danach kannst du Felder nur noch dort anpassen, wo es wirklich noetig ist.</li>
                    </ol>
                </div>
            </div>
        </section>

        <details class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer list-none px-5 py-4 sm:px-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Optionale Feineinstellungen</div>
                        <div class="mt-1 text-sm text-slate-500">Slug, Erfolgsmeldung und automatische Bestaetigungsmail nur dann, wenn du es jetzt schon brauchst.</div>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </summary>

            <div class="border-t border-slate-200 px-5 py-5 sm:px-6">
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Slug</label>
                        <input type="text"
                               name="slug"
                               value="{{ old('slug', $form->slug) }}"
                               class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                               placeholder="wird-aus-dem-titel-erzeugt">
                        <p class="mt-2 text-xs text-slate-500">Kann leer bleiben. Clubano erzeugt ihn automatisch aus dem Titel.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Erfolgsmeldung</label>
                        <textarea name="success_message"
                                  rows="3"
                                  class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                  placeholder="Vielen Dank. Das Formular wurde erfolgreich gesendet.">{{ old('success_message', $form->success_message) }}</textarea>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold text-slate-900">Bestätigungsmail nach dem Absenden</div>
                            <p class="mt-1 text-sm text-slate-600">
                                Nur aktivieren, wenn Besucher direkt eine Rueckmeldung per E-Mail erhalten sollen.
                            </p>
                        </div>

                        <label class="inline-flex items-center text-sm font-medium text-slate-700">
                            <input type="checkbox"
                                   name="confirmation_mail_enabled"
                                   value="1"
                                   x-model="confirmationMailEnabled"
                                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                   @checked(old('confirmation_mail_enabled', $form->confirmation_mail_enabled))>
                            <span class="ml-2">Aktivieren</span>
                        </label>
                    </div>

                    <div x-show="confirmationMailEnabled" x-cloak class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Mail-Betreff</label>
                            <input type="text"
                                   name="confirmation_mail_subject"
                                   value="{{ old('confirmation_mail_subject', $form->confirmation_mail_subject) }}"
                                   class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                                   placeholder="z. B. Deine Anmeldung bei {verein}">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Mail-Inhalt</label>
                            @include('forms.partials.confirmation-placeholders', ['targetId' => 'confirmation_mail_body'])
                            <textarea name="confirmation_mail_body"
                                      id="confirmation_mail_body"
                                      rows="10"
                                      class="mt-3 w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm">{{ old('confirmation_mail_body', $form->confirmation_mail_body) }}</textarea>
                            <p class="mt-2 text-xs text-slate-500">
                                Du kannst Platzhalter wie <span class="font-medium">{'{formular}'}</span> oder spaeter eigene Feld-Slugs verwenden.
                            </p>
                        </div>
                    </div>
                </div>

                <label class="mt-6 inline-flex items-center text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old('is_active', $form->is_active))>
                    <span class="ml-2">Formular direkt aktiv und oeffentlich erreichbar machen</span>
                </label>
            </div>
        </details>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('forms.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">Zurueck</a>
            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                Formular anlegen
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    tinymce.init({
        selector: '#confirmation_mail_body',
        license_key: 'gpl',
        height: 320,
        menubar: false,
        plugins: 'lists link table code autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | removeformat code',
        branding: false,
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
    });

    document.querySelector('form')?.addEventListener('submit', function () {
        tinymce.triggerSave();
    });
});
</script>
@endpush

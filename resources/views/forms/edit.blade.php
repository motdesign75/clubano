@extends('layouts.app')

@section('title', 'Formular bearbeiten')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    @include('forms.partials.form', [
        'submitRoute' => route('forms.update', $form),
        'method' => 'PUT',
        'submitLabel' => 'Formular speichern',
    ])

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-2xl">
                <div class="text-sm font-semibold text-slate-900">Felder ganz einfach</div>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Denke in Bausteinen: Was soll abgefragt werden? Name, E-Mail, Auswahl oder Nachricht. Alles andere ist nur Feinschliff.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('forms.public.show', $form->slug) }}" target="_blank" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Formular oeffnen
                </a>
                <a href="#neues-feld" class="inline-flex items-center rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Feld hinzufuegen
                </a>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.85fr]">
        <section class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Formular-Bausteine</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Jeder Baustein laesst sich aufklappen, aendern, verschieben oder loeschen.
                        </p>
                    </div>
                    <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ $form->fields->count() }} Feld{{ $form->fields->count() === 1 ? '' : 'er' }}
                    </div>
                </div>
            </div>

            @forelse($form->fields as $field)
                <details id="formularfeld-{{ $field->id }}" class="group rounded-3xl border border-slate-200 bg-white shadow-sm scroll-mt-24" @if($loop->first) open @endif>
                    <summary class="list-none cursor-pointer px-5 py-4 sm:px-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-semibold text-slate-600">
                                        {{ $field->sort_order }}
                                    </span>
                                    <h3 class="truncate text-base font-semibold text-slate-950">{{ $field->label }}</h3>
                                    @if($field->is_required && !$field->isDisplayOnly())
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200">
                                            Pflichtfeld
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                                    <span>{{ $fieldTypes[$field->field_type] ?? $field->field_type }}</span>
                                    @unless($field->isDisplayOnly())
                                        <span>{{ $field->slug }}</span>
                                    @endunless
                                    @if($field->placeholder)
                                        <span>Platzhalter: {{ $field->placeholder }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2" onclick="event.stopPropagation()">
                                <span class="hidden text-xs font-semibold uppercase tracking-[0.16em] text-slate-400 sm:inline">Sortieren</span>
                                <form method="POST" action="{{ route('forms.fields.move', [$form, $field]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit"
                                            @disabled($loop->first)
                                            aria-label="{{ $field->label }} nach oben verschieben"
                                            class="inline-flex h-10 min-w-10 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-35">
                                        ↑
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('forms.fields.move', [$form, $field]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit"
                                            @disabled($loop->last)
                                            aria-label="{{ $field->label }} nach unten verschieben"
                                            class="inline-flex h-10 min-w-10 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-35">
                                        ↓
                                    </button>
                                </form>
                                <span class="hidden text-sm text-slate-500 sm:inline">
                                    {{ $field->help_text ? Str::limit(strip_tags($field->help_text), 60) : 'Zum Bearbeiten aufklappen' }}
                                </span>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </summary>

                    <div class="border-t border-slate-200 px-5 py-5 sm:px-6">
                        <form method="POST" action="{{ route('forms.fields.update', [$form, $field]) }}" class="space-y-5" x-data="{ fieldType: '{{ $field->field_type }}', displayOnly() { return ['heading', 'content', 'divider'].includes(this.fieldType); } }">
                            @csrf
                            @method('PUT')

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700" x-text="fieldType === 'heading' ? 'Überschrift' : (fieldType === 'content' ? 'Titel des Textblocks' : (fieldType === 'divider' ? 'Beschriftung der Trennlinie' : 'Name des Feldes'))"></label>
                                    <input type="text" name="label" value="{{ old('label', $field->label) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                                </div>
                                <div x-show="!displayOnly()" x-cloak>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Interner Kurzname</label>
                                    <input type="text" name="slug" value="{{ old('slug', $field->slug) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Art des Feldes</label>
                                    <select name="field_type" x-model="fieldType" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                                        @foreach($fieldTypes as $key => $label)
                                            <option value="{{ $key }}" @selected($field->field_type === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="!displayOnly()" x-cloak>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Platzhalter</label>
                                    <input type="text" name="placeholder" value="{{ old('placeholder', $field->placeholder) }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700" x-text="displayOnly() ? 'Text' : 'Hilfetext'"></label>
                                <textarea name="help_text" rows="2" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">{{ old('help_text', $field->help_text) }}</textarea>
                                <p class="mt-2 text-xs text-slate-500" x-text="displayOnly() ? 'Dieser Text erscheint direkt im Formular und wird nicht als Antwort gespeichert.' : 'Kurze Hilfe fuer Menschen. Kein Techniktext.'"></p>
                            </div>

                            <div x-show="['select', 'radio', 'checkbox_group'].includes(fieldType)" x-cloak>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Antwortmoeglichkeiten</label>
                                <textarea name="options" rows="5" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10" placeholder="Jede Antwort in eine neue Zeile">{{ old('options', str_replace('|', "\n", (string) $field->options)) }}</textarea>
                                <p class="mt-2 text-xs text-slate-500">Einfach eine Zeile pro Auswahl. Mehr ist nicht noetig.</p>
                            </div>

                            <div class="flex flex-col gap-4 border-t border-slate-200 pt-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                    <label class="inline-flex items-center text-sm text-slate-700" x-show="!displayOnly()">
                                        <input type="checkbox" name="is_required" value="1" class="rounded border-gray-300" @checked($field->is_required)>
                                        <span class="ml-2">Muss ausgefuellt werden</span>
                                    </label>

                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <button type="submit" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                        Speichern
                                    </button>
                                    <button formaction="{{ route('forms.fields.destroy', [$form, $field]) }}"
                                            formmethod="POST"
                                            name="_method"
                                            value="DELETE"
                                            onclick="return confirm('Feld wirklich loeschen?')"
                                            class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                        Loeschen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </details>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500 shadow-sm">
                    Noch keine Felder vorhanden.
                </div>
            @endforelse
        </section>

        <aside class="space-y-6">
            @include('forms.partials.embed-card', ['form' => $form])

            <section id="neues-feld" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{
                fieldType: 'text',
                label: '',
                displayOnly() { return ['heading', 'content', 'divider'].includes(this.fieldType); },
                choose(type) {
                    this.fieldType = type;
                    if (type === 'heading' && !this.label) this.label = 'Neuer Abschnitt';
                    if (type === 'content' && !this.label) this.label = 'Hinweis';
                    if (type === 'divider' && !this.label) this.label = 'Trennlinie';
                }
            }">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Neues Feld hinzufuegen</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Einmal kurz beschreiben, was abgefragt werden soll. Alles Weitere bleibt optional.
                    </p>
                </div>

                <form method="POST" action="{{ route('forms.fields.store', $form) }}" class="mt-5 space-y-4">
                    @csrf

                    <div class="grid gap-2 sm:grid-cols-3">
                        <button type="button" @click="choose('heading')" :class="fieldType === 'heading' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-700'" class="rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition">
                            Überschrift
                            <span class="mt-1 block text-xs font-normal opacity-75">Neuer Abschnitt</span>
                        </button>
                        <button type="button" @click="choose('content')" :class="fieldType === 'content' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-700'" class="rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition">
                            Text
                            <span class="mt-1 block text-xs font-normal opacity-75">Erklärung oder Hinweis</span>
                        </button>
                        <button type="button" @click="choose('divider')" :class="fieldType === 'divider' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-700'" class="rounded-2xl border px-3 py-3 text-left text-sm font-semibold transition">
                            Linie
                            <span class="mt-1 block text-xs font-normal opacity-75">Optische Trennung</span>
                        </button>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" x-text="fieldType === 'heading' ? 'Überschrift' : (fieldType === 'content' ? 'Titel des Textblocks' : (fieldType === 'divider' ? 'Beschriftung der Trennlinie' : 'Frage oder Bezeichnung'))"></label>
                        <input type="text" name="label" x-model="label" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10" placeholder="z. B. Telefonnummer">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Art des Feldes</label>
                        <select name="field_type" x-model="fieldType" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                            @foreach($fieldTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="['select', 'radio', 'checkbox_group'].includes(fieldType)" x-cloak>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Antwortmoeglichkeiten</label>
                        <textarea name="options" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10" placeholder="Ja&#10;Nein"></textarea>
                    </div>

                    <details class="rounded-2xl border border-slate-200 bg-slate-50">
                        <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-slate-700">
                            Erweiterte Einstellungen
                        </summary>
                        <div class="space-y-4 border-t border-slate-200 px-4 py-4">
                            <div x-show="!displayOnly()" x-cloak>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Interner Kurzname</label>
                                <input type="text" name="slug" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10" placeholder="z. B. phone">
                            </div>

                            <div x-show="!displayOnly()" x-cloak>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Platzhalter</label>
                                <input type="text" name="placeholder" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700" x-text="displayOnly() ? 'Text' : 'Hilfetext'"></label>
                                <textarea name="help_text" rows="2" class="w-full rounded-2xl border border-slate-300 px-4 py-3 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"></textarea>
                            </div>

                            <label class="inline-flex items-center text-sm text-slate-700" x-show="!displayOnly()">
                                <input type="checkbox" name="is_required" value="1" class="rounded border-gray-300">
                                <span class="ml-2">Muss ausgefuellt werden</span>
                            </label>
                        </div>
                    </details>

                    <button type="submit" class="w-full rounded-full bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Feld hinzufuegen
                    </button>
                </form>
            </section>
        </aside>
    </div>
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

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            tinymce.triggerSave();
        });
    });
});
</script>
@endpush

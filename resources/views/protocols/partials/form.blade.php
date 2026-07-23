@php
    $editing = isset($protocol);
    $formAction = $editing ? route('protocols.update', $protocol) : route('protocols.store');
    $titleValue = old('title', $protocol->title ?? '');
    $typeValue = old('type', $protocol->type ?? '');
    $locationValue = old('location', $protocol->location ?? '');
    $startTimeValue = old('start_time', isset($protocol->start_time) && $protocol->start_time ? \Illuminate\Support\Str::of($protocol->start_time)->substr(0, 5) : '');
    $endTimeValue = old('end_time', isset($protocol->end_time) && $protocol->end_time ? \Illuminate\Support\Str::of($protocol->end_time)->substr(0, 5) : '');
    $resolutionsValue = old('resolutions', $protocol->resolutions ?? '');
    $nextMeetingValue = old('next_meeting', $protocol->next_meeting ?? '');
    $rawAgendaValue = old('raw_agenda', $protocol->raw_agenda ?? '');
    $rawNotesValue = old('raw_notes', $protocol->raw_notes ?? '');
    $contentValue = old('content', $protocol->content ?? '');
    $selectedParticipants = collect(old('participant_ids', $selected ?? []))->map(fn ($id) => (int) $id)->all();
    $attachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];
    $entryTypes = \App\Models\ProtocolEntry::typeOptions();
    $entryTypeHints = [
        'information' => 'Bericht, Sachstand oder Hinweis',
        'discussion' => 'Austausch ohne verbindliche Entscheidung',
        'resolution' => 'Verbindliche Entscheidung',
        'task' => 'Aufgabe mit Verantwortung und Frist',
        'date' => 'Termin oder Veranstaltung',
        'follow_up' => 'Punkt für eine spätere Sitzung',
    ];
    $entryTypeTone = [
        'information' => 'border-slate-200 bg-slate-50 text-slate-800',
        'discussion' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
        'resolution' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'task' => 'border-amber-200 bg-amber-50 text-amber-800',
        'date' => 'border-sky-200 bg-sky-50 text-sky-800',
        'follow_up' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];
    $dateInputValue = function ($value) {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value ?: '';
    };
    $entryValues = collect(old('entries', $protocol->entries ?? []))
        ->map(fn ($entry) => [
            'type' => data_get($entry, 'type', 'information'),
            'title' => data_get($entry, 'title', ''),
            'content' => data_get($entry, 'content', ''),
            'responsible_name' => data_get($entry, 'responsible_name', ''),
            'due_date' => $dateInputValue(data_get($entry, 'due_date')),
            'scheduled_date' => $dateInputValue(data_get($entry, 'scheduled_date')),
            'visible_in_protocol' => (bool) data_get($entry, 'visible_in_protocol', true),
        ])
        ->filter(fn ($entry) => filled($entry['title']) || filled($entry['content']))
        ->values()
        ->all();

    if (empty($entryValues)) {
        $entryValues = [[
            'type' => 'information',
            'title' => '',
            'content' => '',
            'responsible_name' => '',
            'due_date' => '',
            'scheduled_date' => '',
            'visible_in_protocol' => true,
        ]];
    }

    if (is_string($attachments)) {
        $attachments = [$attachments];
    }

    $protocolTypes = [
        'Mitgliederversammlung',
        'Vorstandssitzung',
        'Jahreshauptversammlung',
        'Sonstiges',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokolle</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ $editing ? 'Protokoll ruhig nachziehen' : 'Neues Protokoll anlegen' }}
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    {{ $editing
                        ? 'Bringe Inhalt, Teilnehmer und Anhänge in eine Form, die später schnell wieder lesbar ist.'
                        : 'Halte Beschlüsse, Ergebnisse und Teilnehmer so fest, dass der Verein später nicht mehr suchen muss.' }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm text-slate-200">
                <div class="font-semibold">{{ $editing ? 'Bearbeitung' : 'Neuanlage' }}</div>
                <div class="mt-1 text-slate-300">
                    {{ $editing ? 'Bestehendes Protokoll aktualisieren' : 'Agenda vorbereiten, Sitzung führen, Protokoll erzeugen' }}
                </div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <section class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <div class="font-semibold text-rose-950">Bitte kurz prüfen.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-rose-900">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($editing)
            @method('PUT')
        @endif

        <nav class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Protokoll erfassen</div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="#protocol-basics" class="rounded-full bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white">Rahmen</a>
                <a href="#protocol-people" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Teilnehmer</a>
                <a href="#protocol-content" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Agenda & Mitschrift</a>
                <a href="#protocol-files" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Anhänge</a>
            </div>
        </nav>

        <section id="protocol-basics" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Rahmen</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Worum geht es?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Titel, Typ, Ort und Zeit geben dem Protokoll seinen Platz.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:col-span-3">
                    <div class="md:col-span-2">
                        <label for="title" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Titel</label>
                        <input id="title" name="title" type="text" value="{{ $titleValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                               placeholder="z. B. Vorstandssitzung" required>
                        @error('title')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="type" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokolltyp</label>
                        <select id="type" name="type"
                                class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                required>
                            <option value="">Bitte wählen</option>
                            @foreach($protocolTypes as $protocolType)
                                <option value="{{ $protocolType }}" @selected($typeValue === $protocolType)>{{ $protocolType }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="location" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ort</label>
                        <input id="location" name="location" type="text" value="{{ $locationValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                               placeholder="z. B. Vereinsheim">
                        @error('location')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="start_time" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Beginn</label>
                        <input id="start_time" name="start_time" type="time" value="{{ $startTimeValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                        @error('start_time')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="end_time" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ende</label>
                        <input id="end_time" name="end_time" type="time" value="{{ $endTimeValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                        @error('end_time')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>
        </section>

        <section id="protocol-people" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Teilnehmer</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Wer war dabei?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Markiere die Personen, die im Protokoll auftauchen sollen. Die Liste bleibt auch auf Tablets gut bedienbar.
                    </p>
                </div>

                <div class="xl:col-span-3">
                    <div class="max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white">
                        <div class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($members as $member)
                                <label class="flex items-start gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-800 transition first:border-t-0 hover:bg-slate-50">
                                    <input type="checkbox"
                                           name="participant_ids[]"
                                           value="{{ $member->id }}"
                                           {{ in_array($member->id, $selectedParticipants, true) ? 'checked' : '' }}
                                           class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                    <span class="min-w-0 leading-5">{{ $member->full_name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('participant_ids')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section id="protocol-content" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6"
                 x-data="protocolEntryEditor(@js($entryValues), @js($rawAgendaValue), @js($rawNotesValue))">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokoll</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Aus der Agenda entsteht das Protokoll.</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Bereite die Tagesordnung vor, schreibe während der Sitzung mit und erzeuge daraus die fertige Niederschrift.
                    </p>
                </div>

                <div class="space-y-6 xl:col-span-3">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <button type="button"
                                class="rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition"
                                :class="step === 'agenda' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                @click="step = 'agenda'">
                            1. Agenda
                        </button>
                        <button type="button"
                                class="rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition"
                                :class="step === 'notes' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                @click="step = 'notes'">
                            2. Mitschrift
                        </button>
                        <button type="button"
                                class="rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition"
                                :class="step === 'structure' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                @click="step = 'structure'">
                            3. Ordnen
                        </button>
                        <button type="button"
                                class="rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition"
                                :class="step === 'finish' ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                @click="step = 'finish'">
                            4. Fertig
                        </button>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-5" x-show="step === 'agenda'">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Vor der Sitzung</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Tagesordnung</h3>
                            </div>
                            <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                <span x-text="agendaLineCount()"></span> TOPs
                            </div>
                        </div>

                        <textarea id="raw_agenda"
                                  name="raw_agenda"
                                  x-model="rawAgenda"
                                  rows="9"
                                  class="mt-4 w-full rounded-2xl border-slate-200 bg-white text-base leading-7 text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300"
                                  placeholder="TOP 1 Begrüßung&#10;TOP 2 Renovierung Vereinsheim&#10;TOP 3 Kartoffelmarkt&#10;TOP 4 Braukurs&#10;TOP 5 Sonstiges">{{ $rawAgendaValue }}</textarea>
                        @error('raw_agenda')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm leading-6 text-slate-600">
                                Jeder Tagesordnungspunkt wird zur geführten Schreibfläche für die Sitzung.
                            </div>
                            <button type="button"
                                    class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                                    @click="structureFromAgenda()">
                                Agenda übernehmen
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5" x-show="step === 'notes'">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Sitzungsmodus</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Schnelle Mitschrift</h3>
                            </div>
                            <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                <span x-text="noteLineCount()"></span> Stichpunkte
                            </div>
                        </div>

                        <textarea id="raw_notes"
                                  name="raw_notes"
                                  x-model="rawNotes"
                                  rows="11"
                                  class="mt-4 w-full rounded-2xl border-slate-200 bg-white text-base leading-7 text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300"
                                  placeholder="- Vorsitzender berichtet über Renovierung&#10;- Diskussion Kartoffelmarkt&#10;- Beschluss: Teilnahme am Kartoffelmarkt&#10;- Dirk organisiert Getränke bis 15.08.2026&#10;- Braukurs am 15.08.2026&#10;- Getränkepreise nächste Sitzung">{{ $rawNotesValue }}</textarea>
                        @error('raw_notes')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm leading-6 text-slate-600">
                                Eine Zeile ist ein Gedanke. Wörter wie Beschluss, diskutiert, bis oder nächste Sitzung helfen beim Einordnen.
                            </div>
                            <button type="button"
                                    class="inline-flex items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                                    @click="structureFromNotes(true)">
                                Mitschrift ergänzen
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4" x-show="step === 'structure'">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Ordnen</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Aus Stichpunkten werden Protokollpunkte</h3>
                            </div>
                            <button type="button"
                                    class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    @click="add()">
                                Punkt hinzufügen
                            </button>
                        </div>

                        <template x-for="(entry, index) in entries" :key="entry.key">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start">
                                        <div class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                                             :class="toneFor(entry.type)"
                                             x-text="labelFor(entry.type)">
                                        </div>

                                        <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Typ</label>
                                                <select :name="`entries[${index}][type]`"
                                                        x-model="entry.type"
                                                        class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                                                    @foreach($entryTypes as $entryType => $entryLabel)
                                                        <option value="{{ $entryType }}">{{ $entryLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <p class="mt-1 text-xs text-slate-500" x-text="hintFor(entry.type)"></p>
                                            </div>

                                            <div>
                                                <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Überschrift</label>
                                                <input type="text"
                                                       :name="`entries[${index}][title]`"
                                                       x-model="entry.title"
                                                       class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                                       placeholder="z. B. Kartoffelmarkt">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 gap-2">
                                        <button type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                title="Nach oben"
                                                @click="move(index, -1)"
                                                x-show="index > 0">
                                            ↑
                                        </button>
                                        <button type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                title="Nach unten"
                                                @click="move(index, 1)"
                                                x-show="index < entries.length - 1">
                                            ↓
                                        </button>
                                        <button type="button"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-rose-200 text-rose-600 transition hover:bg-rose-50"
                                                title="Punkt entfernen"
                                                @click="remove(index)"
                                                x-show="entries.length > 1">
                                            ×
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Inhalt</label>
                                    <textarea :name="`entries[${index}][content]`"
                                              x-model="entry.content"
                                              rows="4"
                                              class="mt-2 w-full rounded-2xl border-slate-200 text-sm leading-6 focus:border-slate-400 focus:ring-slate-300"
                                              placeholder="Was soll im Protokoll stehen?"></textarea>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    <div x-show="entry.type === 'task' || entry.type === 'follow_up'">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Verantwortlich</label>
                                        <input type="text"
                                               :name="`entries[${index}][responsible_name]`"
                                               x-model="entry.responsible_name"
                                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                               placeholder="Name">
                                    </div>

                                    <div x-show="entry.type === 'task' || entry.type === 'follow_up'">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fällig am</label>
                                        <input type="date"
                                               :name="`entries[${index}][due_date]`"
                                               x-model="entry.due_date"
                                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                                    </div>

                                    <div x-show="entry.type === 'date'">
                                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Termin am</label>
                                        <input type="date"
                                               :name="`entries[${index}][scheduled_date]`"
                                               x-model="entry.scheduled_date"
                                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300">
                                    </div>

                                    <label class="flex items-center gap-3 self-end rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700">
                                        <input type="hidden" :name="`entries[${index}][visible_in_protocol]`" value="0">
                                        <input type="checkbox"
                                               :name="`entries[${index}][visible_in_protocol]`"
                                               value="1"
                                               x-model="entry.visible_in_protocol"
                                               class="rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                        Im Protokoll anzeigen
                                    </label>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div class="border-t border-slate-100 pt-6" x-show="step === 'finish'">
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Ergebnisse & nächste Schritte</div>
                        <div class="mt-4 grid gap-5 lg:grid-cols-2">
                            <div>
                                <label for="resolutions" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Beschlüsse / Ergebnisse</label>
                                <textarea id="resolutions" name="resolutions" rows="5"
                                          class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                          placeholder="Was wurde entschieden oder verbindlich festgehalten?">{{ $resolutionsValue }}</textarea>
                                @error('resolutions')
                                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="next_meeting" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Nächstes Treffen / To-dos</label>
                                <textarea id="next_meeting" name="next_meeting" rows="5"
                                          class="mt-2 w-full rounded-2xl border-slate-200 text-sm focus:border-slate-400 focus:ring-slate-300"
                                          placeholder="Was steht als Nächstes an?">{{ $nextMeetingValue }}</textarea>
                                @error('next_meeting')
                                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <details class="rounded-2xl border border-slate-200 bg-slate-50 p-4" x-show="step === 'finish'" :open="step === 'finish'">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Fertiges Protokoll bei Bedarf manuell nachziehen</summary>
                        <div class="mt-4">
                            <label for="content" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokolltext</label>
                            <input id="content" type="hidden" name="content" value="{{ $contentValue }}">
                            <trix-editor input="content" class="mt-2 min-h-[240px] rounded-2xl border border-slate-200 bg-white"></trix-editor>
                            @error('content')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <section id="protocol-files" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Anhänge</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Was mit dazu gehört</h2>
                </div>

                <div class="xl:col-span-3">
                    @if($editing && !empty($attachments))
                        <div class="space-y-2">
                            @foreach($attachments as $file)
                                <a href="{{ route('protocols.attachments.show', ['protocol' => $protocol, 'index' => $loop->index]) }}"
                                   target="_blank"
                                   class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 text-sm font-medium text-slate-700 transition first:border-t-0 hover:text-slate-950">
                                    <span class="truncate">{{ basename($file) }}</span>
                                    <span class="shrink-0 text-xs font-semibold text-indigo-700">Öffnen</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="{{ $editing && !empty($attachments) ? 'mt-4' : '' }}">
                        <input type="file" name="attachments[]" multiple
                               class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Erlaubt: PDF, Bilder, Word, Excel. Maximal 10 MB pro Datei.</p>
                        @error('attachments')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        </section>

        <div class="sticky bottom-4 z-10 -mx-2 rounded-[28px] border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:shadow-none sm:backdrop-blur-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $editing ? route('protocols.show', $protocol) : route('protocols.index') }}"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    {{ $editing ? 'Zurück zum Protokoll' : 'Zurück zur Übersicht' }}
                </a>

                <button type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    {{ $editing ? 'Änderungen speichern' : 'Protokoll speichern' }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function protocolEntryEditor(initialEntries, initialRawAgenda, initialRawNotes) {
        return {
            step: initialRawAgenda ? 'agenda' : (initialRawNotes ? 'notes' : (initialEntries.some((entry) => (entry.title || entry.content)) ? 'structure' : 'agenda')),
            rawAgenda: initialRawAgenda || '',
            rawNotes: initialRawNotes || '',
            entries: initialEntries.map((entry, index) => ({ key: Date.now() + index, ...entry })),
            labels: @js($entryTypes),
            hints: @js($entryTypeHints),
            tones: @js($entryTypeTone),
            add() {
                this.entries.push({
                    key: Date.now() + Math.random(),
                    type: 'information',
                    title: '',
                    content: '',
                    responsible_name: '',
                    due_date: '',
                    scheduled_date: '',
                    visible_in_protocol: true,
                });
            },
            remove(index) {
                this.entries.splice(index, 1);
            },
            move(index, direction) {
                const target = index + direction;

                if (target < 0 || target >= this.entries.length) {
                    return;
                }

                const item = this.entries.splice(index, 1)[0];
                this.entries.splice(target, 0, item);
            },
            labelFor(type) {
                return this.labels[type] || 'Punkt';
            },
            hintFor(type) {
                return this.hints[type] || '';
            },
            toneFor(type) {
                return this.tones[type] || this.tones.information;
            },
            noteLineCount() {
                return this.rawNotes
                    .split(/\r?\n/)
                    .map((line) => line.replace(/^\s*[-*•]\s*/, '').trim())
                    .filter(Boolean)
                    .length;
            },
            agendaLineCount() {
                return this.agendaLines().length;
            },
            agendaLines() {
                return this.rawAgenda
                    .split(/\r?\n/)
                    .map((line) => line.replace(/^\s*(?:[-*•]|\d+[\).]|TOP\s*\d+[\).:-]?)\s*/i, '').trim())
                    .filter(Boolean);
            },
            structureFromAgenda() {
                const lines = this.agendaLines();

                if (lines.length === 0) {
                    this.entries = [{
                        key: Date.now(),
                        type: 'information',
                        title: '',
                        content: '',
                        responsible_name: '',
                        due_date: '',
                        scheduled_date: '',
                        visible_in_protocol: true,
                    }];
                    this.step = 'notes';
                    return;
                }

                this.entries = lines.map((line, index) => ({
                    key: Date.now() + index,
                    type: 'information',
                    title: line.slice(0, 90),
                    content: '',
                    responsible_name: '',
                    due_date: '',
                    scheduled_date: '',
                    visible_in_protocol: true,
                }));

                this.step = 'notes';
            },
            structureFromNotes(append = false) {
                const lines = this.rawNotes
                    .split(/\r?\n/)
                    .map((line) => line.replace(/^\s*[-*•]\s*/, '').trim())
                    .filter(Boolean);

                if (lines.length === 0) {
                    if (!append) {
                        this.entries = [{
                            key: Date.now(),
                            type: 'information',
                            title: '',
                            content: '',
                            responsible_name: '',
                            due_date: '',
                            scheduled_date: '',
                            visible_in_protocol: true,
                        }];
                    }
                    this.step = 'structure';
                    return;
                }

                const newEntries = lines.map((line, index) => {
                    const type = this.detectType(line);
                    const date = this.extractDate(line);

                    return {
                        key: Date.now() + index,
                        type,
                        title: this.titleFromLine(line),
                        content: line,
                        responsible_name: type === 'task' || type === 'follow_up' ? this.detectResponsible(line) : '',
                        due_date: type === 'task' || type === 'follow_up' ? date : '',
                        scheduled_date: type === 'date' ? date : '',
                        visible_in_protocol: true,
                    };
                });

                const hasOnlyEmptyPlaceholder = this.entries.length === 1
                    && !this.entries[0].title
                    && !this.entries[0].content;

                this.entries = append && !hasOnlyEmptyPlaceholder
                    ? this.entries.concat(newEntries)
                    : newEntries;

                this.step = 'structure';
            },
            detectType(line) {
                const text = line.toLowerCase();

                if (text.includes('beschluss') || text.includes('beschlossen') || text.includes('entscheidung') || text.includes('entscheidet')) {
                    return 'resolution';
                }

                if (text.includes('diskussion') || text.includes('diskutiert') || text.includes('beratung') || text.includes('besprochen')) {
                    return 'discussion';
                }

                if (text.includes('wiedervorlage') || text.includes('nächste sitzung') || text.includes('naechste sitzung') || text.includes('erneut beraten')) {
                    return 'follow_up';
                }

                if (text.includes('organisiert') || text.includes('erledigt') || text.includes('kümmert') || text.includes('kuemmert') || text.includes(' bis ')) {
                    return 'task';
                }

                if (text.includes('termin') || text.includes('veranstaltung') || text.includes('braukurs')) {
                    return 'date';
                }

                if (/\b\d{1,2}\.\d{1,2}\.(\d{2}|\d{4})\b/.test(text)) {
                    return 'date';
                }

                return 'information';
            },
            titleFromLine(line) {
                return line
                    .replace(/^\s*(beschluss|diskussion|termin|aufgabe|wiedervorlage)\s*[:|-]\s*/i, '')
                    .slice(0, 70);
            },
            detectResponsible(line) {
                const match = line.match(/^([A-ZÄÖÜ][A-Za-zÄÖÜäöüß-]+(?:\s+[A-ZÄÖÜ][A-Za-zÄÖÜäöüß-]+)?)/);

                return match ? match[1] : '';
            },
            extractDate(line) {
                const match = line.match(/\b(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})\b/);

                if (!match) {
                    return '';
                }

                const day = match[1].padStart(2, '0');
                const month = match[2].padStart(2, '0');
                const year = match[3].length === 2 ? `20${match[3]}` : match[3];

                return `${year}-${month}-${day}`;
            },
        };
    }
</script>

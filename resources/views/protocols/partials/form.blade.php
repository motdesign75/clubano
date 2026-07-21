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
    $contentValue = old('content', $protocol->content ?? '');
    $selectedParticipants = collect(old('participant_ids', $selected ?? []))->map(fn ($id) => (int) $id)->all();
    $attachments = $protocol->attachments ?? $protocol->attachment_paths ?? [];

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
                    {{ $editing ? 'Bestehendes Protokoll aktualisieren' : 'Mit Trix-Editor, Teilnehmern und Anhängen' }}
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
                <a href="#protocol-content" class="rounded-full px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Inhalt</a>
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

        <section id="protocol-content" class="rounded-2xl border border-slate-200 bg-white p-6 scroll-mt-6">
            <div class="grid gap-6 xl:grid-cols-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokoll</div>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Inhalt und Ergebnisse</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Erst der Verlauf, danach die verbindlichen Punkte. So findet jeder sofort, was besprochen und entschieden wurde.
                    </p>
                </div>

                <div class="space-y-6 xl:col-span-3">
                    <div>
                        <label for="content" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Verlauf und Notizen</label>
                        <input id="content" type="hidden" name="content" value="{{ $contentValue }}">
                        <trix-editor input="content" class="mt-2 min-h-[320px] rounded-2xl border border-slate-200 bg-white"></trix-editor>
                        @error('content')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t border-slate-100 pt-6">
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

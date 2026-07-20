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
    <section class="rounded-3xl bg-slate-950 px-6 py-6 text-white shadow-sm sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Protokolle</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ $editing ? 'Protokoll ruhig nachziehen' : 'Neues Protokoll anlegen' }}
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-300 sm:text-base">
                    {{ $editing
                        ? 'Bringe Inhalt, Teilnehmer und Anhaenge in eine Form, die spaeter schnell wieder lesbar ist.'
                        : 'Halte Beschluesse, Ergebnisse und Teilnehmer so fest, dass der Verein spaeter nicht mehr suchen muss.' }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm text-slate-200">
                <div class="font-semibold">{{ $editing ? 'Bearbeitung' : 'Neuanlage' }}</div>
                <div class="mt-1 text-slate-300">
                    {{ $editing ? 'Bestehendes Protokoll aktualisieren' : 'Mit Trix-Editor, Teilnehmern und Anhaengen' }}
                </div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <section class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4">
            <div class="font-semibold text-rose-950">Bitte kurz pruefen.</div>
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

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="title" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Titel</label>
                        <input id="title" name="title" type="text" value="{{ $titleValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                               placeholder="z. B. Vorstandssitzung" required>
                        @error('title')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="type" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokolltyp</label>
                        <select id="type" name="type"
                                class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                required>
                            <option value="">Bitte waehlen</option>
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
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                               placeholder="z. B. Vereinsheim">
                        @error('location')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="start_time" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Beginn</label>
                        <input id="start_time" name="start_time" type="time" value="{{ $startTimeValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                        @error('start_time')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="end_time" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Ende</label>
                        <input id="end_time" name="end_time" type="time" value="{{ $endTimeValue }}"
                               class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300">
                        @error('end_time')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="resolutions" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Beschluesse / Ergebnisse</label>
                        <textarea id="resolutions" name="resolutions" rows="4"
                                  class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                  placeholder="Was wurde entschieden oder festgehalten?">{{ $resolutionsValue }}</textarea>
                        @error('resolutions')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="next_meeting" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Naechstes Treffen</label>
                        <textarea id="next_meeting" name="next_meeting" rows="3"
                                  class="mt-2 w-full rounded-2xl border-slate-200 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300"
                                  placeholder="Was steht als Naechstes an?">{{ $nextMeetingValue }}</textarea>
                        @error('next_meeting')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Teilnehmer</div>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Wer war dabei?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Markiere die Personen, die im Protokoll auftauchen sollen. Die Liste bleibt auch auf Tablets gut bedienbar.
                    </p>

                    <div class="mt-5 max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            @foreach ($members as $member)
                                <label class="flex items-start gap-3 rounded-2xl bg-white px-3 py-3 text-sm text-slate-800 ring-1 ring-slate-200 transition hover:bg-slate-50">
                                    <input type="checkbox"
                                           name="participant_ids[]"
                                           value="{{ $member->id }}"
                                           {{ in_array($member->id, $selectedParticipants, true) ? 'checked' : '' }}
                                           class="mt-0.5 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-400">
                                    <span class="min-w-0 leading-5">{{ $member->full_name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @error('participant_ids')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Anhaenge</div>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Was mit dazu gehoert</h2>

                    @if($editing && !empty($attachments))
                        <div class="mt-4 space-y-2">
                            @foreach($attachments as $file)
                                <a href="{{ route('protocols.attachments.show', ['protocol' => $protocol, 'index' => $loop->index]) }}"
                                   target="_blank"
                                   class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                    <span class="truncate">{{ basename($file) }}</span>
                                    <span class="shrink-0 text-xs font-semibold text-indigo-700">Oeffnen</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4">
                        <input type="file" name="attachments[]" multiple
                               class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-full file:border-0 file:bg-slate-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Erlaubt: PDF, Bilder, Word, Excel. Maximal 10 MB pro Datei.</p>
                        @error('attachments')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            </aside>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Protokoll</div>
            <h2 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Der eigentliche Inhalt</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Schreibe so, dass spaeter ohne Rueckfrage klar ist, was besprochen und beschlossen wurde.
            </p>

            <div class="mt-5">
                <input id="content" type="hidden" name="content" value="{{ $contentValue }}">
                <trix-editor input="content" class="min-h-[320px] rounded-2xl border border-slate-200 bg-white shadow-sm"></trix-editor>
                @error('content')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="sticky bottom-4 z-10 -mx-2 rounded-[28px] border border-slate-200 bg-white/95 px-4 py-4 shadow-lg backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0 sm:shadow-none sm:backdrop-blur-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ $editing ? route('protocols.show', $protocol) : route('protocols.index') }}"
                   class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    {{ $editing ? 'Zurueck zum Protokoll' : 'Zurueck zur Uebersicht' }}
                </a>

                <button type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    {{ $editing ? 'Aenderungen speichern' : 'Protokoll speichern' }}
                </button>
            </div>
        </div>
    </form>
</div>

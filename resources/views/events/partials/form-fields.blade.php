@php
    $isPublic = (int) old('is_public', $event->is_public ?? 1);
    $bookingEnabled = (bool) old('booking_enabled', $event->booking_enabled ?? false);
    $isEditingEvent = $event->exists;
    $recurrenceEnabled = (bool) old('recurrence_enabled', false);
    $categoryProfiles = ($categories ?? collect())->mapWithKeys(fn ($category) => [
        $category->id => [
            'visibility' => $category->default_visibility ?: 'public',
            'target_tag_id' => $category->default_target_tag_id,
            'attendance_enabled' => (bool) $category->attendance_enabled_default,
            'response_required' => (bool) $category->response_required_default,
            'counts_toward_required_hours' => (bool) $category->counts_toward_required_hours_default,
            'reminders_enabled' => (bool) $category->reminders_enabled_default,
        ],
    ]);
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr),320px]">
    <section class="space-y-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-950 text-white">
                    <x-heroicon-o-sparkles class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-slate-950">Worum geht es?</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Titel, Kategorie und Beschreibung geben dem Termin seinen Platz im Kalender.</p>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="title" class="text-sm font-semibold text-slate-900">Titel *</label>
                    <input type="text" name="title" id="title" required value="{{ old('title', $event->title) }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                           placeholder="z. B. Sommerfest, Vorstandssitzung, Training">
                </div>

                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr),auto] sm:items-end">
                    <div>
                        <label for="category_id" class="text-sm font-semibold text-slate-900">Kategorie</label>
                        <select name="category_id" id="category_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                            <option value="">Keine Kategorie</option>
                            @foreach(($categories ?? collect()) as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $event->category_id) === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <a href="{{ route('event-categories.index') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Kategorien
                    </a>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-700 ring-1 ring-slate-200">
                            <x-heroicon-o-user-group class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-slate-950">Für wen ist diese Aktivität?</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Wähle eine Zielgruppe, damit Einladungen, Rückmeldungen und Auswertungen später eindeutig bleiben.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="target_tag_id" class="text-sm font-semibold text-slate-900">Zielgruppe</label>
                        <select name="target_tag_id" id="target_tag_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                            <option value="">Alle aktiven Mitglieder / keine feste Zielgruppe</option>
                            @foreach(($targetTags ?? collect()) as $tag)
                                <option value="{{ $tag->id }}" @selected((string) old('target_tag_id', $event->target_tag_id) === (string) $tag->id)>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="description" class="text-sm font-semibold text-slate-900">Beschreibung</label>
                    <textarea name="description" id="description" rows="8"
                              class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                              placeholder="Was sollen Mitglieder oder Besucher wissen?">{{ old('description', $event->description) }}</textarea>
                </div>
            </div>

            @unless($isEditingEvent)
                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="recurrence_enabled" value="1" class="mt-1 rounded border-slate-300" @checked($recurrenceEnabled)>
                        <span>
                            <span class="block text-sm font-semibold text-slate-950">Als Serie anlegen</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-500">Für Training, Stammtisch oder regelmäßige Vorstandsrunden erstellt Clubano mehrere echte Termine.</span>
                        </span>
                    </label>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="recurrence_frequency" class="text-sm font-semibold text-slate-900">Wiederholung</label>
                            <select name="recurrence_frequency" id="recurrence_frequency" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                                <option value="weekly" @selected(old('recurrence_frequency') === 'weekly')>Wöchentlich</option>
                                <option value="biweekly" @selected(old('recurrence_frequency') === 'biweekly')>Alle zwei Wochen</option>
                                <option value="monthly_same_date" @selected(in_array(old('recurrence_frequency'), ['monthly', 'monthly_same_date'], true))>Monatlich am gleichen Datum</option>
                                <option value="monthly_nth_weekday" @selected(old('recurrence_frequency') === 'monthly_nth_weekday')>Monatlich am gleichen Wochentag</option>
                            </select>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Beispiel: Start am ersten Freitag erzeugt jeden ersten Freitag.</p>
                        </div>

                        <div>
                            <label for="recurrence_until" class="text-sm font-semibold text-slate-900">Serie bis</label>
                            <input type="date" name="recurrence_until" id="recurrence_until" value="{{ old('recurrence_until') }}"
                                   class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                        </div>
                    </div>

                    <p class="mt-3 text-xs leading-5 text-slate-500">Beim Speichern entstehen einzelne Termine im Kalender. Du kannst jeden Termin danach separat bearbeiten oder löschen.</p>
                </div>
            @else
                @if($event->recurrence_group_id)
                    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="text-sm font-semibold text-slate-950">Teil einer Serie</div>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            Dieser Termin gehört zu einer Serie. Änderungen in diesem Editor betreffen nur diesen einzelnen Termin.
                        </p>
                    </div>
                @endif
            @endunless
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <x-heroicon-o-clock class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold text-slate-950">Wann und wo?</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Zeit, Ort und Verantwortung sind die wichtigsten Orientierungspunkte.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="start" class="text-sm font-semibold text-slate-900">Beginn *</label>
                    <input type="datetime-local" name="start" id="start" required
                           value="{{ old('start', $event->start ? $event->start->format('Y-m-d\TH:i') : '') }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                </div>

                <div>
                    <label for="end" class="text-sm font-semibold text-slate-900">Ende *</label>
                    <input type="datetime-local" name="end" id="end" required
                           value="{{ old('end', $event->end ? $event->end->format('Y-m-d\TH:i') : '') }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                </div>

                <div>
                    <label for="location" class="text-sm font-semibold text-slate-900">Ort</label>
                    <input type="text" name="location" id="location" value="{{ old('location', $event->location) }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300"
                           placeholder="Vereinsheim, Sportplatz, online">
                </div>

                <div>
                    <label for="responsible_user_id" class="text-sm font-semibold text-slate-900">Verantwortlich</label>
                    <select name="responsible_user_id" id="responsible_user_id" class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                        <option value="">Niemand fest zugeordnet</option>
                        @foreach(($users ?? collect()) as $user)
                            <option value="{{ $user->id }}" @selected((string) old('responsible_user_id', $event->responsible_user_id) === (string) $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    <aside class="space-y-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Veröffentlichen</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Entscheide, ob der Termin nur intern oder öffentlich sichtbar ist.</p>

            <div class="mt-4 grid gap-2">
                <label class="cursor-pointer rounded-lg border px-4 py-3 {{ $isPublic === 1 ? 'border-slate-950 bg-slate-50' : 'border-slate-200' }}">
                    <input type="radio" name="is_public" value="1" class="sr-only" @checked($isPublic === 1)>
                    <span class="block text-sm font-semibold text-slate-950">Öffentlich</span>
                    <span class="mt-1 block text-sm text-slate-500">Auf öffentlicher Eventseite sichtbar.</span>
                </label>
                <label class="cursor-pointer rounded-lg border px-4 py-3 {{ $isPublic === 0 ? 'border-slate-950 bg-slate-50' : 'border-slate-200' }}">
                    <input type="radio" name="is_public" value="0" class="sr-only" @checked($isPublic === 0)>
                    <span class="block text-sm font-semibold text-slate-950">Intern</span>
                    <span class="mt-1 block text-sm text-slate-500">Nur im Vereinskalender sichtbar.</span>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Ablauf</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Clubano nutzt diese Optionen für Rückmeldungen, Anwesenheit und spätere Auswertungen.</p>

            <div class="mt-4 grid gap-2">
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <input type="checkbox" name="response_required" id="response_required" value="1" class="mt-1 rounded border-slate-300" @checked(old('response_required', $event->response_required ?? false))>
                    <span>
                        <span class="block text-sm font-semibold text-slate-950">Rückmeldung erwarten</span>
                        <span class="mt-1 block text-sm text-slate-500">Später können Mitglieder zu- oder absagen.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <input type="checkbox" name="attendance_enabled" id="attendance_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('attendance_enabled', $event->attendance_enabled ?? false))>
                    <span>
                        <span class="block text-sm font-semibold text-slate-950">Anwesenheit erfassen</span>
                        <span class="mt-1 block text-sm text-slate-500">Trainer oder Vorstand dokumentieren die reale Teilnahme.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <input type="checkbox" name="counts_toward_required_hours" id="counts_toward_required_hours" value="1" class="mt-1 rounded border-emerald-300 text-emerald-700" @checked(old('counts_toward_required_hours', $event->counts_toward_required_hours ?? false))>
                    <span>
                        <span class="block text-sm font-semibold text-emerald-950">Zählt zu Pflichtstunden</span>
                        <span class="mt-1 block text-sm text-emerald-700">Beim Erfassen wird diese Aktivität als Arbeitszeit vorgeschlagen.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <input type="checkbox" name="reminders_enabled" id="reminders_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('reminders_enabled', $event->reminders_enabled ?? false))>
                    <span>
                        <span class="block text-sm font-semibold text-slate-950">Erinnerungen vorbereiten</span>
                        <span class="mt-1 block text-sm text-slate-500">Die eigentliche Automatik folgt im Benachrichtigungsmodul.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="booking_enabled" value="1" class="mt-1 rounded border-slate-300" @checked($bookingEnabled)>
                <span>
                    <span class="block text-lg font-semibold text-slate-950">Anmeldung aktivieren</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-500">Clubano erstellt automatisch ein Buchungsformular.</span>
                </span>
            </label>

            <div class="mt-5 grid gap-4">
                <div>
                    <label for="price_per_person" class="text-sm font-semibold text-slate-900">Preis für Gäste / Nichtmitglieder</label>
                    <input type="number" step="0.01" min="0" name="price_per_person" id="price_per_person"
                           value="{{ old('price_per_person', number_format((float) ($event->price_per_person ?? 0), 2, '.', '')) }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <p class="mt-1 text-xs text-slate-500">Dieser Preis gilt für externe Teilnehmer, Gäste, Firmen und Organisationen.</p>
                </div>

                <div>
                    <label for="member_price_per_person" class="text-sm font-semibold text-slate-900">Preis für Mitglieder</label>
                    <input type="number" step="0.01" min="0" name="member_price_per_person" id="member_price_per_person"
                           value="{{ old('member_price_per_person', number_format((float) ($event->member_price_per_person ?? 0), 2, '.', '')) }}"
                           class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    <p class="mt-1 text-xs text-slate-500">0,00 bedeutet: Mitglieder nehmen kostenfrei teil.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <label for="currency" class="text-sm font-semibold text-slate-900">Währung</label>
                        <input type="text" name="currency" id="currency" maxlength="3"
                               value="{{ old('currency', strtoupper($event->currency ?: 'EUR')) }}"
                               class="mt-2 w-full rounded-lg border-slate-300 text-sm uppercase focus:border-slate-500 focus:ring-slate-300">
                    </div>

                    <div>
                        <label for="max_participants_per_booking" class="text-sm font-semibold text-slate-900">Personen pro Anmeldung</label>
                        <input type="number" min="1" max="50" name="max_participants_per_booking" id="max_participants_per_booking"
                               value="{{ old('max_participants_per_booking', max(1, (int) ($event->max_participants_per_booking ?: 1))) }}"
                               class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Bild</h2>
            <input type="file" name="image" id="image" accept="image/*"
                   class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
            <p class="mt-2 text-xs leading-5 text-slate-500">Für öffentliche Eventseite und Teilen in sozialen Kanälen.</p>

            @if($event->image_url)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-40 w-full rounded-lg object-cover">
                    <label class="mt-3 inline-flex items-center text-sm text-slate-700">
                        <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300">
                        <span class="ml-2">Vorhandenes Foto entfernen</span>
                    </label>
                </div>
            @endif
        </section>
    </aside>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const profiles = @json($categoryProfiles);
        const categorySelect = document.getElementById('category_id');

        if (!categorySelect) {
            return;
        }

        categorySelect.addEventListener('change', () => {
            const profile = profiles[categorySelect.value];

            if (!profile) {
                return;
            }

            const visibility = document.querySelector(`input[name="is_public"][value="${profile.visibility === 'internal' ? '0' : '1'}"]`);
            const targetTag = document.getElementById('target_tag_id');
            const checks = {
                response_required: document.getElementById('response_required'),
                attendance_enabled: document.getElementById('attendance_enabled'),
                counts_toward_required_hours: document.getElementById('counts_toward_required_hours'),
                reminders_enabled: document.getElementById('reminders_enabled'),
            };

            if (visibility) {
                visibility.checked = true;
            }

            if (targetTag && profile.target_tag_id) {
                targetTag.value = profile.target_tag_id;
            }

            Object.entries(checks).forEach(([key, input]) => {
                if (input) {
                    input.checked = Boolean(profile[key]);
                }
            });
        });
    });
</script>
@endpush

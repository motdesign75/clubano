@php
    $isPublic = (int) old('is_public', $event->is_public ?? 1);
    $bookingEnabled = (bool) old('booking_enabled', $event->booking_enabled ?? false);
    $bookingForm = $event->activeBookingForm ?? null;
    $bookingAddressTone = old('booking_address_tone', $bookingForm?->booking_address_tone ?? 'du');
    $isEditingEvent = $event->exists;
    $recurrenceEnabled = (bool) old('recurrence_enabled', false);
    $recurrenceFrequency = old('recurrence_frequency', 'weekly');
    $recurrenceUntil = old('recurrence_until');
    $inputClass = 'mt-2 w-full min-h-14 rounded-2xl border-slate-300 px-4 text-base shadow-sm focus:border-slate-500 focus:ring-slate-300';
    $selectClass = 'mt-2 w-full min-h-14 rounded-2xl border-slate-300 px-4 text-base shadow-sm focus:border-slate-500 focus:ring-slate-300';
    $labelClass = 'text-sm font-bold text-slate-950';
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

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr),360px]">
    <section class="space-y-5">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-white">
                    <x-heroicon-o-sparkles class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Schritt 1</div>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">Was findet statt?</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Erst der Name. Alles andere ist Zusatz, damit der Termin später leicht gefunden wird.</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <label for="title" class="{{ $labelClass }}">Name des Termins *</label>
                    <input type="text" name="title" id="title" required value="{{ old('title', $event->title) }}"
                           class="{{ $inputClass }}"
                           placeholder="z. B. Sommerfest">
                </div>

                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr),auto] md:items-end">
                    <div>
                        <label for="category_id" class="{{ $labelClass }}">Art des Termins</label>
                        <select name="category_id" id="category_id" class="{{ $selectClass }}">
                            <option value="">Keine Kategorie</option>
                            @foreach(($categories ?? collect()) as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $event->category_id) === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <a href="{{ route('event-categories.index') }}"
                       class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Kategorien
                    </a>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-700 ring-1 ring-slate-200">
                            <x-heroicon-o-user-group class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold text-slate-950">Wer soll es sehen?</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Optional: Wenn der Termin nur für eine Gruppe gedacht ist, wähle sie hier aus.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="target_tag_id" class="{{ $labelClass }}">Zielgruppe</label>
                        <select name="target_tag_id" id="target_tag_id" class="{{ $selectClass }}">
                            <option value="">Alle aktiven Mitglieder / keine feste Zielgruppe</option>
                            @foreach(($targetTags ?? collect()) as $tag)
                                <option value="{{ $tag->id }}" @selected((string) old('target_tag_id', $event->target_tag_id) === (string) $tag->id)>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @unless($isEditingEvent)
                <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
                    <label class="flex cursor-pointer items-start gap-4">
                        <input type="checkbox" name="recurrence_enabled" value="1" class="mt-1 h-5 w-5 rounded border-amber-300 text-amber-700" @checked($recurrenceEnabled) data-recurrence-toggle>
                        <span>
                            <span class="block text-base font-semibold text-amber-950">Das ist ein Serientermin</span>
                            <span class="mt-1 block text-sm leading-6 text-amber-800">Für Training, Stammtisch oder wiederkehrende Treffen erstellt Clubano mehrere Termine auf einmal.</span>
                        </span>
                    </label>

                    <div class="mt-5 grid gap-4 md:grid-cols-2" data-recurrence-options>
                        <div>
                            <label for="recurrence_frequency" class="{{ $labelClass }}">Wie oft?</label>
                            <select name="recurrence_frequency" id="recurrence_frequency" class="{{ $selectClass }}">
                                <option value="weekly" @selected($recurrenceFrequency === 'weekly')>Jede Woche</option>
                                <option value="biweekly" @selected($recurrenceFrequency === 'biweekly')>Alle zwei Wochen</option>
                                <option value="monthly_same_date" @selected(in_array($recurrenceFrequency, ['monthly', 'monthly_same_date'], true))>Jeden Monat am gleichen Datum</option>
                                <option value="monthly_nth_weekday" @selected($recurrenceFrequency === 'monthly_nth_weekday')>Jeden Monat am gleichen Wochentag</option>
                            </select>
                            <p class="mt-2 text-sm leading-6 text-amber-800">Beispiel: Start am ersten Freitag erzeugt jeden ersten Freitag.</p>
                        </div>

                        <div>
                            <label for="recurrence_until" class="{{ $labelClass }}">Bis wann?</label>
                            <input type="date" name="recurrence_until" id="recurrence_until" value="{{ $recurrenceUntil }}"
                                   class="{{ $inputClass }}">
                        </div>
                    </div>

                    <p class="mt-4 rounded-2xl bg-white/70 px-4 py-3 text-sm leading-6 text-amber-900 ring-1 ring-amber-100">Beim Speichern entstehen einzelne Termine im Kalender. Danach kannst du jeden Termin separat bearbeiten oder löschen.</p>
                </div>
            @else
                @if($event->recurrence_group_id)
                    <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
                        <div class="text-base font-semibold text-amber-950">Dieser Termin gehört zu einer Serie</div>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            Dieser Termin gehört zu einer Serie. Änderungen in diesem Editor betreffen nur diesen einzelnen Termin.
                        </p>
                    </div>
                @endif
            @endunless
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                    <x-heroicon-o-clock class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Schritt 2</div>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">Wann und wo?</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Datum, Uhrzeit und Ort sind die wichtigsten Infos im Kalender.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="start" class="{{ $labelClass }}">Start *</label>
                    <input type="datetime-local" name="start" id="start" required
                           value="{{ old('start', $event->start ? $event->start->format('Y-m-d\TH:i') : '') }}"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label for="end" class="{{ $labelClass }}">Ende *</label>
                    <input type="datetime-local" name="end" id="end" required
                           value="{{ old('end', $event->end ? $event->end->format('Y-m-d\TH:i') : '') }}"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label for="location" class="{{ $labelClass }}">Ort</label>
                    <input type="text" name="location" id="location" value="{{ old('location', $event->location) }}"
                           class="{{ $inputClass }}"
                           placeholder="Vereinsheim, Sportplatz, online">
                </div>

                <div>
                    <label for="responsible_user_id" class="{{ $labelClass }}">Wer kümmert sich?</label>
                    <select name="responsible_user_id" id="responsible_user_id" class="{{ $selectClass }}">
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

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                    <x-heroicon-o-document-text class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Schritt 3</div>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">Was sollen andere wissen?</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Optional: kurze Einladung, Ablauf oder Hinweise für Teilnehmende.</p>
                </div>
            </div>

            <div class="mt-6">
                <label for="description" class="{{ $labelClass }}">Beschreibung</label>
                <textarea name="description" id="description" rows="8"
                          class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base leading-7 shadow-sm focus:border-slate-500 focus:ring-slate-300"
                          placeholder="Was sollen Mitglieder oder Besucher wissen?">{{ old('description', $event->description) }}</textarea>
            </div>
        </div>
    </section>

    <aside class="space-y-5 xl:sticky xl:top-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Sichtbarkeit</div>
            <h2 class="mt-1 text-xl font-semibold text-slate-950">Wer darf den Termin sehen?</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Entscheide, ob der Termin nur intern oder öffentlich sichtbar ist.</p>

            <div class="mt-4 grid gap-2">
                <label class="cursor-pointer rounded-2xl border px-4 py-4 {{ $isPublic === 1 ? 'border-slate-950 bg-slate-50' : 'border-slate-200' }}">
                    <input type="radio" name="is_public" value="1" class="sr-only" @checked($isPublic === 1)>
                    <span class="block text-base font-semibold text-slate-950">Öffentlich</span>
                    <span class="mt-1 block text-sm text-slate-500">Auf öffentlicher Eventseite sichtbar.</span>
                </label>
                <label class="cursor-pointer rounded-2xl border px-4 py-4 {{ $isPublic === 0 ? 'border-slate-950 bg-slate-50' : 'border-slate-200' }}">
                    <input type="radio" name="is_public" value="0" class="sr-only" @checked($isPublic === 0)>
                    <span class="block text-base font-semibold text-slate-950">Intern</span>
                    <span class="mt-1 block text-sm text-slate-500">Nur im Vereinskalender sichtbar.</span>
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">Optionen</div>
            <h2 class="mt-1 text-xl font-semibold text-slate-950">Was brauchst du?</h2>
            <p class="mt-1 text-sm leading-6 text-slate-500">Clubano nutzt diese Optionen für Rückmeldungen, Anwesenheit und spätere Auswertungen.</p>

            <div class="mt-4 grid gap-2">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 px-4 py-4">
                    <input type="checkbox" name="response_required" id="response_required" value="1" class="mt-1 h-5 w-5 rounded border-slate-300" @checked(old('response_required', $event->response_required ?? false))>
                    <span>
                        <span class="block text-base font-semibold text-slate-950">Rückmeldung erwarten</span>
                        <span class="mt-1 block text-sm text-slate-500">Später können Mitglieder zu- oder absagen.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 px-4 py-4">
                    <input type="checkbox" name="attendance_enabled" id="attendance_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300" @checked(old('attendance_enabled', $event->attendance_enabled ?? false))>
                    <span>
                        <span class="block text-base font-semibold text-slate-950">Anwesenheit erfassen</span>
                        <span class="mt-1 block text-sm text-slate-500">Trainer oder Vorstand dokumentieren die reale Teilnahme.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                    <input type="checkbox" name="counts_toward_required_hours" id="counts_toward_required_hours" value="1" class="mt-1 h-5 w-5 rounded border-emerald-300 text-emerald-700" @checked(old('counts_toward_required_hours', $event->counts_toward_required_hours ?? false))>
                    <span>
                        <span class="block text-base font-semibold text-emerald-950">Zählt zu Pflichtstunden</span>
                        <span class="mt-1 block text-sm text-emerald-700">Beim Erfassen wird diese Aktivität als Arbeitszeit vorgeschlagen.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 px-4 py-4">
                    <input type="checkbox" name="reminders_enabled" id="reminders_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300" @checked(old('reminders_enabled', $event->reminders_enabled ?? false))>
                    <span>
                        <span class="block text-base font-semibold text-slate-950">Erinnerungen vorbereiten</span>
                        <span class="mt-1 block text-sm text-slate-500">Die eigentliche Automatik folgt im Benachrichtigungsmodul.</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="booking_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300" @checked($bookingEnabled)>
                <span>
                    <span class="block text-xl font-semibold text-slate-950">Anmeldung aktivieren</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-500">Clubano erstellt automatisch ein Buchungsformular.</span>
                </span>
            </label>

            <div class="mt-5 grid gap-4">
                <div>
                    <label for="booking_address_tone" class="{{ $labelClass }}">Ansprache im Anmeldeformular</label>
                    <select name="booking_address_tone" id="booking_address_tone"
                            class="{{ $selectClass }}">
                        <option value="du" @selected($bookingAddressTone === 'du')>Du-Ansprache</option>
                        <option value="sie" @selected($bookingAddressTone === 'sie')>Sie-Ansprache</option>
                    </select>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Für öffentliche Anmeldungen mit Gästen, Firmen oder anderen Vereinen wirkt die Sie-Ansprache oft passender.</p>
                </div>

                <div>
                    <label for="price_per_person" class="{{ $labelClass }}">Preis für Gäste / Nichtmitglieder</label>
                    <input type="number" step="0.01" min="0" name="price_per_person" id="price_per_person"
                           value="{{ old('price_per_person', number_format((float) ($event->price_per_person ?? 0), 2, '.', '')) }}"
                           class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-slate-500">Dieser Preis gilt für externe Teilnehmer, Gäste, Firmen und Organisationen.</p>
                </div>

                <div>
                    <label for="member_price_per_person" class="{{ $labelClass }}">Preis für Mitglieder</label>
                    <input type="number" step="0.01" min="0" name="member_price_per_person" id="member_price_per_person"
                           value="{{ old('member_price_per_person', number_format((float) ($event->member_price_per_person ?? 0), 2, '.', '')) }}"
                           class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-slate-500">0,00 bedeutet: Mitglieder nehmen kostenfrei teil.</p>
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                    <input type="checkbox" name="organization_bookings_free" value="1" class="mt-1 h-5 w-5 rounded border-emerald-300 text-emerald-700" @checked(old('organization_bookings_free', $event->organization_bookings_free ?? false))>
                    <span>
                        <span class="block text-sm font-semibold text-emerald-950">Vereine kostenfrei</span>
                        <span class="mt-1 block text-sm text-emerald-700">Gilt nur für externe Vereine. Firmen, Unternehmen und sonstige Organisationen zahlen weiterhin den Gästepreis.</span>
                    </span>
                </label>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <label for="currency" class="{{ $labelClass }}">Währung</label>
                        <input type="text" name="currency" id="currency" maxlength="3"
                               value="{{ old('currency', strtoupper($event->currency ?: 'EUR')) }}"
                               class="{{ $inputClass }} uppercase">
                    </div>

                    <div>
                        <label for="max_participants_per_booking" class="{{ $labelClass }}">Personen pro Anmeldung</label>
                        <input type="number" min="1" max="50" name="max_participants_per_booking" id="max_participants_per_booking"
                               value="{{ old('max_participants_per_booking', max(1, (int) ($event->max_participants_per_booking ?: 1))) }}"
                               class="{{ $inputClass }}">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950">Bild</h2>
            <input type="file" name="image" id="image" accept="image/*"
                   class="mt-3 w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
            <p class="mt-2 text-xs leading-5 text-slate-500">Für öffentliche Eventseite und Teilen in sozialen Kanälen.</p>

            @if($event->image_url)
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="h-40 w-full rounded-2xl object-cover">
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
        const recurrenceToggle = document.querySelector('[data-recurrence-toggle]');
        const recurrenceOptions = document.querySelector('[data-recurrence-options]');

        const syncRecurrenceOptions = () => {
            if (!recurrenceToggle || !recurrenceOptions) {
                return;
            }

            recurrenceOptions.classList.toggle('hidden', !recurrenceToggle.checked);
            recurrenceOptions.querySelectorAll('input, select').forEach((input) => {
                input.disabled = !recurrenceToggle.checked;
            });
        };

        recurrenceToggle?.addEventListener('change', syncRecurrenceOptions);
        syncRecurrenceOptions();

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

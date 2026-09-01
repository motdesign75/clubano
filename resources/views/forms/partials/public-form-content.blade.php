<div class="{{ $embedded ?? false ? 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' : 'rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200' }}">
    @php
        $isEventBooking = $form->form_type === 'event' && $form->event;
        $event = $form->event;
        $currency = strtoupper($event?->currency ?: 'EUR');
        $participantCountOld = max(1, min((int) old('participant_count', 1), max(1, (int) ($event?->max_participants_per_booking ?: 1))));
        $participantRowsOld = old('participants', []);
        $participantTemplate = [];
        $useBookerAsParticipantOld = (bool) old('use_booker_as_participant', 1);
        $organizationField = $isEventBooking ? $form->fields->firstWhere('slug', 'organization') : null;
        $bookingModeOld = old('booking_mode', filled(old('fields.organization')) ? 'organization' : 'person');
        $bookingModeOld = in_array($bookingModeOld, ['person', 'organization'], true) ? $bookingModeOld : 'person';
        if ($organizationField?->is_required) {
            $bookingModeOld = 'organization';
        }
        if ($bookingModeOld === 'organization') {
            $participantCountOld = 1;
            $useBookerAsParticipantOld = true;
        }
        $additionalParticipantCountOld = max(0, $participantCountOld - ($useBookerAsParticipantOld ? 1 : 0));

        for ($i = 0; $i < $additionalParticipantCountOld; $i++) {
            $participantTemplate[] = [
                'first_name' => $participantRowsOld[$i]['first_name'] ?? '',
                'last_name' => $participantRowsOld[$i]['last_name'] ?? '',
                'email' => $participantRowsOld[$i]['email'] ?? '',
                'phone' => $participantRowsOld[$i]['phone'] ?? '',
            ];
        }
    @endphp

    <div class="mb-8">
        <div class="text-sm font-medium text-indigo-600">{{ $form->tenant->name }}</div>
        <h1 class="mt-2 {{ ($embedded ?? false) ? 'text-2xl' : 'text-3xl' }} font-semibold text-gray-900">{{ $form->title }}</h1>

        @if($form->description)
            <p class="mt-3 text-base leading-7 text-gray-600">{{ $form->description }}</p>
        @endif

        @if($isEventBooking)
            <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-4 text-sm text-indigo-950">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-semibold">Event: {{ $event->title }}</div>
                        <div class="mt-1 text-indigo-900/80">
                            @if($event->start)
                                {{ $event->start->format('d.m.Y H:i') }} Uhr
                            @endif
                            @if($event->location)
                                · {{ $event->location }}
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($event->is_paid)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                {{ number_format((float) $event->price_per_person, 2, ',', '.') }} {{ $currency }} pro Person
                            </span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                Kostenfrei
                            </span>
                        @endif

                        <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-700 ring-1 ring-indigo-100">
                            Max. {{ max(1, (int) $event->max_participants_per_booking) }} Person{{ max(1, (int) $event->max_participants_per_booking) === 1 ? '' : 'en' }} pro Anmeldung
                        </span>
                    </div>
                </div>
            </div>
        @elseif($form->form_type === 'event' && $form->event)
            <div class="mt-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                Event: {{ $form->event->title }}
                @if($form->event->start)
                    · {{ $form->event->start->format('d.m.Y H:i') }}
                @endif
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ ($embedded ?? false) ? route('forms.public.embed.submit', $form->slug) : route('forms.public.submit', $form->slug) }}"
          class="space-y-8"
          @if($isEventBooking)
              x-data="{
                  pricePerPerson: {{ json_encode((float) ($event->price_per_person ?? 0)) }},
                  maxParticipants: {{ max(1, (int) ($event->max_participants_per_booking ?: 1)) }},
                  participantCount: {{ $participantCountOld }},
                  voucherCode: {{ json_encode(old('voucher_code', '')) }},
                  participants: {{ json_encode($participantTemplate) }},
                  useBookerAsParticipant: {{ $useBookerAsParticipantOld ? 'true' : 'false' }},
                  bookingMode: {{ json_encode($bookingModeOld) }},
                  organizationRequired: {{ $organizationField?->is_required ? 'true' : 'false' }},
                  booker: {
                      organization: {{ json_encode(old('fields.organization', '')) }},
                      first_name: {{ json_encode(old('fields.first_name', '')) }},
                      last_name: {{ json_encode(old('fields.last_name', '')) }},
                      email: {{ json_encode(old('fields.email', '')) }},
                      phone: {{ json_encode(old('fields.mobile', old('fields.phone', ''))) }},
                  },
                  additionalParticipantCount() {
                      if (this.bookingMode === 'organization') {
                          return 0;
                      }
                      return Math.max(0, this.participantCount - (this.useBookerAsParticipant ? 1 : 0));
                  },
                  syncParticipants() {
                      if (this.bookingMode === 'organization') {
                          this.participantCount = 1;
                          this.useBookerAsParticipant = true;
                          this.participants = [];
                          return;
                      }
                      const target = Math.max(1, Math.min(this.maxParticipants, Number(this.participantCount) || 1));
                      this.participantCount = target;
                      const additionalTarget = this.additionalParticipantCount();

                      if (target === 1 && this.participants.length === 0 && !this.useBookerAsParticipant) {
                          this.useBookerAsParticipant = true;
                      }

                      while (this.participants.length < additionalTarget) {
                          this.participants.push({ first_name: '', last_name: '', email: '', phone: '' });
                      }

                      while (this.participants.length > additionalTarget) {
                          this.participants.pop();
                      }
                  },
                  syncBookerToParticipant() {
                      this.syncParticipants();
                  },
                  switchBookingMode(mode) {
                      this.bookingMode = this.organizationRequired ? 'organization' : mode;
                      this.syncParticipants();
                  },
                  totalAmount() {
                      return (this.participantCount * this.pricePerPerson).toFixed(2).replace('.', ',');
                  },
                  voucherHint() {
                      return this.voucherCode && this.pricePerPerson > 0 ? 'Gutschein wird nach dem Absenden geprüft und angerechnet.' : '';
                  }
              }"
              x-init="syncParticipants()"
          @endif>
        @csrf

        @if($isEventBooking)
            <section class="space-y-6 rounded-2xl border border-slate-200 bg-slate-50/70 p-5 sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Ansprechpartner</h2>
                    <p class="mt-1 text-sm text-slate-500">Diese Person erhält die Bestätigung und weitere Informationen zur Buchung.</p>
                </div>
        @endif

        @php
            $fieldSlugs = $form->fields->pluck('slug');
        @endphp
        @foreach($form->fields as $field)
            @continue($isEventBooking && in_array($field->slug, ['participant_count', 'participant_notes'], true))
            @continue($isEventBooking && $field->isLegacyEventBookingAddressDuplicate($fieldSlugs))
            @if($isEventBooking && $field->slug === 'organization')
                <div class="space-y-4">
                    <input type="hidden" name="booking_mode" :value="bookingMode">

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="button"
                                @click="switchBookingMode('person')"
                                :disabled="organizationRequired"
                                :class="bookingMode === 'person' ? 'border-indigo-600 bg-indigo-50 text-indigo-950' : (organizationRequired ? 'border-slate-200 bg-slate-50 text-slate-400' : 'border-slate-200 bg-white text-slate-700')"
                                class="min-h-16 rounded-2xl border px-4 py-3 text-left shadow-sm">
                            <span class="block text-sm font-semibold">Person anmelden</span>
                            <span class="mt-1 block text-xs leading-5 opacity-75">Für einzelne Teilnehmer oder Gruppen mit Personenliste.</span>
                        </button>

                        <button type="button"
                                @click="switchBookingMode('organization')"
                                :class="bookingMode === 'organization' ? 'border-indigo-600 bg-indigo-50 text-indigo-950' : 'border-slate-200 bg-white text-slate-700'"
                                class="min-h-16 rounded-2xl border px-4 py-3 text-left shadow-sm">
                            <span class="block text-sm font-semibold">Organisation oder Verein anmelden</span>
                            <span class="mt-1 block text-xs leading-5 opacity-75">Für Unternehmen, Vereine, Sponsoren oder Gruppen als Einheit.</span>
                        </button>
                    </div>

                    <div x-show="bookingMode === 'organization' || organizationRequired" x-cloak>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $field->label }}
                            @if($field->is_required || $isEventBooking)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <input type="text"
                               name="fields[organization]"
                               value="{{ old('fields.organization') }}"
                               x-model="booker.organization"
                               class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="{{ $field->placeholder }}">
                        @if($field->help_text)
                            <div class="mt-1 text-sm leading-6 text-gray-500">{!! $field->rendered_help_text !!}</div>
                        @endif
                    </div>
                </div>

                @continue
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    {{ $field->label }}
                    @if($field->is_required)
                        <span class="text-red-500">*</span>
                    @endif
                </label>

                @php
                    $name = 'fields[' . $field->slug . ']';
                    $value = old('fields.' . $field->slug);
                    $options = collect(preg_split('/\|/', (string) $field->options) ?: [])->filter()->map(fn($item) => trim($item))->values();
                    $selectedValues = collect(is_array($value) ? $value : [])->filter(fn ($item) => filled($item))->values();
                @endphp

                @if($field->field_type === 'textarea')
                    <textarea name="{{ $name }}" rows="4" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
                @elseif($field->field_type === 'select')
                    <select name="{{ $name }}" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Bitte waehlen</option>
                        @foreach($options as $option)
                            <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($field->field_type === 'radio')
                    <div class="mt-2 grid gap-2">
                        @foreach($options as $option)
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50/50">
                                <input type="radio"
                                       name="{{ $name }}"
                                       value="{{ $option }}"
                                       class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       @checked($value === $option)>
                                <span class="font-medium">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif($field->field_type === 'checkbox_group')
                    <div class="mt-2 grid gap-2">
                        @foreach($options as $option)
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50/50">
                                <input type="checkbox"
                                       name="{{ $name }}[]"
                                       value="{{ $option }}"
                                       class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       @checked($selectedValues->contains($option))>
                                <span class="font-medium">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif($field->field_type === 'checkbox')
                    <label class="mt-2 inline-flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-gray-700">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked($value)>
                        <div class="min-w-0 leading-6 text-gray-700">
                            {!! $field->rendered_help_text ?: \App\Models\PublicFormField::sanitizeHelpText('Ich stimme zu.') !!}
                        </div>
                    </label>
                @else
                    <input type="{{ $field->field_type }}"
                           name="{{ $name }}"
                           value="{{ is_bool($value) ? ($value ? '1' : '0') : $value }}"
                           class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="{{ $field->placeholder }}"
                           @if($isEventBooking && $field->slug === 'first_name')
                               x-model="booker.first_name"
                               @input="syncBookerToParticipant()"
                           @elseif($isEventBooking && $field->slug === 'last_name')
                               x-model="booker.last_name"
                               @input="syncBookerToParticipant()"
                           @elseif($isEventBooking && $field->slug === 'email')
                               x-model="booker.email"
                               @input="syncBookerToParticipant()"
                           @elseif($isEventBooking && in_array($field->slug, ['phone', 'mobile'], true))
                               x-model="booker.phone"
                               @input="syncBookerToParticipant()"
                           @endif>
                @endif

                @if($field->help_text && !in_array($field->field_type, ['checkbox'], true))
                    <div class="mt-1 text-sm leading-6 text-gray-500">
                        {!! $field->rendered_help_text !!}
                    </div>
                @endif
            </div>
        @endforeach

        @if($isEventBooking)
            </section>

            <section class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="participant_count" value="1" :disabled="bookingMode !== 'organization'">

                <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
                    <div>
                        <label for="participant_count" class="block text-sm font-medium text-gray-700" x-show="bookingMode === 'person'">
                            Teilnehmerzahl <span class="text-red-500">*</span>
                        </label>
                        <input id="participant_count"
                               type="number"
                               name="participant_count"
                               min="1"
                               max="{{ max(1, (int) $event->max_participants_per_booking) }}"
                               x-model.number="participantCount"
                               @input="syncParticipants()"
                               :disabled="bookingMode !== 'person'"
                               x-show="bookingMode === 'person'"
                               class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-sm text-gray-500" x-show="bookingMode === 'person'">
                            Maximal {{ max(1, (int) $event->max_participants_per_booking) }} Person{{ max(1, (int) $event->max_participants_per_booking) === 1 ? '' : 'en' }} pro Anmeldung.
                        </p>
                        <div x-show="bookingMode === 'organization'" x-cloak class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 text-sm leading-6 text-indigo-950">
                            Die Organisation oder der Verein wird als eine Anmeldung geführt. Eine zusätzliche Teilnehmerzahl ist dafür nicht nötig.
                        </div>
                    </div>

                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                        <div class="text-sm font-medium text-emerald-900">Zusammenfassung</div>
                        <div class="mt-3 space-y-2 text-sm text-emerald-950">
                            <div class="flex items-center justify-between gap-4">
                                <span>Preis pro Person</span>
                                <span class="font-semibold">
                                    @if($event->is_paid)
                                        {{ number_format((float) $event->price_per_person, 2, ',', '.') }} {{ $currency }}
                                    @else
                                        Kostenfrei
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Teilnehmer</span>
                                <span class="font-semibold" x-text="participantCount"></span>
                            </div>
                            <div class="border-t border-emerald-200 pt-2">
                                <div class="flex items-center justify-between gap-4 text-base">
                                    <span class="font-semibold">Gesamt</span>
                                    <span class="font-semibold" x-text="pricePerPerson > 0 ? `${totalAmount()} {{ $currency }}` : '0,00 {{ $currency }}'"></span>
                                </div>
                                <p x-show="voucherHint()" x-cloak class="mt-2 text-xs leading-5 text-emerald-800" x-text="voucherHint()"></p>
                            </div>
                        </div>
                    </div>
                </div>

                @if($event->is_paid)
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                        <label for="voucher_code" class="block text-sm font-semibold text-blue-950">Gutschein einlösen</label>
                        <p class="mt-1 text-sm leading-6 text-blue-900/80">
                            Falls du einen Gutschein hast, trage den Code hier ein. Der Betrag wird nach dem Absenden geprüft und automatisch angerechnet.
                        </p>
                        <input id="voucher_code"
                               type="text"
                               name="voucher_code"
                               value="{{ old('voucher_code') }}"
                               x-model="voucherCode"
                               class="mt-3 w-full rounded-xl border-blue-200 bg-white font-mono text-sm uppercase shadow-sm focus:border-blue-500 focus:ring-blue-200"
                               placeholder="z. B. CLB-2026-ABC123">
                        @error('voucher_code')
                            <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div x-show="bookingMode === 'person'" x-cloak>
                    <h2 class="text-lg font-semibold text-slate-900">Teilnehmer</h2>
                    <p class="mt-1 text-sm text-slate-500">Bitte trage alle Personen ein, für die du diese Veranstaltung buchen möchtest.</p>
                </div>

                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4" x-show="bookingMode === 'person'" x-cloak>
                    <input type="hidden" name="use_booker_as_participant" :value="useBookerAsParticipant ? 1 : 0">
                    <label class="flex items-start gap-3 text-sm text-indigo-950">
                        <input type="checkbox"
                               x-model="useBookerAsParticipant"
                               @change="syncParticipants()"
                               class="mt-0.5 rounded border-indigo-200 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block font-semibold">Ansprechpartner nimmt selbst teil</span>
                            <span class="mt-1 block text-indigo-900/80">
                                Dann nutzen wir die Angaben oben direkt als ersten Teilnehmer. Du trägst nur noch weitere Personen ein.
                            </span>
                        </span>
                    </label>
                </div>

                <div x-show="bookingMode === 'person' && useBookerAsParticipant" x-cloak class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">Teilnehmer 1:</span>
                    <span x-text="`${booker.first_name || 'Vorname'} ${booker.last_name || 'Nachname'}`"></span>
                    <span class="text-slate-400">aus Ansprechpartner übernommen</span>
                </div>

                <div class="space-y-4" x-show="additionalParticipantCount() > 0" x-cloak>
                    <template x-for="(participant, index) in participants" :key="index">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-4 text-sm font-semibold text-slate-800">
                                Teilnehmer <span x-text="index + 1 + (useBookerAsParticipant ? 1 : 0)"></span>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Vorname <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           :name="`participants[${index}][first_name]`"
                                           x-model="participant.first_name"
                                           class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Nachname <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           :name="`participants[${index}][last_name]`"
                                           x-model="participant.last_name"
                                           class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">E-Mail</label>
                                    <input type="email"
                                           :name="`participants[${index}][email]`"
                                           x-model="participant.email"
                                           class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Telefon</label>
                                    <input type="text"
                                           :name="`participants[${index}][phone]`"
                                           x-model="participant.phone"
                                           class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div>
                    <label for="participant_notes" class="block text-sm font-medium text-gray-700">Hinweis zur Gruppe</label>
                    <textarea id="participant_notes"
                              name="participant_notes"
                              rows="4"
                              class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="z. B. gemeinsames Sitzen, Kindersitz oder wichtige Hinweise">{{ old('participant_notes') }}</textarea>
                </div>
            </section>
        @endif

        <button type="submit" class="inline-flex rounded-lg bg-indigo-600 px-5 py-3 font-medium text-white shadow hover:bg-indigo-700">
            {{ $isEventBooking ? 'Verbindlich anmelden' : 'Formular absenden' }}
        </button>
    </form>
</div>

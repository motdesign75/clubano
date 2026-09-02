<div class="{{ $embedded ?? false ? 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm' : 'overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/70' }}">
    @php
        $isEventBooking = $form->form_type === 'event' && $form->event;
        $event = $form->event;
        $currency = strtoupper($event?->currency ?: 'EUR');
        $externalPrice = (float) ($event?->price_per_person ?? 0);
        $memberPrice = (float) ($event?->member_price_per_person ?? 0);
        $hasMemberRate = $isEventBooking && $externalPrice > 0 && $memberPrice < $externalPrice;
        $clubBookingsFree = $isEventBooking && (bool) ($event?->organization_bookings_free ?? false);
        $tenant = $form->tenant;
        $tenantLogoUrl = $tenant?->logo_url;
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

    <div class="{{ $embedded ?? false ? 'mb-8' : 'bg-slate-950 px-5 py-6 text-white sm:px-8 sm:py-8' }}">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="{{ ($embedded ?? false) ? 'text-sm font-medium text-indigo-600' : 'text-xs font-semibold uppercase tracking-[0.22em] text-white/60' }}">
                    {{ $tenant->name }}
                </div>
                <h1 class="mt-3 {{ ($embedded ?? false) ? 'text-2xl text-gray-900' : 'text-3xl text-white sm:text-4xl' }} font-semibold tracking-tight">{{ $form->title }}</h1>

                @if($form->description)
                    <p class="mt-3 max-w-2xl text-base leading-7 {{ ($embedded ?? false) ? 'text-gray-600' : 'text-white/75' }}">{{ $form->description }}</p>
                @endif
            </div>

            @if($tenantLogoUrl)
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white p-2 shadow-sm ring-1 ring-white/20 sm:h-20 sm:w-20">
                    <img src="{{ $tenantLogoUrl }}" alt="Logo {{ $tenant->name }}" class="max-h-full max-w-full object-contain">
                </div>
            @endif
        </div>

        @if(!($embedded ?? false))
            <div class="mt-6 h-px bg-white/10"></div>
        @endif

        @if($isEventBooking)
            <div class="mt-5 rounded-2xl border {{ ($embedded ?? false) ? 'border-indigo-100 bg-indigo-50 text-indigo-950' : 'border-white/15 bg-white/10 text-white' }} px-4 py-4 text-sm backdrop-blur">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-semibold">Event: {{ $event->title }}</div>
                        <div class="mt-1 {{ ($embedded ?? false) ? 'text-indigo-900/80' : 'text-white/70' }}">
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
                            @if($hasMemberRate)
                                <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                    Mitglieder {{ $memberPrice > 0 ? number_format($memberPrice, 2, ',', '.').' '.$currency : 'kostenfrei' }}
                                </span>
                                @if($clubBookingsFree)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                        Vereine kostenfrei
                                    </span>
                                @endif
                                <span class="rounded-full bg-white px-3 py-1 font-semibold text-slate-800">
                                    Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }}
                                </span>
                            @elseif($clubBookingsFree)
                                <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                    Vereine kostenfrei
                                </span>
                                <span class="rounded-full bg-white px-3 py-1 font-semibold text-slate-800">
                                    Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }}
                                </span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                    {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }} pro Person
                                </span>
                            @endif
                        @else
                            <span class="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                                Kostenfrei
                            </span>
                        @endif

                        <span class="rounded-full {{ ($embedded ?? false) ? 'bg-white text-slate-700 ring-indigo-100' : 'bg-white/15 text-white ring-white/15' }} px-3 py-1 font-medium ring-1">
                            Max. {{ max(1, (int) $event->max_participants_per_booking) }} Person{{ max(1, (int) $event->max_participants_per_booking) === 1 ? '' : 'en' }} pro Anmeldung
                        </span>
                    </div>
                </div>
            </div>
        @elseif($form->form_type === 'event' && $form->event)
            <div class="mt-4 rounded-2xl {{ ($embedded ?? false) ? 'bg-indigo-50 text-indigo-900' : 'bg-white/10 text-white/80' }} px-4 py-3 text-sm">
                Event: {{ $form->event->title }}
                @if($form->event->start)
                    · {{ $form->event->start->format('d.m.Y H:i') }}
                @endif
            </div>
        @endif
    </div>

    <div class="{{ $embedded ?? false ? '' : 'px-5 py-6 sm:px-8 sm:py-8' }}">
        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">
                <div class="text-sm font-semibold">Bitte prüfe die markierten Angaben.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
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
                  externalPricePerPerson: {{ json_encode($externalPrice) }},
                  memberPricePerPerson: {{ json_encode($memberPrice) }},
                  hasMemberRate: {{ $hasMemberRate ? 'true' : 'false' }},
                  clubBookingsFree: {{ $clubBookingsFree ? 'true' : 'false' }},
                  organizationBookingType: {{ json_encode(old('organization_booking_type', '')) }},
                  maxParticipants: {{ max(1, (int) ($event->max_participants_per_booking ?: 1)) }},
                  participantCount: {{ $participantCountOld }},
                  voucherCode: {{ json_encode(old('voucher_code', '')) }},
                  participants: {{ json_encode($participantTemplate) }},
                  useBookerAsParticipant: {{ $useBookerAsParticipantOld ? 'true' : 'false' }},
                  bookerClaimsMembership: {{ old('booking_claims_membership') ? 'true' : 'false' }},
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
                  memberRateApplies() {
                      return this.hasMemberRate
                          && this.bookerClaimsMembership
                          && (this.bookingMode === 'organization' || this.useBookerAsParticipant);
                  },
                  externalParticipantCount() {
                      if (this.bookingMode === 'organization' && this.clubBookingsFree && this.organizationBookingType === 'club') {
                          return 0;
                      }

                      return Math.max(0, this.participantCount - (this.memberRateApplies() ? 1 : 0));
                  },
                  totalAmount() {
                      if (this.bookingMode === 'organization' && this.clubBookingsFree && this.organizationBookingType === 'club') {
                          return '0,00';
                      }

                      const memberTotal = this.memberRateApplies() ? this.memberPricePerPerson : 0;
                      return (memberTotal + (this.externalParticipantCount() * this.externalPricePerPerson)).toFixed(2).replace('.', ',');
                  },
                  voucherHint() {
                      return this.voucherCode && (this.externalPricePerPerson > 0 || this.memberPricePerPerson > 0) ? 'Gutschein wird nach dem Absenden geprüft und angerechnet.' : '';
                  }
              }"
              x-init="syncParticipants()"
          @endif>
        @csrf

        @if($isEventBooking)
            <section class="space-y-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5 sm:p-6">
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
            @if($field->field_type === 'heading')
                <div class="pt-2">
                    <h2 class="text-xl font-semibold tracking-tight text-slate-950">{{ $field->label }}</h2>
                    @if($field->help_text)
                        <div class="mt-2 text-sm leading-6 text-slate-600">{!! $field->rendered_help_text !!}</div>
                    @endif
                </div>

                @continue
            @endif
            @if($field->field_type === 'content')
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-700">
                    <div class="font-semibold text-slate-950">{{ $field->label }}</div>
                    @if($field->help_text)
                        <div class="mt-2">{!! $field->rendered_help_text !!}</div>
                    @endif
                </div>

                @continue
            @endif
            @if($field->field_type === 'divider')
                <div class="py-2">
                    <div class="border-t border-slate-200"></div>
                    @if($field->label)
                        <div class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $field->label }}</div>
                    @endif
                </div>

                @continue
            @endif
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
                            <span class="block text-sm font-semibold">Firma, Organisation oder Verein anmelden</span>
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
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10"
                               placeholder="{{ $field->placeholder }}">
                        @if($field->help_text)
                            <div class="mt-1 text-sm leading-6 text-gray-500">{!! $field->rendered_help_text !!}</div>
                        @endif

                        <div class="mt-4">
                            <label for="organization_booking_type" class="block text-sm font-semibold text-slate-700">
                                Art der Anmeldung <span class="text-red-500">*</span>
                            </label>
                            <select id="organization_booking_type"
                                    name="organization_booking_type"
                                    x-model="organizationBookingType"
                                    class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                                <option value="">Bitte wählen</option>
                                <option value="club">Verein</option>
                                <option value="business">Firma / Unternehmen</option>
                                <option value="organization">Sonstige Organisation</option>
                            </select>
                            @if($clubBookingsFree)
                                <p class="mt-2 text-sm leading-6 text-slate-500">
                                    Kostenfrei gilt nur bei Auswahl „Verein“. Firmen, Unternehmen und sonstige Organisationen zahlen den Gästepreis.
                                </p>
                            @endif
                            @error('organization_booking_type')
                                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                @continue
            @endif
            <div>
                <label class="block text-sm font-semibold text-slate-700">
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
                    <textarea name="{{ $name }}" rows="4" class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10" placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
                @elseif($field->field_type === 'select')
                    <select name="{{ $name }}" class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                        <option value="">Bitte wählen</option>
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
                                       class="mt-0.5 border-slate-300 text-slate-950 focus:ring-slate-500"
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
                                       class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-500"
                                       @checked($selectedValues->contains($option))>
                                <span class="font-medium">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif($field->field_type === 'checkbox')
                    <label class="mt-2 inline-flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-gray-700">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 rounded border-slate-300 text-slate-950 shadow-sm focus:ring-slate-500" @checked($value)>
                        <div class="min-w-0 leading-6 text-gray-700">
                            {!! $field->rendered_help_text ?: \App\Models\PublicFormField::sanitizeHelpText('Ich stimme zu.') !!}
                        </div>
                    </label>
                @else
                    <input type="{{ $field->field_type }}"
                           name="{{ $name }}"
                           value="{{ is_bool($value) ? ($value ? '1' : '0') : $value }}"
                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10"
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
                    <div class="mt-2 text-sm leading-6 text-slate-500">
                        {!! $field->rendered_help_text !!}
                    </div>
                @endif
            </div>
        @endforeach

        @if($isEventBooking)
            </section>

            <section class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="participant_count" value="1" :disabled="bookingMode !== 'organization'">

                <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
                    <div>
                        <label for="participant_count" class="block text-sm font-semibold text-slate-700" x-show="bookingMode === 'person'">
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
                               class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
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
                                <span>Preis</span>
                                <span class="font-semibold">
                                    @if($event->is_paid)
                                        @if($hasMemberRate)
                                            Mitglieder {{ $memberPrice > 0 ? number_format($memberPrice, 2, ',', '.').' '.$currency : 'frei' }} · Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }}
                                            @if($clubBookingsFree)
                                                · Vereine frei
                                            @endif
                                        @elseif($clubBookingsFree)
                                            Vereine frei · Firmen, Organisationen und Gäste {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }}
                                        @else
                                            {{ number_format($externalPrice, 2, ',', '.') }} {{ $currency }}
                                        @endif
                                    @else
                                        Kostenfrei
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                    <span>Teilnehmer</span>
                                    <span class="font-semibold" x-text="participantCount"></span>
                                </div>
                                @if($hasMemberRate)
                                    <div class="flex items-center justify-between gap-4" x-show="memberRateApplies()" x-cloak>
                                        <span>davon Mitglied</span>
                                        <span class="font-semibold">1</span>
                                    </div>
                                @endif
                            <div class="border-t border-emerald-200 pt-2">
                                <div class="flex items-center justify-between gap-4 text-base">
                                    <span class="font-semibold">Gesamt</span>
                                    <span class="font-semibold" x-text="`${totalAmount()} {{ $currency }}`"></span>
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

                @if($hasMemberRate)
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4" x-show="bookingMode === 'organization' || useBookerAsParticipant" x-cloak>
                        <input type="hidden" name="booking_claims_membership" value="0">
                        <label class="flex items-start gap-3 text-sm text-emerald-950">
                            <input type="checkbox"
                                   name="booking_claims_membership"
                                   value="1"
                                   x-model="bookerClaimsMembership"
                                   class="mt-0.5 rounded border-emerald-200 text-emerald-700 focus:ring-emerald-500">
                            <span>
                                <span class="block font-semibold">Ich / wir sind Mitglied in diesem Verein</span>
                                <span class="mt-1 block text-emerald-900/80">
                                    Clubano prüft die Mitgliedschaft im Verein über E-Mail-Adresse und, bei Organisationen, über den Organisationsnamen.
                                    Der Mitgliederpreis gilt für den Ansprechpartner oder die angemeldete Organisation.
                                </span>
                            </span>
                        </label>
                    </div>
                @endif

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
                                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Nachname <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           :name="`participants[${index}][last_name]`"
                                           x-model="participant.last_name"
                                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">E-Mail</label>
                                    <input type="email"
                                           :name="`participants[${index}][email]`"
                                           x-model="participant.email"
                                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Telefon</label>
                                    <input type="text"
                                           :name="`participants[${index}][phone]`"
                                           x-model="participant.phone"
                                           class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div>
                    <label for="participant_notes" class="block text-sm font-semibold text-slate-700">Hinweis zur Gruppe</label>
                    <textarea id="participant_notes"
                              name="participant_notes"
                              rows="4"
                              class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-base shadow-sm focus:border-slate-900 focus:ring-slate-900/10"
                              placeholder="z. B. gemeinsames Sitzen, Kindersitz oder wichtige Hinweise">{{ old('participant_notes') }}</textarea>
                </div>
            </section>
        @endif

        <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-6 text-slate-500">Deine Angaben werden verschlüsselt übertragen und nur für diesen Zweck verwendet.</p>
            <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-950 px-6 text-base font-semibold text-white shadow-sm transition hover:bg-slate-800">
            {{ $isEventBooking ? 'Verbindlich anmelden' : 'Formular absenden' }}
            </button>
        </div>
    </form>
    </div>
</div>

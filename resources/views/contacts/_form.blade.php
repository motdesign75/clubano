@php
    $selectedContactType = old('contact_type', $contact->contact_type ?? 'person');
    $selectedCategory = old('category', $contact->category ?? '');
@endphp

<div class="space-y-8">

    {{-- Fehler --}}
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="font-semibold">Bitte prüfe deine Eingaben.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Grunddaten --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Grunddaten</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="contact_type" class="block text-sm font-medium text-gray-700">
                    Kontakttyp *
                </label>
                <select name="contact_type"
                        id="contact_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected($selectedContactType === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('contact_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">
                    Kategorie
                </label>
                <select name="category"
                        id="category"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Keine Kategorie</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" @selected($selectedCategory === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('category')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           @checked(old('is_active', $contact->is_active ?? true))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Aktiv
                </label>
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                    <input type="checkbox"
                           name="is_favorite"
                           value="1"
                           @checked(old('is_favorite', $contact->is_favorite ?? false))
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Favorit
                </label>
            </div>
        </div>
    </section>

    {{-- Organisation --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Organisation</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="organization" class="block text-sm font-medium text-gray-700">
                    Organisation / Firma
                </label>
                <input type="text"
                       name="organization"
                       id="organization"
                       value="{{ old('organization', $contact->organization ?? $contact->company ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('organization')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="department" class="block text-sm font-medium text-gray-700">
                    Abteilung
                </label>
                <input type="text"
                       name="department"
                       id="department"
                       value="{{ old('department', $contact->department ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('department')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="position" class="block text-sm font-medium text-gray-700">
                    Position / Rolle
                </label>
                <input type="text"
                       name="position"
                       id="position"
                       value="{{ old('position', $contact->position ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('position')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <input type="hidden"
                   name="company"
                   value="{{ old('company', $contact->organization ?? $contact->company ?? '') }}">
        </div>
    </section>

    {{-- Person --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Person</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="gender" class="block text-sm font-medium text-gray-700">
                    Geschlecht
                </label>
                <select name="gender"
                        id="gender"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Keine Angabe</option>
                    <option value="female" @selected(old('gender', $contact->gender ?? '') === 'female')>Weiblich</option>
                    <option value="male" @selected(old('gender', $contact->gender ?? '') === 'male')>Männlich</option>
                    <option value="diverse" @selected(old('gender', $contact->gender ?? '') === 'diverse')>Divers</option>
                </select>
                @error('gender')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="salutation" class="block text-sm font-medium text-gray-700">
                    Anrede
                </label>
                <input type="text"
                       name="salutation"
                       id="salutation"
                       value="{{ old('salutation', $contact->salutation ?? '') }}"
                       placeholder="Frau, Herr, ..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('salutation')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">
                    Titel
                </label>
                <input type="text"
                       name="title"
                       id="title"
                       value="{{ old('title', $contact->title ?? '') }}"
                       placeholder="Dr., Prof., ..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="birthday" class="block text-sm font-medium text-gray-700">
                    Geburtstag
                </label>
                <input type="date"
                       name="birthday"
                       id="birthday"
                       value="{{ old('birthday', optional($contact->birthday ?? null)->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('birthday')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-2">
                <label for="first_name" class="block text-sm font-medium text-gray-700">
                    Vorname
                </label>
                <input type="text"
                       name="first_name"
                       id="first_name"
                       value="{{ old('first_name', $contact->first_name ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('first_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-2">
                <label for="last_name" class="block text-sm font-medium text-gray-700">
                    Nachname
                </label>
                <input type="text"
                       name="last_name"
                       id="last_name"
                       value="{{ old('last_name', $contact->last_name ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('last_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    {{-- Kommunikation --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Kommunikation</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">
                    E-Mail
                </label>
                <input type="email"
                       name="email"
                       id="email"
                       value="{{ old('email', $contact->email ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="secondary_email" class="block text-sm font-medium text-gray-700">
                    Zweite E-Mail
                </label>
                <input type="email"
                       name="secondary_email"
                       id="secondary_email"
                       value="{{ old('secondary_email', $contact->secondary_email ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('secondary_email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="mobile" class="block text-sm font-medium text-gray-700">
                    Mobil
                </label>
                <input type="text"
                       name="mobile"
                       id="mobile"
                       value="{{ old('mobile', $contact->mobile ?? $contact->phone_mobile ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('mobile')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <input type="hidden"
                       name="phone_mobile"
                       value="{{ old('phone_mobile', $contact->mobile ?? $contact->phone_mobile ?? '') }}">
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">
                    Telefon
                </label>
                <input type="text"
                       name="phone"
                       id="phone"
                       value="{{ old('phone', $contact->phone ?? $contact->phone_landline ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <input type="hidden"
                       name="phone_landline"
                       value="{{ old('phone_landline', $contact->phone ?? $contact->phone_landline ?? '') }}">
            </div>

            <div>
                <label for="fax" class="block text-sm font-medium text-gray-700">
                    Fax
                </label>
                <input type="text"
                       name="fax"
                       id="fax"
                       value="{{ old('fax', $contact->fax ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('fax')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="website" class="block text-sm font-medium text-gray-700">
                    Website
                </label>
                <input type="url"
                       name="website"
                       id="website"
                       value="{{ old('website', $contact->website ?? '') }}"
                       placeholder="https://example.de"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('website')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    {{-- Adresse --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Adresse</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="street" class="block text-sm font-medium text-gray-700">
                    Straße und Hausnummer
                </label>
                <input type="text"
                       name="street"
                       id="street"
                       value="{{ old('street', $contact->street ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('street')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="address_addition" class="block text-sm font-medium text-gray-700">
                    Adresszusatz
                </label>
                <input type="text"
                       name="address_addition"
                       id="address_addition"
                       value="{{ old('address_addition', $contact->address_addition ?? $contact->street_addition ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('address_addition')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <input type="hidden"
                       name="street_addition"
                       value="{{ old('street_addition', $contact->address_addition ?? $contact->street_addition ?? '') }}">
            </div>

            <div>
                <label for="zip" class="block text-sm font-medium text-gray-700">
                    PLZ
                </label>
                <input type="text"
                       name="zip"
                       id="zip"
                       value="{{ old('zip', $contact->zip ?? $contact->postal_code ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('zip')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <input type="hidden"
                       name="postal_code"
                       value="{{ old('postal_code', $contact->zip ?? $contact->postal_code ?? '') }}">
            </div>

            <div>
                <label for="city" class="block text-sm font-medium text-gray-700">
                    Ort
                </label>
                <input type="text"
                       name="city"
                       id="city"
                       value="{{ old('city', $contact->city ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('city')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="state" class="block text-sm font-medium text-gray-700">
                    Bundesland / Region
                </label>
                <input type="text"
                       name="state"
                       id="state"
                       value="{{ old('state', $contact->state ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('state')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="country" class="block text-sm font-medium text-gray-700">
                    Land
                </label>
                <input type="text"
                       name="country"
                       id="country"
                       value="{{ old('country', $contact->country ?? 'Deutschland') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('country')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="care_of" class="block text-sm font-medium text-gray-700">
                    c/o
                </label>
                <input type="text"
                       name="care_of"
                       id="care_of"
                       value="{{ old('care_of', $contact->care_of ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('care_of')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    {{-- Beziehung / Datenschutz / Notizen --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Weitere Angaben</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="relationship" class="block text-sm font-medium text-gray-700">
                    Beziehung / Rolle zum Verein
                </label>
                <input type="text"
                       name="relationship"
                       id="relationship"
                       value="{{ old('relationship', $contact->relationship ?? '') }}"
                       placeholder="z. B. Sponsor seit 2024, Ansprechpartner Stadt"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('relationship')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="source" class="block text-sm font-medium text-gray-700">
                    Quelle
                </label>
                <input type="text"
                       name="source"
                       id="source"
                       value="{{ old('source', $contact->source ?? '') }}"
                       placeholder="z. B. Empfehlung, Messe, Website"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('source')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="last_contacted_at" class="block text-sm font-medium text-gray-700">
                    Letzter Kontakt
                </label>
                <input type="date"
                       name="last_contacted_at"
                       id="last_contacted_at"
                       value="{{ old('last_contacted_at', optional($contact->last_contacted_at ?? null)->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('last_contacted_at')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="consent_given_at" class="block text-sm font-medium text-gray-700">
                    Einwilligung erteilt am
                </label>
                <input type="date"
                       name="consent_given_at"
                       id="consent_given_at"
                       value="{{ old('consent_given_at', optional($contact->consent_given_at ?? $contact->gdpr_consent_at ?? null)->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                <input type="hidden"
                       name="gdpr_consent_at"
                       value="{{ old('gdpr_consent_at', optional($contact->consent_given_at ?? $contact->gdpr_consent_at ?? null)->format('Y-m-d')) }}">

                @error('consent_given_at')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox"
                               name="consent_email"
                               value="1"
                               @checked(old('consent_email', $contact->consent_email ?? $contact->gdpr_consent ?? false))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        E-Mail erlaubt
                    </label>

                    <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox"
                               name="consent_phone"
                               value="1"
                               @checked(old('consent_phone', $contact->consent_phone ?? $contact->gdpr_consent ?? false))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Telefon erlaubt
                    </label>

                    <label class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox"
                               name="consent_post"
                               value="1"
                               @checked(old('consent_post', $contact->consent_post ?? $contact->gdpr_consent ?? false))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Post erlaubt
                    </label>

                    <input type="hidden"
                           name="gdpr_consent"
                           value="{{ old('gdpr_consent', ($contact->consent_email ?? false) || ($contact->consent_phone ?? false) || ($contact->consent_post ?? false) || ($contact->gdpr_consent ?? false) ? 1 : 0) }}">
                </div>
            </div>

            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700">
                    Notizen
                </label>
                <textarea name="notes"
                          id="notes"
                          rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $contact->notes ?? '') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="internal_notes" class="block text-sm font-medium text-gray-700">
                    Interne Notizen
                </label>
                <textarea name="internal_notes"
                          id="internal_notes"
                          rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('internal_notes', $contact->internal_notes ?? '') }}</textarea>
                @error('internal_notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    {{-- Aktionen --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('contacts.index') }}"
           class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Zurück
        </a>

        <button type="submit"
                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
            Speichern
        </button>
    </div>
</div>
